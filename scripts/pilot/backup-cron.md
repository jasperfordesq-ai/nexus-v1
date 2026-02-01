# Database Backup Cron Configuration

**Document ID:** NEXUS-BACKUP-CRON
**Version:** 1.0
**Date:** February 2026

---

## Overview

This document provides cron job examples for automating database backups using the `backup_database.php` script.

---

## Prerequisites

1. `backup_database.php` script located at `/var/www/nexus/scripts/backup_database.php`
2. PHP CLI installed and accessible
3. MySQL credentials configured in `.env` or script
4. Backup directory with write permissions
5. Sufficient disk space for backups

---

## Basic Daily Backup

Run backup at 02:00 every day:

```cron
# Daily database backup at 02:00
0 2 * * * /usr/bin/php /var/www/nexus/scripts/backup_database.php >> /var/log/nexus/backup.log 2>&1
```

---

## Rotating Backups

### Keep 7 Daily Backups

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_rotate.sh

BACKUP_DIR="/var/backups/nexus"
KEEP_DAYS=7

# Create backup
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Remove backups older than KEEP_DAYS
find "$BACKUP_DIR" -name "nexus_backup_*.sql.gz" -mtime +$KEEP_DAYS -delete

# Log result
echo "$(date): Backup completed, old backups cleaned" >> /var/log/nexus/backup.log
```

Cron entry:
```cron
# Daily rotating backup at 02:00
0 2 * * * /var/www/nexus/scripts/backup_rotate.sh >> /var/log/nexus/backup.log 2>&1
```

### Keep Weekly and Monthly Backups

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_tiered.sh

BACKUP_DIR="/var/backups/nexus"
DATE=$(date +%Y%m%d)
DAY_OF_WEEK=$(date +%u)  # 1=Monday, 7=Sunday
DAY_OF_MONTH=$(date +%d)

# Daily backup
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Copy to weekly folder on Sundays
if [ "$DAY_OF_WEEK" -eq 7 ]; then
    cp "$BACKUP_DIR/nexus_backup_$DATE.sql.gz" "$BACKUP_DIR/weekly/" 2>/dev/null || true
fi

# Copy to monthly folder on 1st of month
if [ "$DAY_OF_MONTH" -eq "01" ]; then
    cp "$BACKUP_DIR/nexus_backup_$DATE.sql.gz" "$BACKUP_DIR/monthly/" 2>/dev/null || true
fi

# Cleanup: Keep 7 daily, 4 weekly, 12 monthly
find "$BACKUP_DIR" -maxdepth 1 -name "nexus_backup_*.sql.gz" -mtime +7 -delete
find "$BACKUP_DIR/weekly" -name "*.sql.gz" -mtime +28 -delete 2>/dev/null || true
find "$BACKUP_DIR/monthly" -name "*.sql.gz" -mtime +365 -delete 2>/dev/null || true

echo "$(date): Tiered backup completed" >> /var/log/nexus/backup.log
```

Setup directories:
```bash
mkdir -p /var/backups/nexus/weekly
mkdir -p /var/backups/nexus/monthly
chown -R www-data:www-data /var/backups/nexus
```

Cron entry:
```cron
# Tiered backup at 02:00
0 2 * * * /var/www/nexus/scripts/backup_tiered.sh >> /var/log/nexus/backup.log 2>&1
```

---

## Offsite Backup Sync

### Sync to Remote Server (rsync over SSH)

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_offsite.sh

BACKUP_DIR="/var/backups/nexus"
REMOTE_USER="backup"
REMOTE_HOST="backup.example.com"
REMOTE_PATH="/backups/nexus"

# Run local backup first
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Sync to remote (requires SSH key authentication)
rsync -avz --delete "$BACKUP_DIR/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"

if [ $? -eq 0 ]; then
    echo "$(date): Offsite sync completed" >> /var/log/nexus/backup.log
else
    echo "$(date): ERROR - Offsite sync failed" >> /var/log/nexus/backup.log
fi
```

### Sync to AWS S3

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_s3.sh

BACKUP_DIR="/var/backups/nexus"
S3_BUCKET="s3://council-backups/nexus"
DATE=$(date +%Y%m%d)

# Run local backup
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Sync to S3 (requires AWS CLI configured)
aws s3 cp "$BACKUP_DIR/nexus_backup_$DATE.sql.gz" "$S3_BUCKET/daily/"

# Lifecycle policy in S3 handles retention
echo "$(date): S3 upload completed" >> /var/log/nexus/backup.log
```

### Sync to Azure Blob Storage

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_azure.sh

BACKUP_DIR="/var/backups/nexus"
CONTAINER="nexus-backups"
DATE=$(date +%Y%m%d)

# Run local backup
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Upload to Azure (requires Azure CLI configured)
az storage blob upload \
    --container-name "$CONTAINER" \
    --file "$BACKUP_DIR/nexus_backup_$DATE.sql.gz" \
    --name "daily/nexus_backup_$DATE.sql.gz"

echo "$(date): Azure upload completed" >> /var/log/nexus/backup.log
```

---

## Encrypted Backups

### Encrypt Backup with GPG

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_encrypted.sh

BACKUP_DIR="/var/backups/nexus"
GPG_RECIPIENT="backup@council.gov.uk"
DATE=$(date +%Y%m%d)

# Run backup
/usr/bin/php /var/www/nexus/scripts/backup_database.php

# Encrypt the backup
BACKUP_FILE="$BACKUP_DIR/nexus_backup_$DATE.sql.gz"
if [ -f "$BACKUP_FILE" ]; then
    gpg --encrypt --recipient "$GPG_RECIPIENT" "$BACKUP_FILE"

    # Remove unencrypted version
    rm "$BACKUP_FILE"

    echo "$(date): Encrypted backup created" >> /var/log/nexus/backup.log
else
    echo "$(date): ERROR - Backup file not found" >> /var/log/nexus/backup.log
fi
```

### Decrypt Backup for Restore

```bash
gpg --decrypt nexus_backup_20260201.sql.gz.gpg > nexus_backup_20260201.sql.gz
gunzip nexus_backup_20260201.sql.gz
```

---

## Backup Verification

### Verify Backup Integrity

```bash
#!/bin/bash
# /var/www/nexus/scripts/backup_verify.sh

BACKUP_DIR="/var/backups/nexus"
LATEST=$(ls -t "$BACKUP_DIR"/nexus_backup_*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST" ]; then
    echo "$(date): ERROR - No backup files found" >> /var/log/nexus/backup.log
    exit 1
fi

# Test gzip integrity
if gunzip -t "$LATEST" 2>/dev/null; then
    echo "$(date): Backup verified OK: $LATEST" >> /var/log/nexus/backup.log
else
    echo "$(date): ERROR - Backup corrupted: $LATEST" >> /var/log/nexus/backup.log
    # Send alert
    # mail -s "NEXUS Backup Corrupted" admin@council.gov.uk < /dev/null
    exit 1
fi

# Check backup size (alert if suspiciously small)
SIZE=$(stat -f%z "$LATEST" 2>/dev/null || stat -c%s "$LATEST" 2>/dev/null)
MIN_SIZE=1000000  # 1MB minimum

if [ "$SIZE" -lt "$MIN_SIZE" ]; then
    echo "$(date): WARNING - Backup unusually small: $SIZE bytes" >> /var/log/nexus/backup.log
fi
```

Add to cron after backup:
```cron
# Verify backup at 03:00 (after 02:00 backup)
0 3 * * * /var/www/nexus/scripts/backup_verify.sh
```

---

## Monitoring and Alerts

### Simple Email Alert on Failure

```bash
#!/bin/bash
# Wrapper script with alerting

/var/www/nexus/scripts/backup_rotate.sh

if [ $? -ne 0 ]; then
    echo "NEXUS database backup failed at $(date)" | \
        mail -s "ALERT: NEXUS Backup Failed" admin@council.gov.uk
fi
```

### Check Backup Age

```bash
#!/bin/bash
# /var/www/nexus/scripts/check_backup_age.sh
# Alert if no backup in last 25 hours

BACKUP_DIR="/var/backups/nexus"
MAX_AGE=90000  # 25 hours in seconds

LATEST=$(ls -t "$BACKUP_DIR"/nexus_backup_*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST" ]; then
    echo "No backup found"
    exit 1
fi

AGE=$(( $(date +%s) - $(stat -c%Y "$LATEST" 2>/dev/null || stat -f%m "$LATEST" 2>/dev/null) ))

if [ "$AGE" -gt "$MAX_AGE" ]; then
    echo "Backup is $((AGE / 3600)) hours old - too old!"
    exit 1
else
    echo "Backup is $((AGE / 3600)) hours old - OK"
    exit 0
fi
```

---

## Complete Cron Configuration Example

```cron
# NEXUS Database Backup Schedule
# ================================

# Daily backup at 02:00
0 2 * * * /var/www/nexus/scripts/backup_tiered.sh >> /var/log/nexus/backup.log 2>&1

# Verify backup at 03:00
0 3 * * * /var/www/nexus/scripts/backup_verify.sh >> /var/log/nexus/backup.log 2>&1

# Sync to offsite at 04:00
0 4 * * * /var/www/nexus/scripts/backup_offsite.sh >> /var/log/nexus/backup.log 2>&1

# Check backup age at 08:00 (catch failures before business hours)
0 8 * * * /var/www/nexus/scripts/check_backup_age.sh || mail -s "NEXUS Backup Alert" admin@council.gov.uk
```

---

## Restore Procedure

See `restore_database.php` for full restoration. Quick reference:

```bash
# Decompress if needed
gunzip nexus_backup_20260201.sql.gz

# Restore (CAUTION: destructive operation)
php /var/www/nexus/scripts/restore_database.php /path/to/nexus_backup_20260201.sql
```

**Always test restoration on a non-production environment first.**

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | February 2026 | Initial release |
