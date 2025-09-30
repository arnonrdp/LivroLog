#!/usr/bin/env bash
# api/docker/entrypoint.sh
# Laravel optimization entrypoint for production containers
# Comments in English only

set -e

# Debug: print all commands as they execute
set -x

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

# Skip Swagger generation during container startup (can cause timeouts)
# Generate manually with: docker exec <container> php artisan l5-swagger:generate
# php artisan l5-swagger:generate || true

# Set proper permissions for Nginx (skip read-only files)
# Note: Some files may be read-only mounted, using || true to continue
chown -R www-data:www-data /var/www/html/bootstrap/cache /var/www/html/storage 2>/dev/null || true
chmod -R 775 /var/www/html/bootstrap/cache /var/www/html/storage 2>/dev/null || true

# Ensure supervisor log directory exists with correct permissions
mkdir -p /var/log/supervisor
chmod 755 /var/log/supervisor

echo "Laravel optimization completed successfully"
echo "================================================================"
echo "Starting Supervisor to manage PHP-FPM and Nginx..."
echo "Command: $@"
echo "================================================================"

# List supervisor binary location for debugging
which supervisord || echo "WARNING: supervisord not found in PATH"

# Execute the main command (Supervisor with PHP-FPM + Nginx)
exec "$@"