#!/bin/bash
# =============================================================================
# Project NEXUS - Docker Database Restore Script (Linux/Mac)
# =============================================================================
# Restores a MySQL dump from backups/db/
# Usage: ./scripts/docker-restore.sh [backup-file.sql]
# =============================================================================

set -e

# Configuration
CONTAINER="nexus-php-db"
DB_NAME="nexus"
DB_USER="root"
DB_PASS="nexus_root_secret"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/../backups/db"

# Check for backup file argument
if [ -z "$1" ]; then
    echo ""
    echo "Usage: ./scripts/docker-restore.sh [backup-file.sql]"
    echo ""
    echo "Available backups:"
    echo ""
    ls -1 "$BACKUP_DIR"/*.sql 2>/dev/null || echo "  No backups found in $BACKUP_DIR"
    echo ""
    exit 1
fi

# Determine backup file path
BACKUP_FILE="$1"
if [ ! -f "$BACKUP_FILE" ]; then
    BACKUP_FILE="$BACKUP_DIR/$1"
fi
if [ ! -f "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file not found: $1"
    exit 1
fi

echo ""
echo "============================================================================="
echo "  NEXUS Database Restore"
echo "============================================================================="
echo ""
echo "Container:   $CONTAINER"
echo "Database:    $DB_NAME"
echo "Backup:      $BACKUP_FILE"
echo ""
echo "WARNING: This will OVERWRITE all data in the $DB_NAME database!"
echo ""

# Confirmation
read -p "Type YES to confirm restore: " CONFIRM
if [ "$CONFIRM" != "YES" ]; then
    echo ""
    echo "Restore cancelled."
    exit 0
fi

# Check if container is running
if ! docker ps --filter "name=$CONTAINER" --filter "status=running" | grep -q "$CONTAINER"; then
    echo ""
    echo "ERROR: Container $CONTAINER is not running."
    echo "Run 'docker compose up -d' first."
    exit 1
fi

echo ""
echo "Restoring database..."

# Drop and recreate database, then restore
docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore from backup
cat "$BACKUP_FILE" | docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"

echo ""
echo "============================================================================="
echo "  Restore Complete!"
echo "============================================================================="
echo "Database $DB_NAME has been restored from $BACKUP_FILE"
echo ""
