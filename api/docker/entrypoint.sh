#!/usr/bin/env bash
# api/docker/entrypoint.sh
# Laravel optimization entrypoint for production containers
# Comments in English only

set -e

# Try to get APP_KEY from environment variable first, then from .env file
APP_KEY_TO_CHECK="${APP_KEY}"
if [ -z "${APP_KEY_TO_CHECK}" ] && [ -f "/var/www/html/.env" ]; then
    APP_KEY_TO_CHECK=$(grep '^APP_KEY=' /var/www/html/.env | cut -d'=' -f2 | tr -d '"'"'"'')
    echo "📖 Reading APP_KEY from .env file"
fi

# Check if APP_KEY exists and is valid (base64: + at least 44 chars = 50+ total)
if [ -z "${APP_KEY_TO_CHECK}" ] || [[ "${APP_KEY_TO_CHECK}" == *"XXXX"* ]] || [[ ! "${APP_KEY_TO_CHECK}" =~ ^base64: ]] || [ ${#APP_KEY_TO_CHECK} -lt 50 ]; then
    echo "⚠️ WARNING: APP_KEY is not set or invalid!"
    echo "  Current value length: ${#APP_KEY_TO_CHECK} chars"
    echo "  Required: starts with 'base64:' and at least 50 chars total"
    
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
    echo "✅ APP_KEY is configured (${#APP_KEY_TO_CHECK} chars)"
fi

# Safe Laravel optimizations (do not depend on DB)
echo "Optimizing Laravel caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan config:cache || true
php artisan route:cache || true

# Only cache views if views directory exists
if [ -d "resources/views" ]; then
    echo "Caching views..."
    php artisan view:cache || true
else
    echo "WARNING: 'resources/views' directory not found. Skipping view cache step."
fi

# Generate Swagger documentation (optional)
php artisan l5-swagger:generate || true

# Set proper permissions for Nginx (skip .env if read-only)
chown -R www-data:www-data /var/www/html 2>/dev/null || echo "Note: Some files are read-only mounted"
chmod -R 755 /var/www/html 2>/dev/null || true

# Handle .env separately since it might be read-only mounted
if [ -f "/var/www/html/.env" ]; then
    chown www-data:www-data /var/www/html/.env 2>/dev/null || echo "Note: .env is read-only mounted (this is expected)"
    chmod 644 /var/www/html/.env 2>/dev/null || true
fi

# Ensure supervisor log directory exists with correct permissions
mkdir -p /var/log/supervisor
chmod 755 /var/log/supervisor

echo "Laravel optimization completed successfully"

# Execute the main command (Supervisor with PHP-FPM + Nginx)
exec "$@"