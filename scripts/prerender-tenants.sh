#!/bin/bash
# =============================================================================
# Project NEXUS - Per-Tenant Server-Side Pre-Rendering
# =============================================================================
# Runs AFTER deployment, when all containers are up and the API is live.
# Spins up a one-shot Playwright Docker container that visits each tenant's
# actual domain, renders every public page with REAL tenant data (branding,
# titles, descriptions, content), and injects the HTML into the nginx
# container. Works for ALL tenants — current and future.
#
# Usage:
#   sudo bash scripts/prerender-tenants.sh                              # All tenants/routes
#   sudo bash scripts/prerender-tenants.sh --tenant hour-timebank       # One tenant/all routes
#   sudo bash scripts/prerender-tenants.sh --routes /about,/privacy     # All tenants/specific routes
#   sudo bash scripts/prerender-tenants.sh --tenant hour-timebank --routes /about
#
# Requirements:
#   - All containers must be running and healthy
#   - API must be responding (maintenance mode OFF)
#   - Docker must be available (uses mcr.microsoft.com/playwright image)
# =============================================================================

set -euo pipefail

DEPLOY_DIR="/opt/nexus-php"
NGINX_CONTAINER="nexus-react-prod"
PRERENDER_DIR="/usr/share/nginx/html/prerendered"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.59.1-noble"
WORKER_SCRIPT="$DEPLOY_DIR/scripts/prerender-worker.mjs"
OUTPUT_DIR="/tmp/nexus-prerender-$$"
WORK_DIR="$DEPLOY_DIR/.prerender-worker"
PRERENDER_CONCURRENCY="${PRERENDER_CONCURRENCY:-4}"

# Public routes to pre-render for each tenant
PUBLIC_ROUTES=(
    "/"
    "/about"
    "/faq"
    "/contact"
    "/help"
    "/explore"
    "/listings"
    "/blog"
    "/terms"
    "/privacy"
    "/accessibility"
    "/cookies"
    "/community-guidelines"
    "/acceptable-use"
    "/legal"
    "/timebanking-guide"
    "/platform/terms"
    "/platform/privacy"
    "/platform/disclaimer"
)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${CYAN}[INFO]${NC} $*"; }
log_ok()   { echo -e "${GREEN}[OK]${NC}   $*"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $*"; }

# --- Parse args ---
FILTER_TENANT=""
FILTER_ROUTES=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --tenant) FILTER_TENANT="$2"; shift 2 ;;
        --routes) FILTER_ROUTES="$2"; shift 2 ;;
        *) shift ;;
    esac
done

if [ -n "$FILTER_ROUTES" ]; then
    IFS=',' read -r -a PUBLIC_ROUTES <<< "$FILTER_ROUTES"
fi

if [ -n "$FILTER_TENANT" ] && [[ ! "$FILTER_TENANT" =~ ^[A-Za-z0-9_-]+$ ]]; then
    log_err "Invalid tenant slug for pre-rendering: $FILTER_TENANT"
    exit 1
fi

ROUTE_RE='^/[A-Za-z0-9._~/%:@!$()*+,;=-]*$'
for ROUTE in "${PUBLIC_ROUTES[@]}"; do
    if [[ ! "$ROUTE" =~ $ROUTE_RE ]]; then
        log_err "Invalid route for pre-rendering: $ROUTE"
        exit 1
    fi
done

# --- Cleanup on exit ---
cleanup() {
    rm -rf "$OUTPUT_DIR" 2>/dev/null || true
}
trap cleanup EXIT

# --- Get active tenants from the database ---
get_tenants() {
    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep "^DB_USER=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep "^DB_NAME=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")

    local QUERY="SELECT slug, COALESCE(domain, '') as domain FROM tenants WHERE is_active = 1"
    if [ -n "$FILTER_TENANT" ]; then
        QUERY="$QUERY AND slug = '$FILTER_TENANT'"
    fi

    docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db \
        mysql -u"$DB_USER" "$DB_NAME" -N -e "$QUERY" 2>/dev/null
}

# --- Build the URL manifest for the Playwright worker ---
build_manifest() {
    local TENANTS="$1"
    local FRONTEND_URL
    FRONTEND_URL=$(grep "^FRONTEND_URL=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "https://app.project-nexus.ie")
    local APP_HOST
    APP_HOST=$(echo "$FRONTEND_URL" | sed 's|https\?://||' | sed 's|/.*||')

    echo '{"urls":['
    local FIRST=1

    while IFS=$'\t' read -r SLUG DOMAIN; do
        local HOST PREFIX
        if [ -n "$DOMAIN" ]; then
            HOST="$DOMAIN"
            PREFIX=""
        elif [ -n "$SLUG" ]; then
            HOST="$APP_HOST"
            PREFIX="/${SLUG}"
        else
            HOST="$APP_HOST"
            PREFIX=""
        fi

        for ROUTE in "${PUBLIC_ROUTES[@]}"; do
            if [ "$FIRST" -eq 1 ]; then
                FIRST=0
            else
                echo ","
            fi

            local FULL_URL="https://${HOST}${PREFIX}${ROUTE}"
            local OUT_ROUTE="${PREFIX}${ROUTE}"
            local OUT_PATH="/output/${HOST}${OUT_ROUTE}/index.html"
            if [ "$OUT_ROUTE" = "/" ]; then
                OUT_PATH="/output/${HOST}/index.html"
            fi

            echo -n "{\"url\":\"${FULL_URL}\",\"output\":\"${OUT_PATH}\"}"
        done
    done <<< "$TENANTS"

    echo ']}'
}

# --- Main ---
main() {
    log_info "=== Per-Tenant Server-Side Pre-Rendering ==="
    echo ""

    # Verify nginx container is running
    if ! docker ps --format '{{.Names}}' | grep -q "^${NGINX_CONTAINER}$"; then
        log_err "Container $NGINX_CONTAINER is not running"
        exit 1
    fi

    # Verify worker script exists
    if [ ! -f "$WORKER_SCRIPT" ]; then
        log_err "Worker script not found: $WORKER_SCRIPT"
        exit 1
    fi

    # Get tenants
    local TENANTS
    TENANTS=$(get_tenants)

    if [ -z "$TENANTS" ]; then
        log_err "No active tenants found"
        exit 1
    fi

    local TENANT_COUNT
    TENANT_COUNT=$(echo "$TENANTS" | wc -l)
    local TOTAL_PAGES=$((TENANT_COUNT * ${#PUBLIC_ROUTES[@]}))
    log_info "Found $TENANT_COUNT tenant(s), $TOTAL_PAGES pages to pre-render"

    # Create output directory
    mkdir -p "$OUTPUT_DIR"

    # Build manifest
    local MANIFEST
    MANIFEST=$(build_manifest "$TENANTS")

    log_info "Starting Playwright render container..."

    # Run the Playwright worker in a Docker container
    # --network host: so it can reach the live site via public URLs
    # -v: mount the worker script and output directory
    # -w /work: persistent dir for node_modules so Playwright is installed once
    # The Docker image has browsers but not always the npm package.
    mkdir -p "$WORK_DIR"
    cp "$WORKER_SCRIPT" "$WORK_DIR/worker.mjs"

    set +e
    echo "$MANIFEST" | docker run --rm -i \
        --network host \
        -v "$WORK_DIR:/work" \
        -v "$OUTPUT_DIR:/output" \
        -w /work \
        -e "PRERENDER_CONCURRENCY=$PRERENDER_CONCURRENCY" \
        "$PLAYWRIGHT_IMAGE" \
        bash -c "if [ ! -d node_modules/playwright ]; then npm init -y >/dev/null 2>&1 && npm install --no-save playwright >/dev/null 2>&1; fi; node worker.mjs" 2>&1

    local EXIT_CODE=$?
    set -e

    if [ $EXIT_CODE -ne 0 ]; then
        log_warn "Playwright worker exited with code $EXIT_CODE (some pages may have failed)"
    fi

    # Copy rendered files into the nginx container
    log_info "Injecting pre-rendered HTML into $NGINX_CONTAINER..."

    # Create the prerendered directory in the container
    docker exec "$NGINX_CONTAINER" mkdir -p "$PRERENDER_DIR" 2>/dev/null || true

    # Copy all rendered files
    if [ -d "$OUTPUT_DIR" ] && [ "$(ls -A "$OUTPUT_DIR" 2>/dev/null)" ]; then
        docker cp "$OUTPUT_DIR/." "${NGINX_CONTAINER}:${PRERENDER_DIR}/" 2>/dev/null

        # Count what we copied
        local FILE_COUNT
        FILE_COUNT=$(find "$OUTPUT_DIR" -name "index.html" | wc -l)
        log_ok "$FILE_COUNT pre-rendered pages injected into $NGINX_CONTAINER"
    else
        log_warn "No rendered files to inject"
    fi

    # Reload nginx config (not strictly needed — try_files checks filesystem)
    docker exec "$NGINX_CONTAINER" nginx -s reload 2>/dev/null || true

    if [ $EXIT_CODE -ne 0 ]; then
        log_warn "Pre-rendering completed with partial output; success marker will not be updated"
        exit $EXIT_CODE
    fi

    log_ok "Pre-rendering complete"
}

main "$@"
