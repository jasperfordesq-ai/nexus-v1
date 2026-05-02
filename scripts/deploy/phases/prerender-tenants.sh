#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

FRONTEND_CONTAINER="${FRONTEND_CONTAINER:-nexus-react-prod}"
PRERENDER_DEPLOY_DIR="${PRERENDER_DEPLOY_DIR:-$DEPLOY_DIR}"
PRERENDER_DIR="/usr/share/nginx/html/prerendered"

has_existing_prerendered_html() {
    docker exec "$FRONTEND_CONTAINER" sh -c "find '$PRERENDER_DIR' -name index.html -type f -print -quit 2>/dev/null | grep -q ." 2>/dev/null
}

changed_files_since_last_deploy() {
    local BASE_COMMIT
    BASE_COMMIT=""

    if [ -f "$LAST_PRERENDER_FILE" ]; then
        BASE_COMMIT="$(cat "$LAST_PRERENDER_FILE" 2>/dev/null || true)"
    elif [ -n "${PRERENDER_BASE_COMMIT:-}" ]; then
        BASE_COMMIT="$PRERENDER_BASE_COMMIT"
    elif [ -f "$LAST_DEPLOY_FILE" ]; then
        BASE_COMMIT="$(cat "$LAST_DEPLOY_FILE" 2>/dev/null || true)"
    fi

    if [ -n "$BASE_COMMIT" ] && git -C "$PRERENDER_DEPLOY_DIR" cat-file -e "$BASE_COMMIT^{commit}" 2>/dev/null; then
        git -C "$PRERENDER_DEPLOY_DIR" diff --name-only "$BASE_COMMIT"..HEAD
        return 0
    fi

    git -C "$PRERENDER_DEPLOY_DIR" diff --name-only HEAD~1..HEAD 2>/dev/null || git -C "$PRERENDER_DEPLOY_DIR" ls-files
}

needs_prerender_for_changes() {
    local CHANGED_FILES="$1"

    if [ -z "$CHANGED_FILES" ]; then
        return 1
    fi

    echo "$CHANGED_FILES" | grep -Eq '(^|/)(scripts/prerender-tenants\.sh|scripts/prerender-worker\.mjs|scripts/deploy/phases/prerender-tenants\.sh|compose\.prod\.yml|react-frontend/nginx\.conf)$' && return 0
    echo "$CHANGED_FILES" | grep -Eq '^react-frontend/(src|public|index\.html|vite\.config\.ts|package(-lock)?\.json|scripts/prerender\.mjs)' && return 0
    echo "$CHANGED_FILES" | grep -Eq '^lang/en/|^public/locales/|^react-frontend/public/locales/' && return 0
    echo "$CHANGED_FILES" | grep -Eq '^resources/|^routes/|^app/(Http|Models|Services|View)|^src/(Controllers/Api|Models|Services)' && return 0
    echo "$CHANGED_FILES" | grep -Eq '^database/migrations/|^migrations/|^database/schema/' && return 0

    return 1
}

should_run_prerender() {
    if [ "${SKIP_PRERENDER:-0}" = "1" ]; then
        log_info "Skipping per-tenant pre-rendering (--skip-prerender set)"
        return 1
    fi

    if [ "${FORCE_PRERENDER:-0}" = "1" ]; then
        log_info "Forcing per-tenant pre-rendering (--force-prerender set)"
        return 0
    fi

    if [ -n "${PRERENDER_TENANT:-}" ] || [ -n "${PRERENDER_ROUTES:-}" ]; then
        log_info "Scoped pre-render requested; refreshing selected tenant/routes"
        return 0
    fi

    if ! has_existing_prerendered_html; then
        log_info "No existing pre-rendered HTML found; rendering all tenant public pages once"
        return 0
    fi

    local CHANGED_FILES
    CHANGED_FILES="$(changed_files_since_last_deploy)"

    if needs_prerender_for_changes "$CHANGED_FILES"; then
        log_info "Public-facing changes detected; refreshing tenant pre-rendered HTML"
        return 0
    fi

    log_ok "Per-tenant pre-rendering skipped (no public-facing changes detected)"
    return 1
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
    if [ -n "${PRERENDER_TENANT:-}" ]; then
        ARGS+=(--tenant "$PRERENDER_TENANT")
    fi
    if [ -n "${PRERENDER_ROUTES:-}" ]; then
        ARGS+=(--routes "$PRERENDER_ROUTES")
    fi

    if bash "$PRERENDER_DEPLOY_DIR/scripts/prerender-tenants.sh" "${ARGS[@]}" 2>&1 | tee -a "$LOG_FILE"; then
        if [ -z "${PRERENDER_TENANT:-}" ] && [ -z "${PRERENDER_ROUTES:-}" ]; then
            git -C "$PRERENDER_DEPLOY_DIR" rev-parse HEAD > "$LAST_PRERENDER_FILE"
        fi
        log_ok "Per-tenant pre-rendering complete"
        return 0
    fi

    log_warn "Per-tenant pre-rendering had errors (non-blocking)"
    log_warn "Run manually: sudo bash scripts/prerender-tenants.sh"
    return 1
}

prerender_tenants || true
