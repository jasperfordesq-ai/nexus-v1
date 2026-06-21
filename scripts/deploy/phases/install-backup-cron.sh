#!/bin/bash
# =============================================================================
# Project NEXUS — Install Nightly DB Backup Cron (idempotent)
# =============================================================================
# Writes /etc/cron.d/nexus-db-backup so the host runs server-nightly-backup.sh
# every night at 02:00. Without an installed cron the nightly DB/uploads/storage
# backups silently never run — and you only find out during a restore, when
# there is nothing to restore.
#
# This makes the backup cron SELF-HEALING: every deploy re-asserts it, so a
# fresh host (or a host where the cron was lost) can't ship without backups.
# It is the install-side complement to the scheduled `backup:verify` alarm
# (bootstrap/app.php) which DETECTS a lapsed backup; this PREVENTS the lapse.
#
# Idempotent: re-writes the cron file each deploy so any drift is corrected.
# flock guards against overlap with a manually-installed crontab entry (the
# script's own header documents a `crontab -e` option) — running twice the same
# night is harmless anyway (the dump filename is date-stamped and overwritten),
# but flock keeps it clean.
#
# Non-fatal: a failure here must never abort a deploy (the deploy script calls
# it with `|| log_warn`). Modelled on install-prerender-cron.sh.
# =============================================================================

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/nexus-php}"
CRON_FILE="${BACKUP_CRON_FILE:-/etc/cron.d/nexus-db-backup}"
LOG_DIR="${BACKUP_LOG_DIR:-$DEPLOY_DIR/logs}"
BACKUP_SCRIPT="$DEPLOY_DIR/scripts/server-nightly-backup.sh"
BACKUP_SCHEDULE="${BACKUP_CRON_SCHEDULE:-0 2 * * *}"
LOCK_FILE="${BACKUP_LOCK_FILE:-$DEPLOY_DIR/.db-backup.lock}"

log() { echo "[$(date -Is)] install-backup-cron: $*"; }

if [ ! -f "$BACKUP_SCRIPT" ]; then
    log "WARN: $BACKUP_SCRIPT not present — skipping backup cron install"
    exit 0
fi

# Seed a backup on a brand-new host: if NO nightly dump exists yet, kick one now
# (in the background, sharing the 02:00 flock) so the host has a backup
# immediately AND the first scheduled backup:verify (09:30) passes instead of
# paging for the ~7h gap until the first 02:00 run. No-op once any nightly dump
# exists, so an ordinary redeploy never triggers it. nohup + & detaches it from
# this phase (same pattern the deploy uses to background prerender), so it never
# blocks the deploy and isn't killed when this phase returns. Runs BEFORE the
# idempotent cron-install early-exit below so it is evaluated on every deploy.
BACKUP_OUT_DIR="${DEPLOY_DIR}/backups"
if ! ls "$BACKUP_OUT_DIR"/nexus_db_*.sql.gz >/dev/null 2>&1; then
    log "No nightly DB backup present yet — seeding one in the background (fresh host)"
    mkdir -p "$LOG_DIR"
    nohup flock -n "$LOCK_FILE" bash "$BACKUP_SCRIPT" >> "$LOG_DIR/db-backup.log" 2>&1 &
fi

# Cron files in /etc/cron.d need a trailing newline and 0644 perms. flock -n
# skips a run if a prior backup is still in flight (or a manual crontab entry
# fired at the same time).
read -r -d '' DESIRED <<EOF || true
# Project NEXUS — nightly database/uploads/storage backup (auto-installed by deploy)
# Edits to this file are overwritten on every deploy. Source of truth:
#   scripts/deploy/phases/install-backup-cron.sh
# This SUPERSEDES any manual 'crontab -e' entry for server-nightly-backup.sh —
# remove the manual one to avoid two cron sources (harmless but confusing).
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

$BACKUP_SCHEDULE root flock -n $LOCK_FILE bash $BACKUP_SCRIPT >> $LOG_DIR/db-backup.log 2>&1
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

# Most modern distros pick up /etc/cron.d/* automatically — touching the dir is
# a harmless nudge for implementations that need it.
touch /etc/cron.d 2>/dev/null || true

log "Installed cron at $CRON_FILE (schedule: $BACKUP_SCHEDULE)"
