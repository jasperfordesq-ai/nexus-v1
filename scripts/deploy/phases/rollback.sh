#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/git-compose-guard.sh"
. "$SCRIPT_DIR/../lib/ssh-keepalive.sh"
. "$SCRIPT_DIR/validate-env.sh"

get_last_successful_commit() {
    if [ -f "$LAST_DEPLOY_FILE" ]; then
        cat "$LAST_DEPLOY_FILE"
    else
        echo ""
    fi
}

rollback_deployment() {
    log_step "=== Rollback to Last Successful Deploy ==="

    LAST_COMMIT=$(get_last_successful_commit)

    if [ -z "$LAST_COMMIT" ]; then
        log_err "No previous successful deployment found"
        exit 1
    fi

    CURRENT_COMMIT=$(git rev-parse HEAD)

    if [ "$CURRENT_COMMIT" = "$LAST_COMMIT" ]; then
        log_warn "Already at last successful commit: ${LAST_COMMIT:0:8}"
        exit 0
    fi

    log_info "Current commit: ${CURRENT_COMMIT:0:8}"
    log_info "Rolling back to: ${LAST_COMMIT:0:8}"

    # Fix #4: Ensure compose.yml is protected before checkout
    protect_compose_yml

    # Checkout last successful commit
    git checkout "$LAST_COMMIT"

    # Always restore production compose.yml (belt-and-suspenders after git checkout)
    cp compose.prod.yml compose.yml

    # Fix #4 (Prevention): Validate Dockerfiles before building
    validate_dockerfiles

    # Export commit hash for build arg
    export BUILD_COMMIT=$(git rev-parse --short HEAD)
    log_info "Build commit: $BUILD_COMMIT"

    # Full rebuild all containers (rollback must guarantee correct images)
    log_info "Rebuilding ALL containers with --no-cache..."
    build_with_keepalive docker compose build --no-cache

    log_info "Starting containers (--force-recreate)..."
    docker compose up -d --force-recreate

    log_ok "Rollback complete (full rebuild)"
    log_info "Now at: $(git log -1 --format='%h - %s')"
}

cd "$DEPLOY_DIR"
rollback_deployment
