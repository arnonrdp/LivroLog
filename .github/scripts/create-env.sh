#!/bin/bash
set -euo pipefail

# Create development environment file
cat > "${1}/.env.dev" << 'EOF'
APP_NAME=LivroLog
APP_ENV=development
APP_KEY=base64:YjM1ZDk3NWZhYjU4NGJhOTNjZGZkZDNlYmYyMzk3YzE0YzJhMjczNDg5YzE0YTViNzYxMzQ5NmQ3MWMxMGY5Mw==
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_FRONTEND_URL=https://dev.livrolog.com
APP_URL=https://api.dev.livrolog.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=livrolog_dev
DB_USERNAME=livrolog
DB_PASSWORD=supersecret

# Redis Configuration
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Session Configuration
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_DRIVER=redis

# Logging Configuration
MAIL_MAILER=log
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Google Services Configuration
GOOGLE_BOOKS_API_KEY=${GOOGLE_BOOKS_API_KEY:-}
GOOGLE_BOOKS_LOCATION_IP=177.37.0.0
GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID:-}
GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET:-}
GOOGLE_REDIRECT_URI=https://api.dev.livrolog.com/auth/google/callback

# Amazon Product Advertising API
AMAZON_ASSOCIATE_TAG=livrolog01-20
AMAZON_PA_API_KEY=${AMAZON_PA_API_KEY:-}
AMAZON_PA_SECRET_KEY=${AMAZON_PA_SECRET_KEY:-}
AMAZON_SITESTRIPE_ENABLED=true

# Other Services
OPEN_LIBRARY_ENABLED=true
BCRYPT_ROUNDS=12
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
PHP_CLI_SERVER_WORKERS=4
EOF

echo "✅ Environment file created"