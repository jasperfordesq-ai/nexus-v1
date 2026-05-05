#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

FRONTEND_CONTAINER="${FRONTEND_CONTAINER:-nexus-react-prod}"
PRERENDER_DEPLOY_DIR="${PRERENDER_DEPLOY_DIR:-$DEPLOY_DIR}"
LAST_PRERENDER_ATTEMPT_FILE="${LAST_PRERENDER_ATTEMPT_FILE:-$DEPLOY_DIR/.last-prerender-attempt}"

record_prerender_attempt() {
    if [ -z "${PRERENDER_TENANT:-}" ] && [ -z "${PRERENDER_ROUTES:-}" ]; then
        git -C "$PRERENDER_DEPLOY_DIR" rev-parse HEAD > "$LAST_PRERENDER_ATTEMPT_FILE" 2>/dev/null || true
    fi
}

record_successful_prerender() {
    if [ -z "${PRERENDER_TENANT:-}" ] && [ -z "${PRERENDER_ROUTES:-}" ]; then
        git -C "$PRERENDER_DEPLOY_DIR" rev-parse HEAD > "$LAST_PRERENDER_FILE" 2>/dev/null || true
        record_prerender_attempt
    fi
}

run_prerender_script() {
    if [ -n "${__NEXUS_DEPLOY_DETACHED__:-}" ] || [ -n "${__NEXUS_BLUEGREEN_DETACHED__:-}" ]; then
        bash "$PRERENDER_DEPLOY_DIR/scripts/prerender-tenants.sh" "$@"
    else
        bash "$PRERENDER_DEPLOY_DIR/scripts/prerender-tenants.sh" "$@" 2>&1 | tee -a "$LOG_FILE"
    fi
}

should_run_prerender() {
    if [ "${SKIP_PRERENDER:-0}" = "1" ]; then
        log_info "Skipping per-tenant pre-rendering (--skip-prerender set)"
        return 1
    fi

    log_info "Planning per-tenant pre-render cache (only missing, stale, or scoped pages will render)"
    return 0
}

prerender_tenants() {
    log_step "=== Per-Tenant Pre-Rendering ==="

    if ! should_run_prerender; then
        return 0
    fi

    if [ ! -f "$PRERENDER_DEPLOY_DIR/scripts/prerender-tenants.sh" ]; then
        log_warn "prerender-tenants.sh not found; pre-rendering skipped"
        log_warn "Run manually: sudo bash scripts/prerender-tenants.sh"
        return 1
    fi

    local ARGS=()
    if [ "${FORCE_PRERENDER:-0}" = "1" ]; then
        ARGS+=(--force)
    fi
    if [ -n "${PRERENDER_TENANT:-}" ]; then
        ARGS+=(--tenant "$PRERENDER_TENANT")
    fi
    if [ -n "${PRERENDER_ROUTES:-}" ]; then
        ARGS+=(--routes "$PRERENDER_ROUTES")
    fi

    if run_prerender_script "${ARGS[@]}"; then
        record_successful_prerender
        log_ok "Per-tenant pre-rendering complete"
        return 0
    fi

    record_prerender_attempt
    log_warn "Per-tenant pre-rendering had errors (non-blocking)"
    log_warn "Recorded this attempt so one flaky tenant does not force every later deploy to re-render all tenants"
    log_warn "Run manually: sudo bash scripts/prerender-tenants.sh"
    return 1
}

prerender_tenants || true
