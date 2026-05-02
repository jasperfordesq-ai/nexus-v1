#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/git-compose-guard.sh"
. "$SCRIPT_DIR/../lib/ssh-keepalive.sh"
. "$SCRIPT_DIR/../lib/maintenance-helpers.sh"
. "$SCRIPT_DIR/validate-env.sh"           # provides validate_dockerfiles
. "$SCRIPT_DIR/migrate-legacy-sql.sh"     # provides run_pending_migrations
. "$SCRIPT_DIR/laravel-cache.sh"          # provides run_laravel_cache

# ── Blue-green guard ────────────────────────────────────────────────────────
# This script is the legacy single-container deploy path.  It recreates live
# containers in-place and MUST NEVER run on a server configured for blue-green.
# safe-deploy.sh should have caught this already, but we enforce it here too.
_BG_ROUTES_CHECK="${NEXUS_APACHE_ROUTES_FILE:-/etc/apache2/conf-enabled/nexus-active-upstreams.conf}"
_BG_STATE_CHECK="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
if [ -f "$_BG_ROUTES_CHECK" ] || [ -f "$_BG_STATE_CHECK" ]; then
    log_err "FATAL: build-quick (maintenance-mode path) invoked on a blue-green server."
    log_err "This would recreate live containers during a deploy and cause an outage."
    log_err "Use: sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach"
    exit 1
fi
unset _BG_ROUTES_CHECK _BG_STATE_CHECK
# ────────────────────────────────────────────────────────────────────────────

save_current_commit() {
    CURRENT_COMMIT=$(git rev-parse HEAD)
    echo "$CURRENT_COMMIT" > "$LAST_DEPLOY_FILE"
    log_info "Saved current commit: ${CURRENT_COMMIT:0:8}"
}

deploy_quick() {
    log_step "=== Quick Deployment (Git Pull + Rebuild Frontend + Restart) ==="

    # Fix #4: Ensure compose.yml is permanently protected from git overwrites
    protect_compose_yml

    # Save current state
    save_current_commit

    # Git pull — must clear skip-worktree before reset or it fails
    log_info "Fetching latest from GitHub..."
    git fetch origin main
    pre_reset_compose_yml
    log_info "Resetting to origin/main..."
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)
    log_info "Now at: ${NEW_COMMIT:0:8} - $(git log -1 --format='%s')"

    # Always restore production compose.yml (belt-and-suspenders after git reset)
    log_info "Restoring compose.yml from compose.prod.yml..."
    cp compose.prod.yml compose.yml
    log_ok "compose.yml restored (production version)"

    # Fix #4 (Prevention): Validate Dockerfiles before building
    validate_dockerfiles

    # Export commit hash so compose.prod.yml can pass it as a build arg
    export BUILD_COMMIT=$(git rev-parse --short HEAD)
    log_info "Build commit: $BUILD_COMMIT"

    # Build flags: quick mode uses layer caching by default (faster).
    # Use --no-cache flag to force a clean rebuild: safe-deploy.sh quick --no-cache
    local BUILD_FLAGS=""
    [ "${FORCE_NO_CACHE:-0}" = "1" ] && BUILD_FLAGS="--no-cache"

    # Rebuild React frontend (layer caching skips npm install if deps unchanged)
    log_info "Rebuilding React frontend${BUILD_FLAGS:+ ($BUILD_FLAGS)}..."
    build_with_keepalive docker compose build $BUILD_FLAGS frontend
    log_ok "React frontend rebuilt"

    # Run pending database migrations (before container rebuild)
    if ! run_pending_migrations; then
        log_err "Migration failure — aborting deploy"
        exit 1
    fi

    # Rebuild sales site
    log_info "Rebuilding sales site${BUILD_FLAGS:+ ($BUILD_FLAGS)}..."
    build_with_keepalive docker compose build $BUILD_FLAGS sales
    log_ok "Sales site rebuilt"

    # Recreate frontend + sales containers with new images, restart PHP for OPCache
    log_info "Starting updated containers..."
    docker compose up -d --force-recreate frontend sales
    log_info "Restarting PHP container (OPCache clear)..."
    docker restart nexus-php-app
    log_ok "All containers updated"

    # Re-enable maintenance mode (PHP container restart wipes the file)
    re_enable_maintenance_after_rebuild

    # Laravel cache optimization
    run_laravel_cache
}

cd "$DEPLOY_DIR"
deploy_quick
