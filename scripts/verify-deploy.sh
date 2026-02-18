#!/bin/bash
# =============================================================================
# Project NEXUS - Production Deploy Verification
# =============================================================================
# Run ON THE PRODUCTION SERVER: sudo bash scripts/verify-deploy.sh
#
# Non-destructive — checks current state without changing anything.
# =============================================================================

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_ok()   { echo -e "  ${GREEN}[OK]${NC}   $1"; }
log_warn() { echo -e "  ${YELLOW}[WARN]${NC} $1"; }
log_err()  { echo -e "  ${RED}[FAIL]${NC} $1"; }
log_info() { echo -e "  ${CYAN}[INFO]${NC} $1"; }

PASS=0
FAIL=0
WARN=0

check_pass() { PASS=$((PASS + 1)); log_ok "$1"; }
check_fail() { FAIL=$((FAIL + 1)); log_err "$1"; }
check_warn() { WARN=$((WARN + 1)); log_warn "$1"; }

cd /opt/nexus-php 2>/dev/null || { echo "Cannot cd to /opt/nexus-php"; exit 1; }

echo -e "\n${BOLD}${CYAN}=== Project NEXUS — Production Verification ===${NC}\n"

# =============================================================================
# 1. Git Status
# =============================================================================
echo -e "${BOLD}1. Git Status${NC}"

COMMIT=$(git log --oneline -1 2>/dev/null)
if [ -n "$COMMIT" ]; then
    check_pass "Git commit: $COMMIT"
else
    check_fail "Cannot read git commit"
fi

BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
if [ "$BRANCH" = "main" ]; then
    check_pass "On branch: main"
else
    check_warn "On branch: $BRANCH (expected: main)"
fi

# Check if we're behind origin
git fetch origin main --quiet 2>/dev/null
LOCAL=$(git rev-parse HEAD 2>/dev/null)
REMOTE=$(git rev-parse origin/main 2>/dev/null)
if [ "$LOCAL" = "$REMOTE" ]; then
    check_pass "Up to date with origin/main"
else
    check_warn "Behind origin/main — local: ${LOCAL:0:7}, remote: ${REMOTE:0:7}"
fi
echo ""

# =============================================================================
# 2. compose.yml Verification
# =============================================================================
echo -e "${BOLD}2. compose.yml${NC}"

if [ -f compose.yml ]; then
    if head -5 compose.yml | grep -qi "production"; then
        check_pass "compose.yml is the PRODUCTION version"
    else
        check_fail "compose.yml is NOT the production version!"
        log_info "Run: cp compose.prod.yml compose.yml"
    fi
else
    check_fail "compose.yml does not exist!"
fi

if [ -f compose.prod.yml ]; then
    check_pass "compose.prod.yml exists"
else
    check_fail "compose.prod.yml is MISSING"
fi
echo ""

# =============================================================================
# 3. Environment
# =============================================================================
echo -e "${BOLD}3. Environment Files${NC}"

if [ -f .env ]; then
    check_pass ".env exists"
    # Check critical vars without exposing values
    for var in DB_PASS DB_NAME REDIS_HOST APP_URL; do
        if grep -q "^${var}=" .env 2>/dev/null; then
            check_pass "$var is set"
        else
            check_warn "$var is NOT set in .env"
        fi
    done
else
    check_fail ".env is MISSING"
fi
echo ""

# =============================================================================
# 4. Container Status
# =============================================================================
echo -e "${BOLD}4. Container Status${NC}"

EXPECTED_CONTAINERS=("nexus-php-app" "nexus-react-prod" "nexus-php-db" "nexus-php-redis" "nexus-sales-site")

for container in "${EXPECTED_CONTAINERS[@]}"; do
    status=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null)
    health=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null)

    if [ "$status" = "running" ]; then
        if [ "$health" = "healthy" ]; then
            check_pass "$container: running (healthy)"
        elif [ "$health" = "unhealthy" ]; then
            check_fail "$container: running but UNHEALTHY"
        else
            check_pass "$container: running"
        fi
    elif [ -n "$status" ]; then
        check_fail "$container: $status"
    else
        check_fail "$container: NOT FOUND"
    fi
done
echo ""

# =============================================================================
# 5. Docker Images
# =============================================================================
echo -e "${BOLD}5. Docker Images${NC}"

docker images --format "  {{.Repository}}:{{.Tag}}  Created: {{.CreatedSince}}  Size: {{.Size}}" \
    | grep -i nexus || echo "  (no nexus images found)"
echo ""

# =============================================================================
# 6. Health Endpoints
# =============================================================================
echo -e "${BOLD}6. Health Endpoints${NC}"

check_endpoint() {
    local name="$1"
    local url="$2"
    local http_code

    http_code=$(curl -sf -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")

    if [ "$http_code" = "200" ]; then
        check_pass "$name — HTTP $http_code"
    else
        check_fail "$name — HTTP $http_code"
    fi
}

check_endpoint "PHP API"        "http://127.0.0.1:8090/health.php"
check_endpoint "React Frontend" "http://127.0.0.1:3000/"
check_endpoint "Sales Site"     "http://127.0.0.1:3003/"
echo ""

# =============================================================================
# 7. Volumes
# =============================================================================
echo -e "${BOLD}7. Docker Volumes${NC}"

for vol in nexus-php-db-data nexus-php-redis-data nexus-php-uploads nexus-php-logs; do
    if docker volume inspect "$vol" &>/dev/null; then
        check_pass "Volume $vol exists"
    else
        check_warn "Volume $vol not found"
    fi
done
echo ""

# =============================================================================
# 8. OPcache Status
# =============================================================================
echo -e "${BOLD}8. OPcache Status${NC}"

OPCACHE_OUT=$(docker exec nexus-php-app php -r '
    $s = opcache_get_status(false);
    if ($s) {
        echo "enabled=1\n";
        echo "cached_scripts=" . $s["opcache_statistics"]["num_cached_scripts"] . "\n";
        echo "memory_used=" . round($s["memory_usage"]["used_memory"] / 1024 / 1024, 1) . "MB\n";
        echo "hit_rate=" . round($s["opcache_statistics"]["opcache_hit_rate"], 1) . "%\n";
        echo "last_restart=" . ($s["opcache_statistics"]["last_restart_time"] > 0 ? date("Y-m-d H:i:s", $s["opcache_statistics"]["last_restart_time"]) : "never") . "\n";
    } else {
        echo "enabled=0\n";
    }
' 2>/dev/null)

if echo "$OPCACHE_OUT" | grep -q "enabled=1"; then
    check_pass "OPcache is enabled"
    echo "$OPCACHE_OUT" | while IFS='=' read -r key val; do
        [ "$key" != "enabled" ] && log_info "  $key: $val"
    done
else
    check_warn "OPcache status unavailable (container may not be running)"
fi
echo ""

# =============================================================================
# 9. Disk Usage
# =============================================================================
echo -e "${BOLD}9. Disk Usage${NC}"

log_info "Docker disk usage:"
docker system df 2>/dev/null | sed 's/^/    /'
echo ""

DISK_FREE=$(df -h /opt/nexus-php 2>/dev/null | tail -1 | awk '{print $4}')
log_info "Free disk space: $DISK_FREE"
echo ""

# =============================================================================
# Summary
# =============================================================================
echo -e "${BOLD}${CYAN}=== VERIFICATION SUMMARY ===${NC}"
echo ""
echo -e "  ${GREEN}Passed:${NC}  $PASS"
echo -e "  ${YELLOW}Warnings:${NC} $WARN"
echo -e "  ${RED}Failed:${NC}  $FAIL"
echo ""

if [ "$FAIL" -eq 0 ]; then
    echo -e "  ${GREEN}${BOLD}All checks passed!${NC}"
else
    echo -e "  ${RED}${BOLD}$FAIL check(s) failed — see above for details.${NC}"
fi
echo ""
