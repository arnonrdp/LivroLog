#!/bin/bash
#
# Database Backup Script for LivroLog Migration
# Creates backups of current MySQL databases before Docker migration
#

set -euo pipefail

# Configuration
BACKUP_DIR="/tmp/livrolog-migration-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
SERVER_HOST="35.170.25.86"
SERVER_USER="bitnami"
SSH_KEY="~/.ssh/livrolog-key.pem"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🗄️ LivroLog Database Backup Script${NC}"
echo "Timestamp: $TIMESTAMP"
echo "Backup directory: $BACKUP_DIR"
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Function to backup database via SSH
backup_database() {
    local env_name="$1"
    local db_name="$2"
    local db_user="$3"
    local db_password="$4"
    local backup_file="$BACKUP_DIR/livrolog_${env_name}_${TIMESTAMP}.sql"
    
    echo -e "${YELLOW}📥 Backing up ${env_name} database...${NC}"
    
    # Create backup via SSH
    ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" \
        "mysqldump -u'$db_user' -p'$db_password' '$db_name' --single-transaction --routines --triggers" \
        > "$backup_file"
    
    if [[ -s "$backup_file" ]]; then
        local size=$(du -h "$backup_file" | cut -f1)
        echo -e "${GREEN}✅ ${env_name} backup created: $backup_file ($size)${NC}"
    else
        echo -e "${RED}❌ ${env_name} backup failed or empty${NC}"
        return 1
    fi
}

# Function to test database connection
test_connection() {
    local env_name="$1"
    local db_user="$2"
    local db_password="$3"
    
    echo -e "${YELLOW}🔍 Testing ${env_name} connection...${NC}"
    
    if ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" \
        "mysql -u'$db_user' -p'$db_password' -e 'SELECT 1;' >/dev/null 2>&1"; then
        echo -e "${GREEN}✅ ${env_name} connection OK${NC}"
        return 0
    else
        echo -e "${RED}❌ ${env_name} connection failed${NC}"
        return 1
    fi
}

echo "🔗 Testing connections first..."

# Production database backup
echo -e "\n${YELLOW}=== PRODUCTION BACKUP ===${NC}"
if test_connection "Production" "root" "3StLYpY7z4R="; then
    backup_database "production" "livrolog" "root" "3StLYpY7z4R="
else
    echo -e "${RED}Skipping production backup due to connection failure${NC}"
fi

# Development database backup (assuming similar credentials)
echo -e "\n${YELLOW}=== DEVELOPMENT BACKUP ===${NC}"
# Note: Development might use different credentials - check .env.dev on server
if ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" "test -f /var/www/livrolog-dev/shared/.env.dev"; then
    echo "📋 Found development .env file, extracting credentials..."
    
    # Extract DB credentials from dev environment
    DEV_CREDS=$(ssh -i "$SSH_KEY" "$SERVER_USER@$SERVER_HOST" \
        "grep -E '^(DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' /var/www/livrolog-dev/shared/.env.dev 2>/dev/null || echo 'DB_DATABASE=livrolog_dev
DB_USERNAME=root
DB_PASSWORD=3StLYpY7z4R='")
    
    eval "$DEV_CREDS"
    
    if test_connection "Development" "${DB_USERNAME:-root}" "${DB_PASSWORD:-3StLYpY7z4R=}"; then
        backup_database "development" "${DB_DATABASE:-livrolog_dev}" "${DB_USERNAME:-root}" "${DB_PASSWORD:-3StLYpY7z4R=}"
    else
        echo -e "${YELLOW}⚠️  Development database might not exist or use different credentials${NC}"
        echo "This is normal if development uses the same database as production"
    fi
else
    echo -e "${YELLOW}⚠️  Development .env file not found, skipping dev backup${NC}"
fi

echo -e "\n${YELLOW}=== BACKUP SUMMARY ===${NC}"
echo "Backup directory: $BACKUP_DIR"
ls -lah "$BACKUP_DIR"/*.sql 2>/dev/null || echo "No SQL backups found"

echo -e "\n${GREEN}🎯 Backup script completed!${NC}"
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Verify backup files in $BACKUP_DIR"
echo "2. Store backups in a safe location before migration"
echo "3. Test restore procedure if needed"
echo ""
echo -e "${YELLOW}💡 Restore command example:${NC}"
echo "mysql -u root -p database_name < backup_file.sql"