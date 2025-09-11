#!/bin/bash
#
# Database Migration Script
# Migrates data from host MySQL to Docker containers
#

set -euo pipefail

# Configuration
SERVER_HOST="35.170.25.86"
SERVER_USER="bitnami"
SSH_KEY="~/.ssh/livrolog-key.pem"
BACKUP_DIR="/tmp/livrolog-migration-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Database Migration Script${NC}"
echo "This script will migrate data from host databases to Docker containers"
echo "Timestamp: $TIMESTAMP"
echo ""

# Function to show usage
show_usage() {
    echo "Usage: $0 [environment]"
    echo ""
    echo "Arguments:"
    echo "  environment    'dev' for development or 'prod' for production"
    echo ""
    echo "Examples:"
    echo "  $0 dev    # Migrate development database"
    echo "  $0 prod   # Migrate production database"
    echo ""
}

# Function to migrate development data
migrate_dev() {
    echo -e "${YELLOW}🔄 Migrating Development Database${NC}"
    echo ""
    
    # Step 1: Create backup
    echo -e "${BLUE}Step 1: Creating backup${NC}"
    ./scripts/backup-databases.sh
    
    # Step 2: Stop current containers
    echo -e "${BLUE}Step 2: Stopping current containers${NC}"
    ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" \
        "cd /var/www/livrolog-dev/docker && docker compose down || true"
    
    # Step 3: Trigger new deployment (push to dev branch needed)
    echo -e "${BLUE}Step 3: New deployment required${NC}"
    echo "⚠️  You need to push changes to 'dev' branch to trigger new deployment"
    echo "⚠️  Or use GitHub Actions manual dispatch"
    
    # Step 4: Wait for containers and import data
    echo -e "${BLUE}Step 4: Data import (after deployment)${NC}"
    echo "After new containers are running, execute:"
    echo ""
    echo "# Find the latest backup"
    echo "ls -la $BACKUP_DIR/livrolog_development_*.sql"
    echo ""
    echo "# Import to new MySQL container"
    echo "ssh -i $SSH_KEY $SERVER_USER@$SERVER_HOST"
    echo "docker exec -i livrolog-mysql-dev mysql -u livrolog -psupersecret livrolog_dev < latest_backup.sql"
}

# Function to migrate production data  
migrate_prod() {
    echo -e "${YELLOW}🔄 Migrating Production Database${NC}"
    echo ""
    echo -e "${RED}⚠️  WARNING: This will affect production!${NC}"
    echo "Make sure you have:"
    echo "1. ✅ Valid backups"
    echo "2. ✅ Tested the migration in development"
    echo "3. ✅ Maintenance window scheduled"
    echo ""
    
    read -p "Continue with production migration? (type 'YES' to confirm): " confirm
    if [[ "$confirm" != "YES" ]]; then
        echo "Migration cancelled"
        exit 1
    fi
    
    # Step 1: Create backup
    echo -e "${BLUE}Step 1: Creating backup${NC}"
    ./scripts/backup-databases.sh
    
    # Step 2: Stop current containers
    echo -e "${BLUE}Step 2: Stopping current containers${NC}"
    ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" \
        "cd /var/www/livrolog/docker && docker compose down || true"
    
    # Step 3: Trigger new deployment (push to main branch needed)
    echo -e "${BLUE}Step 3: New deployment required${NC}"
    echo "⚠️  You need to push changes to 'main' branch to trigger new deployment"
    echo "⚠️  Or use GitHub Actions manual dispatch"
    
    # Step 4: Wait for containers and import data
    echo -e "${BLUE}Step 4: Data import (after deployment)${NC}"
    echo "After new containers are running, execute:"
    echo ""
    echo "# Find the latest backup"
    echo "ls -la $BACKUP_DIR/livrolog_production_*.sql"
    echo ""
    echo "# Import to new MariaDB container"
    echo "ssh -i $SSH_KEY $SERVER_USER@$SERVER_HOST"
    echo "docker exec -i livrolog-mariadb mysql -u root -p'PASSWORD' livrolog < latest_backup.sql"
    echo ""
    echo "# Test the application"
    echo "curl http://35.170.25.86:18080/healthz"
    echo "curl http://35.170.25.86:18081/healthz"
}

# Function to show status
show_status() {
    echo -e "${BLUE}📊 Current Status${NC}"
    echo ""
    
    echo -e "${YELLOW}Checking server containers...${NC}"
    ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" "docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'" || echo "No containers running"
    
    echo ""
    echo -e "${YELLOW}Checking available backups...${NC}"
    ls -la "$BACKUP_DIR"/*.sql 2>/dev/null || echo "No backups found in $BACKUP_DIR"
}

# Main script logic
if [[ $# -eq 0 ]]; then
    show_usage
    show_status
    exit 1
fi

ENVIRONMENT="$1"

case "$ENVIRONMENT" in
    "dev"|"development")
        migrate_dev
        ;;
    "prod"|"production")
        migrate_prod
        ;;
    "status")
        show_status
        ;;
    *)
        echo -e "${RED}❌ Unknown environment: $ENVIRONMENT${NC}"
        show_usage
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}✅ Migration script completed${NC}"
echo ""
echo -e "${YELLOW}Important reminders:${NC}"
echo "1. Monitor containers after deployment"
echo "2. Test all application functionality"  
echo "3. Keep backups for rollback if needed"
echo "4. Update DNS/proxy settings if required"