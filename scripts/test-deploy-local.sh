#!/bin/bash
# =============================================================================
# Local Testing Version of safe-deploy.sh
# =============================================================================
# This is a test version for local Docker environment
# Uses current directory instead of /opt/nexus-php/
# =============================================================================

set -e

# --- Configuration (LOCAL) ---
DEPLOY_DIR="$(pwd)"
LOCK_FILE="$DEPLOY_DIR/.deploy.lock.test"
LOG_DIR="$DEPLOY_DIR/logs"
LAST_DEPLOY_FILE="$DEPLOY_DIR/.last-successful-deploy.test"
MIN_DISK_SPACE_MB=1024

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Logging functions ---
log_ok()   { echo -e "${GREEN}[OK]${NC}   $1"; }
log_info() { echo -e "${CYAN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $1"; }
log_step() { echo -e "\n${BOLD}$1${NC}"; }

# --- Helper functions ---
cleanup() {
    if [ -f "$LOCK_FILE" ]; then
        rm -f "$LOCK_FILE"
        log_info "Test lock released"
    fi
}

trap cleanup EXIT

check_lock() {
    if [ -f "$LOCK_FILE" ]; then
        LOCK_PID=$(cat "$LOCK_FILE")
        if ps -p "$LOCK_PID" > /dev/null 2>&1; then
            log_err "Another test is running (PID: $LOCK_PID)"
            exit 1
        else
            log_warn "Stale lock file found (removing)"
            rm -f "$LOCK_FILE"
        fi
    fi
}

create_lock() {
    echo $$ > "$LOCK_FILE"
    log_info "Test lock created (PID: $$)"
}

# --- Validation ---
validate_environment() {
    log_step "=== Pre-Deploy Validation (Local) ==="

    local VALIDATION_FAILED=0

    # Check disk space
    if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
        # Windows - check drive where current dir is
        AVAILABLE_MB=$(df -m . | tail -1 | awk '{print $4}')
    else
        AVAILABLE_MB=$(df -m "$DEPLOY_DIR" | tail -1 | awk '{print $4}')
    fi

    if [ "$AVAILABLE_MB" -lt "$MIN_DISK_SPACE_MB" ]; then
        log_err "Insufficient disk space: ${AVAILABLE_MB}MB (minimum: ${MIN_DISK_SPACE_MB}MB)"
        VALIDATION_FAILED=1
    else
        log_ok "Disk space: ${AVAILABLE_MB}MB available"
    fi

    # Check critical files
    if [ ! -f "$DEPLOY_DIR/.env.docker" ]; then
        log_err ".env.docker file missing"
        VALIDATION_FAILED=1
    else
        log_ok ".env.docker exists"
    fi

    if [ ! -f "$DEPLOY_DIR/compose.yml" ]; then
        log_err "compose.yml missing"
        VALIDATION_FAILED=1
    else
        log_ok "compose.yml exists"
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

    # Check database connectivity (read password from .env.docker)
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env.docker" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus_secret")
    if docker exec nexus-php-db mysqladmin ping -h localhost -unexus -p"$DB_PASS" > /dev/null 2>&1; then
        log_ok "Database connection OK (password from .env.docker)"
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
        return 1
    fi

    log_ok "All pre-deploy checks passed"
    return 0
}

# --- Smoke tests ---
run_smoke_tests() {
    log_step "=== Smoke Tests (Local) ==="

    log_info "Waiting 3 seconds for containers..."
    sleep 3

    local TESTS_FAILED=0

    # Test API health endpoint
    if curl -sf http://localhost:8090/health.php > /dev/null 2>&1; then
        log_ok "API health check passed"
    else
        log_err "API health check failed"
        TESTS_FAILED=1
    fi

    # Test API bootstrap endpoint
    if curl -sf http://localhost:8090/api/v2/tenant/bootstrap > /dev/null 2>&1; then
        log_ok "API bootstrap endpoint OK"
    else
        log_warn "API bootstrap endpoint failed (may require tenant header)"
    fi

    # Test frontend
    if curl -sf http://localhost:5173/ > /dev/null 2>&1; then
        log_ok "Frontend dev server OK (port 5173)"
    else
        log_warn "Frontend dev server not running (non-critical)"
    fi

    # Check database connectivity
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env.docker" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus_secret")
    if docker exec nexus-php-db mysqladmin ping -h localhost -unexus -p"$DB_PASS" > /dev/null 2>&1; then
        log_ok "Database still accessible"
    else
        log_err "Database connection lost"
        TESTS_FAILED=1
    fi

    # Check container health
    local UNHEALTHY=$(docker ps --filter "name=nexus-php" --format "{{.Names}}: {{.Status}}" | grep -i "unhealthy" || true)
    if [ -n "$UNHEALTHY" ]; then
        log_err "Unhealthy containers detected:"
        echo "$UNHEALTHY"
        TESTS_FAILED=1
    else
        log_ok "All containers healthy"
    fi

    if [ $TESTS_FAILED -eq 1 ]; then
        log_err "Smoke tests failed"
        return 1
    fi

    log_ok "All smoke tests passed"
    return 0
}

# --- Status ---
show_status() {
    log_step "=== Local Deployment Status ==="

    # Current commit
    CURRENT_COMMIT=$(git rev-parse HEAD)
    log_info "Current commit: ${CURRENT_COMMIT:0:8}"
    git log -1 --format='  %h - %s (%ar)'

    # Last successful deploy (test)
    if [ -f "$LAST_DEPLOY_FILE" ]; then
        LAST_COMMIT=$(cat "$LAST_DEPLOY_FILE")
        log_info "Last test deploy: ${LAST_COMMIT:0:8}"
        git log -1 --format='  %h - %s (%ar)' "$LAST_COMMIT" 2>/dev/null || log_warn "Commit not found"
    else
        log_warn "No previous test deployment recorded"
    fi

    echo ""

    # Container status
    log_info "Local container status:"
    docker ps --filter "name=nexus-php" --format "table {{.Names}}\t{{.Status}}"

    echo ""

    # Recent logs
    log_info "Recent API logs (last 5 lines):"
    docker logs nexus-php-app --tail=5 2>&1 | tail -5 || log_warn "Could not fetch logs"
}

# =============================================================================
# Main Execution
# =============================================================================

echo "============================================"
echo "  Local Deployment Test Script"
echo "  Directory: $DEPLOY_DIR"
echo "============================================"
echo ""

MODE="${1:-status}"

case "$MODE" in
    validate)
        check_lock
        create_lock
        validate_environment
        ;;

    smoke-tests)
        run_smoke_tests
        ;;

    status)
        show_status
        ;;

    full-test)
        check_lock
        create_lock
        log_step "=== Running Full Local Test ==="
        if validate_environment; then
            log_ok "Validation passed"
        else
            log_err "Validation failed - stopping"
            exit 1
        fi

        if run_smoke_tests; then
            log_ok "Smoke tests passed"
            # Save current commit as successful test
            git rev-parse HEAD > "$LAST_DEPLOY_FILE"
        else
            log_err "Smoke tests failed"
            exit 1
        fi

        echo ""
        echo "============================================"
        echo "  All Tests Passed!"
        echo "============================================"
        ;;

    *)
        log_err "Invalid mode: $MODE"
        echo "Usage: bash scripts/test-deploy-local.sh [validate|smoke-tests|status|full-test]"
        exit 1
        ;;
esac
