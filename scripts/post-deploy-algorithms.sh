#!/bin/bash
# =============================================================================
# Project NEXUS — Algorithm Upgrade Post-Deploy Script
# Run once on the server after deploying commit 03fb5a8d
# Usage: sudo bash scripts/post-deploy-algorithms.sh
# =============================================================================
set -e

NEXUS_DIR="/opt/nexus-php"
PHP="php"
LOG_PREFIX="[nexus-algorithms]"

cd "$NEXUS_DIR"

echo "$LOG_PREFIX Starting algorithm upgrade post-deploy..."
echo ""

# ─── 1. Pull latest code ─────────────────────────────────────────────────────
echo "$LOG_PREFIX [1/8] Pulling latest code..."
git pull origin main

# ─── 2. Install new composer packages ────────────────────────────────────────
echo "$LOG_PREFIX [2/8] Installing packages (meilisearch-php + rubix-ml)..."
composer install --no-dev --optimize-autoloader

# ─── 3. Add MEILISEARCH_KEY to .env if not already set ──────────────────────
echo "$LOG_PREFIX [3/8] Checking MEILISEARCH_KEY..."
if grep -q "^MEILISEARCH_KEY=" .env 2>/dev/null; then
    echo "  MEILISEARCH_KEY already set — skipping"
else
    KEY=$(openssl rand -hex 32)
    echo "MEILISEARCH_KEY=$KEY" >> .env
    echo "  Generated and saved MEILISEARCH_KEY to .env"
fi

# ─── 4. Full Docker rebuild (picks up new Meilisearch container) ─────────────
echo "$LOG_PREFIX [4/8] Rebuilding Docker containers..."
sudo bash scripts/safe-deploy.sh full

# ─── 5. Wait for Meilisearch to be healthy ───────────────────────────────────
echo "$LOG_PREFIX [5/8] Waiting for Meilisearch to start..."
MAX_WAIT=60
ELAPSED=0
until docker exec nexus-meilisearch wget --no-verbose --tries=1 --spider http://localhost:7700/health 2>/dev/null; do
    if [ $ELAPSED -ge $MAX_WAIT ]; then
        echo "  WARNING: Meilisearch did not start within ${MAX_WAIT}s — search index sync will be skipped"
        MEILI_OK=0
        break
    fi
    echo "  Waiting... (${ELAPSED}s)"
    sleep 5
    ELAPSED=$((ELAPSED + 5))
done
MEILI_OK=${MEILI_OK:-1}

# ─── 6. Run database migrations ──────────────────────────────────────────────
echo "$LOG_PREFIX [6/8] Running migrations..."
$PHP scripts/safe_migrate.php

# ─── 7. Backfill search index + embeddings ───────────────────────────────────
if [ $MEILI_OK -eq 1 ]; then
    echo "$LOG_PREFIX [7/8] Backfilling Meilisearch index..."
    $PHP scripts/sync_search_index.php --all-tenants
else
    echo "$LOG_PREFIX [7/8] Skipping Meilisearch sync (container not ready)"
    echo "  Re-run manually: php scripts/sync_search_index.php --all-tenants"
fi

echo "$LOG_PREFIX        Backfilling OpenAI embeddings (may take a while)..."
$PHP scripts/backfill_embeddings.php --all-tenants || true   # non-fatal if no API key

echo "$LOG_PREFIX        Running first KNN training pass..."
$PHP scripts/train_recommendations.php --all-tenants || true  # non-fatal if no data yet

# ─── 8. Add nightly cron (idempotent) ────────────────────────────────────────
echo "$LOG_PREFIX [8/8] Setting up nightly KNN training cron..."
CRON_CMD="0 3 * * * cd $NEXUS_DIR && $PHP scripts/train_recommendations.php --all-tenants >> /var/log/nexus-recs.log 2>&1"
( crontab -l 2>/dev/null | grep -v "train_recommendations" ; echo "$CRON_CMD" ) | crontab -
echo "  Cron set: $CRON_CMD"

# ─── Done ─────────────────────────────────────────────────────────────────────
echo ""
echo "$LOG_PREFIX ✓ All done."
echo ""
echo "Verify health at: https://api.project-nexus.ie/api/v2/admin/config/algorithm-health"
echo "Or in admin UI:   https://app.project-nexus.ie/admin/algorithm-settings"
