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

# Check if APP_KEY exists and is valid
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" ]; then
    echo "⚠️ WARNING: APP_KEY is not set or invalid!"
    
    # In production, fail fast to avoid security issues
    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "❌ ERROR: Production requires a valid APP_KEY in .env file"
        echo "Generate one with: php artisan key:generate --show"
        exit 1
    else
        # Non-production: generate a key
        echo "Generating application key (non-production)..."
        php artisan key:generate --force || true
    fi
else
    echo "✅ APP_KEY is configured"
fi

# Laravel cache optimizations
echo "Optimizing Laravel caches..."
php artisan config:clear
php artisan config:cache
php artisan route:cache

# Only cache views if views directory exists
if [ -d "resources/views" ]; then
    echo "Caching views..."
    php artisan view:cache || true
fi

# Generate Swagger documentation
php artisan l5-swagger:generate || true

# Set proper permissions for Nginx
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "Laravel optimization completed successfully"

# Execute the main command (Supervisor with PHP-FPM + Nginx)
exec "$@"