#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/git-compose-guard.sh"
. "$SCRIPT_DIR/../lib/ssh-keepalive.sh"
. "$SCRIPT_DIR/../lib/maintenance-helpers.sh"
. "$SCRIPT_DIR/validate-env.sh"
. "$SCRIPT_DIR/migrate-legacy-sql.sh"
. "$SCRIPT_DIR/laravel-cache.sh"

save_current_commit() {
    CURRENT_COMMIT=$(git rev-parse HEAD)
    echo "$CURRENT_COMMIT" > "$LAST_DEPLOY_FILE"
    log_info "Saved current commit: ${CURRENT_COMMIT:0:8}"
}

deploy_full() {
    log_step "=== Full Deployment (Git Pull + Rebuild) ==="

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

    # Run pending database migrations (before container rebuild)
    if ! run_pending_migrations; then
        log_err "Migration failure — aborting deploy"
        exit 1
    fi

    # Rebuild containers
    log_info "Building containers with --no-cache..."
    build_with_keepalive docker compose build --no-cache

    log_info "Starting containers (--force-recreate)..."
    docker compose up -d --force-recreate

    log_ok "Full rebuild complete"

    # Re-enable maintenance mode (container recreate wipes the file)
    re_enable_maintenance_after_rebuild

    # Laravel cache optimization
    run_laravel_cache
}

cd "$DEPLOY_DIR"
deploy_full
