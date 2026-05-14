#!/bin/bash
# =============================================================================
# Project NEXUS — Install logrotate config for the bot-only access log
# =============================================================================
# nginx writes JSONL crawler hits to the shared prerender volume. Without a
# rotation policy this grows unbounded — measurable in MB/day on a small
# tenant set, GB/month on a busy one. Rotate daily, keep 14 days, compress.
#
# The log lives inside a docker named volume mounted at
# /var/lib/docker/volumes/nexus-php-prerendered/_data/.bot-access.jsonl.
# We resolve the volume mountpoint at install time and stamp it into the
# logrotate file.
# =============================================================================

set -euo pipefail

LOGROTATE_FILE="${PRERENDER_LOGROTATE_FILE:-/etc/logrotate.d/nexus-prerender-bot-access}"
VOLUME_NAME="${PRERENDER_VOLUME_NAME:-nexus-php-prerendered}"

log() { echo "[$(date -Is)] install-prerender-logrotate: $*"; }

MOUNT=$(docker volume inspect -f '{{.Mountpoint}}' "$VOLUME_NAME" 2>/dev/null || true)
if [ -z "$MOUNT" ]; then
    log "WARN: docker volume $VOLUME_NAME not found — skipping logrotate install"
    exit 0
fi

LOGFILE="$MOUNT/.bot-access.jsonl"

read -r -d '' DESIRED <<EOF || true
# Project NEXUS — bot-only crawler access log (auto-installed by deploy)
# Source of truth: scripts/deploy/phases/install-prerender-logrotate.sh
$LOGFILE {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
    su root root
    create 0644 root root
}
EOF

if [ -f "$LOGROTATE_FILE" ] && [ "$(cat "$LOGROTATE_FILE")" = "$DESIRED" ]; then
    log "Logrotate config already up-to-date: $LOGROTATE_FILE"
    exit 0
fi

TMP=$(mktemp)
printf '%s\n' "$DESIRED" > "$TMP"

if [ "$(id -u)" != "0" ]; then
    sudo install -o root -g root -m 0644 "$TMP" "$LOGROTATE_FILE"
else
    install -o root -g root -m 0644 "$TMP" "$LOGROTATE_FILE"
fi
rm -f "$TMP"

log "Installed logrotate config at $LOGROTATE_FILE for $LOGFILE"
