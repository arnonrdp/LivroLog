# Deployment Scripts

Scripts to help with deployment and maintenance of LivroLog.

## Scripts

### `restart-queue-workers.sh`
Restarts Laravel queue workers after deployment to ensure they're using the latest code.

**Usage:**
```bash
ssh livrolog 'bash /path/to/scripts/restart-queue-workers.sh'
```

### `deploy-post.sh`
Runs all necessary post-deployment tasks:
1. Clears Laravel caches
2. Restarts queue workers
3. Runs database migrations

**Usage:**
```bash
ssh livrolog 'bash /path/to/scripts/deploy-post.sh'
```

## Integration with CI/CD

Add this to your deployment pipeline after code is deployed:

```bash
# Example for GitHub Actions or similar
ssh user@server << 'EOF'
  cd /var/www/livrolog/current
  bash scripts/deploy-post.sh
EOF
```

## Manual Queue Worker Management

If you need to manually manage workers:

```bash
# Stop all workers
pkill -f "php artisan queue:work"

# Start production worker
cd /var/www/livrolog/current/api
nohup php artisan queue:work --verbose --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Start dev worker
cd /var/www/livrolog-dev/current/api
nohup php artisan queue:work --verbose --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Check workers status
ps aux | grep "queue:work" | grep -v grep
```

## Monitoring

Check if jobs are being processed:

```bash
# Production
cd /var/www/livrolog/current/api
php artisan tinker --execute="echo 'Jobs in queue: ' . DB::table('jobs')->count();"

# Dev
cd /var/www/livrolog-dev/current/api
php artisan tinker --execute="echo 'Jobs in queue: ' . DB::table('jobs')->count();"
```