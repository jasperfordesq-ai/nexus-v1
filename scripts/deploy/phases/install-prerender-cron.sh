#!/bin/bash
# =============================================================================
# Project NEXUS — Install Prerender Job Processor Cron (idempotent)
# =============================================================================
# Writes /etc/cron.d/nexus-prerender-processor so the host runs the
# prerender-job-processor.sh every minute. Without this, prerender_jobs rows
# sit in `queued` forever (observers and the in-container scheduler can both
# enqueue work but only the host can `docker exec` the worker).
#
# Idempotent: re-writes the cron file each deploy so any drift is corrected.
# Safe to run as a non-root user only when the cron file already matches —
# otherwise sudo is needed.
# =============================================================================

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/nexus-php}"
CRON_FILE="${PRERENDER_CRON_FILE:-/etc/cron.d/nexus-prerender-processor}"
LOG_DIR="${PRERENDER_LOG_DIR:-$DEPLOY_DIR/logs}"
PROCESSOR_SCRIPT="$DEPLOY_DIR/scripts/prerender-job-processor.sh"
REAPER_SCRIPT="$DEPLOY_DIR/scripts/prerender-reap-stale.sh"
RESOLVER_SCRIPT="$DEPLOY_DIR/scripts/resolve-active-container.sh"
STATE_FILE="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
REAPER_INTERVAL_MINUTES="${PRERENDER_REAPER_INTERVAL_MINUTES:-5}"

log() { echo "[$(date -Is)] install-prerender-cron: $*"; }

if ! [[ "$REAPER_INTERVAL_MINUTES" =~ ^[1-9]$|^[1-5][0-9]$ ]]; then
    log "ERROR: PRERENDER_REAPER_INTERVAL_MINUTES must be an integer from 1 to 59 (received: $REAPER_INTERVAL_MINUTES)"
    exit 64
fi

if [ ! -f "$PROCESSOR_SCRIPT" ]; then
    log "WARN: $PROCESSOR_SCRIPT not present — skipping cron install"
    exit 0
fi

if [ ! -f "$REAPER_SCRIPT" ] || [ ! -f "$RESOLVER_SCRIPT" ]; then
    log "WARN: prerender reaper/resolver scripts are incomplete - skipping cron install"
    exit 0
fi

# Cron files in /etc/cron.d need a trailing newline and 0644 perms.
# We also install a quick reaper that runs every $REAPER_INTERVAL_MINUTES
# minutes and unsticks jobs whose renewable lease expired.
read -r -d '' DESIRED <<EOF || true
# Project NEXUS — prerender job processor (auto-installed by deploy)
# Edits to this file are overwritten on every deploy. Source of truth:
#   scripts/deploy/phases/install-prerender-cron.sh
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
DEPLOY_DIR=$DEPLOY_DIR
NEXUS_BLUEGREEN_STATE_FILE=$STATE_FILE

# Claim & process the next queued prerender job every minute (host-side).
# The processor acquires this lock internally. Wrapping it in a second flock
# on the same file makes the child contend with its parent's lock and causes
# every cron tick to exit without claiming a job.
* * * * * root /bin/bash $PROCESSOR_SCRIPT >> $LOG_DIR/prerender-job-processor.log 2>&1

# Reap stale claimed/running rows whose worker died.
*/$REAPER_INTERVAL_MINUTES * * * * root /bin/bash $REAPER_SCRIPT >> $LOG_DIR/prerender-reap-stale.log 2>&1
EOF

# Idempotency: only rewrite if content differs.
if [ -f "$CRON_FILE" ] && [ "$(cat "$CRON_FILE")" = "$DESIRED" ]; then
    log "Cron file already up-to-date: $CRON_FILE"
    exit 0
fi

mkdir -p "$LOG_DIR"
TMP=$(mktemp)
printf '%s\n' "$DESIRED" > "$TMP"

# /etc/cron.d entries must be owned by root:root and 0644.
if [ "$(id -u)" != "0" ]; then
    sudo install -o root -g root -m 0644 "$TMP" "$CRON_FILE"
else
    install -o root -g root -m 0644 "$TMP" "$CRON_FILE"
fi
rm -f "$TMP"

# Some cron implementations need to be poked. Most modern distros pick up
# /etc/cron.d/* automatically — touching the dir is a harmless nudge.
touch /etc/cron.d 2>/dev/null || true

log "Installed cron at $CRON_FILE"
