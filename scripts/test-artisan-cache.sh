#!/bin/bash
# =============================================================================
# Project NEXUS - Artisan Cache Fail-Fast Validator (TD15)
# =============================================================================
# Purpose: Catch config:cache / route:cache / event:cache failures BEFORE
# they reach production. These commands run at container startup in
# Dockerfile.prod and are fail-fast — if any one fails, the container
# crash-loops and the site stays in maintenance mode.
#
# This script runs the three cache commands against the CURRENT local dev
# container and cleans up afterwards so developer state is untouched.
#
# Usage:
#   bash scripts/test-artisan-cache.sh
#
# Exit codes:
#   0 = all three cache commands succeeded
#   1 = one or more cache commands failed (fix before deploying)
#   2 = dev container not running (cannot test)
#
# Called by:
#   - .husky/pre-push (if hooks are ever re-enabled)
#   - .github/workflows/ci.yml (PHP job)
#   - Manual pre-deploy validation
# =============================================================================

set -u

CONTAINER="${CONTAINER:-nexus-php-app}"

if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
    CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; CYAN=''; BOLD=''; NC=''
fi

log_ok()   { echo -e "${GREEN}[PASS]${NC} $1"; }
log_info() { echo -e "${CYAN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $1"; }

echo -e "${BOLD}Project NEXUS - Artisan Cache Fail-Fast Test${NC}"
echo "Container: $CONTAINER"
echo "============================================================"

# Detect `docker` availability (Windows/WSL quirks — see MEMORY.md)
DOCKER_CMD="docker"
if ! $DOCKER_CMD ps >/dev/null 2>&1; then
    if command -v powershell.exe >/dev/null 2>&1; then
        log_info "Falling back to powershell.exe docker (MEMORY.md workaround)"
        DOCKER_CMD="powershell.exe -Command docker"
    else
        log_err "docker CLI unavailable"
        exit 2
    fi
fi

if ! $DOCKER_CMD ps --format '{{.Names}}' 2>/dev/null | grep -q "^${CONTAINER}$"; then
    log_err "Container '$CONTAINER' is not running. Start with: docker compose up -d"
    exit 2
fi

FAIL=0

run_cache_cmd() {
    local cmd="$1"
    local label="$2"
    log_info "Running: php artisan $cmd"
    if OUT=$($DOCKER_CMD exec "$CONTAINER" php artisan "$cmd" 2>&1); then
        log_ok "$label"
    else
        log_err "$label failed:"
        echo "$OUT" | sed 's/^/    /'
        FAIL=$((FAIL + 1))
    fi
}

# Build cache — fail-fast
run_cache_cmd "config:cache" "config:cache"
run_cache_cmd "route:cache"  "route:cache"
run_cache_cmd "event:cache"  "event:cache"

# Always clean up so dev workflow is not affected by stale caches
echo ""
log_info "Cleaning up (restoring dev-friendly cleared state)"
$DOCKER_CMD exec "$CONTAINER" php artisan config:clear >/dev/null 2>&1 || true
$DOCKER_CMD exec "$CONTAINER" php artisan route:clear  >/dev/null 2>&1 || true
$DOCKER_CMD exec "$CONTAINER" php artisan event:clear  >/dev/null 2>&1 || true
log_ok "Caches cleared"

echo "============================================================"
if [ $FAIL -gt 0 ]; then
    log_err "$FAIL artisan cache command(s) failed"
    echo ""
    echo "In production, this would crash-loop the container and leave the"
    echo "site in maintenance mode. Fix before deploying:"
    echo ""
    echo "  1. Inspect config files under config/ for env() calls missing from .env"
    echo "  2. Run:  bash scripts/validate-env.php  (validate required env keys)"
    echo "  3. If environment is correct, check for syntax errors in routes/ or"
    echo "     config/ — these are commonly the cause of cache failures."
    echo ""
    echo "  Alternatively, consider moving caching to a runtime entrypoint"
    echo "  (see docker/entrypoint-cache.sh) to convert fail-fast into a warning."
    exit 1
fi

log_ok "All artisan cache commands succeeded — safe to deploy"
exit 0
