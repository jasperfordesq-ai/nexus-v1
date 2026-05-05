#!/bin/bash
# =============================================================================
# Project NEXUS - Global Maintenance Mode Control
# =============================================================================
# Usage: sudo bash scripts/maintenance.sh [on|off|status]
#
# This is the CANONICAL method for controlling maintenance mode on the entire
# Project NEXUS platform across ALL tenants.
#
# THREE LAYERS are controlled simultaneously by this script:
#
#   Layer 1 (FILE-BASED):  .maintenance file in the PHP container
#     - Checked by index.php BEFORE Laravel boots
#     - Blocks ALL non-localhost HTTP requests with 503
#     - Fastest gate — no database, no framework overhead
#
#   Layer 2 (DATABASE-BASED):  tenant_settings.general.maintenance_mode
#     - Checked by Laravel CheckMaintenanceMode middleware
#     - Checked by React TenantShell (shows MaintenancePage)
#     - Per-tenant setting, but this script sets ALL tenants at once
#
#   Layer 3 (REDIS CACHE):  tenant_bootstrap cache keys
#     - TenantBootstrapController caches settings for 10 minutes
#     - React frontend reads maintenance_mode from cached bootstrap data
#     - Must be flushed so frontend sees DB changes immediately
#
# ALL layers must agree, or users will still see maintenance mode even
# after one layer is disabled. This script always toggles ALL THREE.
#
# DO NOT improvise alternative approaches. Use this script.
# =============================================================================

set -eo pipefail

# --- Configuration ---
DEPLOY_DIR="/opt/nexus-php"
PHP_CONTAINER="${PHP_CONTAINER:-}"
DB_CONTAINER="nexus-php-db"
REDIS_CONTAINER="nexus-php-redis"
MAINTENANCE_FILE="/var/www/html/.maintenance"
CACHE_PREFIX="nexus_laravel"

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

detect_php_container() {
    if [ -n "$PHP_CONTAINER" ]; then
        return
    fi

    local state_file color candidate
    state_file="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
    if [ -f "$state_file" ]; then
        color="$(tr -d '[:space:]' < "$state_file" 2>/dev/null || echo "")"
        case "$color" in
            blue|green)
                candidate="nexus-$color-php-app"
                if docker ps --format "{{.Names}}" | grep -qx "$candidate"; then
                    PHP_CONTAINER="$candidate"
                    return
                fi
                log_warn "Active color is '$color' but $candidate is not running; falling back to container discovery"
                ;;
            *)
                log_warn "Invalid blue-green active color '$color' in $state_file; falling back to container discovery"
                ;;
        esac
    fi

    PHP_CONTAINER=$(docker ps --format "{{.Names}}" | grep -E '^nexus-(blue|green)-php-app$' | head -n 1 || true)
    if [ -z "$PHP_CONTAINER" ]; then
        PHP_CONTAINER="nexus-php-app"
    fi
}

check_container() {
    local container="$1"
    if ! docker ps --format "{{.Names}}" | grep -qx "$container"; then
        log_err "$container container is not running"
        exit 1
    fi
}

verify_http_status() {
    local expected="$1"
    local label="$2"

    if [ -z "${MAINTENANCE_VERIFY_URL:-}" ]; then
        log_info "Skipping HTTP verification; set MAINTENANCE_VERIFY_URL to a non-localhost, non-exempt endpoint"
        return 0
    fi

    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
    if [ "$http_code" = "$expected" ]; then
        log_ok "Verified: HTTP $http_code ($label)"
    else
        log_warn "HTTP $http_code from $MAINTENANCE_VERIFY_URL; expected $expected ($label)"
    fi
}

# Read DB credentials from .env
get_db_creds() {
    local env_file="$DEPLOY_DIR/.env"
    if [ -f "$env_file" ]; then
        DB_USER=$(grep "^DB_USER=" "$env_file" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
        DB_PASS=$(grep "^DB_PASS=" "$env_file" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
        DB_NAME=$(grep "^DB_NAME=" "$env_file" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
        # Fallback: try DB_PASSWORD= if DB_PASS= not found
        if [ -z "$DB_PASS" ]; then
            DB_PASS=$(grep "^DB_PASSWORD=" "$env_file" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "")
        fi
    else
        DB_USER="nexus"
        DB_PASS=""
        DB_NAME="nexus"
    fi
}

# Set database maintenance_mode for all tenants
db_maintenance_set() {
    local value="$1"  # "true" or "false"
    get_db_creds

    if [ -z "$DB_PASS" ]; then
        log_warn "No DB password found in $DEPLOY_DIR/.env — skipping database layer"
        return 1
    fi

    # Update existing rows
    local updated
    updated=$(docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" mysql -u"$DB_USER" "$DB_NAME" -sN -e \
        "UPDATE tenant_settings SET setting_value = '$value' WHERE setting_key = 'general.maintenance_mode'; SELECT ROW_COUNT();" 2>/dev/null)

    # Insert for any tenants that don't have the setting yet
    docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" mysql -u"$DB_USER" "$DB_NAME" -e \
        "INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
         SELECT t.id, 'general.maintenance_mode', '$value', 'boolean'
         FROM tenants t WHERE t.is_active = 1
         AND NOT EXISTS (
             SELECT 1 FROM tenant_settings ts
             WHERE ts.tenant_id = t.id AND ts.setting_key = 'general.maintenance_mode'
         );" 2>/dev/null

    log_ok "Layer 2: Database maintenance_mode = '$value' (${updated:-0} rows updated)"
    return 0
}

# Flush Redis cache for tenant bootstrap data so frontend sees changes immediately
flush_bootstrap_cache() {
    if ! docker ps --filter "name=$REDIS_CONTAINER" --format "{{.Names}}" | grep -q "$REDIS_CONTAINER"; then
        log_warn "Layer 3: $REDIS_CONTAINER not running — skipping cache flush"
        return
    fi

    # Read cache prefix from .env (fallback to default)
    local prefix="$CACHE_PREFIX"
    local env_prefix
    env_prefix=$(grep "^CACHE_PREFIX=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "")
    if [ -n "$env_prefix" ]; then
        prefix="$env_prefix"
    fi

    # Delete all tenant_bootstrap cache keys (format: nexus_laravel:t{id}:tenant_bootstrap)
    local deleted=0
    local keys
    keys=$(docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning KEYS "${prefix}:*tenant_bootstrap*" 2>/dev/null || echo "")

    if [ -n "$keys" ]; then
        for key in $keys; do
            docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning DEL "$key" > /dev/null 2>&1
            deleted=$((deleted + 1))
        done
    fi

    # Also flush tenant_settings cache keys (format: nexus_laravel:t{id}:tenant_settings)
    keys=$(docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning KEYS "${prefix}:*tenant_settings*" 2>/dev/null || echo "")

    if [ -n "$keys" ]; then
        for key in $keys; do
            docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning DEL "$key" > /dev/null 2>&1
            deleted=$((deleted + 1))
        done
    fi

    log_ok "Layer 3: Redis cache flushed ($deleted keys deleted)"
}

# Check how many tenants have maintenance_mode = 'true' in the database
db_maintenance_status() {
    get_db_creds
    if [ -z "$DB_PASS" ]; then
        log_warn "No DB password — cannot check database layer"
        return
    fi

    local count
    count=$(docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" mysql -u"$DB_USER" "$DB_NAME" -sN -e \
        "SELECT COUNT(*) FROM tenant_settings WHERE setting_key = 'general.maintenance_mode' AND setting_value = 'true';" 2>/dev/null || echo "?")

    local total
    total=$(docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" mysql -u"$DB_USER" "$DB_NAME" -sN -e \
        "SELECT COUNT(*) FROM tenant_settings WHERE setting_key = 'general.maintenance_mode';" 2>/dev/null || echo "?")

    if [ "$count" = "0" ]; then
        echo -e "    Database: ${GREEN}ALL tenants live${NC} (0/$total in maintenance)"
    else
        echo -e "    Database: ${RED}$count/$total tenants in maintenance mode${NC}"
    fi
}

# Check Redis cache status
redis_cache_status() {
    if ! docker ps --filter "name=$REDIS_CONTAINER" --format "{{.Names}}" | grep -q "$REDIS_CONTAINER"; then
        echo -e "    Redis:    ${YELLOW}container not running${NC}"
        return
    fi

    local prefix="$CACHE_PREFIX"
    local env_prefix
    env_prefix=$(grep "^CACHE_PREFIX=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "")
    if [ -n "$env_prefix" ]; then
        prefix="$env_prefix"
    fi

    local cache_count
    cache_count=$(docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning KEYS "${prefix}:*tenant_bootstrap*" 2>/dev/null | wc -l || echo "0")
    cache_count=$(echo "$cache_count" | tr -d ' ')

    if [ "$cache_count" = "0" ]; then
        echo -e "    Redis:    ${GREEN}no cached bootstrap data${NC}"
    else
        echo -e "    Redis:    ${YELLOW}$cache_count tenant bootstrap keys cached${NC} (may serve stale maintenance_mode)"
    fi
}

maintenance_on() {
    echo -e "\n${BOLD}=== Enabling Global Maintenance Mode ===${NC}\n"

    check_container "$PHP_CONTAINER"
    check_container "$DB_CONTAINER"

    # --- Layer 1: File-based gate ---
    docker exec "$PHP_CONTAINER" touch "$MAINTENANCE_FILE"
    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        log_ok "Layer 1: .maintenance file created"
    else
        log_err "Layer 1: Failed to create .maintenance file"
        exit 1
    fi

    # --- Layer 2: Database ---
    db_maintenance_set "true" || log_warn "Layer 2: Database update failed — file-based gate still active"

    # --- Layer 3: Flush Redis cache ---
    flush_bootstrap_cache

    # --- Verify ---
    sleep 1
    local HTTP_CODE
    HTTP_CODE="skipped"
    if [ -n "${MAINTENANCE_VERIFY_URL:-}" ]; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
    fi

    if [ "$HTTP_CODE" = "503" ]; then
        log_ok "Verified: HTTP 503 (maintenance active)"
    else
        log_warn "HTTP $HTTP_CODE — maintenance may not be fully active yet"
    fi

    echo ""
    log_ok "MAINTENANCE MODE IS ON — all tenants, all users blocked (except localhost)"
    echo -e "    ${CYAN}To disable:${NC} sudo bash scripts/maintenance.sh off"
    echo ""
}

maintenance_off() {
    echo -e "\n${BOLD}=== Disabling Global Maintenance Mode ===${NC}\n"

    check_container "$PHP_CONTAINER"
    check_container "$DB_CONTAINER"

    # --- Layer 1: Remove file ---
    docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"
    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        log_err "Layer 1: .maintenance file STILL exists"
        exit 1
    else
        log_ok "Layer 1: .maintenance file removed"
    fi

    # --- Layer 2: Database ---
    db_maintenance_set "false" || log_warn "Layer 2: Database update failed — check manually"

    # --- Layer 3: Flush Redis cache ---
    flush_bootstrap_cache

    # --- Verify ---
    sleep 1
    local HTTP_CODE
    HTTP_CODE="skipped"
    if [ -n "${MAINTENANCE_VERIFY_URL:-}" ]; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
    fi

    if [ "$HTTP_CODE" = "200" ]; then
        log_ok "Verified: HTTP 200 (platform is live)"
    elif [ "$HTTP_CODE" = "503" ]; then
        log_warn "Still returning 503 — restarting PHP container to clear OPCache..."
        docker restart "$PHP_CONTAINER" > /dev/null 2>&1
        sleep 3
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/api/v2/tenants 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_ok "After restart: HTTP 200 (platform is live)"
        else
            log_warn "HTTP $HTTP_CODE after restart — investigate manually"
        fi
    else
        log_warn "HTTP $HTTP_CODE — container may be starting up"
    fi

    echo ""
    log_ok "MAINTENANCE MODE IS OFF — platform is live for all users"
    echo ""
}

maintenance_status() {
    echo -e "\n${BOLD}=== Maintenance Mode Status ===${NC}\n"

    check_container "$PHP_CONTAINER"

    # Layer 1: File check
    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        echo -e "    File:     ${RED}${BOLD}ON${NC} — .maintenance exists in container"
    else
        echo -e "    File:     ${GREEN}${BOLD}OFF${NC} — no .maintenance file"
    fi

    # Layer 2: Database check
    db_maintenance_status

    # Layer 3: Redis cache check
    redis_cache_status

    # HTTP verification
    local HTTP_CODE
    HTTP_CODE="skipped"
    if [ -n "${MAINTENANCE_VERIFY_URL:-}" ]; then
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$MAINTENANCE_VERIFY_URL" 2>/dev/null || echo "000")
    fi
    echo -e "    HTTP:     $HTTP_CODE"

    # Overall status
    echo ""
    local file_on=false
    local db_on=false

    if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
        file_on=true
    fi

    get_db_creds
    if [ -n "$DB_PASS" ]; then
        local db_count
        db_count=$(docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" mysql -u"$DB_USER" "$DB_NAME" -sN -e \
            "SELECT COUNT(*) FROM tenant_settings WHERE setting_key = 'general.maintenance_mode' AND setting_value = 'true';" 2>/dev/null || echo "0")
        if [ "$db_count" != "0" ]; then
            db_on=true
        fi
    fi

    if [ "$file_on" = "true" ] && [ "$db_on" = "true" ]; then
        echo -e "    ${RED}${BOLD}MAINTENANCE MODE IS ON${NC} (both layers active)"
    elif [ "$file_on" = "true" ]; then
        echo -e "    ${RED}${BOLD}MAINTENANCE MODE IS ON${NC} (file gate active, DB says live)"
        echo -e "    ${YELLOW}Layers are out of sync — run 'maintenance.sh on' or 'maintenance.sh off'${NC}"
    elif [ "$db_on" = "true" ]; then
        echo -e "    ${RED}${BOLD}PARTIALLY IN MAINTENANCE${NC} (file removed but DB still says maintenance!)"
        echo -e "    ${YELLOW}⚠ Users will still see maintenance page! Run 'maintenance.sh off' to fix${NC}"
    else
        echo -e "    ${GREEN}${BOLD}PLATFORM IS LIVE${NC} (both layers off)"
    fi

    echo ""
    echo -e "    ${CYAN}Enable:${NC}  sudo bash scripts/maintenance.sh on"
    echo -e "    ${CYAN}Disable:${NC} sudo bash scripts/maintenance.sh off"
    echo ""
}

# --- Main ---
detect_php_container

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
        echo "  on      Enable maintenance mode (file + database + cache flush)"
        echo "  off     Disable maintenance mode (file + database + cache flush)"
        echo "  status  Show all layers' status + detect out-of-sync state"
        echo ""
        exit 1
        ;;
esac
