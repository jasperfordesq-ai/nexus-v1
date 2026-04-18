#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

verify_production_images() {
    log_step "=== Production Image Verification ==="

    local VERIFY_FAILED=0

    # Verify React frontend is running nginx (production), not node (dev)
    if docker exec nexus-react-prod which nginx > /dev/null 2>&1; then
        log_ok "Frontend: nginx detected (production image)"
    elif docker exec nexus-react-prod which node > /dev/null 2>&1; then
        log_err "Frontend: node detected — THIS IS A DEV IMAGE ON PRODUCTION!"
        log_err "Run 'sudo bash scripts/safe-deploy.sh full' to fix"
        VERIFY_FAILED=1
    else
        log_warn "Frontend: could not verify image type (container may not be running)"
    fi

    # Verify React container image name
    local REACT_IMAGE
    REACT_IMAGE=$(docker inspect nexus-react-prod --format '{{.Config.Image}}' 2>/dev/null || echo "unknown")
    if [[ "$REACT_IMAGE" == "nexus-react-prod:latest" ]]; then
        log_ok "Frontend image: $REACT_IMAGE (correct)"
    elif [[ "$REACT_IMAGE" == *"dev"* ]] || [[ "$REACT_IMAGE" == "staging_frontend"* ]]; then
        log_err "Frontend image: $REACT_IMAGE — WRONG IMAGE (dev/legacy name)"
        VERIFY_FAILED=1
    else
        log_warn "Frontend image: $REACT_IMAGE (unexpected name)"
    fi

    # Verify PHP container uses production Dockerfile (OPCache validate_timestamps=0)
    local OPCACHE_VALIDATE
    OPCACHE_VALIDATE=$(docker exec nexus-php-app php -r 'echo ini_get("opcache.validate_timestamps");' 2>/dev/null || echo "unknown")
    if [[ "$OPCACHE_VALIDATE" == "0" ]] || [[ "$OPCACHE_VALIDATE" == "" ]]; then
        log_ok "PHP OPCache: validate_timestamps=0 (production)"
    elif [[ "$OPCACHE_VALIDATE" == "1" ]]; then
        log_err "PHP OPCache: validate_timestamps=1 — THIS IS A DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP OPCache: validate_timestamps=$OPCACHE_VALIDATE (unexpected)"
    fi

    # Verify PHP display_errors is off (production)
    local DISPLAY_ERRORS
    DISPLAY_ERRORS=$(docker exec nexus-php-app php -r 'echo ini_get("display_errors");' 2>/dev/null || echo "unknown")
    if [[ "$DISPLAY_ERRORS" == "" ]] || [[ "$DISPLAY_ERRORS" == "0" ]] || [[ "$DISPLAY_ERRORS" == "Off" ]]; then
        log_ok "PHP display_errors: Off (production)"
    elif [[ "$DISPLAY_ERRORS" == "1" ]] || [[ "$DISPLAY_ERRORS" == "On" ]]; then
        log_err "PHP display_errors: On — THIS IS A DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP display_errors: $DISPLAY_ERRORS (unexpected)"
    fi

    # Verify build commit is baked into the React image (if BUILD_COMMIT was set)
    if [ -n "${BUILD_COMMIT:-}" ]; then
        local REACT_COMMIT
        REACT_COMMIT=$(docker exec nexus-react-prod sh -c 'cat /app/dist/.build-commit 2>/dev/null || echo "none"')
        if [[ "$REACT_COMMIT" == "$BUILD_COMMIT" ]]; then
            log_ok "React build commit: $REACT_COMMIT (matches)"
        else
            log_warn "React build commit: '$REACT_COMMIT' (expected: '$BUILD_COMMIT')"
        fi
    fi

    if [ $VERIFY_FAILED -eq 1 ]; then
        log_err "Image verification FAILED — dev images detected on production!"
        log_err "Run 'sudo bash scripts/safe-deploy.sh full' to rebuild with production images"
        return 1
    fi

    log_ok "All production images verified"
    return 0
}

if ! verify_production_images; then
    log_err "ABORTING: Dev images detected on production"
    log_err "Fix: sudo bash scripts/safe-deploy.sh full"
    exit 1
fi
