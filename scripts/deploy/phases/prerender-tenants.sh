#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

FRONTEND_CONTAINER="${FRONTEND_CONTAINER:-nexus-react-prod}"
PRERENDER_DEPLOY_DIR="${PRERENDER_DEPLOY_DIR:-$DEPLOY_DIR}"
LAST_PRERENDER_ATTEMPT_FILE="${LAST_PRERENDER_ATTEMPT_FILE:-$DEPLOY_DIR/.last-prerender-attempt}"
PRERENDER_EVENT_LOG="${PRERENDER_EVENT_LOG:-$DEPLOY_DIR/logs/prerender-events.jsonl}"

emit_phase_event() {
    local EVENT="$1"; shift
    local EXTRA="${1:-}"
    local DIR
    DIR="$(dirname "$PRERENDER_EVENT_LOG")"
    [ -d "$DIR" ] || mkdir -p "$DIR" 2>/dev/null || return 0
    local COMMIT
    COMMIT="$(git -C "$PRERENDER_DEPLOY_DIR" rev-parse --short HEAD 2>/dev/null || echo unknown)"
    printf '{"ts":"%s","event":"%s","source":"phase","commit":"%s"%s}\n' \
        "$(date -Is)" "$EVENT" "$COMMIT" "${EXTRA:+,$EXTRA}" \
        >> "$PRERENDER_EVENT_LOG" 2>/dev/null || true
}

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

    # Skip-on-clean: if this deploy did not change any files that influence
    # public-page rendered output, the previous prerender is still valid and a
    # full re-render is wasted work. Disabled when --force / --tenant / --routes
    # is in play, or when there is no prior successful prerender to compare to.
    if [ "${PRERENDER_SKIP_ON_CLEAN:-1}" = "1" ] \
        && [ "${FORCE_PRERENDER:-0}" != "1" ] \
        && [ -z "${PRERENDER_TENANT:-}" ] \
        && [ -z "${PRERENDER_ROUTES:-}" ]; then
        local base_commit=""
        if [ -f "$LAST_PRERENDER_FILE" ]; then
            base_commit="$(cat "$LAST_PRERENDER_FILE" 2>/dev/null || true)"
        fi
        if [ -n "$base_commit" ] && git -C "$PRERENDER_DEPLOY_DIR" cat-file -e "$base_commit" 2>/dev/null; then
            local head_commit
            head_commit="$(git -C "$PRERENDER_DEPLOY_DIR" rev-parse HEAD 2>/dev/null || true)"
            if [ -n "$head_commit" ] && [ "$head_commit" = "$base_commit" ]; then
                log_info "Skip-on-clean: HEAD matches last successful prerender ($base_commit); nothing to render"
                emit_phase_event "skip_on_clean" "\"reason\":\"head_eq_base\",\"base\":\"$base_commit\""
                record_successful_prerender
                return 1
            fi
            # Paths that affect public-page output. PHP-only changes, doc
            # changes, backend services, queue jobs, etc. cannot change what
            # the prerender worker captures.
            if git -C "$PRERENDER_DEPLOY_DIR" diff --quiet "$base_commit" HEAD -- \
                    react-frontend public 2>/dev/null; then
                log_info "Skip-on-clean: no react-frontend/ or public/ changes since $base_commit; reusing existing prerender cache"
                log_info "Override with: PRERENDER_SKIP_ON_CLEAN=0 or --force-prerender"
                emit_phase_event "skip_on_clean" "\"reason\":\"no_frontend_diff\",\"base\":\"$base_commit\",\"head\":\"$head_commit\""
                record_successful_prerender
                return 1
            fi
        fi
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
