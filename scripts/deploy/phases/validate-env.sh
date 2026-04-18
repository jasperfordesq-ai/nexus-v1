#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
. "$SCRIPT_DIR/../lib/common.sh"

validate_environment() {
    log_step "=== Pre-Deploy Validation ==="

    local VALIDATION_FAILED=0

    # Check disk space
    AVAILABLE_MB=$(df -m "$DEPLOY_DIR" | tail -1 | awk '{print $4}')
    if [ "$AVAILABLE_MB" -lt "$MIN_DISK_SPACE_MB" ]; then
        log_err "Insufficient disk space: ${AVAILABLE_MB}MB (minimum: ${MIN_DISK_SPACE_MB}MB)"
        VALIDATION_FAILED=1
    else
        log_ok "Disk space: ${AVAILABLE_MB}MB available"
    fi

    # Check critical files
    if [ ! -f "$DEPLOY_DIR/.env" ]; then
        log_err ".env file missing"
        VALIDATION_FAILED=1
    else
        log_ok ".env exists"
    fi

    if [ ! -f "$DEPLOY_DIR/compose.prod.yml" ]; then
        log_err "compose.prod.yml missing"
        VALIDATION_FAILED=1
    else
        log_ok "compose.prod.yml exists"
    fi

    # Check if containers are running
    if ! docker ps --filter "name=nexus-php-app" --format "{{.Names}}" | grep -q "nexus-php-app"; then
        log_warn "nexus-php-app container is not running"
    else
        log_ok "nexus-php-app container running"
    fi

    if ! docker ps --filter "name=nexus-php-db" --format "{{.Names}}" | grep -q "nexus-php-db"; then
        log_err "nexus-php-db container is not running"
        VALIDATION_FAILED=1
    else
        log_ok "nexus-php-db container running"
    fi

    # Check database connectivity (read password from .env)
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    if docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysqladmin ping -h localhost -unexus > /dev/null 2>&1; then
        log_ok "Database connection OK"
    else
        log_err "Database connection failed"
        VALIDATION_FAILED=1
    fi

    # Check Redis connectivity
    if docker exec nexus-php-redis redis-cli ping > /dev/null 2>&1; then
        log_ok "Redis connection OK"
    else
        log_warn "Redis connection failed (non-critical)"
    fi

    if [ $VALIDATION_FAILED -eq 1 ]; then
        log_err "Pre-deploy validation failed"
        exit 1
    fi

    log_ok "All pre-deploy checks passed"
}

validate_dockerfiles() {
    log_step "=== Dockerfile Sanity Check ==="

    local FAILED=0

    if [ -f "react-frontend/Dockerfile.prod" ]; then
        if grep -q "FROM nginx:alpine" react-frontend/Dockerfile.prod; then
            log_ok "Dockerfile.prod: nginx base image confirmed (production)"
        else
            log_err "Dockerfile.prod: nginx base image NOT found — wrong Dockerfile?"
            FAILED=1
        fi
    else
        log_warn "react-frontend/Dockerfile.prod not found — skipping check"
    fi

    if [ -f "react-frontend/Dockerfile" ]; then
        if grep -q "FROM node:" react-frontend/Dockerfile; then
            log_ok "Dockerfile (dev): node base image confirmed"
        else
            log_warn "react-frontend/Dockerfile: node base image not found (unexpected)"
        fi
    fi

    if [ $FAILED -eq 1 ]; then
        log_err "Dockerfile sanity check failed — aborting deploy"
        exit 1
    fi
}

# When executed directly (not sourced), run validate_environment.
# Build phases may `source` this file to get access to validate_dockerfiles.
if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    validate_environment
fi
