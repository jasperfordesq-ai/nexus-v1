#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Nightly Production Database Backup
# Runs ON the production server (not from dev machine) — install via cron:
#
#   sudo crontab -e
#   0 2 * * * bash /opt/nexus-php/scripts/server-nightly-backup.sh >> /opt/nexus-php/backups/backup.log 2>&1
#
# Keeps the last 7 daily backups. Backup files are named:
#   nexus_prod_backup_YYYY-MM-DD.sql.gz

set -euo pipefail

BACKUP_DIR="/opt/nexus-php/backups"
ENV_FILE="/opt/nexus-php/.env"
DB_CONTAINER="nexus-php-db"
KEEP_DAYS=7
DATE=$(date +%Y-%m-%d)
BACKUP_FILE="${BACKUP_DIR}/nexus_prod_backup_${DATE}.sql.gz"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }

log "=== Nightly backup starting ==="

# Read credentials from .env
DB_NAME=$(grep '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2 | tr -d '"' || \
          grep '^DB_NAME=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_USER=$(grep '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2 | tr -d '"' || \
          grep '^DB_USER=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_PASS=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2 | tr -d '"' || \
          grep '^DB_PASS=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')

if [[ -z "${DB_NAME:-}" || -z "${DB_USER:-}" || -z "${DB_PASS:-}" ]]; then
    log "ERROR: Could not read DB credentials from $ENV_FILE"
    exit 1
fi

mkdir -p "$BACKUP_DIR"

log "Dumping database: $DB_NAME → $BACKUP_FILE"
docker exec "$DB_CONTAINER" \
    mariadb-dump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    | gzip > "$BACKUP_FILE"

# Verify
if [[ ! -s "$BACKUP_FILE" ]]; then
    log "ERROR: Backup file is empty or missing"
    exit 1
fi

SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
log "Backup complete — $SIZE"

# Rotate: remove backups older than KEEP_DAYS
log "Rotating old backups (keeping last $KEEP_DAYS days)..."
find "$BACKUP_DIR" -name "nexus_prod_backup_*.sql.gz" -mtime +"$KEEP_DAYS" -delete
REMAINING=$(find "$BACKUP_DIR" -name "nexus_prod_backup_*.sql.gz" | wc -l)
log "Rotation done — $REMAINING backup(s) retained"

log "=== Nightly backup finished ==="
