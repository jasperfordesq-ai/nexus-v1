#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

run_smoke_tests() {
    log_step "=== Post-Deploy Smoke Tests ==="

    log_info "Waiting 5 seconds for containers to stabilize..."
    sleep 5

    local TESTS_FAILED=0

    # Test API health endpoint
    if curl -sf http://127.0.0.1:8090/health.php > /dev/null 2>&1; then
        log_ok "API health check passed"
    else
        log_err "API health check failed"
        TESTS_FAILED=1
    fi

    # Test Laravel /up endpoint — validates full framework bootstrap (routes, config, app key)
    # This is the Laravel 12 standard liveness probe, distinct from the raw health.php check
    if curl -sf http://127.0.0.1:8090/up > /dev/null 2>&1; then
        log_ok "Laravel /up framework check passed"
    else
        log_err "Laravel /up check failed — framework may not be fully bootstrapped"
        TESTS_FAILED=1
    fi

    # Test API bootstrap endpoint (with tenant context for real validation)
    local BOOTSTRAP
    BOOTSTRAP=$(curl -sf -H "X-Tenant-Slug: hour-timebank" http://127.0.0.1:8090/api/v2/tenant/bootstrap 2>/dev/null || echo "")
    if echo "$BOOTSTRAP" | grep -q '"tenant"'; then
        log_ok "API bootstrap returns valid tenant data"
    elif [ -n "$BOOTSTRAP" ]; then
        log_warn "API bootstrap responded but missing tenant data"
    else
        log_warn "API bootstrap endpoint failed (may require tenant header)"
    fi

    # Test frontend — verify it serves the React app, not an error page
    local FRONTEND_HTML
    FRONTEND_HTML=$(curl -sf http://127.0.0.1:3000/ 2>/dev/null || echo "")
    if echo "$FRONTEND_HTML" | grep -q 'id="root"'; then
        log_ok "Frontend serves React app"
    elif [ -n "$FRONTEND_HTML" ]; then
        log_err "Frontend responded but missing React root element"
        TESTS_FAILED=1
    else
        log_err "Frontend health check failed"
        TESTS_FAILED=1
    fi

    # Test sales site
    if curl -sf http://127.0.0.1:3003/ > /dev/null 2>&1; then
        log_ok "Sales site health check passed"
    else
        log_warn "Sales site health check failed (non-critical)"
    fi

    # Check database connectivity (read password from .env)
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | sed 's/^DB_PASS=//' | tr -d '"'"'"')
    if docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysqladmin ping -h localhost -unexus > /dev/null 2>&1; then
        log_ok "Database still accessible"
    else
        log_err "Database connection lost"
        TESTS_FAILED=1
    fi

    # Check container health (only OUR containers, not nexus-backend-*, nexus-frontend-*, etc.)
    local UNHEALTHY=$(docker ps --filter "name=nexus-php" --filter "name=nexus-react-prod" --filter "name=nexus-sales-site" --format "{{.Names}}: {{.Status}}" | grep -i "unhealthy" || true)
    if [ -n "$UNHEALTHY" ]; then
        log_err "Unhealthy containers detected:"
        echo "$UNHEALTHY" | tee -a "$LOG_FILE"
        TESTS_FAILED=1
    else
        log_ok "All containers healthy"
    fi

    if [ $TESTS_FAILED -eq 1 ]; then
        log_err "Smoke tests failed - consider rollback"
        return 1
    fi

    log_ok "All smoke tests passed"
    return 0
}

run_smoke_tests
