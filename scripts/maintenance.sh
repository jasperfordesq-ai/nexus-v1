#!/bin/bash
# =============================================================================
# Project NEXUS - Global Maintenance Mode Control
# =============================================================================
# Usage: sudo bash scripts/maintenance.sh [on|off|status]
#
# This is the CANONICAL method for controlling maintenance mode on the entire
# Project NEXUS platform across ALL tenants.
#
# How it works:
#   Creates/removes a .maintenance file that is checked by index.php BEFORE
#   any framework code loads. When present, ALL non-localhost requests receive
#   HTTP 503 with a static maintenance page. This is the fastest, most reliable
#   gate — no database, no Redis, no Laravel, no React involved.
#
# This method was established on 2026-03-21 after discovering that the
# database-driven approach (tenant_settings) was unreliable because the
# middleware was not registered. The file-based approach is now the canonical
# method for global maintenance mode.
#
# DO NOT improvise alternative approaches. Use this script.
# =============================================================================

set -e

# --- Configuration ---
DEPLOY_DIR="/opt/nexus-php"
PHP_CONTAINER="nexus-php-app"
MAINTENANCE_FILE="/var/www/html/.maintenance"

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Functions ---
log_ok()   { echo -e "${GREEN}[OK]${NC}   $1"; }
log_info() { echo -e "${CYAN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $1"; }

check_container() {
    if ! docker ps --filter "name=$PHP_CONTAINER" --format "{{.Names}}" | grep -q "$PHP_CONTAINER"; then
        log_err "$PHP_CONTAINER container is not running"
        exit 1
    fi
}

maintenance_on() {
    echo -e "\n${BOLD}=== Enabling Global Maintenance Mode ===${NC}\n"

    check_container

    # Create the .maintenance file (idempotent — touch is safe if file exists)
    docker exec "$PHP_CONTAINER" touch "$MAINTENANCE_FILE"
    log_ok "Maintenance file created: $MAINTENANCE_FILE"

    # Verify it worked
    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        log_ok "Verified: .maintenance file exists"
    else
        log_err "Verification failed: .maintenance file NOT found"
        exit 1
    fi

    # Verify the API returns 503
    sleep 1
    local HTTP_CODE
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/health.php 2>/dev/null || echo "000")

    if [ "$HTTP_CODE" = "503" ]; then
        log_ok "Verified: API returning HTTP 503 (maintenance mode active)"
    elif [ "$HTTP_CODE" = "200" ]; then
        # health.php might not go through index.php — try a regular endpoint
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/api/v2/tenants 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "503" ]; then
            log_ok "Verified: API returning HTTP 503 (maintenance mode active)"
        else
            log_warn "API returned HTTP $HTTP_CODE — maintenance may not be fully active"
            log_warn "Check that index.php contains the .maintenance file check"
        fi
    else
        log_warn "Could not verify API response (HTTP $HTTP_CODE) — container may be starting up"
    fi

    echo ""
    log_ok "MAINTENANCE MODE IS ON — all tenants, all users blocked (except localhost)"
    echo -e "    ${CYAN}To disable:${NC} sudo bash scripts/maintenance.sh off"
    echo ""
}

maintenance_off() {
    echo -e "\n${BOLD}=== Disabling Global Maintenance Mode ===${NC}\n"

    check_container

    # Remove the .maintenance file (idempotent — rm -f is safe if file doesn't exist)
    docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"
    log_ok "Maintenance file removed"

    # Verify it's gone
    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        log_err "Verification failed: .maintenance file STILL exists"
        exit 1
    else
        log_ok "Verified: .maintenance file removed"
    fi

    # Verify the API is responding normally
    sleep 1
    local HTTP_CODE
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/health.php 2>/dev/null || echo "000")

    if [ "$HTTP_CODE" = "200" ]; then
        log_ok "Verified: API returning HTTP 200 (platform is live)"
    elif [ "$HTTP_CODE" = "503" ]; then
        log_warn "API still returning 503 — OPCache may be stale"
        log_info "Restarting PHP container to clear OPCache..."
        docker restart "$PHP_CONTAINER" > /dev/null 2>&1
        sleep 3
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/health.php 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_ok "After restart: API returning HTTP 200 (platform is live)"
        else
            log_warn "API returning HTTP $HTTP_CODE after restart — investigate manually"
        fi
    else
        log_warn "Could not verify API response (HTTP $HTTP_CODE)"
    fi

    echo ""
    log_ok "MAINTENANCE MODE IS OFF — platform is live for all users"
    echo ""
}

maintenance_status() {
    echo -e "\n${BOLD}=== Maintenance Mode Status ===${NC}\n"

    check_container

    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        echo -e "    Status: ${RED}${BOLD}MAINTENANCE MODE IS ON${NC}"
        echo -e "    File:   $MAINTENANCE_FILE exists in $PHP_CONTAINER"
        echo ""
        log_info "All non-localhost requests are being blocked with HTTP 503"
        echo -e "    ${CYAN}To disable:${NC} sudo bash scripts/maintenance.sh off"
    else
        echo -e "    Status: ${GREEN}${BOLD}PLATFORM IS LIVE${NC}"
        echo -e "    File:   $MAINTENANCE_FILE does not exist"
        echo ""
        log_info "All requests are being served normally"
        echo -e "    ${CYAN}To enable:${NC} sudo bash scripts/maintenance.sh on"
    fi

    echo ""

    # Also check HTTP response for verification
    local HTTP_CODE
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/api/v2/tenants 2>/dev/null || echo "000")
    log_info "API HTTP response code: $HTTP_CODE"
    echo ""
}

# --- Main ---
case "${1:-}" in
    on)
        maintenance_on
        ;;
    off)
        maintenance_off
        ;;
    status)
        maintenance_status
        ;;
    *)
        echo ""
        echo "Usage: sudo bash scripts/maintenance.sh [on|off|status]"
        echo ""
        echo "  on      Enable global maintenance mode (all tenants, all users blocked)"
        echo "  off     Disable global maintenance mode (platform goes live)"
        echo "  status  Check current maintenance mode status"
        echo ""
        exit 1
        ;;
esac
