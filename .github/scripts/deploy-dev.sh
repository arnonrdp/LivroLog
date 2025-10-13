#!/bin/bash
set -euo pipefail

# Configuration variables passed as environment
DOCKER_DIR="${DEPLOY_PATH}/docker"
SHARED_DIR="${DEPLOY_PATH}/shared"
OWNER="${GITHUB_REPOSITORY_OWNER}"
TAG="dev"
IMAGE_API="ghcr.io/${OWNER}/livrolog-api"
IMAGE_WEB="ghcr.io/${OWNER}/livrolog-web"

echo "=================================================="
echo "🚀 LivroLog Development Deployment"
echo "Branch: ${TARGET_BRANCH}"
echo "SHA: ${GITHUB_SHA}"
echo "Images: ${IMAGE_API}:${TAG}, ${IMAGE_WEB}:${TAG}"
echo "Deploy Path: ${DEPLOY_PATH}"
echo "=================================================="

# Create required directories with proper permissions
echo "📁 Setting up directories and permissions..."
sudo mkdir -p "${SHARED_DIR}"/{storage,db}
sudo mkdir -p "${DOCKER_DIR}"

# Set MySQL data directory permissions (UID 999)
sudo chown -R 999:999 "${SHARED_DIR}/db"

# Set Laravel storage permissions (www-data)
sudo chown -R www-data:www-data "${SHARED_DIR}/storage" 2>/dev/null || sudo chown -R 82:82 "${SHARED_DIR}/storage"

# Set deployment directory permissions
sudo chown -R ${SERVER_USER}:${SERVER_USER} "${DEPLOY_PATH}"

# Create development environment file with GitHub secrets
echo "📝 Creating development .env file..."
export AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID:-}"
export AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY:-}"
export GOOGLE_BOOKS_API_KEY="${GOOGLE_BOOKS_API_KEY:-}"
export GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID:-}"
export GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET:-}"
export AMAZON_PA_API_KEY="${AMAZON_PA_API_KEY:-}"
export AMAZON_PA_SECRET_KEY="${AMAZON_PA_SECRET_KEY:-}"

# Create environment file using separate script
bash "${DOCKER_DIR}/../scripts/create-env.sh" "${SHARED_DIR}"

# Check if containers are already running and restart API if needed
if docker ps | grep -q livrolog-api-dev; then
  echo "🔄 Restarting existing API container to apply new environment..."
  docker restart livrolog-api-dev
  sleep 15
fi

# Navigate to docker directory
cd "${DOCKER_DIR}"

# Login to GitHub Container Registry if PAT available
if [ -n "${GHCR_PAT:-}" ]; then
  echo "🔐 Authenticating with GitHub Container Registry..."
  echo "${GHCR_PAT}" | docker login ghcr.io -u "${OWNER}" --password-stdin
else
  echo "⚠️ No GHCR_PAT provided - attempting to pull public images"
fi

# Change to deployment directory where docker-compose.dev.yml is located
cd "${DOCKER_DIR}"

# Pull latest images with error handling
echo "📦 Pulling latest Docker images..."

# Force pull latest images using compose first
docker compose -f docker-compose.dev.yml pull || echo "⚠️ Compose pull failed, trying individual pulls"

if ! docker pull "${IMAGE_API}:${TAG}"; then
  echo "⚠️ Failed to pull API image, checking if local version exists..."
  if ! docker image inspect "${IMAGE_API}:${TAG}" >/dev/null 2>&1; then
    echo "❌ No API image available locally or remotely"
    exit 1
  fi
fi

if ! docker pull "${IMAGE_WEB}:${TAG}"; then
  echo "⚠️ Failed to pull Web image, checking if local version exists..."
  if ! docker image inspect "${IMAGE_WEB}:${TAG}" >/dev/null 2>&1; then
    echo "❌ No Web image available locally or remotely"
    exit 1
  fi
fi

# Clean up old images to force using new ones
echo "🧹 Cleaning up old images..."
docker image prune -f || true

# Stop existing containers gracefully
echo "🛑 Stopping existing development containers..."
if docker compose -f docker-compose.dev.yml ps --services --filter "status=running" | grep -q .; then
  docker compose -f docker-compose.dev.yml down --timeout 30
else
  echo "ℹ️ No running containers to stop"
fi

# Clean up orphaned containers and networks
echo "🧹 Cleaning up orphaned resources..."
docker system prune -f --volumes || true

# Export environment variables for docker-compose
export GITHUB_REPOSITORY_OWNER="${OWNER}"
export TAG="${TAG}"
export DB_DATABASE="livrolog_dev"
export DB_USERNAME="livrolog"
export DB_PASSWORD="supersecret"

# Start containers with health checks
echo "🚀 Starting development containers..."

if ! docker compose -f docker-compose.dev.yml up -d --force-recreate --remove-orphans; then
  echo "❌ Failed to start containers"
  echo "📊 Container logs:"
  docker compose -f docker-compose.dev.yml logs --tail=50
  exit 1
fi

# Wait for containers to initialize
echo "⏳ Waiting for containers to initialize (60 seconds)..."
sleep 60

# Health checks with detailed status
echo "🔍 Performing health checks..."

# Check container status
echo "📊 Container Status:"
docker compose -f docker-compose.dev.yml ps

# Check MySQL connectivity
echo "🔍 Testing MySQL connectivity..."
for i in {1..30}; do
  if docker exec livrolog-mysql-dev mysqladmin ping -h localhost -u livrolog -psupersecret --silent; then
    echo "✅ MySQL is responding"
    break
  else
    if [ $i -eq 30 ]; then
      echo "❌ MySQL connectivity test failed after 30 attempts"
      echo "MySQL logs:"
      docker logs livrolog-mysql-dev --tail=20
      exit 1
    fi
    echo "⏳ Waiting for MySQL (attempt $i/30)..."
    sleep 2
  fi
done

# Check Redis connectivity  
echo "🔍 Testing Redis connectivity..."
if docker exec livrolog-redis-dev redis-cli ping >/dev/null 2>&1; then
  echo "✅ Redis is responding"
else
  echo "❌ Redis connectivity test failed"
  echo "Redis logs:"
  docker logs livrolog-redis-dev --tail=20
  exit 1
fi

# Check API health endpoint (using Nginx static endpoint)
echo "🔍 Testing API health endpoint..."
for i in {1..12}; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8081/health 2>/dev/null || echo "000")
  if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ API health endpoint is responding"
    break
  else
    if [ $i -eq 12 ]; then
      echo "❌ API health endpoint test failed after 12 attempts (HTTP: $HTTP_CODE)"
      echo "API logs:"
      docker logs livrolog-api-dev --tail=30
      exit 1
    fi
    echo "⏳ Waiting for API health endpoint (attempt $i/12, HTTP: $HTTP_CODE)..."
    sleep 10
  fi
done

# Check Web frontend
echo "🔍 Testing Web frontend..."  
for i in {1..12}; do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/ 2>/dev/null || echo "000")
  if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ Web frontend is responding"
    break
  else
    if [ $i -eq 12 ]; then
      echo "❌ Web frontend test failed after 12 attempts (HTTP: $HTTP_CODE)"
      echo "Web logs:"
      docker logs livrolog-web-dev --tail=30
      exit 1
    fi
    echo "⏳ Waiting for Web frontend (attempt $i/12, HTTP: $HTTP_CODE)..."
    sleep 10
  fi
done

echo "=================================================="
echo "✅ LivroLog Development Deployment Complete!"
echo "=================================================="
echo "🌐 Access URLs:"
echo "  • Web Frontend: http://127.0.0.1:8080 (dev.livrolog.com)"
echo "  • API Backend: http://127.0.0.1:8081 (api.dev.livrolog.com)"
echo "  • MySQL: 127.0.0.1:3307"
echo "  • Redis: 127.0.0.1:6380"
echo "=================================================="