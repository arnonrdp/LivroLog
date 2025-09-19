#!/bin/bash
set -euo pipefail

echo "=== Fixing LivroLog Development Environment ==="
date

# Backup corrupted file
if [ -f /var/www/livrolog-dev/shared/.env.dev ]; then
  echo "📋 Backing up corrupted .env.dev file..."
  sudo cp /var/www/livrolog-dev/shared/.env.dev /var/www/livrolog-dev/shared/.env.dev.corrupted.$(date +%Y%m%d_%H%M%S)
fi

# Create clean environment file
echo "📝 Creating clean .env.dev file..."
sudo tee /var/www/livrolog-dev/shared/.env.dev > /dev/null << 'ENV_EOF'
APP_NAME=LivroLog
APP_ENV=development
APP_KEY=base64:UGxhY2Vob2xkZXJLZXlGb3JEZXZlbG9wbWVudA==
APP_DEBUG=true
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=livrolog_dev
DB_USERNAME=livrolog
DB_PASSWORD=supersecret

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_DRIVER=redis
MAIL_MAILER=log
LOG_CHANNEL=stack
LOG_LEVEL=debug
ENV_EOF

# Set proper permissions
sudo chown bitnami:bitnami /var/www/livrolog-dev/shared/.env.dev
chmod 644 /var/www/livrolog-dev/shared/.env.dev

echo "✅ New .env.dev file created"
echo "📋 File content verification:"
head -10 /var/www/livrolog-dev/shared/.env.dev

# Restart API container
echo "🔄 Restarting API container to apply new environment..."
docker restart livrolog-api-dev

# Wait for container to restart  
echo "⏳ Waiting for API container to restart..."
sleep 30

# Check container status
echo "📊 Container status after restart:"
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep livrolog-api-dev

# Test API health endpoint
echo "🧪 Testing API health endpoint..."
for i in {1..10}; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/healthz 2>/dev/null || echo "000")
  if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ API health endpoint responding (HTTP $HTTP_CODE) - attempt $i"
    break
  elif [ $i -eq 10 ]; then
    echo "❌ API health endpoint still failing after 10 attempts (HTTP $HTTP_CODE)"
    echo "=== API Container Logs ==="
    docker logs livrolog-api-dev --tail=30
  else
    echo "⏳ API not ready yet (HTTP $HTTP_CODE, attempt $i/10), waiting 10 seconds..."
    sleep 10
  fi
done

# Test books endpoint
echo "🧪 Testing books API endpoint..."
BOOKS_TEST=$(curl -s -w "HTTP_CODE:%{http_code}" http://127.0.0.1:8081/books?sort_by=popular 2>/dev/null)
echo "Books API test result: $(echo "$BOOKS_TEST" | grep HTTP_CODE || echo "No response")"

echo "✅ Fix process completed!"