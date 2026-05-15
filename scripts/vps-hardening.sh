#!/usr/bin/env bash
# vps-hardening.sh
#
# Hardening idempotente para o VPS de produção (Ubuntu 24.04).
# Cobre: firewall, fail2ban, swap, SSH, kernel/pacotes, imagens Docker.
#
# Uso:
#   sudo ./vps-hardening.sh --dry-run     # mostra o que faria, sem alterar
#   sudo ./vps-hardening.sh --step <nome> # roda uma etapa só
#   sudo ./vps-hardening.sh               # executa tudo, com prompts
#
# Etapas disponiveis: firewall fail2ban swap ssh apt docker all
#
# Roda no servidor (nao na sua maquina). Copiar com:
#   scp scripts/vps-hardening.sh root@<host>:/root/

set -euo pipefail

DRY_RUN=0
STEP="all"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/root/hardening-backups/${TS}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --step) STEP="$2"; shift 2 ;;
    -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
    *) echo "Argumento desconhecido: $1"; exit 1 ;;
  esac
done

run() {
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "[DRY-RUN] $*"
  else
    echo "[RUN] $*"
    eval "$@"
  fi
}

confirm() {
  local msg="$1"
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "[DRY-RUN] confirmaria: $msg"
    return 0
  fi
  read -r -p "$msg [y/N] " ans
  [[ "$ans" =~ ^[Yy]$ ]]
}

require_root() {
  if [[ $EUID -ne 0 ]]; then
    echo "Execute como root."
    exit 1
  fi
}

section() {
  echo
  echo "========================================================"
  echo "  $1"
  echo "========================================================"
}

ensure_backup_dir() {
  [[ $DRY_RUN -eq 1 ]] && return 0
  mkdir -p "$BACKUP_DIR"
}

backup_file() {
  local f="$1"
  [[ -e "$f" ]] || return 0
  ensure_backup_dir
  run "cp -a '$f' '$BACKUP_DIR/$(basename "$f").bak'"
}

# ----------------------------------------------------------------------
step_firewall() {
  section "1. Firewall (UFW)"
  if ! command -v ufw >/dev/null; then
    run "apt-get install -y ufw"
  fi

  run "ufw --force default deny incoming"
  run "ufw --force default allow outgoing"
  run "ufw allow 22/tcp comment 'SSH'"
  run "ufw allow 80/tcp comment 'HTTP'"
  run "ufw allow 443/tcp comment 'HTTPS'"
  # Tailscale (interface tailscale0 ja eh confiavel)
  if ip link show tailscale0 >/dev/null 2>&1; then
    run "ufw allow in on tailscale0"
  fi
  run "ufw --force enable"
  run "ufw status verbose"
}

# ----------------------------------------------------------------------
step_fail2ban() {
  section "2. Fail2ban (protecao SSH)"
  run "apt-get install -y fail2ban"

  local jail="/etc/fail2ban/jail.local"
  if [[ ! -f "$jail" ]] || ! grep -q "^\[sshd\]" "$jail"; then
    backup_file "$jail"
    if [[ $DRY_RUN -eq 0 ]]; then
      cat > "$jail" <<'CONF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
CONF
    else
      echo "[DRY-RUN] criaria $jail com jail [sshd] enabled"
    fi
  else
    echo "Jail [sshd] ja configurada — sem alteracoes."
  fi
  run "systemctl enable --now fail2ban"
  run "fail2ban-client status sshd || true"
}

# ----------------------------------------------------------------------
step_swap() {
  section "3. Swap (4 GB)"
  if swapon --show | grep -q .; then
    echo "Swap ja ativa:"
    run "swapon --show"
    return 0
  fi
  run "fallocate -l 4G /swapfile"
  run "chmod 600 /swapfile"
  run "mkswap /swapfile"
  run "swapon /swapfile"
  if ! grep -q "^/swapfile" /etc/fstab; then
    backup_file /etc/fstab
    if [[ $DRY_RUN -eq 0 ]]; then
      echo '/swapfile none swap sw 0 0' >> /etc/fstab
    else
      echo "[DRY-RUN] adicionaria /swapfile ao /etc/fstab"
    fi
  fi
  # Reduzir swappiness para uso conservador
  run "sysctl -w vm.swappiness=10"
  if ! grep -q "^vm.swappiness" /etc/sysctl.conf; then
    if [[ $DRY_RUN -eq 0 ]]; then
      echo "vm.swappiness=10" >> /etc/sysctl.conf
    fi
  fi
}

# ----------------------------------------------------------------------
step_ssh() {
  section "4. SSH hardening"

  echo "AVISO: este passo desabilita login com senha."
  echo "Garanta que sua chave publica esta em /root/.ssh/authorized_keys"
  echo "ANTES de prosseguir. Abra OUTRA sessao SSH agora para testar."
  echo
  if ! confirm "Sua chave SSH funciona em outra sessao e voce quer prosseguir?"; then
    echo "Pulando hardening de SSH."
    return 0
  fi

  # O Ubuntu cloud-init grava PasswordAuthentication yes em 50-cloud-init.conf.
  # Adicionamos 99-hardening.conf que e lido por ultimo e tem precedencia.
  local conf="/etc/ssh/sshd_config.d/99-hardening.conf"
  backup_file "$conf"
  if [[ $DRY_RUN -eq 0 ]]; then
    cat > "$conf" <<'CONF'
PasswordAuthentication no
PermitRootLogin prohibit-password
PubkeyAuthentication yes
KbdInteractiveAuthentication no
MaxAuthTries 3
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
CONF
  else
    echo "[DRY-RUN] criaria $conf desabilitando password auth"
  fi
  run "sshd -t"  # valida config antes de reiniciar
  run "systemctl restart ssh"
  echo "SSH reiniciado. NAO feche esta sessao ate confirmar nova conexao por chave."
}

# ----------------------------------------------------------------------
step_apt() {
  section "5. Patches do sistema e kernel"
  run "apt-get update"
  run "DEBIAN_FRONTEND=noninteractive apt-get -y -o Dpkg::Options::='--force-confdef' -o Dpkg::Options::='--force-confold' dist-upgrade"
  run "apt-get -y autoremove --purge"
  run "apt-get -y autoclean"

  if [[ -f /var/run/reboot-required ]]; then
    echo
    echo ">>> Reboot necessario para ativar novo kernel <<<"
    cat /var/run/reboot-required.pkgs 2>/dev/null || true
    echo
    if confirm "Reiniciar agora?"; then
      run "shutdown -r +1 'Hardening: reboot pos-upgrade em 1 minuto'"
    else
      echo "Lembre de rodar 'reboot' em janela de manutencao."
    fi
  fi
}

# ----------------------------------------------------------------------
step_docker() {
  section "6. Atualizar imagens Docker dos stacks"
  for dir in /var/www/livrolog /var/www/fischub.com/api; do
    local compose
    if [[ -f "$dir/docker-compose.prod.yml" ]]; then
      compose="$dir/docker-compose.prod.yml"
    elif [[ -f "$dir/current/docker-compose.prod.yml" ]]; then
      compose="$dir/current/docker-compose.prod.yml"
    elif [[ -f "$dir/docker-compose.yml" ]]; then
      compose="$dir/docker-compose.yml"
    else
      echo "Nenhum compose em $dir — pulando."
      continue
    fi
    echo
    echo "Stack em $dir (arquivo: $compose)"
    run "docker compose -f '$compose' pull"
    if confirm "Recriar containers com novas imagens?"; then
      run "docker compose -f '$compose' up -d"
    fi
  done
  run "docker image prune -f"
}

# ----------------------------------------------------------------------
require_root

case "$STEP" in
  firewall) step_firewall ;;
  fail2ban) step_fail2ban ;;
  swap)     step_swap ;;
  ssh)      step_ssh ;;
  apt)      step_apt ;;
  docker)   step_docker ;;
  all)
    step_firewall
    step_fail2ban
    step_swap
    step_apt
    step_docker
    step_ssh   # SSH por ultimo: se algo der errado nas etapas anteriores, ainda da pra logar com senha
    ;;
  *) echo "Etapa desconhecida: $STEP"; exit 1 ;;
esac

echo
echo "Concluido. Backups em: $BACKUP_DIR"
