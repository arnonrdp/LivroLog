#!/bin/bash
# Script to verify production .env file has all required variables
# Run on production server before Docker deployment

set -e

ENV_FILE="/var/www/livrolog/shared/.env"

echo "🔍 Checking production environment file: $ENV_FILE"

# Check if file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ ERROR: $ENV_FILE does not exist!"
    exit 1
fi

# Required variables
REQUIRED_VARS=(
    "APP_NAME"
    "APP_ENV"
    "APP_KEY"
    "APP_URL"
    "DB_CONNECTION"
    "DB_HOST"
    "DB_PORT"
    "DB_DATABASE"
    "DB_USERNAME"
    "DB_PASSWORD"
)

# Check each required variable
MISSING_VARS=()
for VAR in "${REQUIRED_VARS[@]}"; do
    if ! grep -q "^${VAR}=" "$ENV_FILE"; then
        MISSING_VARS+=("$VAR")
    fi
done

# Report missing variables
if [ ${#MISSING_VARS[@]} -gt 0 ]; then
    echo "❌ Missing required variables:"
    printf ' - %s\n' "${MISSING_VARS[@]}"
    exit 1
fi

# Check APP_KEY specifically
APP_KEY=$(grep "^APP_KEY=" "$ENV_FILE" | cut -d'=' -f2-)
# Validate APP_KEY: not empty, not default, starts with 'base64:', and is at least 50 chars total
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ] || [[ "$APP_KEY" == *"XXXX"* ]] || [[ ! "$APP_KEY" =~ ^base64: ]] || [ ${#APP_KEY} -lt 50 ]; then
    echo "❌ ERROR: APP_KEY is invalid or not set properly!"
    echo ""
    echo "Requirements:"
    echo "  • Must start with 'base64:'"
    echo "  • Must be at least 50 characters total (base64: + 44 chars minimum)"
    echo "  • Must not contain placeholder text (XXXX)"
    echo ""
    echo "To generate a new APP_KEY, run:"
    echo "  docker run --rm ghcr.io/arnonrdp/livrolog-api:prod php artisan key:generate --show"
    echo ""
    echo "Then add it to $ENV_FILE as:"
    echo "  APP_KEY=base64:generated_key_here"
    exit 1
fi

# Check APP_ENV
APP_ENV=$(grep "^APP_ENV=" "$ENV_FILE" | cut -d'=' -f2-)
if [ "$APP_ENV" != "production" ]; then
    echo "⚠️ WARNING: APP_ENV is set to '$APP_ENV' (expected 'production')"
fi

# Check database connection
echo "📊 Database configuration:"
grep "^DB_" "$ENV_FILE" | sed 's/DB_PASSWORD=.*/DB_PASSWORD=***hidden***/'

# Check Redis (optional but recommended)
if grep -q "^REDIS_HOST=" "$ENV_FILE"; then
    echo "✅ Redis configuration found"
else
    echo "⚠️ Redis not configured (optional)"
fi

# Check Google Books API (optional but recommended)
if grep -q "^GOOGLE_BOOKS_API_KEY=" "$ENV_FILE"; then
    echo "✅ Google Books API configured"
else
    echo "⚠️ Google Books API not configured (optional)"
fi

echo ""
echo "✅ Environment file validation completed!"
echo ""
echo "📋 Summary:"
echo "  - All required variables are present"
echo "  - APP_KEY is configured"
echo "  - Database settings are defined"
echo ""
echo "🚀 Ready for Docker deployment!"