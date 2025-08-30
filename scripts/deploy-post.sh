#!/bin/bash
#
# Post-deployment script for LivroLog
# This should be called after deploying new code
#

echo "=== Running post-deployment tasks ==="

# 1. Clear Laravel caches
echo "Clearing Laravel caches..."
if [ -d "/var/www/livrolog/current/api" ]; then
    cd /var/www/livrolog/current/api
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
fi

if [ -d "/var/www/livrolog-dev/current/api" ]; then
    cd /var/www/livrolog-dev/current/api
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
fi

# 2. Restart queue workers
echo ""
echo "Restarting queue workers..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bash "$SCRIPT_DIR/restart-queue-workers.sh"

# 3. Run database migrations (if needed)
echo ""
echo "Checking for pending migrations..."
if [ -d "/var/www/livrolog/current/api" ]; then
    cd /var/www/livrolog/current/api
    php artisan migrate --force
fi

if [ -d "/var/www/livrolog-dev/current/api" ]; then
    cd /var/www/livrolog-dev/current/api
    php artisan migrate --force
fi

echo ""
echo "=== Post-deployment tasks completed ==="#