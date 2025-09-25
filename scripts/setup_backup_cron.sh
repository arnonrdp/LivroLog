#!/bin/bash

# Setup backup cron job for LivroLog production server
# This script is executed during deployment to ensure backup automation persists

echo "Setting up backup cron job..."

# Ensure backup script is in place
if [ ! -f "/home/bitnami/backup_database.sh" ]; then
    cp /home/bitnami/scripts/backup_database.sh /home/bitnami/backup_database.sh
    chmod +x /home/bitnami/backup_database.sh
    echo "✓ Backup script copied to /home/bitnami/"
else
    echo "✓ Backup script already exists"
fi

# Ensure backup directory exists
mkdir -p /home/bitnami/backups
echo "✓ Backup directory ensured"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "backup_database.sh"; then
    echo "✓ Backup cron job already exists"
else
    # Add cron job for daily backup at 3 AM UTC
    (crontab -l 2>/dev/null; echo "0 3 * * * /home/bitnami/backup_database.sh >> /home/bitnami/backup_cron.log 2>&1") | crontab -
    echo "✓ Added daily backup cron job (3:00 AM UTC)"
fi

# Run initial backup if no backups exist
if [ -z "$(ls -A /home/bitnami/backups/*.sql.gz 2>/dev/null)" ]; then
    echo "No backups found. Running initial backup..."
    /home/bitnami/backup_database.sh
else
    echo "✓ Existing backups found"
fi

echo "Backup cron setup completed!"
echo ""
echo "Backup Schedule:"
echo "  - Daily at 3:00 AM UTC"
echo "  - Location: /home/bitnami/backups/"
echo "  - Logs: /home/bitnami/backup.log"
echo "  - Retention: 30 days"
echo ""

# Show current cron jobs
echo "Current backup cron jobs:"
crontab -l | grep backup || echo "  (none found)"