# Docker Production Deployment Guide

This document describes the Docker-based production deployment for LivroLog.

## Architecture Overview

- **Frontend (Web)**: Vue.js + Quasar served by Nginx on port 18080 (loopback)
- **Backend (API)**: Laravel + PHP-FPM + Nginx on port 18081 (loopback)
- **Proxy**: Apache reverse proxy serving public ports 80/443
- **Database**: External MariaDB (existing Bitnami installation)
- **Cache**: External Redis (existing Bitnami installation)

## Container Images

Images are built and pushed to GitHub Container Registry (GHCR):
- `ghcr.io/arnonrdp/livrolog-web`
- `ghcr.io/arnonrdp/livrolog-api`

### Tags Strategy

| Branch | Tags Generated |
|--------|---------------|
| `main` | `prod`, `latest`, `main-${sha}` |
| `dev` | `dev`, `dev-${sha}` |

### Image Retention Policy

To configure GHCR retention (recommended):
1. Go to GitHub repository → Packages
2. Select each package (livrolog-web, livrolog-api)
3. Package settings → Manage versions
4. Configure retention: Keep last 30 versions per tag pattern

## Deployment Process

1. **CI/CD Pipeline**: 
   - `required-checks` → `build` → `docker-canary-prod`
   
2. **Tag Resolution** (in order of preference):
   - `prod` (for main branch)
   - `${sha}` (specific commit)
   - `main` (fallback)

3. **Health Checks**:
   - Web: `http://127.0.0.1:18080/healthz`
   - API: `http://127.0.0.1:18081/health`

4. **Rollback**: Automatic on health check failure

## Apache Configuration

The Apache reverse proxy configuration routes:
- `/` → `http://127.0.0.1:18080/` (Vue.js frontend)
- `/api/` → `http://127.0.0.1:18081/` (Laravel API)
- `/health` → `http://127.0.0.1:18081/health` (API health)
- `/documentation` → `http://127.0.0.1:18081/documentation` (Swagger)
- `/storage/` → `http://127.0.0.1:18081/storage/` (File uploads)

## Manual Operations

### Manual Rollback
```bash
cd /var/www/livrolog/docker
LAST_TAG=$(cat .last_tag_prod)
export TAG=$LAST_TAG OWNER=arnonrdp
docker compose -p livrolog -f docker-compose.prod.yml pull
docker compose -p livrolog -f docker-compose.prod.yml up -d --remove-orphans
```

### Check Container Status
```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
docker logs livrolog-api
docker logs livrolog-web
```

### Manual Smoke Tests
```bash
curl -I http://127.0.0.1:18080/        # Frontend
curl -I http://127.0.0.1:18081/health  # API health
curl -I https://livrolog.com/          # Public frontend
curl -I https://livrolog.com/api/      # Public API
```

## Files and Directories

- `/var/www/livrolog/docker/` - Compose files and logs
- `/var/www/livrolog/shared/.env` - Laravel environment
- `/var/www/livrolog/shared/storage/` - Persistent file storage
- `/opt/bitnami/apache2/conf/vhosts/livrolog.conf` - Apache vhost

## Troubleshooting

### Container Not Starting
1. Check logs: `docker logs livrolog-api` / `docker logs livrolog-web`
2. Check environment: `docker exec livrolog-api env | grep DB_`
3. Check storage permissions: `ls -la /var/www/livrolog/shared/storage`

### Health Checks Failing
1. Test endpoints directly: `curl http://127.0.0.1:18081/health`
2. Check database connection: `docker exec livrolog-api php artisan migrate:status`
3. Check Redis: `docker exec livrolog-api php artisan tinker --execute="Cache::put('test', 'ok'); echo Cache::get('test');"`

### Apache Proxy Issues
1. Test Apache config: `sudo /opt/bitnami/apache2/bin/httpd -t`
2. Check proxy modules: `sudo /opt/bitnami/apache2/bin/httpd -M | grep proxy`
3. Check logs: `tail -f /opt/bitnami/apache2/logs/livrolog_error.log`