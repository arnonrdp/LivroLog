# Database Migration Guide - LivroLog

Este guia documenta a migração dos bancos de dados do host nativo para containers Docker.

## Situação Atual vs. Objetivo

### Antes (Host Nativo)
- **Production**: MySQL no host (127.0.0.1:3306) + Redis host
- **Development**: MySQL no host (127.0.0.1:3306) + Redis host

### Depois (Containerizado)
- **Production**: MariaDB container + Redis container
- **Development**: MySQL container + Redis container

## Pré-requisitos

1. Acesso SSH ao servidor (35.170.25.86)
2. Backup dos dados atuais
3. Docker e Docker Compose funcionando
4. Tempo de manutenção planejado

## Passo 1: Validação e Backup

### Validar Configurações
```bash
# Testar estrutura dos arquivos Docker
./scripts/test-docker-configs.sh
```

### Backup Automático
```bash
# Execute o script de backup
./scripts/backup-databases.sh
```

### Backup Manual (alternativa)
```bash
# Conectar ao servidor
ssh -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86

# Backup Production
mysqldump -u root -p'3StLYpY7z4R=' livrolog --single-transaction --routines --triggers > livrolog_prod_backup.sql

# Backup Development (se existir)
mysqldump -u root -p'3StLYpY7z4R=' livrolog_dev --single-transaction --routines --triggers > livrolog_dev_backup.sql
```

## Passo 2: Preparação dos Volumes

### No Servidor
```bash
# Criar diretórios para volumes Docker
sudo mkdir -p /var/www/livrolog/shared/db
sudo mkdir -p /var/www/livrolog-dev/shared/db

# Configurar permissões
sudo chown -R 999:999 /var/www/livrolog/shared/db
sudo chown -R 999:999 /var/www/livrolog-dev/shared/db
```

## Passo 3: Migração Automatizada

### Development
```bash
# Execute o script de migração
./scripts/migrate-data.sh dev
```

### Production
```bash
# Execute o script de migração (requer confirmação)
./scripts/migrate-data.sh prod
```

### Manual (se necessário)

#### Development
1. **Deploy novo ambiente**
   - Push para branch `dev` ou manual dispatch no GitHub Actions
   
2. **Migrar dados após deploy**
   ```bash
   # Conectar ao servidor
   ssh -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86
   
   # Importar backup para novo container
   docker exec -i livrolog-mysql-dev mysql -u livrolog -psupersecret livrolog_dev < backup_file.sql
   ```

#### Production
1. **Deploy novo ambiente**
   - Push para branch `main` ou manual dispatch no GitHub Actions
   
2. **Migrar dados após deploy**
   ```bash
   # Conectar ao servidor
   ssh -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86
   
   # Importar backup para novo container  
   docker exec -i livrolog-mariadb mysql -u root -p'3StLYpY7z4R=' livrolog < backup_file.sql
   ```

## Passo 5: Validação

### Testes de Conectividade
```bash
# Verificar containers
docker ps

# Testar API
curl http://localhost:18081/healthz  # Production
curl http://localhost:8081/healthz   # Development

# Testar Web
curl http://localhost:18080/healthz  # Production  
curl http://localhost:8080/healthz   # Development

# Verificar logs
docker logs livrolog-api
docker logs livrolog-web
```

### Testes de Dados
```bash
# Conectar ao banco e verificar dados
docker exec -it livrolog-mariadb mysql -u root -p
# ou
docker exec -it livrolog-mysql-dev mysql -u livrolog -p

# Dentro do MySQL
SHOW DATABASES;
USE livrolog;
SHOW TABLES;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM books;
```

## Rollback Plan

Se algo der errado:

### 1. Parar Containers Docker
```bash
docker compose down
```

### 2. Restaurar Configuração Original
```bash
# Reverter para docker-compose files anteriores
git checkout HEAD~1 docker-compose.prod.yml docker-compose.dev.yml
```

### 3. Reiniciar com Host Database
```bash
# Certificar que MySQL host está rodando
sudo systemctl start mysql
sudo systemctl start redis

# Resubir containers com configuração host
docker compose up -d
```

## Monitoramento Pós-Migração

### Logs Importantes
```bash
# API Logs
docker logs -f livrolog-api

# Database Logs  
docker logs -f livrolog-mariadb

# Redis Logs
docker logs -f livrolog-redis
```

### Métricas de Performance
- Tempo de resposta da API
- Conectividade database
- Jobs em background (queue processing)
- Uso de memória/CPU dos containers

## Troubleshooting

### Container não inicia
1. Verificar logs: `docker logs container_name`
2. Verificar volumes: `docker volume ls`
3. Verificar rede: `docker network ls`

### Database não conecta
1. Verificar healthcheck: `docker ps`
2. Testar conectividade: `docker exec container mysql -u user -p`
3. Verificar variáveis de ambiente: `docker inspect container`

### Performance lenta
1. Verificar recursos: `docker stats`
2. Otimizar configurações MySQL/MariaDB
3. Verificar indices do banco

## Contatos de Emergência

- **Logs**: Sempre verificar logs antes de escalar
- **Rollback**: Procedimento documentado acima  
- **Backup**: Sempre manter backups recentes