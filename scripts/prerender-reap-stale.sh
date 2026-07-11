#!/bin/bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

# Host-side wrapper used by cron. It resolves the active PHP color from the
# blue/green state file and fails closed if docker state is ambiguous.

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/nexus-php}"
RESOLVER_SCRIPT="${NEXUS_CONTAINER_RESOLVER:-$DEPLOY_DIR/scripts/resolve-active-container.sh}"
REAPER_LOCK="${PRERENDER_REAPER_LOCK:-$DEPLOY_DIR/.prerender-reaper.lock}"
REAPER_TIMEOUT_SECONDS="${PRERENDER_REAPER_TIMEOUT_SECONDS:-120}"

[[ "$REAPER_TIMEOUT_SECONDS" =~ ^[0-9]+$ ]] && [ "$REAPER_TIMEOUT_SECONDS" -ge 30 ] \
    || { echo "prerender-reap-stale: invalid timeout" >&2; exit 64; }
command -v timeout >/dev/null 2>&1 \
    || { echo "prerender-reap-stale: GNU timeout is required" >&2; exit 69; }

exec 9>"$REAPER_LOCK"
flock -n 9 || exit 0

APP_CONTAINER="${APP_CONTAINER:-}"
if [ -z "$APP_CONTAINER" ]; then
    if [ ! -r "$RESOLVER_SCRIPT" ]; then
        echo "prerender-reap-stale: resolver unavailable at $RESOLVER_SCRIPT" >&2
        exit 69
    fi
    # shellcheck source=resolve-active-container.sh
    source "$RESOLVER_SCRIPT"
    APP_CONTAINER="$(resolve_active_nexus_container php-app)"
fi

INNER_TIMEOUT=$((REAPER_TIMEOUT_SECONDS - 5))
exec timeout --foreground --signal=TERM --kill-after=5s "${REAPER_TIMEOUT_SECONDS}s" \
    docker exec "$APP_CONTAINER" timeout --foreground --signal=TERM --kill-after=5s \
        "${INNER_TIMEOUT}s" php artisan prerender:reap-stale --requeue "$@"
