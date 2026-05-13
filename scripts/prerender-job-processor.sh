#!/bin/bash
# =============================================================================
# Project NEXUS — Prerender Job Processor (host-side)
# =============================================================================
# Two-step orchestration to keep all DB + log parsing in PHP (testable) while
# the actual Playwright invocation stays on the host (needs docker).
#
#   1. `php artisan prerender:process-queue --claim-next --shell-export`
#       runs inside the PHP container. Atomically claims the oldest queued
#       row and transitions it to claimed→running, emitting KEY=VALUE lines
#       we `eval` here.
#
#   2. We invoke `scripts/prerender-tenants.sh` on the host with the captured
#       args, tee the output to a temp log, then call
#       `php artisan prerender:process-queue --finalise-id=...` which parses
#       counters from the log and writes them back to the row + broadcasts.
#
# Cron entry (every minute):
#   * * * * * root /opt/nexus-php/scripts/prerender-job-processor.sh \
#               >> /opt/nexus-php/logs/prerender-job-processor.log 2>&1
# =============================================================================

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/nexus-php}"
PROCESSOR_LOCK="${PROCESSOR_LOCK:-$DEPLOY_DIR/.prerender-job-processor.lock}"
SCRIPT_DIR="${SCRIPT_DIR:-$DEPLOY_DIR/scripts}"

log() { echo "[$(date -Is)] $*"; }

# Pick the active php-app container (blue/green). Override with APP_CONTAINER.
APP_CONTAINER="${APP_CONTAINER:-}"
if [ -z "$APP_CONTAINER" ]; then
    APP_CONTAINER=$(docker ps --format '{{.Names}}' 2>/dev/null \
        | grep -E '^nexus-(blue|green)-php-app$' \
        | head -1 || true)
fi
APP_CONTAINER="${APP_CONTAINER:-nexus-php-app}"

# Acquire flock — exit silently if another tick is mid-flight.
exec 9>"$PROCESSOR_LOCK"
if ! flock -n 9; then
    exit 0
fi

if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
    log "WARN: app container $APP_CONTAINER not running"
    exit 0
fi

# Step 1: claim. Empty output = empty queue (silent exit).
CLAIM_OUT=$(docker exec "$APP_CONTAINER" php artisan prerender:process-queue --claim-next --shell-export 2>/dev/null || true)
if [ -z "$CLAIM_OUT" ]; then
    exit 0
fi

# Validate output before eval — every line must match KEY=VALUE with safe chars.
if ! echo "$CLAIM_OUT" | grep -Eq "^JOB_ID=[0-9]+$"; then
    log "FATAL: unexpected claim output: $CLAIM_OUT"
    exit 1
fi
# shellcheck disable=SC1090
eval "$CLAIM_OUT"

log "Claimed job #$JOB_ID (tenant='$JOB_TENANT_SLUG' routes='$JOB_ROUTES' force=$JOB_FORCE dry_run=$JOB_DRY_RUN)"

# Step 2: build argv for prerender-tenants.sh.
ARGS=()
[ -n "$JOB_TENANT_SLUG" ] && ARGS+=(--tenant "$JOB_TENANT_SLUG")
[ -n "$JOB_ROUTES" ]      && ARGS+=(--routes "$JOB_ROUTES")
[ "$JOB_FORCE"   = "1" ]  && ARGS+=(--force)
[ "$JOB_DRY_RUN" = "1" ]  && ARGS+=(--dry-run)

TMP_LOG="$(mktemp /tmp/prerender-job-${JOB_ID}-XXXX.log)"
START_TS=$(date +%s)

set +e
bash "$SCRIPT_DIR/prerender-tenants.sh" "${ARGS[@]}" >"$TMP_LOG" 2>&1
EXIT_CODE=$?
set -e

DURATION=$(( $(date +%s) - START_TS ))
case "$EXIT_CODE" in
    0) STATUS="succeeded" ;;
    2) STATUS="partial"   ;;
    *) STATUS="failed"    ;;
esac

# Step 3: ship the log file to the container so artisan can ingest its tail
# (and auto-parse counters). The PHP container has nexus-php-logs mounted at
# /var/log — write there directly.
CONTAINER_LOG="/var/log/prerender-job-${JOB_ID}.log"
docker cp "$TMP_LOG" "${APP_CONTAINER}:${CONTAINER_LOG}" >/dev/null

docker exec "$APP_CONTAINER" php artisan prerender:process-queue \
    --finalise-id="$JOB_ID" \
    --status="$STATUS" \
    --exit-code="$EXIT_CODE" \
    --duration="$DURATION" \
    --log-file="$CONTAINER_LOG" || log "WARN: finalise failed for job #$JOB_ID"

docker exec "$APP_CONTAINER" rm -f "$CONTAINER_LOG" >/dev/null 2>&1 || true
rm -f "$TMP_LOG"

log "Finished job #$JOB_ID status=$STATUS duration=${DURATION}s exit=$EXIT_CODE"
