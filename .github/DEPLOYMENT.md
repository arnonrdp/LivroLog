# Deployment Configuration

## GitHub Secrets Required

Configure the following secrets in your GitHub repository settings (Settings → Secrets and variables → Actions):

### Required Secrets

1. **SERVER_HOST**
   - Value: `35.170.25.86`
   - Description: IP address of the deployment server

2. **SERVER_USER**
   - Value: `bitnami`
   - Description: SSH username for the server

3. **SERVER_SSH_KEY**
   - Value: Contents of your private SSH key file (`~/livrolog-key.pem`)
   - Description: Private SSH key for authentication
   - How to add:
     ```bash
     cat ~/livrolog-key.pem
     ```
     Copy the entire output including `-----BEGIN RSA PRIVATE KEY-----` and `-----END RSA PRIVATE KEY-----`

## Deployment Flow

### Branches and Environments

- **main branch** → Production
  - Deploys to: `livrolog.com` and `api.livrolog.com`
  - Path: `/var/www/livrolog`
  - Uses: `.env` (production configuration)
  - Runs migrations automatically

- **dev branch** → Development
  - Deploys to: `dev.livrolog.com` and `api.dev.livrolog.com`
  - Path: `/var/www/livrolog-dev`
  - Uses: `.env.dev` (development configuration)
  - Does NOT run migrations (manual control)

### Deployment Process

1. Push to `main` or `dev` branch triggers deployment
2. GitHub Actions builds both API and Frontend
3. Files are deployed to timestamped release folder
4. Symlinks are updated atomically
5. Services are restarted
6. Health checks verify deployment

### Manual Deployment Commands

If you need to deploy manually:

```bash
# For production
ssh livrolog
cd /var/www/livrolog/current/api
php artisan migrate --force
sudo /opt/bitnami/ctlscript.sh restart apache

# For development
ssh livrolog
cd /var/www/livrolog-dev/current/api
php artisan migrate:fresh --seed
sudo /opt/bitnami/ctlscript.sh restart apache
```

## Server Structure

```
/var/www/
├── livrolog/              # Production
│   ├── current/           # Symlink to active release
│   ├── releases/          # Timestamped deployments
│   │   └── 20250810141504/
│   └── shared/            # Persistent files
│       ├── .env           # Production config
│       └── storage/       # Laravel storage
│
└── livrolog-dev/          # Development
    ├── current/           # Symlink to active release
    ├── releases/          # Timestamped deployments
    └── shared/            # Persistent files
        ├── .env.dev       # Development config
        └── storage/       # Laravel storage
```

## Rollback Procedure

To rollback to a previous release:

```bash
# List available releases
ssh livrolog
ls -la /var/www/livrolog/releases/

# Rollback to specific release
ln -nfs /var/www/livrolog/releases/[TIMESTAMP] /var/www/livrolog/current
sudo /opt/bitnami/ctlscript.sh restart apache
```

## Troubleshooting

### Check deployment logs
```bash
ssh livrolog
tail -f /opt/bitnami/apache/logs/error_log
tail -f /var/www/livrolog/current/api/storage/logs/laravel.log
```

### Test endpoints
```bash
# Health check
curl http://api.livrolog.com/api/health
curl http://api.dev.livrolog.com/api/health

# Frontend
curl -I http://livrolog.com
curl -I http://dev.livrolog.com
```

### Clear caches
```bash
ssh livrolog
cd /var/www/livrolog/current/api
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```