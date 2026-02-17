#!/bin/bash
# =============================================================================
# Project NEXUS - Safe Production Deploy Script
# =============================================================================
# Usage: sudo bash scripts/safe-deploy.sh [quick|full]
#
# SAFETY: This script protects production-only files from git overwrite.
# The repo's compose.yml is the LOCAL DEV version. Production needs
# compose.prod.yml as the active compose.yml. This script always restores it.
# =============================================================================

set -e
cd /opt/nexus-php

echo '============================================'
echo '  Project NEXUS - Safe Production Deploy'
echo '============================================'
echo

# --- Safety checks ---
echo '[CHECK] Verifying critical production files...'

if [ ! -f .env ]; then
    echo '[FATAL] .env is missing! Aborting.'
    exit 1
fi

if [ ! -f compose.prod.yml ]; then
    echo '[FATAL] compose.prod.yml is missing! Aborting.'
    exit 1
fi

echo '[OK] .env exists'
echo '[OK] compose.prod.yml exists'
echo

# --- Backup ---
echo '[BACKUP] Saving production compose.yml...'
cp compose.yml compose.yml.pre-deploy-backup 2>/dev/null || true
echo '[OK] Backup saved'
echo

# --- Git pull ---
echo '[GIT] Fetching latest from GitHub...'
git fetch origin main
echo '[GIT] Resetting to origin/main...'
git reset --hard origin/main
echo '[GIT] Now at:'
git log --oneline -1
echo

# --- CRITICAL: Restore production compose.yml ---
echo '[SAFETY] Restoring compose.yml from compose.prod.yml...'
cp compose.prod.yml compose.yml
echo '[OK] compose.yml restored (production version)'
echo

# --- Deploy based on mode ---
MODE="${1:-quick}"

if [ "$MODE" = "full" ]; then
    echo '[BUILD] Building containers with --no-cache...'
    docker compose build --no-cache
    echo '[BUILD] Starting containers...'
    docker compose up -d
    echo '[OK] Full rebuild complete'
else
    echo '[RESTART] Restarting PHP (OPCache clear)...'
    docker restart nexus-php-app
    echo '[OK] PHP restarted'
fi

echo

# --- Health check ---
echo '[HEALTH] Waiting 5 seconds...'
sleep 5

API_OK=$(curl -sf http://127.0.0.1:8090/health.php > /dev/null 2>&1 && echo "OK" || echo "FAILED")
FRONTEND_OK=$(curl -sf http://127.0.0.1:3000/ > /dev/null 2>&1 && echo "OK" || echo "FAILED")

echo "[HEALTH] API: ${API_OK}"
echo "[HEALTH] Frontend: ${FRONTEND_OK}"
echo

# --- Verify compose.yml is production version ---
COMPOSE_CHECK=$(head -3 compose.yml | grep -c "Production" || true)
if [ "$COMPOSE_CHECK" -eq 0 ]; then
    echo '[WARNING] compose.yml does NOT appear to be the production version!'
    echo '[WARNING] Check that compose.prod.yml was copied correctly.'
else
    echo '[VERIFY] compose.yml is the production version'
fi

echo
echo '============================================'
echo '  Deploy complete!'
echo '============================================'
git log --oneline -1
