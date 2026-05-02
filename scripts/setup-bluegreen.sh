#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# One-time setup script: wires up the Apache side of zero-downtime blue-green
# deployment on the production server.
#
# Run this ONCE after the server has blue-green containers running for the
# first time, or after any server migration that loses the Apache config.
#
# Usage:
#   sudo bash scripts/setup-bluegreen.sh
#
# What it does:
#   1. Detects which color (blue/green) is currently active and its ports
#   2. Creates /etc/apache2/conf-enabled/nexus-active-upstreams.conf
#   3. Tests Apache can load it
#   4. Prints the exact Include directives to paste into the three Plesk
#      vhost "Additional Apache directives" panels
#   5. Reloads Apache

set -euo pipefail

SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SELF_DIR/.." && pwd)"

# common.sh requires LOG_FILE to be set before it is sourced
LOG_FILE="/dev/null"
export LOG_FILE DEPLOY_DIR

. "$SELF_DIR/deploy/lib/common.sh"

ROUTES_FILE="/etc/apache2/conf-enabled/nexus-active-upstreams.conf"
VHOST_INCLUDE_DIR="/etc/apache2/conf-enabled"
STATE_FILE="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"

BLUE_API_PORT="${NEXUS_BLUE_API_PORT:-8090}"
BLUE_FRONTEND_PORT="${NEXUS_BLUE_FRONTEND_PORT:-3000}"
BLUE_SALES_PORT="${NEXUS_BLUE_SALES_PORT:-3003}"
GREEN_API_PORT="${NEXUS_GREEN_API_PORT:-8190}"
GREEN_FRONTEND_PORT="${NEXUS_GREEN_FRONTEND_PORT:-3100}"
GREEN_SALES_PORT="${NEXUS_GREEN_SALES_PORT:-3103}"

# ---------------------------------------------------------------------------
log_step "=== Project NEXUS — Blue-Green Apache Setup ==="
# ---------------------------------------------------------------------------

# ── 1. Determine active color and ports ────────────────────────────────────
if [ -f "$STATE_FILE" ]; then
    ACTIVE_COLOR="$(tr -d '[:space:]' < "$STATE_FILE" 2>/dev/null || echo "")"
    case "$ACTIVE_COLOR" in
        blue|green) ;;
        *) ACTIVE_COLOR="" ;;
    esac
fi

if [ -z "${ACTIVE_COLOR:-}" ]; then
    # No state file yet — detect from running containers
    if docker ps --format "{{.Names}}" | grep -q "^nexus-green-php-app$"; then
        ACTIVE_COLOR="green"
    elif docker ps --format "{{.Names}}" | grep -q "^nexus-blue-php-app$"; then
        ACTIVE_COLOR="blue"
    elif docker ps --format "{{.Names}}" | grep -q "^nexus-php-app$"; then
        log_warn "Only the legacy single-color container is running (nexus-php-app)."
        log_warn "Run a blue-green deploy first, then re-run this setup script."
        log_info "  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach"
        exit 1
    else
        log_err "No running PHP app container found."
        log_err "Start the application first, then run this script."
        exit 1
    fi
fi

if [ "$ACTIVE_COLOR" = "blue" ]; then
    API_PORT="$BLUE_API_PORT"
    FRONTEND_PORT="$BLUE_FRONTEND_PORT"
    SALES_PORT="$BLUE_SALES_PORT"
else
    API_PORT="$GREEN_API_PORT"
    FRONTEND_PORT="$GREEN_FRONTEND_PORT"
    SALES_PORT="$GREEN_SALES_PORT"
fi

log_ok "Active color: $ACTIVE_COLOR (API=$API_PORT frontend=$FRONTEND_PORT sales=$SALES_PORT)"

# ── 2. Write the routes file ────────────────────────────────────────────────
log_step "=== Writing Apache routes file ==="

if [ -f "$ROUTES_FILE" ]; then
    cp "$ROUTES_FILE" "${ROUTES_FILE}.bak-$(date +%Y%m%d-%H%M%S)"
    log_info "Backed up existing routes file"
fi

cat > "$ROUTES_FILE" <<ROUTES
# Managed by scripts/deploy/bluegreen-deploy.sh
# Active color: $ACTIVE_COLOR
Define NEXUS_API_PORT $API_PORT
Define NEXUS_FRONTEND_PORT $FRONTEND_PORT
Define NEXUS_SALES_PORT $SALES_PORT
ROUTES

log_ok "Written: $ROUTES_FILE"

# ── 3. Check Apache can load it ─────────────────────────────────────────────
log_step "=== Testing Apache configuration ==="

if apachectl configtest 2>/dev/null; then
    log_ok "Apache configtest passed"
else
    log_err "Apache configtest FAILED."
    log_err "The routes file was written but Apache cannot load it."
    log_err "Check if the vhost Include lines are already in place (step 4 below)."
    log_err "The file has been written — fix the vhost includes then run:"
    log_err "  apachectl configtest && systemctl reload apache2"
fi

# ── 4. Check if vhosts already Include the routes file ──────────────────────
log_step "=== Checking Apache vhost configuration ==="

VHOST_HAS_INCLUDE=0
if grep -r "nexus-active-upstreams" /etc/apache2/ 2>/dev/null | grep -v ".bak" | grep -qv "^Binary"; then
    VHOST_HAS_INCLUDE=1
fi

if [ "$VHOST_HAS_INCLUDE" = "1" ]; then
    log_ok "Apache vhosts already reference nexus-active-upstreams.conf"
else
    log_warn "The Apache vhosts do NOT yet Include the routes file."
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "  ACTION REQUIRED — Plesk vhost configuration"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "  In Plesk admin panel, for EACH of the three domains, go to:"
    echo "  Websites & Domains → [domain] → Apache & nginx Settings"
    echo "  → 'Additional Apache directives for HTTP' AND 'for HTTPS'"
    echo "  and add the appropriate block below."
    echo ""
    echo "  ── api.project-nexus.ie ────────────────────────────────────"
    echo "  Include /etc/apache2/conf-enabled/nexus-active-upstreams.conf"
    echo "  ProxyPreserveHost On"
    echo "  ProxyPass / http://127.0.0.1:\${NEXUS_API_PORT}/ retry=0"
    echo "  ProxyPassReverse / http://127.0.0.1:\${NEXUS_API_PORT}/"
    echo ""
    echo "  ── app.project-nexus.ie ────────────────────────────────────"
    echo "  Include /etc/apache2/conf-enabled/nexus-active-upstreams.conf"
    echo "  ProxyPreserveHost On"
    echo "  ProxyPass / http://127.0.0.1:\${NEXUS_FRONTEND_PORT}/ retry=0"
    echo "  ProxyPassReverse / http://127.0.0.1:\${NEXUS_FRONTEND_PORT}/"
    echo ""
    echo "  ── project-nexus.ie ────────────────────────────────────────"
    echo "  Include /etc/apache2/conf-enabled/nexus-active-upstreams.conf"
    echo "  ProxyPass / http://127.0.0.1:\${NEXUS_SALES_PORT}/ retry=0"
    echo "  ProxyPassReverse / http://127.0.0.1:\${NEXUS_SALES_PORT}/"
    echo ""
    echo "  After saving in Plesk, run:"
    echo "    apachectl configtest && systemctl reload apache2"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
fi

# ── 5. Smoke-test the current active containers ──────────────────────────────
log_step "=== Smoke-testing active containers on their ports ==="

SMOKE_FAILED=0

if curl -sf "http://127.0.0.1:$API_PORT/up" >/dev/null 2>&1; then
    log_ok "API /up responded on port $API_PORT"
else
    log_err "API /up did NOT respond on port $API_PORT — container may be unhealthy"
    SMOKE_FAILED=1
fi

BOOTSTRAP="$(curl -sf -H "X-Tenant-Slug: hour-timebank" "http://127.0.0.1:$API_PORT/api/v2/tenant/bootstrap" 2>/dev/null || true)"
if echo "$BOOTSTRAP" | grep -q '"tenant"'; then
    log_ok "Tenant bootstrap responded correctly"
else
    log_err "Tenant bootstrap failed on port $API_PORT"
    SMOKE_FAILED=1
fi

FRONTEND_HTML="$(curl -sf "http://127.0.0.1:$FRONTEND_PORT/" 2>/dev/null || true)"
if echo "$FRONTEND_HTML" | grep -q 'id="root"'; then
    log_ok "React frontend serving on port $FRONTEND_PORT"
else
    log_err "React frontend not serving on port $FRONTEND_PORT"
    SMOKE_FAILED=1
fi

if curl -sf "http://127.0.0.1:$SALES_PORT/" >/dev/null 2>&1; then
    log_ok "Sales site serving on port $SALES_PORT"
else
    log_warn "Sales site not responding on port $SALES_PORT (non-critical)"
fi

# ── 6. Reload Apache ─────────────────────────────────────────────────────────
if [ "$VHOST_HAS_INCLUDE" = "1" ] && [ "$SMOKE_FAILED" = "0" ]; then
    log_step "=== Reloading Apache ==="
    if systemctl reload apache2 2>/dev/null; then
        log_ok "Apache reloaded — blue-green routing is now active"
    else
        log_err "Apache reload failed — check 'systemctl status apache2'"
    fi
else
    log_info "Skipping Apache reload (vhost includes not yet configured or smoke tests failed)."
fi

# ── 7. Write state file if missing ───────────────────────────────────────────
if [ ! -f "$STATE_FILE" ]; then
    echo "$ACTIVE_COLOR" > "$STATE_FILE"
    log_ok "State file written: $STATE_FILE = $ACTIVE_COLOR"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
log_step "=== Setup Complete ==="
log_info "Routes file:   $ROUTES_FILE"
log_info "Active color:  $ACTIVE_COLOR"
log_info "State file:    $STATE_FILE"
echo ""
if [ "$VHOST_HAS_INCLUDE" = "0" ]; then
    log_warn "NEXT STEP: Add the Include + ProxyPass directives in Plesk (see above)."
    log_warn "Until that is done, Apache will continue to use its current hardcoded ports."
else
    log_ok "Blue-green routing is fully configured."
    log_ok "Future deploys via safe-deploy.sh will use zero-downtime blue-green automatically."
fi
