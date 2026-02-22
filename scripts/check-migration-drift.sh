#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Migration Drift Checker
# Compares migration history between local and production databases.
# Lists pending migrations that need to be applied to production.
#
# Usage: bash scripts/check-migration-drift.sh
#        bash scripts/check-migration-drift.sh --ci   (exit 1 if drift found)
#
# Required env vars (or defaults):
#   PROD_SSH_KEY   - Path to SSH private key
#   PROD_SSH_HOST  - SSH user@host

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

success() { echo -e "${GREEN}✓ $1${NC}"; }
error()   { echo -e "${RED}✗ $1${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $1${NC}"; }
info()    { echo -e "${CYAN}→ $1${NC}"; }

CI_MODE=false
if [[ "${1:-}" == "--ci" ]]; then
    CI_MODE=true
fi

SSH_KEY="${PROD_SSH_KEY:-C:\\ssh-keys\\project-nexus.pem}"
SSH_HOST="${PROD_SSH_HOST:-azureuser@20.224.171.253}"
SSH_OPTS="-i ${SSH_KEY} -o ConnectTimeout=10 -o StrictHostKeyChecking=no"
DB_NAME="${PROD_DB_NAME:-nexus}"
DB_CONTAINER="nexus-php-db"

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║         MIGRATION DRIFT CHECK                             ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# ─── Step 1: Get local migration history ──────────────────────
info "Reading local migration history..."

LOCAL_MIGRATIONS=$(docker exec nexus-php-db mariadb -u nexus -pnexus_secret nexus \
    -N -e "SELECT migration_name FROM migrations ORDER BY id;" 2>/dev/null || echo "")

if [[ -z "$LOCAL_MIGRATIONS" ]]; then
    warn "No migrations recorded locally"
    LOCAL_COUNT=0
else
    LOCAL_COUNT=$(echo "$LOCAL_MIGRATIONS" | wc -l | tr -d ' ')
    success "Local: ${LOCAL_COUNT} migration(s) recorded"
fi

# ─── Step 2: Get production migration history ─────────────────
info "Reading production migration history..."

# Read credentials from server
if [[ -n "${PROD_DB_PASS:-}" && -n "${PROD_DB_USER:-}" ]]; then
    DB_USER="$PROD_DB_USER"
    DB_PASS="$PROD_DB_PASS"
else
    DB_USER=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_USER=' /opt/nexus-php/.env | cut -d= -f2" 2>/dev/null)
    DB_PASS=$(ssh $SSH_OPTS "$SSH_HOST" "sudo grep '^DB_PASS=' /opt/nexus-php/.env | cut -d= -f2" 2>/dev/null)
fi

if [[ -z "$DB_PASS" ]]; then
    error "Could not read production DB credentials"
    if $CI_MODE; then
        echo "::warning::Cannot connect to production — skipping drift check"
        exit 0
    fi
    exit 1
fi

PROD_MIGRATIONS=$(ssh $SSH_OPTS "$SSH_HOST" \
    "sudo docker exec ${DB_CONTAINER} mariadb -u '${DB_USER}' -p'${DB_PASS}' ${DB_NAME} \
     -N -e \"SELECT migration_name FROM migrations ORDER BY id;\"" 2>/dev/null || echo "")

if [[ -z "$PROD_MIGRATIONS" ]]; then
    warn "No migrations recorded on production"
    PROD_COUNT=0
else
    PROD_COUNT=$(echo "$PROD_MIGRATIONS" | wc -l | tr -d ' ')
    success "Production: ${PROD_COUNT} migration(s) recorded"
fi

# ─── Step 3: Get migration files in repo ──────────────────────
info "Scanning migration files in repo..."
REPO_FILES=$(ls -1 migrations/*.sql 2>/dev/null | xargs -I{} basename {} | sort)
REPO_COUNT=$(echo "$REPO_FILES" | wc -l | tr -d ' ')
info "Repository: ${REPO_COUNT} migration file(s) in migrations/"

# ─── Step 4: Find pending migrations ─────────────────────────
echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  COMPARISON"
echo "═══════════════════════════════════════════════════════════"
echo ""

DRIFT_FOUND=0

# Migrations in local but not in production
if [[ -n "$LOCAL_MIGRATIONS" ]]; then
    while IFS= read -r migration; do
        if [[ -n "$migration" ]] && ! echo "$PROD_MIGRATIONS" | grep -qF "$migration"; then
            warn "PENDING on production: ${migration}"
            DRIFT_FOUND=1
        fi
    done <<< "$LOCAL_MIGRATIONS"
fi

# Migrations in production but not in local (shouldn't happen but check)
if [[ -n "$PROD_MIGRATIONS" ]]; then
    while IFS= read -r migration; do
        if [[ -n "$migration" ]] && ! echo "$LOCAL_MIGRATIONS" | grep -qF "$migration"; then
            error "ON PRODUCTION BUT NOT LOCAL: ${migration}"
            DRIFT_FOUND=1
        fi
    done <<< "$PROD_MIGRATIONS"
fi

# Migration files in repo but not recorded anywhere
if [[ -n "$REPO_FILES" ]]; then
    while IFS= read -r file; do
        if [[ -n "$file" ]]; then
            IN_LOCAL=false
            IN_PROD=false
            if echo "$LOCAL_MIGRATIONS" | grep -qF "$file" 2>/dev/null; then IN_LOCAL=true; fi
            if echo "$PROD_MIGRATIONS" | grep -qF "$file" 2>/dev/null; then IN_PROD=true; fi

            if ! $IN_LOCAL && ! $IN_PROD; then
                warn "UNTRACKED: ${file} (in repo but not applied anywhere)"
            elif $IN_LOCAL && ! $IN_PROD; then
                : # Already reported above as PENDING
            fi
        fi
    done <<< "$REPO_FILES"
fi

echo ""
if [[ $DRIFT_FOUND -eq 0 ]]; then
    echo "═══════════════════════════════════════════════════════════"
    success "NO DRIFT — local and production migration histories match"
    echo "═══════════════════════════════════════════════════════════"
else
    echo "═══════════════════════════════════════════════════════════"
    error "DRIFT DETECTED — run 'make migrate-prod FILE=<name>' for each pending migration"
    echo "═══════════════════════════════════════════════════════════"
    if $CI_MODE; then
        exit 1
    fi
fi
echo ""

# ─── Summary table ────────────────────────────────────────────
echo "  Migration Files in Repo: ${REPO_COUNT}"
echo "  Applied Locally:         ${LOCAL_COUNT}"
echo "  Applied on Production:   ${PROD_COUNT}"
echo ""
