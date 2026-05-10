#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Pre-cutover prerender snapshot warmup.
#
# Each color's frontend container is rebuilt fresh on every deploy, which
# means the new color starts with an empty /usr/share/nginx/html/prerendered/.
# Without this warmup, the moment we cut traffic to the new color, every bot
# request misses the snapshot cache and falls back to the SPA — until the
# detached post-cutover prerender finishes (~20-40 minutes for full re-render).
#
# This phase copies the active color's existing snapshots into the target
# color before traffic switch. Since snapshots are now bot-only and asset-hash
# independent (see nginx.bluegreen.conf bot detection), copying old snapshots
# into the new color is safe: bots get continuity. The detached post-cutover
# prerender then refreshes them on its normal schedule.
#
# No-ops when:
#   - $active is unknown (first deploy ever)
#   - the active container is not running
#   - the active container has no snapshots
#
# Inputs (env):
#   ACTIVE_COLOR  - source color to copy from (e.g. "blue")
#   TARGET_COLOR  - destination color to copy to (e.g. "green")
#
# Exit code is always 0 — warmup failure must never block a deploy.

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

ACTIVE_COLOR="${ACTIVE_COLOR:-${1:-}}"
TARGET_COLOR="${TARGET_COLOR:-${2:-}}"
PRERENDER_DIR_INSIDE_CONTAINER="/usr/share/nginx/html/prerendered"
PRERENDER_EVENT_LOG="${PRERENDER_EVENT_LOG:-$DEPLOY_DIR/logs/prerender-events.jsonl}"

emit_warmup_event() {
    local EVENT="$1"; shift
    local EXTRA="${1:-}"
    local DIR
    DIR="$(dirname "$PRERENDER_EVENT_LOG")"
    [ -d "$DIR" ] || mkdir -p "$DIR" 2>/dev/null || return 0
    printf '{"ts":"%s","event":"%s","source":"warmup","active":"%s","target":"%s"%s}\n' \
        "$(date -Is)" "$EVENT" "$ACTIVE_COLOR" "$TARGET_COLOR" "${EXTRA:+,$EXTRA}" \
        >> "$PRERENDER_EVENT_LOG" 2>/dev/null || true
}

if [ -z "$ACTIVE_COLOR" ] || [ "$ACTIVE_COLOR" = "unknown" ] || [ -z "$TARGET_COLOR" ]; then
    log_info "Warmup: no prior active color — skipping snapshot copy (first deploy)"
    emit_warmup_event "skip" "\"reason\":\"no_active_color\""
    exit 0
fi

if [ "$ACTIVE_COLOR" = "$TARGET_COLOR" ]; then
    log_warn "Warmup: active and target are the same ($ACTIVE_COLOR) — skipping"
    emit_warmup_event "skip" "\"reason\":\"same_color\""
    exit 0
fi

ACTIVE_CONTAINER="nexus-${ACTIVE_COLOR}-react"
TARGET_CONTAINER="nexus-${TARGET_COLOR}-react"

if ! docker ps --format '{{.Names}}' | grep -qx "$ACTIVE_CONTAINER"; then
    log_info "Warmup: active container $ACTIVE_CONTAINER not running — skipping"
    emit_warmup_event "skip" "\"reason\":\"active_container_missing\""
    exit 0
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$TARGET_CONTAINER"; then
    log_warn "Warmup: target container $TARGET_CONTAINER not running — skipping"
    emit_warmup_event "skip" "\"reason\":\"target_container_missing\""
    exit 0
fi

# Detect whether both colors mount the same volume at $PRERENDER_DIR_INSIDE_CONTAINER.
# In compose.bluegreen.yml the named volume `nexus-php-prerendered` is mounted
# into both colors, so writes are already visible to both sides — a copy here
# is wasted work. We compare each container's mount source for the prerender
# path; if they match, skip.
mount_source_for() {
    local CONTAINER="$1"
    docker inspect "$CONTAINER" \
        --format '{{ range .Mounts }}{{ if eq .Destination "'"$PRERENDER_DIR_INSIDE_CONTAINER"'" }}{{ .Source }}{{ "\n" }}{{ end }}{{ end }}' \
        2>/dev/null | head -n1
}

ACTIVE_MOUNT="$(mount_source_for "$ACTIVE_CONTAINER")"
TARGET_MOUNT="$(mount_source_for "$TARGET_CONTAINER")"

if [ -n "$ACTIVE_MOUNT" ] && [ -n "$TARGET_MOUNT" ] && [ "$ACTIVE_MOUNT" = "$TARGET_MOUNT" ]; then
    log_info "Warmup: both colors share prerender volume ($ACTIVE_MOUNT) — copy not needed"
    emit_warmup_event "skip" "\"reason\":\"shared_volume\",\"mount\":\"$ACTIVE_MOUNT\""
    exit 0
fi

# Quick check: does the active container even have any snapshots?
SNAPSHOT_COUNT="$(docker exec "$ACTIVE_CONTAINER" sh -c \
    "find $PRERENDER_DIR_INSIDE_CONTAINER -name index.html -type f 2>/dev/null | wc -l | tr -d ' '" \
    2>/dev/null || echo 0)"

if [ -z "$SNAPSHOT_COUNT" ] || [ "$SNAPSHOT_COUNT" = "0" ]; then
    log_info "Warmup: active container has no snapshots — skipping"
    emit_warmup_event "skip" "\"reason\":\"no_snapshots\""
    exit 0
fi

log_info "Warmup: copying $SNAPSHOT_COUNT snapshot(s) from $ACTIVE_CONTAINER to $TARGET_CONTAINER"

START_TS="$(date +%s)"
TMP_DIR="$(mktemp -d -t nexus-warmup-XXXXXX)"
trap 'rm -rf "$TMP_DIR" 2>/dev/null || true' EXIT

# Stage 1: ensure source dir exists and copy out to host. docker cp will
# create the snapshot subtree under $TMP_DIR/prerendered.
if ! docker cp "${ACTIVE_CONTAINER}:${PRERENDER_DIR_INSIDE_CONTAINER}/." "$TMP_DIR/" 2>/dev/null; then
    log_warn "Warmup: docker cp from active container failed — skipping"
    emit_warmup_event "fail" "\"stage\":\"cp_from_active\""
    exit 0
fi

# Stage 2: ensure destination dir exists in target, then copy in.
docker exec "$TARGET_CONTAINER" mkdir -p "$PRERENDER_DIR_INSIDE_CONTAINER" 2>/dev/null || true

if ! docker cp "$TMP_DIR/." "${TARGET_CONTAINER}:${PRERENDER_DIR_INSIDE_CONTAINER}/" 2>/dev/null; then
    log_warn "Warmup: docker cp to target container failed — skipping"
    emit_warmup_event "fail" "\"stage\":\"cp_to_target\""
    exit 0
fi

# Stage 3: nginx must reload to pick up new files for try_files. Reload is
# zero-downtime; if it fails, files are still on disk for the next reload.
docker exec "$TARGET_CONTAINER" nginx -s reload 2>/dev/null || \
    log_warn "Warmup: nginx reload in target failed — files copied but may not serve until next reload"

DURATION=$(($(date +%s) - START_TS))
log_ok "Warmup: copied snapshots into $TARGET_CONTAINER in ${DURATION}s"
emit_warmup_event "success" "\"snapshots\":$SNAPSHOT_COUNT,\"duration_s\":$DURATION"
exit 0
