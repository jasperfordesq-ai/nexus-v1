#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Production Migration Runner
# Usage: bash scripts/migrate-production.sh <migration-file.sql> [--dry-run]
#
# Required env vars (set in .env or export before running):
#   PROD_SSH_KEY   - Path to SSH private key (default: C:\ssh-keys\project-nexus.pem)
#   PROD_SSH_HOST  - SSH user@host (default: azureuser@20.224.171.253)
#   PROD_DB_USER   - Production DB username (default: read from server .env)
#   PROD_DB_PASS   - Production DB password (default: read from server .env)
#   PROD_DB_NAME   - Production DB name (default: nexus)

set -euo pipefail

# ─── Colors ───────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

success() { echo -e "${GREEN}✓ $1${NC}"; }
error()   { echo -e "${RED}✗ $1${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $1${NC}"; }
info()    { echo -e "${CYAN}→ $1${NC}"; }

# ─── Args ─────────────────────────────────────────────────────
MIGRATION_FILE="${1:-}"
DRY_RUN=false
if [[ "${2:-}" == "--dry-run" ]]; then
    DRY_RUN=true
fi

if [[ -z "$MIGRATION_FILE" ]]; then
    error "Usage: bash scripts/migrate-production.sh <migration-file.sql> [--dry-run]"
    exit 1
fi

MIGRATION_PATH="migrations/${MIGRATION_FILE}"
if [[ ! -f "$MIGRATION_PATH" ]]; then
    error "Migration file not found: $MIGRATION_PATH"
    exit 1
fi

# ─── SSH Config ───────────────────────────────────────────────
SSH_KEY="${PROD_SSH_KEY:-C:\\ssh-keys\\project-nexus.pem}"
SSH_HOST="${PROD_SSH_HOST:-azureuser@20.224.171.253}"
SSH_OPTS="-i ${SSH_KEY} -o ConnectTimeout=10 -o StrictHostKeyChecking=no"
DB_NAME="${PROD_DB_NAME:-nexus}"
DB_CONTAINER="nexus-php-db"

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║         PRODUCTION MIGRATION RUNNER                       ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
info "Migration: ${MIGRATION_FILE}"
info "Target: ${SSH_HOST} → ${DB_CONTAINER}/${DB_NAME}"

if $DRY_RUN; then
    warn "DRY RUN MODE — no changes will be made"
fi
echo ""

# ─── Step 1: Read production DB credentials from server ───────
info "Step 1: Reading production credentials..."

if [[ -n "${PROD_DB_PASS:-}" && -n "${PROD_DB_USER:-}" ]]; then
    DB_USER="$PROD_DB_USER"
    DB_PASS="$PROD_DB_PASS"
    success "Using credentials from environment variables"
else
    DB_USER=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_USER=' /opt/nexus-php/.env | cut -d= -f2")
    DB_PASS=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_PASS=' /opt/nexus-php/.env | cut -d= -f2")
    if [[ -z "$DB_PASS" ]]; then
        error "Could not read DB credentials from production server"
        exit 1
    fi
    success "Credentials read from production .env"
fi

# ─── Step 2: Test connectivity ────────────────────────────────
info "Step 2: Testing database connectivity..."

CONN_TEST=$(ssh $SSH_OPTS "$SSH_HOST" \
    "sudo docker exec ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     -e \"SELECT @@hostname AS host, DATABASE() AS db, NOW() AS time;\"" 2>&1) || {
    error "Cannot connect to production database"
    echo "$CONN_TEST"
    exit 1
}
success "Connected to production database"
echo "$CONN_TEST" | head -3

# ─── Step 3: Check if already applied ─────────────────────────
info "Step 3: Checking if migration already applied..."

ALREADY=$(ssh $SSH_OPTS "$SSH_HOST" \
    "sudo docker exec ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     -N -e \"SELECT COUNT(*) FROM migrations WHERE migration_name = '${MIGRATION_FILE}';\"" 2>/dev/null || echo "0")
ALREADY=$(echo "$ALREADY" | tr -d '[:space:]')

if [[ "$ALREADY" -gt 0 ]]; then
    warn "Migration '${MIGRATION_FILE}' is already recorded in production. Skipping."
    exit 0
fi

success "Migration not yet applied"

if $DRY_RUN; then
    echo ""
    info "DRY RUN — Would execute:"
    echo "════════════════════════════════════════════"
    cat "$MIGRATION_PATH"
    echo ""
    echo "════════════════════════════════════════════"
    success "Dry run complete — no changes made"
    exit 0
fi

# ─── Step 4: Backup ───────────────────────────────────────────
info "Step 4: Creating pre-migration backup..."

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="pre_migration_${MIGRATION_FILE%.sql}_${TIMESTAMP}.sql"
BACKUP_PATH="/opt/nexus-php/backups/${BACKUP_NAME}"

ssh $SSH_OPTS "$SSH_HOST" \
    "sudo mkdir -p /opt/nexus-php/backups && \
     sudo docker exec ${DB_CONTAINER} mariadb-dump -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     | sudo tee ${BACKUP_PATH} > /dev/null" || {
    error "Backup failed!"
    exit 1
}

BACKUP_SIZE=$(ssh $SSH_OPTS "$SSH_HOST" "sudo ls -lh ${BACKUP_PATH} | awk '{print \$5}'")
success "Backup created: ${BACKUP_PATH} (${BACKUP_SIZE})"
echo ""
warn "To restore: sudo cat ${BACKUP_PATH} | sudo docker exec -i ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'<PASS>' ${DB_NAME}"
echo ""

# ─── Step 5: Copy + apply migration ──────────────────────────
info "Step 5: Copying migration to server..."
scp $SSH_OPTS "$MIGRATION_PATH" "${SSH_HOST}:/tmp/${MIGRATION_FILE}" || {
    error "Failed to copy migration file"
    exit 1
}
success "File copied to /tmp/${MIGRATION_FILE}"

info "Step 6: Applying migration..."
ssh $SSH_OPTS "$SSH_HOST" \
    "cat /tmp/${MIGRATION_FILE} | sudo docker exec -i ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME}" || {
    error "Migration FAILED!"
    error "Restore from backup: sudo cat ${BACKUP_PATH} | sudo docker exec -i ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'<PASS>' ${DB_NAME}"
    exit 1
}
success "Migration applied successfully"

# ─── Step 6: Record in tracking table ─────────────────────────
info "Step 7: Recording migration..."
ssh $SSH_OPTS "$SSH_HOST" \
    "sudo docker exec ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     -e \"INSERT INTO migrations (migration_name, backups, executed_at) VALUES ('${MIGRATION_FILE}', '${MIGRATION_FILE}', NOW());\"" || {
    warn "Could not record migration in tracking table (non-fatal)"
}
success "Migration recorded"

# ─── Step 7: Verify ──────────────────────────────────────────
info "Step 8: Verifying..."
echo ""
ssh $SSH_OPTS "$SSH_HOST" \
    "sudo docker exec ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     -e \"SELECT * FROM migrations ORDER BY id DESC LIMIT 5;\""

# Clean up temp file
ssh $SSH_OPTS "$SSH_HOST" "rm -f /tmp/${MIGRATION_FILE}" 2>/dev/null || true

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║  PRODUCTION MIGRATION COMPLETE                            ║"
echo "╠═══════════════════════════════════════════════════════════╣"
echo "║  Backup: ${BACKUP_PATH}"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
