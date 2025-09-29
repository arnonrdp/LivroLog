#!/bin/bash

# Database backup script for LivroLog
# Backups production database and restores it to development

# Configuration
BACKUP_DIR="/home/bitnami/backups"
DATE=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="/home/bitnami/backup.log"

# Database credentials
PROD_PASSWORD="3StLYpY7z4R="
DEV_PASSWORD="supersecret"

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Function to log messages
log_message() {
    echo "[$(date)] $1" >> $LOG_FILE
    echo "[$(date)] $1"  # Also print to stdout
}

# Function to backup database using Docker
backup_database() {
    local container=$1
    local db_name=$2
    local password=$3
    local backup_file="${BACKUP_DIR}/backup_${db_name}_${DATE}.sql"

    log_message "Starting backup of $db_name database from container $container..."

    # Use docker exec to run mysqldump
    # Capture stdout (SQL dump) to file, stderr shows warnings but doesn't stop the dump
    docker exec $container mysqldump -u root -p"$password" --single-transaction --quick --lock-tables=false $db_name > $backup_file 2>/dev/null
    local exit_code=$?

    # Check if backup succeeded (exit code 0 or 2 - code 2 means warnings but dump succeeded)
    if [ $exit_code -eq 0 ] || [ $exit_code -eq 2 ]; then
        # Check if backup file has content
        if [ -s $backup_file ]; then
            # Compress the backup
            gzip $backup_file
            local size=$(ls -lh ${backup_file}.gz | awk '{print $5}')
            log_message "SUCCESS: Backup completed for $db_name - ${backup_file}.gz (Size: $size)"
            return 0
        else
            log_message "ERROR: Backup file for $db_name is empty"
            rm -f $backup_file
            return 1
        fi
    else
        log_message "ERROR: Backup failed for $db_name (exit code: $exit_code)"
        rm -f $backup_file  # Clean up failed backup
        return 1
    fi
}

# Function to restore livrolog to livrolog_dev
restore_to_dev() {
    local latest_backup=$(ls -t ${BACKUP_DIR}/backup_livrolog_*.sql.gz 2>/dev/null | head -1)

    if [ -z "$latest_backup" ]; then
        log_message "ERROR: No livrolog backup found to restore to livrolog_dev"
        return 1
    fi

    log_message "Restoring $latest_backup to livrolog_dev database..."

    # Drop and recreate livrolog_dev database in the dev container
    if ! docker exec livrolog-mysql-dev mysql -u root -p"$DEV_PASSWORD" -e "DROP DATABASE IF EXISTS livrolog_dev; CREATE DATABASE livrolog_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null; then
        log_message "ERROR: Failed to recreate livrolog_dev database"
        return 1
    fi

    # Restore the backup
    if gunzip -c $latest_backup | docker exec -i livrolog-mysql-dev mysql -u root -p"$DEV_PASSWORD" livrolog_dev 2>/dev/null; then
        # Count tables to verify restore
        table_count=$(docker exec livrolog-mysql-dev mysql -u root -p"$DEV_PASSWORD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='livrolog_dev';" -sN 2>/dev/null)
        log_message "SUCCESS: Restored livrolog to livrolog_dev ($table_count tables)"
        return 0
    else
        log_message "ERROR: Failed to restore livrolog to livrolog_dev"
        return 1
    fi
}

# Main backup process
log_message "=== Starting backup process ==="

# Backup production database from mariadb container
if backup_database "livrolog-mariadb-1" "livrolog" "$PROD_PASSWORD"; then
    # Replace livrolog_dev with latest livrolog backup
    restore_to_dev
else
    log_message "ERROR: Production backup failed, skipping dev restore"
fi

# Clean up old backups (keep last 30 days)
old_backups=$(find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 2>/dev/null)
if [ ! -z "$old_backups" ]; then
    echo "$old_backups" | xargs rm -f
    deleted_count=$(echo "$old_backups" | wc -l)
    log_message "Cleaned up $deleted_count old backup(s)"
fi

# Report status
backup_count=$(ls -1 ${BACKUP_DIR}/backup_*.sql.gz 2>/dev/null | wc -l)
total_size=$(du -sh $BACKUP_DIR 2>/dev/null | cut -f1)
latest_backup=$(ls -t ${BACKUP_DIR}/backup_*.sql.gz 2>/dev/null | head -1)
if [ ! -z "$latest_backup" ]; then
    log_message "Latest backup: $(basename $latest_backup) - $(ls -lh $latest_backup | awk '{print $5}')"
fi
log_message "Status: $backup_count backups total, Total size: $total_size"
log_message "=== Backup process completed ==="