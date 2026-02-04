#!/bin/bash
# =============================================================================
# Project NEXUS - Docker Database Backup Script (Linux/Mac)
# =============================================================================
# Creates a timestamped MySQL dump in backups/db/
# Usage: ./scripts/docker-backup.sh
# =============================================================================

set -e

# Configuration
CONTAINER="nexus-mysql-db"
DB_NAME="nexus"
DB_USER="root"
DB_PASS="nexus_root_secret"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/../backups/db"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +"%Y-%m-%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/nexus_${TIMESTAMP}.sql"

echo ""
echo "============================================================================="
echo "  NEXUS Database Backup"
echo "============================================================================="
echo ""
echo "Container:   $CONTAINER"
echo "Database:    $DB_NAME"
echo "Output:      $BACKUP_FILE"
echo ""

# Check if container is running
if ! docker ps --filter "name=$CONTAINER" --filter "status=running" | grep -q "$CONTAINER"; then
    echo "ERROR: Container $CONTAINER is not running."
    echo "Run 'docker compose up -d' first."
    exit 1
fi

# Create backup
echo "Creating backup..."
docker compose exec -T db mysqldump -u"$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" > "$BACKUP_FILE"

# Get file size
SIZE=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE" 2>/dev/null || echo "unknown")

echo ""
echo "============================================================================="
echo "  Backup Complete!"
echo "============================================================================="
echo "File: $BACKUP_FILE"
echo "Size: $SIZE bytes"
echo ""
