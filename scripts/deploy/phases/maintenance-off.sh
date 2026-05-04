#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/state.sh"
. "$SCRIPT_DIR/../lib/maintenance-helpers.sh"

disable_maintenance_mode() {
    log_step "=== Disabling Maintenance Mode (both layers) ==="

    if docker ps --format "{{.Names}}" | grep -qx "$PHP_CONTAINER"; then
        # Layer 1: Remove file
        docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"

        if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
            log_err ".maintenance file still exists — trying again"
            docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"
        fi

        log_ok "Layer 1: .maintenance file removed"

        # Layer 2: Database — clear maintenance mode for all tenants
        _deploy_db_maintenance_set "false"

        # Layer 3: Flush Redis bootstrap cache so frontend sees DB change immediately
        _deploy_flush_bootstrap_cache

        # Verify HTTP 200
        sleep 1
        local HTTP_CODE
        HTTP_CODE="skipped"
        if [ -n "${MAINTENANCE_VERIFY_URL:-}" ]; then
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
        fi
        if [ "$HTTP_CODE" = "200" ]; then
            log_ok "Verified: API returning HTTP 200"
        else
            log_warn "API returned HTTP $HTTP_CODE — may need OPCache clear"
        fi
    else
        log_warn "$PHP_CONTAINER not running — cannot disable maintenance mode"
    fi
}

# Original order: DEPLOY_SUCCESS=1 is set BEFORE disable_maintenance_mode, so
# that if something fails inside disable, the EXIT trap won't auto-disable again.
state_set DEPLOY_SUCCESS 1
disable_maintenance_mode
