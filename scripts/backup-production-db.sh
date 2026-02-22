#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Production Database Backup
# Usage: bash scripts/backup-production-db.sh
#
# Required env vars (or defaults from production .env):
#   PROD_SSH_KEY   - Path to SSH private key
#   PROD_SSH_HOST  - SSH user@host

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

success() { echo -e "${GREEN}✓ $1${NC}"; }
error()   { echo -e "${RED}✗ $1${NC}"; }
info()    { echo -e "${CYAN}→ $1${NC}"; }

SSH_KEY="${PROD_SSH_KEY:-C:\\ssh-keys\\project-nexus.pem}"
SSH_HOST="${PROD_SSH_HOST:-azureuser@20.224.171.253}"
SSH_OPTS="-i ${SSH_KEY} -o ConnectTimeout=10 -o StrictHostKeyChecking=no"
DB_NAME="${PROD_DB_NAME:-nexus}"
DB_CONTAINER="nexus-php-db"

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║         PRODUCTION DATABASE BACKUP                        ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Read credentials from server
info "Reading production credentials..."
if [[ -n "${PROD_DB_PASS:-}" && -n "${PROD_DB_USER:-}" ]]; then
    DB_USER="$PROD_DB_USER"
    DB_PASS="$PROD_DB_PASS"
else
    DB_USER=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_USER=' /opt/nexus-php/.env | cut -d= -f2")
    DB_PASS=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_PASS=' /opt/nexus-php/.env | cut -d= -f2")
fi

if [[ -z "$DB_PASS" ]]; then
    error "Could not read DB credentials"
    exit 1
fi
success "Credentials obtained"

# Create backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="manual_backup_${TIMESTAMP}.sql"
BACKUP_PATH="/opt/nexus-php/backups/${BACKUP_NAME}"

info "Creating backup..."
ssh $SSH_OPTS "$SSH_HOST" \
    "sudo mkdir -p /opt/nexus-php/backups && \
     sudo docker exec ${DB_CONTAINER} mariadb-dump -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     | sudo tee ${BACKUP_PATH} > /dev/null" || {
    error "Backup failed!"
    exit 1
}

# Verify
BACKUP_SIZE=$(ssh $SSH_OPTS "$SSH_HOST" "sudo ls -lh ${BACKUP_PATH} | awk '{print \$5}'")
TAIL_CHECK=$(ssh $SSH_OPTS "$SSH_HOST" "sudo tail -1 ${BACKUP_PATH}")

if echo "$TAIL_CHECK" | grep -q "Dump completed"; then
    success "Backup verified (dump completed marker found)"
else
    error "Backup may be incomplete — missing 'Dump completed' marker"
    exit 1
fi

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║  BACKUP COMPLETE                                          ║"
echo "╠═══════════════════════════════════════════════════════════╣"
echo "║  File: ${BACKUP_PATH}"
echo "║  Size: ${BACKUP_SIZE}"
echo "║                                                           ║"
echo "║  Restore command:                                         ║"
echo "║  sudo cat ${BACKUP_PATH} \\"
echo "║    | sudo docker exec -i ${DB_CONTAINER} mariadb \\"
echo "║      -u '${DB_USER}' -p'<PASS>' ${DB_NAME}"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# List recent backups
info "Recent backups on server:"
ssh $SSH_OPTS "$SSH_HOST" "sudo ls -lht /opt/nexus-php/backups/ | head -10"
echo ""
