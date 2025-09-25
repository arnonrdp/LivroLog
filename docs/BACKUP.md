# LivroLog Backup System

## Overview

The LivroLog backup system automatically backs up the production database daily and maintains a rolling 30-day retention policy. Additionally, it automatically synchronizes the production database to the development environment after each backup.

## Backup Schedule

- **Frequency**: Daily
- **Time**: 3:00 AM UTC
- **Retention**: 30 days
- **Location**: `/home/bitnami/backups/`

## Components

### 1. Backup Script (`scripts/backup_database.sh`)

Main backup script that:
- Creates compressed backups of the production database (`livrolog`)
- Automatically restores the latest production backup to the development database (`livrolog_dev`)
- Cleans up backups older than 30 days
- Logs all operations to `/home/bitnami/backup.log`

### 2. Setup Script (`scripts/setup_backup_cron.sh`)

Deployment script that:
- Ensures backup script is in the correct location
- Creates backup directory if needed
- Configures cron job for daily backups
- Runs initial backup if no backups exist

### 3. GitHub Actions Integration

The backup system is automatically deployed during:
- Development deployments (branch: `dev`)
- Production deployments (branch: `main`)

## Manual Operations

### Run Manual Backup

```bash
ssh livrolog
/home/bitnami/backup_database.sh
```

### Check Backup Status

```bash
ssh livrolog
ls -lah /home/bitnami/backups/
tail -20 /home/bitnami/backup.log
```

### Restore Specific Backup

```bash
ssh livrolog
# Decompress backup
gunzip -c /home/bitnami/backups/backup_livrolog_YYYYMMDD_HHMMSS.sql.gz > /tmp/restore.sql

# Restore to dev database
docker exec -i livrolog-mysql-dev mysql -u root -psupersecret livrolog_dev < /tmp/restore.sql
```

### View Cron Jobs

```bash
ssh livrolog
crontab -l | grep backup
```

## Database Credentials

### Production (MariaDB)
- Container: `livrolog-mariadb-1`
- Database: `livrolog`
- Password: Stored in script (encrypted in secrets)

### Development (MySQL)
- Container: `livrolog-mysql-dev`
- Database: `livrolog_dev`
- Password: `supersecret`

## Monitoring

### Log Files
- **Backup Log**: `/home/bitnami/backup.log` - All backup operations
- **Cron Log**: `/home/bitnami/backup_cron.log` - Cron execution output

### Health Checks
Monitor backup health by checking:
1. Last backup date in logs
2. Backup file sizes (should be ~280KB compressed)
3. Number of backups (should maintain ~30 files)

## Troubleshooting

### Backup Fails

1. Check Docker containers are running:
```bash
docker ps | grep -E "mariadb|mysql"
```

2. Verify database credentials:
```bash
docker exec livrolog-mariadb-1 mysqldump -u root -p"3StLYpY7z4R=" livrolog > /dev/null && echo "OK"
```

3. Check disk space:
```bash
df -h /home/bitnami
```

### Restore Fails

1. Verify development container is running:
```bash
docker ps | grep mysql-dev
```

2. Test connection:
```bash
docker exec livrolog-mysql-dev mysql -u root -psupersecret -e "SHOW DATABASES;"
```

### Cron Not Running

1. Check cron service:
```bash
service cron status
```

2. Verify cron job exists:
```bash
crontab -l | grep backup_database
```

3. Re-run setup script:
```bash
/home/bitnami/setup_backup_cron.sh
```

## Recovery Procedures

### Full Database Recovery

In case of catastrophic failure:

1. Locate latest backup:
```bash
ls -t /home/bitnami/backups/backup_livrolog_*.sql.gz | head -1
```

2. Stop API container to prevent writes:
```bash
docker stop livrolog-api-1
```

3. Restore database:
```bash
gunzip -c /home/bitnami/backups/backup_livrolog_LATEST.sql.gz | \
  docker exec -i livrolog-mariadb-1 mysql -u root -p"3StLYpY7z4R=" livrolog
```

4. Restart API container:
```bash
docker start livrolog-api-1
```

## Security Notes

- Backup files contain sensitive data and should be treated as confidential
- Database passwords are stored in the backup script (consider using environment variables in production)
- SSH access is required for manual operations
- Backup directory is only accessible by the `bitnami` user