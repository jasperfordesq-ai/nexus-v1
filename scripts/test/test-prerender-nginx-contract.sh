#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CONF="$ROOT_DIR/react-frontend/nginx.bluegreen.conf"
DOCKERFILE="$ROOT_DIR/react-frontend/Dockerfile.bluegreen"

grep -Fq 'include /usr/share/nginx/html/prerendered/.status-overrides.list;' "$CONF"
grep -Fq '/usr/share/nginx/html/prerendered/.global-maintenance' "$CONF"
grep -Fq 'error_page 418 = @global_maintenance;' "$CONF"
grep -Fq 'location @global_maintenance' "$CONF"
grep -Fq 'include /usr/share/nginx/html/prerendered/.maintenance-render-auth.list;' "$CONF"
grep -Fq 'if ($nexus_maintenance_render_authenticated = "0") { return 503; }' "$CONF"
if grep -Fq 'if ($arg_nexus_prerender_bypass = "1") { set $nexus_global_maintenance 0; }' "$CONF"; then
    echo 'public query-string bypass must not disable global maintenance' >&2
    exit 1
fi
grep -Fq 'location @prerender_page_snapshot' "$CONF"
grep -Fq 'root /usr/share/nginx/html/prerendered;' "$CONF"
grep -Fq 'location ^~ /prerendered/' "$CONF"
grep -Fq 'if (!-f /usr/share/nginx/html/prerendered/.tenant-identity-v1)' "$CONF"
grep -Fq 'error_page 503 =503 @prerender_send_body;' "$CONF"
if grep -Fq '$request_uri/index.html' "$CONF"; then
    echo 'status body path must not include query arguments' >&2
    exit 1
fi

# Both exact-root and general HTML locations must apply maintenance status.
status_checks="$(grep -Fc 'if ($nexus_safe_prerender_status = "503") { return 503; }' "$CONF")"
[ "$status_checks" -ge 2 ] || {
    echo 'root and non-root locations must both propagate HTTP 503' >&2
    exit 1
}

grep -Fq '20-ensure-prerender-state.sh' "$DOCKERFILE"
grep -Fq 'index.html /usr/share/nginx/html/_spa.html' "$DOCKERFILE"

echo 'prerender nginx status contract passed'
