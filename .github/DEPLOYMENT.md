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

## Database Access

### MariaDB Credentials

The production MariaDB instance has the following users:

- **root**
  - Username: `root`
  - Password: `3StLYpY7z4R=` (stored in `DB_PASSWORD` env var)
  - Access: All databases

- **livrolog** (created manually for SSH tunnel access)
  - Username: `livrolog`
  - Password: `supersecret`
  - Access: `livrolog`, `livrolog_dev`, `livrolog_prod` databases

### SSH Tunnel for Database Access

The MariaDB port (3306) is exposed only to localhost for security. To access from your local machine:

**Option 1: Manual SSH Tunnel**
```bash
ssh -f -N -L 3306:localhost:3306 livrolog
```

**Option 2: Database Client with SSH Tunnel**
- SSH Host: `35.170.25.86`
- SSH Port: `22`
- SSH User: `bitnami`
- SSH Key: `~/.ssh/livrolog-key.pem`
- DB Host: `127.0.0.1`
- DB Port: `3306`
- DB User: `livrolog` (or `root`)
- DB Password: `supersecret` (or `3StLYpY7z4R=`)
- Database: `livrolog`

**Note**: The `livrolog` user must be manually recreated if the MariaDB container is rebuilt:

```bash
ssh livrolog
docker exec livrolog-mariadb-1 mysql -u root -p'3StLYpY7z4R=' -e "
CREATE USER IF NOT EXISTS 'livrolog'@'%' IDENTIFIED BY 'supersecret';
GRANT ALL PRIVILEGES ON livrolog.* TO 'livrolog'@'%';
GRANT ALL PRIVILEGES ON livrolog_prod.* TO 'livrolog'@'%';
GRANT ALL PRIVILEGES ON livrolog_dev.* TO 'livrolog'@'%';
FLUSH PRIVILEGES;
"
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
curl http://api.livrolog.com/health
curl http://api.dev.livrolog.com/health

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

### Fix deployment symlinks

After deployment, ensure symlinks are created:

```bash
ssh livrolog
cd /var/www/livrolog/current/api

# Create .env symlink
ln -sf ../../../shared/.env .env

# Create storage symlink  
rm -rf storage && ln -sf ../../../shared/storage storage

# Clear caches
php artisan config:clear && php artisan cache:clear
```

### Apache CSP Configuration

The Content Security Policy must include proper quotes. Update `/opt/bitnami/apache/conf/bitnami/bitnami-ssl.conf`:

```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-eval' 'unsafe-inline' https://accounts.google.com https://www.googletagmanager.com https://www.google-analytics.com; script-src-elem 'self' 'unsafe-eval' 'unsafe-inline' https://accounts.google.com https://www.googletagmanager.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https: http:; connect-src 'self' https://api.dev.livrolog.com https://api.livrolog.com https://accounts.google.com https://www.googleapis.com https://www.google-analytics.com https://analytics.google.com; frame-src https://accounts.google.com; object-src 'none'; base-uri 'self'"
```

**Important**: All CSP directives must use single quotes around keywords like `'self'` and `'unsafe-inline'`.
