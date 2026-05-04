#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/state.sh"
. "$SCRIPT_DIR/../lib/maintenance-helpers.sh"

enable_maintenance_mode() {
    log_step "=== Enabling Maintenance Mode (both layers) ==="

    if docker ps --format "{{.Names}}" | grep -qx "$PHP_CONTAINER"; then
        # Layer 1: File-based gate
        docker exec "$PHP_CONTAINER" touch "$MAINTENANCE_FILE"

        if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
            log_ok "Layer 1: .maintenance file created"
        else
            log_err "Layer 1: Failed to create .maintenance file"
            exit 1
        fi

        # Layer 2: Database — set all tenants to maintenance mode
        _deploy_db_maintenance_set "true"

        # Layer 3: Flush Redis bootstrap cache so frontend sees DB change immediately
        _deploy_flush_bootstrap_cache

        # Verify HTTP 503
        sleep 1
        local HTTP_CODE
        HTTP_CODE="skipped"
        if [ -n "${MAINTENANCE_VERIFY_URL:-}" ]; then
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
        fi
        if [ "$HTTP_CODE" = "503" ]; then
            log_ok "Verified: API returning HTTP 503"
        else
            log_warn "API returned HTTP $HTTP_CODE (may not have fully activated yet)"
        fi
    else
        log_warn "$PHP_CONTAINER not running — skipping maintenance mode (will be set after rebuild)"
        state_set MAINTENANCE_DEFERRED 1
    fi
}

# Orchestrator flow: set flags BEFORE calling enable (matches original main())
state_set MAINTENANCE_DEFERRED 0
state_set MAINTENANCE_ENABLED_BY_US 1
enable_maintenance_mode
