#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

cd "$DEPLOY_DIR"

# Update last successful deployment
NEW_COMMIT=$(git rev-parse HEAD)
echo "$NEW_COMMIT" > "$LAST_DEPLOY_FILE"

DEPLOY_TS=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
COMMIT_MSG=$(git log -1 --format='%s')
DEPLOY_MODE="${MODE:-${NEXUS_DEPLOY_MODE:-unknown}}"

VERSION_JSON=$(cat <<VEOF
{
    "service": "nexus-php-api",
    "commit": "$NEW_COMMIT",
    "commit_short": "${NEW_COMMIT:0:8}",
    "commit_message": "$COMMIT_MSG",
    "deployed_at": "$DEPLOY_TS",
    "deploy_mode": "$DEPLOY_MODE"
}
VEOF
)

# In blue-green mode httpdocs is baked into the image (not bind-mounted), so
# writing to the host filesystem is not visible to the container.  We must
# docker cp the file in directly.  In legacy mode httpdocs IS bind-mounted, so
# writing to the host path is enough.
if [ "$DEPLOY_MODE" = "bluegreen" ]; then
    # Allow the orchestrator to target a specific color (used to bake the build
    # version into the candidate BEFORE cutover, so post-cutover smoke tests
    # can assert the public endpoint serves the new commit).
    if [ -n "${NEXUS_BUILD_VERSION_COLOR:-}" ]; then
        _BG_COLOR="$NEXUS_BUILD_VERSION_COLOR"
    else
        _BG_STATE="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
        if [ -f "$_BG_STATE" ]; then
            _BG_COLOR="$(tr -d '[:space:]' < "$_BG_STATE" 2>/dev/null || echo blue)"
        else
            _BG_COLOR="blue"
        fi
    fi
    _APP_CONTAINER="nexus-$_BG_COLOR-php-app"
    _TMP_FILE="$(mktemp)"
    printf '%s\n' "$VERSION_JSON" > "$_TMP_FILE"
    chmod 644 "$_TMP_FILE"
    if docker cp "$_TMP_FILE" "$_APP_CONTAINER:/var/www/html/httpdocs/.build-version" 2>/dev/null; then
        # docker cp preserves the source perms but drops to root-owned inside the
        # container. Apache runs as www-data and needs world-readable to read it,
        # otherwise version.php silently falls back to 'unknown' and the drift
        # watchdog spams Telegram with "VERSION.PHP BROKEN".
        docker exec "$_APP_CONTAINER" chmod 644 /var/www/html/httpdocs/.build-version 2>/dev/null || true
        log_ok "Build version written into container $_APP_CONTAINER"
    else
        log_warn "docker cp failed for $_APP_CONTAINER — version.php will show 'unknown'"
    fi
    rm -f "$_TMP_FILE"
    # Also write to host for record-keeping
    printf '%s\n' "$VERSION_JSON" > "$DEPLOY_DIR/httpdocs/.build-version"
else
    # Legacy bind-mount path: host file is served directly by the container
    printf '%s\n' "$VERSION_JSON" > "$DEPLOY_DIR/httpdocs/.build-version"
    log_ok "Build version file written (httpdocs/.build-version)"
fi
