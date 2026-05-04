#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Production image verification.
#
# Color-aware: pass NEXUS_VERIFY_COLOR=blue|green to check the candidate
# (blue/green-aware) containers. Falls back to legacy single-color names
# (nexus-react-prod / nexus-php-app) when no color is set.
#
# Catches the "dev image accidentally on production" failure mode:
#   - React container running node (dev) instead of nginx (prod)
#   - PHP OPCache validate_timestamps=1 (dev) instead of 0 (prod)
#   - PHP display_errors=On (dev) instead of Off (prod)
#   - Build commit not baked into the image
#
# Designed to be safe to call before the traffic switch — hard-fails the deploy
# rather than letting a misbuilt candidate flip live.

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

_COLOR="${NEXUS_VERIFY_COLOR:-}"
case "$_COLOR" in
    blue|green)
        REACT_CONTAINER="nexus-${_COLOR}-react"
        PHP_CONTAINER="nexus-${_COLOR}-php-app"
        ;;
    *)
        REACT_CONTAINER="nexus-react-prod"
        PHP_CONTAINER="nexus-php-app"
        ;;
esac

verify_production_images() {
    log_step "=== Production Image Verification (${_COLOR:-legacy}) ==="

    local VERIFY_FAILED=0

    # Frontend: nginx (prod) vs node (dev)
    if docker exec "$REACT_CONTAINER" which nginx > /dev/null 2>&1; then
        log_ok "Frontend ($REACT_CONTAINER): nginx detected (production image)"
    elif docker exec "$REACT_CONTAINER" which node > /dev/null 2>&1; then
        log_err "Frontend ($REACT_CONTAINER): node detected — DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "Frontend: could not verify image type (container may not be running)"
    fi

    # Frontend image tag — accept either the legacy `:latest` or the blue/green
    # commit-tagged form (e.g. nexus-react-prod:abc1234567ab).
    local REACT_IMAGE
    REACT_IMAGE=$(docker inspect "$REACT_CONTAINER" --format '{{.Config.Image}}' 2>/dev/null || echo "unknown")
    if [[ "$REACT_IMAGE" == nexus-react-prod:* ]]; then
        log_ok "Frontend image: $REACT_IMAGE"
    elif [[ "$REACT_IMAGE" == *"dev"* ]] || [[ "$REACT_IMAGE" == "staging_frontend"* ]]; then
        log_err "Frontend image: $REACT_IMAGE — WRONG IMAGE (dev/legacy name)"
        VERIFY_FAILED=1
    else
        log_warn "Frontend image: $REACT_IMAGE (unexpected name)"
    fi

    # PHP OPCache must be production-tuned
    local OPCACHE_VALIDATE
    OPCACHE_VALIDATE=$(docker exec "$PHP_CONTAINER" php -r 'echo ini_get("opcache.validate_timestamps");' 2>/dev/null || echo "unknown")
    if [[ "$OPCACHE_VALIDATE" == "0" ]] || [[ "$OPCACHE_VALIDATE" == "" ]]; then
        log_ok "PHP OPCache: validate_timestamps=0 (production)"
    elif [[ "$OPCACHE_VALIDATE" == "1" ]]; then
        log_err "PHP OPCache: validate_timestamps=1 — DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP OPCache: validate_timestamps=$OPCACHE_VALIDATE (unexpected)"
    fi

    # PHP display_errors must be off
    local DISPLAY_ERRORS
    DISPLAY_ERRORS=$(docker exec "$PHP_CONTAINER" php -r 'echo ini_get("display_errors");' 2>/dev/null || echo "unknown")
    if [[ "$DISPLAY_ERRORS" == "" ]] || [[ "$DISPLAY_ERRORS" == "0" ]] || [[ "$DISPLAY_ERRORS" == "Off" ]]; then
        log_ok "PHP display_errors: Off (production)"
    elif [[ "$DISPLAY_ERRORS" == "1" ]] || [[ "$DISPLAY_ERRORS" == "On" ]]; then
        log_err "PHP display_errors: On — DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP display_errors: $DISPLAY_ERRORS (unexpected)"
    fi

    # Build commit baked into the React image (when BUILD_COMMIT is set by the orchestrator)
    if [ -n "${BUILD_COMMIT:-}" ]; then
        local REACT_COMMIT
        REACT_COMMIT=$(docker exec "$REACT_CONTAINER" sh -c 'cat /usr/share/nginx/html/.build-commit 2>/dev/null || cat /app/dist/.build-commit 2>/dev/null || echo none')
        if [[ "$REACT_COMMIT" == "$BUILD_COMMIT" ]]; then
            log_ok "React build commit: $REACT_COMMIT (matches)"
        else
            log_warn "React build commit: '$REACT_COMMIT' (expected: '$BUILD_COMMIT')"
        fi
    fi

    if [ $VERIFY_FAILED -eq 1 ]; then
        log_err "Image verification FAILED — dev images detected on production!"
        return 1
    fi

    log_ok "All production images verified"
    return 0
}

if ! verify_production_images; then
    log_err "ABORTING: Dev images detected on production candidate"
    exit 1
fi
