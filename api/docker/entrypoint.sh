#!/usr/bin/env bash
# api/docker/entrypoint.sh
# Laravel optimization entrypoint for production containers
# Comments in English only

set -e

# Wait for database to be ready
echo "Waiting for database connection..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "Database not ready, waiting 2 seconds..."
    sleep 2
done

# Generate application key if needed
php artisan key:generate --force || true

# Laravel cache optimizations
echo "Optimizing Laravel caches..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

# Generate Swagger documentation
php artisan l5-swagger:generate || true

# Set proper permissions for Nginx
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "Laravel optimization completed successfully"

# Execute the main command (Supervisor with PHP-FPM + Nginx)
exec "$@"