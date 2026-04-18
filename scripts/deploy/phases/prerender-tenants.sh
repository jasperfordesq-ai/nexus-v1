#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

prerender_tenants() {
    log_step "=== Per-Tenant Pre-Rendering ==="

    if [ ! -f "$DEPLOY_DIR/scripts/prerender-tenants.sh" ]; then
        log_warn "prerender-tenants.sh not found — pre-rendering skipped"
        log_warn "Run manually: sudo bash scripts/prerender-tenants.sh"
        return 1
    fi

    if bash "$DEPLOY_DIR/scripts/prerender-tenants.sh" 2>&1 | tee -a "$LOG_FILE"; then
        log_ok "Per-tenant pre-rendering complete"
        return 0
    fi

    log_warn "Per-tenant pre-rendering had errors (non-blocking)"
    log_warn "Run manually: sudo bash scripts/prerender-tenants.sh"
    return 1  # Non-blocking
}

prerender_tenants || true
