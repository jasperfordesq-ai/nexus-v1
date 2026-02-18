#!/bin/bash
# =============================================================================
# Project NEXUS - Safe Production Deploy Script (Enhanced)
# =============================================================================
# Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status]
#
# Modes:
#   quick     - Git pull + restart (OPCache clear) - DEFAULT
#   full      - Git pull + rebuild (--no-cache)
#   rollback  - Rollback to last successful deploy
#   status    - Show current deployment status (no changes)
#
# Features:
#   - Rollback capability (saves last successful commit)
#   - Pre-deploy validation (disk space, files, containers)
#   - Post-deploy smoke tests (API, frontend, database)
#   - Deployment locking (prevents concurrent deploys)
#   - Comprehensive logging (timestamped logs)
# =============================================================================

set -e

# --- Configuration ---
DEPLOY_DIR="/opt/nexus-php"
LOCK_FILE="$DEPLOY_DIR/.deploy.lock"
LOG_DIR="$DEPLOY_DIR/logs"
LAST_DEPLOY_FILE="$DEPLOY_DIR/.last-successful-deploy"
MIN_DISK_SPACE_MB=1024  # 1GB minimum free space

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Logging functions ---
log_ok()   { echo -e "${GREEN}[OK]${NC}   $1" | tee -a "$LOG_FILE"; }
log_info() { echo -e "${CYAN}[INFO]${NC} $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $1" | tee -a "$LOG_FILE"; }
log_step() { echo -e "\n${BOLD}$1${NC}" | tee -a "$LOG_FILE"; }

# --- Helper functions ---
cleanup() {
    if [ -f "$LOCK_FILE" ]; then
        rm -f "$LOCK_FILE"
        log_info "Deployment lock released"
    fi
}

trap cleanup EXIT

check_lock() {
    if [ -f "$LOCK_FILE" ]; then
        LOCK_PID=$(cat "$LOCK_FILE")
        if ps -p "$LOCK_PID" > /dev/null 2>&1; then
            log_err "Another deployment is running (PID: $LOCK_PID)"
            exit 1
        else
            log_warn "Stale lock file found (removing)"
            rm -f "$LOCK_FILE"
        fi
    fi
}

create_lock() {
    echo $$ > "$LOCK_FILE"
    log_info "Deployment lock created (PID: $$)"
}

save_current_commit() {
    CURRENT_COMMIT=$(git rev-parse HEAD)
    echo "$CURRENT_COMMIT" > "$LAST_DEPLOY_FILE"
    log_info "Saved current commit: ${CURRENT_COMMIT:0:8}"
}

get_last_successful_commit() {
    if [ -f "$LAST_DEPLOY_FILE" ]; then
        cat "$LAST_DEPLOY_FILE"
    else
        echo ""
    fi
}

# --- Pre-deploy validation ---
validate_environment() {
    log_step "=== Pre-Deploy Validation ==="

    local VALIDATION_FAILED=0

    # Check disk space
    AVAILABLE_MB=$(df -m "$DEPLOY_DIR" | tail -1 | awk '{print $4}')
    if [ "$AVAILABLE_MB" -lt "$MIN_DISK_SPACE_MB" ]; then
        log_err "Insufficient disk space: ${AVAILABLE_MB}MB (minimum: ${MIN_DISK_SPACE_MB}MB)"
        VALIDATION_FAILED=1
    else
        log_ok "Disk space: ${AVAILABLE_MB}MB available"
    fi

    # Check critical files
    if [ ! -f "$DEPLOY_DIR/.env" ]; then
        log_err ".env file missing"
        VALIDATION_FAILED=1
    else
        log_ok ".env exists"
    fi

    if [ ! -f "$DEPLOY_DIR/compose.prod.yml" ]; then
        log_err "compose.prod.yml missing"
        VALIDATION_FAILED=1
    else
        log_ok "compose.prod.yml exists"
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

    # Check database connectivity (read password from .env)
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus_secret")
    if docker exec nexus-php-db mysqladmin ping -h localhost -unexus -p"$DB_PASS" > /dev/null 2>&1; then
        log_ok "Database connection OK"
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
        exit 1
    fi

    log_ok "All pre-deploy checks passed"
}

# --- Post-deploy smoke tests ---
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

    # Test API bootstrap endpoint
    if curl -sf http://127.0.0.1:8090/api/v2/tenant/bootstrap > /dev/null 2>&1; then
        log_ok "API bootstrap endpoint OK"
    else
        log_warn "API bootstrap endpoint failed (may require tenant header)"
    fi

    # Test frontend
    if curl -sf http://127.0.0.1:3000/ > /dev/null 2>&1; then
        log_ok "Frontend health check passed"
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
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus_secret")
    if docker exec nexus-php-db mysqladmin ping -h localhost -unexus -p"$DB_PASS" > /dev/null 2>&1; then
        log_ok "Database still accessible"
    else
        log_err "Database connection lost"
        TESTS_FAILED=1
    fi

    # Check container health
    local UNHEALTHY=$(docker ps --filter "name=nexus" --format "{{.Names}}: {{.Status}}" | grep -i "unhealthy" || true)
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

# --- Deployment modes ---
deploy_quick() {
    log_step "=== Quick Deployment (Git Pull + Restart) ==="

    # Save current state
    save_current_commit

    # Git pull
    log_info "Fetching latest from GitHub..."
    git fetch origin main
    log_info "Resetting to origin/main..."
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)
    log_info "Now at: ${NEW_COMMIT:0:8} - $(git log -1 --format='%s')"

    # Restore production compose.yml
    log_info "Restoring compose.yml from compose.prod.yml..."
    cp compose.prod.yml compose.yml
    log_ok "compose.yml restored (production version)"

    # Restart PHP container (OPCache clear)
    log_info "Restarting PHP container (OPCache clear)..."
    docker restart nexus-php-app
    log_ok "PHP container restarted"
}

deploy_full() {
    log_step "=== Full Deployment (Git Pull + Rebuild) ==="

    # Save current state
    save_current_commit

    # Git pull
    log_info "Fetching latest from GitHub..."
    git fetch origin main
    log_info "Resetting to origin/main..."
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)
    log_info "Now at: ${NEW_COMMIT:0:8} - $(git log -1 --format='%s')"

    # Restore production compose.yml
    log_info "Restoring compose.yml from compose.prod.yml..."
    cp compose.prod.yml compose.yml
    log_ok "compose.yml restored (production version)"

    # Rebuild containers
    log_info "Building containers with --no-cache..."
    docker compose build --no-cache

    log_info "Starting containers..."
    docker compose up -d

    log_ok "Full rebuild complete"
}

rollback_deployment() {
    log_step "=== Rollback to Last Successful Deploy ==="

    LAST_COMMIT=$(get_last_successful_commit)

    if [ -z "$LAST_COMMIT" ]; then
        log_err "No previous successful deployment found"
        exit 1
    fi

    CURRENT_COMMIT=$(git rev-parse HEAD)

    if [ "$CURRENT_COMMIT" = "$LAST_COMMIT" ]; then
        log_warn "Already at last successful commit: ${LAST_COMMIT:0:8}"
        exit 0
    fi

    log_info "Current commit: ${CURRENT_COMMIT:0:8}"
    log_info "Rolling back to: ${LAST_COMMIT:0:8}"

    # Checkout last successful commit
    git checkout "$LAST_COMMIT"

    # Restore production compose.yml
    cp compose.prod.yml compose.yml

    # Restart containers
    log_info "Restarting containers..."
    docker restart nexus-php-app

    log_ok "Rollback complete"
    log_info "Now at: $(git log -1 --format='%h - %s')"
}

show_status() {
    log_step "=== Deployment Status ==="

    # Current commit
    CURRENT_COMMIT=$(git rev-parse HEAD)
    log_info "Current commit: ${CURRENT_COMMIT:0:8}"
    git log -1 --format='  %h - %s (%ar)' | tee -a "$LOG_FILE"

    # Last successful deploy
    LAST_COMMIT=$(get_last_successful_commit)
    if [ -n "$LAST_COMMIT" ]; then
        log_info "Last successful: ${LAST_COMMIT:0:8}"
        git log -1 --format='  %h - %s (%ar)' "$LAST_COMMIT" | tee -a "$LOG_FILE"
    else
        log_warn "No previous successful deployment recorded"
    fi

    echo "" | tee -a "$LOG_FILE"

    # Container status
    log_info "Container status:"
    docker ps --filter "name=nexus" --format "table {{.Names}}\t{{.Status}}" | tee -a "$LOG_FILE"

    echo "" | tee -a "$LOG_FILE"

    # Recent logs (last 5 lines)
    log_info "Recent API logs:"
    docker compose logs --tail=5 app 2>/dev/null | tee -a "$LOG_FILE" || log_warn "Could not fetch logs"
}

# =============================================================================
# Main Execution
# =============================================================================

cd "$DEPLOY_DIR" || {
    echo "ERROR: Cannot cd to $DEPLOY_DIR"
    exit 1
}

# Create log directory
mkdir -p "$LOG_DIR"

# Create timestamped log file
TIMESTAMP=$(date +%Y-%m-%d_%H-%M-%S)
LOG_FILE="$LOG_DIR/deploy-$TIMESTAMP.log"

echo "============================================" | tee "$LOG_FILE"
echo "  Project NEXUS - Safe Production Deploy"    | tee -a "$LOG_FILE"
echo "  Started: $(date '+%Y-%m-%d %H:%M:%S')"      | tee -a "$LOG_FILE"
echo "============================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Parse mode
MODE="${1:-quick}"

# Handle status mode (no lock needed)
if [ "$MODE" = "status" ]; then
    show_status
    exit 0
fi

# Check for deployment lock
check_lock

# Create deployment lock
create_lock

# Run pre-deploy validation (except for rollback)
if [ "$MODE" != "rollback" ]; then
    validate_environment
fi

# Execute deployment based on mode
case "$MODE" in
    quick)
        deploy_quick
        ;;
    full)
        deploy_full
        ;;
    rollback)
        rollback_deployment
        ;;
    *)
        log_err "Invalid mode: $MODE"
        log_info "Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status]"
        exit 1
        ;;
esac

# Run smoke tests
if run_smoke_tests; then
    # Update last successful deployment
    NEW_COMMIT=$(git rev-parse HEAD)
    echo "$NEW_COMMIT" > "$LAST_DEPLOY_FILE"

    echo "" | tee -a "$LOG_FILE"
    echo "============================================" | tee -a "$LOG_FILE"
    echo "  Deployment Successful!"                     | tee -a "$LOG_FILE"
    echo "============================================" | tee -a "$LOG_FILE"
    git log -1 --format='%h - %s (%ar)' | tee -a "$LOG_FILE"
    echo "" | tee -a "$LOG_FILE"
    log_info "Log saved to: $LOG_FILE"
else
    echo "" | tee -a "$LOG_FILE"
    log_err "Deployment completed but smoke tests failed"
    log_warn "Consider running: sudo bash scripts/safe-deploy.sh rollback"
    log_info "Log saved to: $LOG_FILE"
    exit 1
fi
