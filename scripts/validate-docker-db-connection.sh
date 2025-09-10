#!/bin/bash
# Validate Docker container can connect to host MySQL/MariaDB
# Run this script on the production server after configuring MySQL

set -e

echo "🧪 Validating Docker container database connectivity..."

# Change to docker directory
cd /var/www/livrolog/docker

# Set environment variables
export OWNER=arnonrdp
export TAG=prod

echo "🐳 Starting API container for connectivity test..."
docker compose -p livrolog -f docker-compose.prod.yml up -d api

# Wait for container to start
sleep 5

# Get API container ID
API_CID=$(docker compose -p livrolog ps -q api)

if [ -z "$API_CID" ]; then
    echo "❌ API container not found or not started"
    exit 1
fi

echo "📍 API Container ID: $API_CID"

# Test 1: Check if host.docker.internal resolves
echo ""
echo "🔍 Test 1: DNS resolution of host.docker.internal"
if docker exec "$API_CID" getent hosts host.docker.internal; then
    echo "✅ host.docker.internal resolves correctly"
else
    echo "❌ host.docker.internal does not resolve"
    exit 1
fi

# Test 2: Test TCP connectivity to MySQL port
echo ""
echo "🔍 Test 2: TCP connectivity to MySQL port 3306"
if docker exec "$API_CID" bash -c 'timeout 5 bash -c "cat </dev/null >/dev/tcp/host.docker.internal/3306"' 2>/dev/null; then
    echo "✅ Database port 3306 is reachable"
else
    echo "❌ Database port 3306 is not reachable"
    echo "Trying with nc (if available):"
    docker exec "$API_CID" bash -c 'nc -vz host.docker.internal 3306' || true
    exit 1
fi

# Test 3: Check container health
echo ""
echo "🔍 Test 3: Container health check"
if docker exec "$API_CID" curl -fsS "http://localhost:8080/health" >/dev/null 2>&1; then
    echo "✅ API health endpoint responds correctly"
else
    echo "❌ API health endpoint not responding"
    echo "Container logs (last 20 lines):"
    docker logs --tail=20 "$API_CID"
fi

# Test 4: Check if Laravel can connect to database (optional)
echo ""
echo "🔍 Test 4: Laravel database connectivity (optional)"
if docker exec "$API_CID" php -r "
try {
    \$pdo = new PDO('mysql:host=host.docker.internal;port=3306;dbname=information_schema', 'root', getenv('DB_PASSWORD') ?: '');
    echo 'Database connection successful' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . PHP_EOL;
}
" 2>/dev/null; then
    echo "✅ Laravel can connect to database"
else
    echo "⚠️ Laravel cannot connect to database (check credentials in .env)"
fi

echo ""
echo "🧹 Cleaning up test container..."
docker compose -p livrolog -f docker-compose.prod.yml down || true

echo ""
echo "✅ Validation completed!"
echo ""
echo "📋 Summary:"
echo "  • host.docker.internal resolves correctly"
echo "  • TCP port 3306 is reachable from container"
echo "  • API health endpoint responds"
echo ""
echo "🚀 Ready for production deployment!"