# 🚀 Migração Banco de Dados - LivroLog

## ✅ Status da Migração

Esta migração move os bancos de dados MySQL/Redis do host nativo para containers Docker em ambos ambientes.

### 📋 Resumo das Mudanças

| Componente | Antes | Depois |
|------------|-------|--------|
| **Development DB** | MySQL host (localhost:3306) | MySQL container (localhost:3307) |
| **Development Redis** | Redis host (localhost:6379) | Redis container (localhost:6380) |
| **Production DB** | MySQL host (localhost:3306) | MariaDB container (interno) |
| **Production Redis** | Redis host (localhost:6379) | Redis container (interno) |

### 🔧 Arquivos Modificados

1. **`docker-compose.dev.yml`** ✅
   - Adicionado MySQL 8.0 container
   - Adicionado Redis 7-alpine container
   - Configurado rede isolada
   - Portas mapeadas para evitar conflitos

2. **`docker-compose.prod.yml`** ✅
   - Sincronizado com workflow
   - MariaDB 10.11 container
   - Redis 7-alpine container
   - Rede interna (sem portas expostas)

3. **`.github/workflows/deploy.yml`** ✅
   - Workflow development atualizado para containers
   - Health checks para todos os serviços
   - Configuração automática de volumes
   - Timeouts aumentados para startup

### 📁 Scripts Criados

1. **`scripts/backup-databases.sh`** - Backup automático via SSH
2. **`scripts/test-docker-configs.sh`** - Validação das configurações
3. **`scripts/migrate-data.sh`** - Migração guiada por ambiente

### 📚 Documentação

1. **`MIGRATION.md`** - Guia completo de migração
2. **`README-MIGRATION.md`** - Este resumo executivo

## 🎯 Próximos Passos

### 1. Execução da Migração

```bash
# Validar configurações
./scripts/test-docker-configs.sh

# Fazer backup dos dados
./scripts/backup-databases.sh

# Migrar Development
./scripts/migrate-data.sh dev

# Migrar Production (após teste)
./scripts/migrate-data.sh prod
```

### 2. Deploy via Git

**Development:**
```bash
git add .
git commit -m "feat: migrate databases to Docker containers

- Add MySQL and Redis containers for dev environment
- Update deploy workflow for containerized databases
- Add migration and backup scripts
- Configure proper volume persistence and health checks"
git push origin dev
```

**Production:**
```bash
git checkout main
git merge dev
git push origin main
```

### 3. Monitoramento Pós-Deploy

```bash
# Verificar containers
ssh -i ~/.ssh/livrolog-key.pem bitnami@35.170.25.86
docker ps

# Testar endpoints
curl http://35.170.25.86:8080/healthz    # Dev Web
curl http://35.170.25.86:8081/healthz    # Dev API
curl http://35.170.25.86:18080/healthz   # Prod Web  
curl http://35.170.25.86:18081/healthz   # Prod API
```

## ⚠️ Pontos de Atenção

### 🔒 Segurança
- Containers de produção usam rede interna (sem portas expostas)
- Credenciais gerenciadas via environment variables
- Volumes com permissões adequadas

### 🚀 Performance
- Health checks otimizados por serviço
- Timeouts configurados para startup do banco
- Containers com restart automático

### 📊 Rollback
- Backups automáticos antes da migração
- Scripts de rollback documentados
- Configurações anteriores preservadas no Git

## 🆘 Suporte

### Logs de Debug
```bash
# Logs dos containers
docker logs livrolog-mysql-dev
docker logs livrolog-redis-dev  
docker logs livrolog-api-dev
docker logs livrolog-web-dev

# Status dos serviços
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

### Comandos de Rollback
```bash
# Parar containers novos
docker compose down

# Reativar serviços host
sudo systemctl start mysql
sudo systemctl start redis

# Reverter configuração (se necessário)
git revert <commit-hash>
```

## 🎉 Benefícios Esperados

1. **🔧 Consistência**: Mesmo ambiente local/dev/prod
2. **📦 Isolamento**: Containers isolados do host
3. **🔄 Portabilidade**: Fácil backup/restore via volumes
4. **⚡ Escalabilidade**: Base para futuras otimizações
5. **🛠️ Manutenibilidade**: Configuração versionada no Git

---

**Status**: ✅ **PRONTO PARA EXECUÇÃO**

**Próxima Ação**: Execute `./scripts/test-docker-configs.sh` e depois commit as mudanças.