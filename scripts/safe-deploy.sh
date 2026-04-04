#!/bin/bash
# =============================================================================
# Project NEXUS - Safe Production Deploy Script (Enhanced)
# =============================================================================
# Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status] [--migrate]
#
# Modes:
#   quick     - Git pull + rebuild frontend + restart all (DEFAULT)
#   full      - Git pull + rebuild ALL containers (--no-cache)
#   rollback  - Rollback to last successful deploy (full rebuild)
#   status    - Show current deployment status (no changes)
#
# Flags:
#   --migrate - Also run `php artisan migrate --force` (Laravel migrations)
#
# Features:
#   - Rollback capability (saves last successful commit)
#   - Pre-deploy validation (disk space, files, containers)
#   - Post-deploy smoke tests (API, frontend, database)
#   - Post-deploy image verification (ensures prod images, not dev)
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

# --- Maintenance Mode ---
MAINTENANCE_FILE="/var/www/html/.maintenance"
PHP_CONTAINER="nexus-php-app"

# --- SSH Keepalive for Docker Builds ---
# Azure NAT gateway kills idle TCP connections after ~4 minutes.
# During --no-cache builds, C compilation of PHP extensions produces no stdout
# for minutes at a time, causing SSH disconnects. This wrapper prints a dot
# every 30s to keep the connection alive.
KEEPALIVE_PID=""
start_keepalive() {
    ( while true; do echo -n "." | tee -a "$LOG_FILE"; sleep 30; done ) &
    KEEPALIVE_PID=$!
}
stop_keepalive() {
    if [ -n "$KEEPALIVE_PID" ] && kill -0 "$KEEPALIVE_PID" 2>/dev/null; then
        kill "$KEEPALIVE_PID" 2>/dev/null
        wait "$KEEPALIVE_PID" 2>/dev/null || true
        KEEPALIVE_PID=""
        echo ""  # newline after dots
    fi
}
# Run a docker build with SSH keepalive active
build_with_keepalive() {
    start_keepalive
    local rc=0
    "$@" || rc=$?
    stop_keepalive
    return $rc
}

enable_maintenance_mode() {
    log_step "=== Enabling Maintenance Mode (both layers) ==="

    if docker ps --filter "name=$PHP_CONTAINER" --format "{{.Names}}" | grep -q "$PHP_CONTAINER"; then
        # Layer 1: File-based gate
        docker exec "$PHP_CONTAINER" touch "$MAINTENANCE_FILE"

        if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
            log_ok "Layer 1: .maintenance file created"
        else
            log_err "Layer 1: Failed to create .maintenance file"
            exit 1
        fi

        # Layer 2: Database — set all tenants to maintenance mode
        _deploy_db_maintenance_set "true"

        # Layer 3: Flush Redis bootstrap cache so frontend sees DB change immediately
        _deploy_flush_bootstrap_cache

        # Verify HTTP 503
        sleep 1
        local HTTP_CODE
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/api/v2/tenants 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "503" ]; then
            log_ok "Verified: API returning HTTP 503"
        else
            log_warn "API returned HTTP $HTTP_CODE (may not have fully activated yet)"
        fi
    else
        log_warn "$PHP_CONTAINER not running — skipping maintenance mode (will be set after rebuild)"
        MAINTENANCE_DEFERRED=1
    fi
}

disable_maintenance_mode() {
    log_step "=== Disabling Maintenance Mode (both layers) ==="

    if docker ps --filter "name=$PHP_CONTAINER" --format "{{.Names}}" | grep -q "$PHP_CONTAINER"; then
        # Layer 1: Remove file
        docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"

        if docker exec "$PHP_CONTAINER" test -f "$MAINTENANCE_FILE" 2>/dev/null; then
            log_err ".maintenance file still exists — trying again"
            docker exec "$PHP_CONTAINER" rm -f "$MAINTENANCE_FILE"
        fi

        log_ok "Layer 1: .maintenance file removed"

        # Layer 2: Database — clear maintenance mode for all tenants
        _deploy_db_maintenance_set "false"

        # Layer 3: Flush Redis bootstrap cache so frontend sees DB change immediately
        _deploy_flush_bootstrap_cache

        # Verify HTTP 200
        sleep 1
        local HTTP_CODE
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/api/v2/tenants 2>/dev/null || echo "000")
        if [ "$HTTP_CODE" = "200" ]; then
            log_ok "Verified: API returning HTTP 200"
        else
            log_warn "API returned HTTP $HTTP_CODE — may need OPCache clear"
        fi
    else
        log_warn "$PHP_CONTAINER not running — cannot disable maintenance mode"
    fi
}

# Helper: set database maintenance_mode for all tenants during deploy
_deploy_db_maintenance_set() {
    local value="$1"
    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep "^DB_USER=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep "^DB_NAME=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    # Fallback: try DB_PASSWORD=
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "")
    fi

    if [ -z "$DB_PASS" ]; then
        log_warn "Layer 2: No DB password — skipping database maintenance toggle"
        return
    fi

    if docker ps --filter "name=nexus-php-db" --format "{{.Names}}" | grep -q "nexus-php-db"; then
        docker exec nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
            "UPDATE tenant_settings SET setting_value = '$value' WHERE setting_key = 'general.maintenance_mode';" 2>/dev/null \
            && log_ok "Layer 2: Database maintenance_mode = '$value'" \
            || log_warn "Layer 2: Database update failed"
    else
        log_warn "Layer 2: nexus-php-db not running — skipping database maintenance toggle"
    fi
}

# Helper: flush Redis bootstrap cache so frontend sees maintenance changes immediately
_deploy_flush_bootstrap_cache() {
    if docker ps --filter "name=nexus-php-redis" --format "{{.Names}}" | grep -q "nexus-php-redis"; then
        local prefix
        prefix=$(grep "^CACHE_PREFIX=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus_laravel")
        local keys
        keys=$(docker exec nexus-php-redis redis-cli --no-auth-warning KEYS "${prefix}:*tenant_bootstrap*" 2>/dev/null || echo "")
        if [ -n "$keys" ]; then
            for key in $keys; do
                docker exec nexus-php-redis redis-cli --no-auth-warning DEL "$key" > /dev/null 2>&1
            done
        fi
        # Also flush tenant_settings cache
        keys=$(docker exec nexus-php-redis redis-cli --no-auth-warning KEYS "${prefix}:*tenant_settings*" 2>/dev/null || echo "")
        if [ -n "$keys" ]; then
            for key in $keys; do
                docker exec nexus-php-redis redis-cli --no-auth-warning DEL "$key" > /dev/null 2>&1
            done
        fi
        log_ok "Layer 3: Redis bootstrap/settings cache flushed"
    else
        log_warn "Layer 3: nexus-php-redis not running — skipping cache flush"
    fi
}

# After container rebuild/restart, re-enable maintenance if it was deferred
# or ensure it persists (container recreate wipes the file)
re_enable_maintenance_after_rebuild() {
    if docker ps --filter "name=$PHP_CONTAINER" --format "{{.Names}}" | grep -q "$PHP_CONTAINER"; then
        docker exec "$PHP_CONTAINER" touch "$MAINTENANCE_FILE" 2>/dev/null || true
        log_ok "Maintenance mode re-confirmed after container rebuild"
    fi
}

# --- Helper functions ---
cleanup() {
    stop_keepalive  # Kill any lingering keepalive background process
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

# --- Fix #4: Protect compose.yml from git overwrites ---
# Marks compose.yml as skip-worktree so 'git reset --hard' never
# replaces it with the dev version from the repository.
protect_compose_yml() {
    if git ls-files --error-unmatch compose.yml > /dev/null 2>&1; then
        git update-index --skip-worktree compose.yml 2>/dev/null && \
            log_ok "compose.yml protected from git overwrites (skip-worktree)" || \
            log_warn "Could not set skip-worktree on compose.yml"
    else
        log_info "compose.yml is not tracked by git — no protection needed"
    fi
}

# Temporarily remove skip-worktree, reset the file to tracked HEAD state,
# then re-apply skip-worktree. This allows 'git reset --hard origin/main'
# to succeed even when compose.yml differs from the repo version.
pre_reset_compose_yml() {
    if git ls-files --error-unmatch compose.yml > /dev/null 2>&1; then
        git update-index --no-skip-worktree compose.yml 2>/dev/null || true
        git checkout HEAD -- compose.yml 2>/dev/null || true
        git update-index --skip-worktree compose.yml 2>/dev/null || true
    fi
}

# --- Fix #4 (Prevention): Validate Dockerfiles before build ---
# Confirms dev and prod Dockerfiles have the expected base images,
# catching accidental file swaps before they reach the container layer.
validate_dockerfiles() {
    log_step "=== Dockerfile Sanity Check ==="

    local FAILED=0

    if [ -f "react-frontend/Dockerfile.prod" ]; then
        if grep -q "FROM nginx:alpine" react-frontend/Dockerfile.prod; then
            log_ok "Dockerfile.prod: nginx base image confirmed (production)"
        else
            log_err "Dockerfile.prod: nginx base image NOT found — wrong Dockerfile?"
            FAILED=1
        fi
    else
        log_warn "react-frontend/Dockerfile.prod not found — skipping check"
    fi

    if [ -f "react-frontend/Dockerfile" ]; then
        if grep -q "FROM node:" react-frontend/Dockerfile; then
            log_ok "Dockerfile (dev): node base image confirmed"
        else
            log_warn "react-frontend/Dockerfile: node base image not found (unexpected)"
        fi
    fi

    if [ $FAILED -eq 1 ]; then
        log_err "Dockerfile sanity check failed — aborting deploy"
        exit 1
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
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
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

# --- Post-deploy image verification ---
verify_production_images() {
    log_step "=== Production Image Verification ==="

    local VERIFY_FAILED=0

    # Verify React frontend is running nginx (production), not node (dev)
    if docker exec nexus-react-prod which nginx > /dev/null 2>&1; then
        log_ok "Frontend: nginx detected (production image)"
    elif docker exec nexus-react-prod which node > /dev/null 2>&1; then
        log_err "Frontend: node detected — THIS IS A DEV IMAGE ON PRODUCTION!"
        log_err "Run 'sudo bash scripts/safe-deploy.sh full' to fix"
        VERIFY_FAILED=1
    else
        log_warn "Frontend: could not verify image type (container may not be running)"
    fi

    # Verify React container image name
    local REACT_IMAGE
    REACT_IMAGE=$(docker inspect nexus-react-prod --format '{{.Config.Image}}' 2>/dev/null || echo "unknown")
    if [[ "$REACT_IMAGE" == "nexus-react-prod:latest" ]]; then
        log_ok "Frontend image: $REACT_IMAGE (correct)"
    elif [[ "$REACT_IMAGE" == *"dev"* ]] || [[ "$REACT_IMAGE" == "staging_frontend"* ]]; then
        log_err "Frontend image: $REACT_IMAGE — WRONG IMAGE (dev/legacy name)"
        VERIFY_FAILED=1
    else
        log_warn "Frontend image: $REACT_IMAGE (unexpected name)"
    fi

    # Verify PHP container uses production Dockerfile (OPCache validate_timestamps=0)
    local OPCACHE_VALIDATE
    OPCACHE_VALIDATE=$(docker exec nexus-php-app php -r 'echo ini_get("opcache.validate_timestamps");' 2>/dev/null || echo "unknown")
    if [[ "$OPCACHE_VALIDATE" == "0" ]] || [[ "$OPCACHE_VALIDATE" == "" ]]; then
        log_ok "PHP OPCache: validate_timestamps=0 (production)"
    elif [[ "$OPCACHE_VALIDATE" == "1" ]]; then
        log_err "PHP OPCache: validate_timestamps=1 — THIS IS A DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP OPCache: validate_timestamps=$OPCACHE_VALIDATE (unexpected)"
    fi

    # Verify PHP display_errors is off (production)
    local DISPLAY_ERRORS
    DISPLAY_ERRORS=$(docker exec nexus-php-app php -r 'echo ini_get("display_errors");' 2>/dev/null || echo "unknown")
    if [[ "$DISPLAY_ERRORS" == "" ]] || [[ "$DISPLAY_ERRORS" == "0" ]] || [[ "$DISPLAY_ERRORS" == "Off" ]]; then
        log_ok "PHP display_errors: Off (production)"
    elif [[ "$DISPLAY_ERRORS" == "1" ]] || [[ "$DISPLAY_ERRORS" == "On" ]]; then
        log_err "PHP display_errors: On — THIS IS A DEV IMAGE ON PRODUCTION!"
        VERIFY_FAILED=1
    else
        log_warn "PHP display_errors: $DISPLAY_ERRORS (unexpected)"
    fi

    # Verify build commit is baked into the React image (if BUILD_COMMIT was set)
    if [ -n "${BUILD_COMMIT:-}" ]; then
        local REACT_COMMIT
        REACT_COMMIT=$(docker exec nexus-react-prod sh -c 'cat /app/dist/.build-commit 2>/dev/null || echo "none"')
        if [[ "$REACT_COMMIT" == "$BUILD_COMMIT" ]]; then
            log_ok "React build commit: $REACT_COMMIT (matches)"
        else
            log_warn "React build commit: '$REACT_COMMIT' (expected: '$BUILD_COMMIT')"
        fi
    fi

    if [ $VERIFY_FAILED -eq 1 ]; then
        log_err "Image verification FAILED — dev images detected on production!"
        log_err "Run 'sudo bash scripts/safe-deploy.sh full' to rebuild with production images"
        return 1
    fi

    log_ok "All production images verified"
    return 0
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
    DB_PASS=$(grep "^DB_PASSWORD=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    if docker exec nexus-php-db mysqladmin ping -h localhost -unexus -p"$DB_PASS" > /dev/null 2>&1; then
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

# --- Docker image prune ---
prune_docker_images() {
    log_step "=== Docker Image Cleanup ==="
    local RECLAIMED
    RECLAIMED=$(docker image prune -f 2>&1 | grep 'Total reclaimed' || echo 'Total reclaimed space: 0B')
    log_ok "Dangling images removed -- $RECLAIMED"
}

# --- Cloudflare cache purge ---
purge_cloudflare_cache() {
    log_step "=== Cloudflare Cache Purge (All Domains) ==="

    if [ -f "$DEPLOY_DIR/scripts/purge-cloudflare-cache.sh" ]; then
        bash "$DEPLOY_DIR/scripts/purge-cloudflare-cache.sh" 2>&1 | tee -a "$LOG_FILE"
        if [ ${PIPESTATUS[0]} -eq 0 ]; then
            log_ok "Cloudflare cache purged for all domains"
        else
            log_warn "Some Cloudflare cache purges failed (non-blocking)"
        fi
    else
        log_warn "purge-cloudflare-cache.sh not found — skipping cache purge"
    fi
}

# --- Automated database migrations ---
run_pending_migrations() {
    log_step "=== Database Migrations ==="

    local MIGRATION_DIR="$DEPLOY_DIR/migrations"

    if [ ! -d "$MIGRATION_DIR" ]; then
        log_warn "migrations/ directory not found — skipping"
        return 0
    fi

    # Read DB credentials from .env
    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep "^DB_USER=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep "^DB_NAME=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")

    # Ensure the migrations tracking table exists
    docker exec nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            backups VARCHAR(255) DEFAULT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    " 2>/dev/null

    # Get list of already-applied migrations
    local APPLIED
    APPLIED=$(docker exec nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -N -e "SELECT migration_name FROM migrations WHERE migration_name IS NOT NULL;" 2>/dev/null || echo "")

    # Find pending .sql files (sorted alphabetically)
    local PENDING_COUNT=0
    local PENDING_FILES=()

    for SQL_FILE in "$MIGRATION_DIR"/*.sql; do
        [ -f "$SQL_FILE" ] || continue
        local BASENAME
        BASENAME=$(basename "$SQL_FILE")
        if ! echo "$APPLIED" | grep -qxF "$BASENAME"; then
            PENDING_FILES+=("$SQL_FILE")
            PENDING_COUNT=$((PENDING_COUNT + 1))
        fi
    done

    if [ $PENDING_COUNT -eq 0 ]; then
        log_ok "All migrations are up to date"
        return 0
    fi

    log_info "$PENDING_COUNT pending migration(s) to run:"
    for F in "${PENDING_FILES[@]}"; do
        echo "  • $(basename "$F")" | tee -a "$LOG_FILE"
    done
    echo "" | tee -a "$LOG_FILE"

    # Execute each pending migration
    local RAN=0
    local FAILED=0

    for SQL_FILE in "${PENDING_FILES[@]}"; do
        local BASENAME
        BASENAME=$(basename "$SQL_FILE")

        # Scan for dangerous operations (log only, don't block in automated deploy)
        if grep -qiE '(DROP TABLE|DROP DATABASE|TRUNCATE)' "$SQL_FILE" 2>/dev/null; then
            log_warn "$BASENAME contains DROP/TRUNCATE operations"
        fi

        log_info "Running: $BASENAME"
        if docker exec -i nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE" 2>&1 | tee -a "$LOG_FILE"; then
            # Record as applied
            docker exec nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
                INSERT IGNORE INTO migrations (migration_name, backups, executed_at)
                VALUES ('$BASENAME', '$BASENAME', NOW());
            " 2>/dev/null
            log_ok "Applied: $BASENAME"
            RAN=$((RAN + 1))
        else
            log_err "FAILED: $BASENAME"
            FAILED=1
            break
        fi
    done

    echo "" | tee -a "$LOG_FILE"
    if [ $FAILED -eq 1 ]; then
        log_err "Migration failed — deploy halted. $RAN migration(s) applied before failure."
        log_err "Fix the failed migration and re-run the deploy."
        return 1
    fi

    log_ok "$RAN migration(s) applied successfully"
    return 0
}

# --- Laravel artisan cache optimization ---
# Clears and rebuilds config/route/event caches inside the PHP container.
# Safe to call even if artisan does not exist yet (guards with -f check).
run_laravel_cache() {
    log_step "=== Laravel Cache Optimization ==="

    if ! docker exec nexus-php-app test -f /var/www/html/artisan 2>/dev/null; then
        log_info "artisan not found — skipping Laravel cache steps"
        return 0
    fi

    # Clear stale caches first
    log_info "Clearing Laravel caches..."
    docker exec nexus-php-app php /var/www/html/artisan config:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec nexus-php-app php /var/www/html/artisan route:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec nexus-php-app php /var/www/html/artisan event:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec nexus-php-app php /var/www/html/artisan view:clear 2>&1 | tee -a "$LOG_FILE" || true

    # Rebuild caches for production
    log_info "Rebuilding Laravel caches..."
    docker exec nexus-php-app php /var/www/html/artisan config:cache 2>&1 | tee -a "$LOG_FILE" || true
    docker exec nexus-php-app php /var/www/html/artisan route:cache 2>&1 | tee -a "$LOG_FILE" || true
    docker exec nexus-php-app php /var/www/html/artisan event:cache 2>&1 | tee -a "$LOG_FILE" || true

    # Ensure storage:link exists
    docker exec nexus-php-app php /var/www/html/artisan storage:link 2>&1 | tee -a "$LOG_FILE" || true

    log_ok "Laravel caches rebuilt"
}

# --- Laravel artisan migrate (optional, behind --migrate flag) ---
# Runs `php artisan migrate --force` inside the PHP container.
# Only called when LARAVEL_MIGRATE=1 is set or --migrate flag is passed.
run_laravel_artisan_migrate() {
    log_step "=== Laravel Artisan Migrations ==="

    if ! docker exec nexus-php-app test -f /var/www/html/artisan 2>/dev/null; then
        log_info "artisan not found — skipping Laravel migrations"
        return 0
    fi

    log_info "Running php artisan migrate --force..."
    if docker exec nexus-php-app php /var/www/html/artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
        log_ok "Laravel artisan migrations completed"
    else
        log_err "Laravel artisan migrate failed"
        return 1
    fi
}

# --- Deployment modes ---
deploy_quick() {
    log_step "=== Quick Deployment (Git Pull + Rebuild Frontend + Restart) ==="

    # Fix #4: Ensure compose.yml is permanently protected from git overwrites
    protect_compose_yml

    # Save current state
    save_current_commit

    # Git pull — must clear skip-worktree before reset or it fails
    log_info "Fetching latest from GitHub..."
    git fetch origin main
    pre_reset_compose_yml
    log_info "Resetting to origin/main..."
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)
    log_info "Now at: ${NEW_COMMIT:0:8} - $(git log -1 --format='%s')"

    # Always restore production compose.yml (belt-and-suspenders after git reset)
    log_info "Restoring compose.yml from compose.prod.yml..."
    cp compose.prod.yml compose.yml
    log_ok "compose.yml restored (production version)"

    # Fix #4 (Prevention): Validate Dockerfiles before building
    validate_dockerfiles

    # Export commit hash so compose.prod.yml can pass it as a build arg
    export BUILD_COMMIT=$(git rev-parse --short HEAD)
    log_info "Build commit: $BUILD_COMMIT"

    # Rebuild React frontend with --no-cache (CRITICAL: without this, old image keeps running)
    log_info "Rebuilding React frontend with --no-cache..."
    build_with_keepalive docker compose build --no-cache frontend
    log_ok "React frontend rebuilt"

    # Run pending database migrations (before container rebuild)
    if ! run_pending_migrations; then
        log_err "Migration failure — aborting deploy"
        exit 1
    fi

    # Rebuild sales site
    log_info "Rebuilding sales site..."
    build_with_keepalive docker compose build --no-cache sales
    log_ok "Sales site rebuilt"

    # Recreate frontend + sales containers with new images, restart PHP for OPCache
    log_info "Starting updated containers..."
    docker compose up -d --force-recreate frontend sales
    log_info "Restarting PHP container (OPCache clear)..."
    docker restart nexus-php-app
    log_ok "All containers updated"

    # Re-enable maintenance mode (PHP container restart wipes the file)
    re_enable_maintenance_after_rebuild

    # Laravel cache optimization
    run_laravel_cache
}

deploy_full() {
    log_step "=== Full Deployment (Git Pull + Rebuild) ==="

    # Fix #4: Ensure compose.yml is permanently protected from git overwrites
    protect_compose_yml

    # Save current state
    save_current_commit

    # Git pull — must clear skip-worktree before reset or it fails
    log_info "Fetching latest from GitHub..."
    git fetch origin main
    pre_reset_compose_yml
    log_info "Resetting to origin/main..."
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)
    log_info "Now at: ${NEW_COMMIT:0:8} - $(git log -1 --format='%s')"

    # Always restore production compose.yml (belt-and-suspenders after git reset)
    log_info "Restoring compose.yml from compose.prod.yml..."
    cp compose.prod.yml compose.yml
    log_ok "compose.yml restored (production version)"

    # Fix #4 (Prevention): Validate Dockerfiles before building
    validate_dockerfiles

    # Export commit hash so compose.prod.yml can pass it as a build arg
    export BUILD_COMMIT=$(git rev-parse --short HEAD)
    log_info "Build commit: $BUILD_COMMIT"

    # Run pending database migrations (before container rebuild)
    if ! run_pending_migrations; then
        log_err "Migration failure — aborting deploy"
        exit 1
    fi

    # Rebuild containers
    log_info "Building containers with --no-cache..."
    build_with_keepalive docker compose build --no-cache

    log_info "Starting containers (--force-recreate)..."
    docker compose up -d --force-recreate

    log_ok "Full rebuild complete"

    # Re-enable maintenance mode (container recreate wipes the file)
    re_enable_maintenance_after_rebuild

    # Laravel cache optimization
    run_laravel_cache
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

    # Fix #4: Ensure compose.yml is protected before checkout
    protect_compose_yml

    # Checkout last successful commit
    git checkout "$LAST_COMMIT"

    # Always restore production compose.yml (belt-and-suspenders after git checkout)
    cp compose.prod.yml compose.yml

    # Fix #4 (Prevention): Validate Dockerfiles before building
    validate_dockerfiles

    # Export commit hash for build arg
    export BUILD_COMMIT=$(git rev-parse --short HEAD)
    log_info "Build commit: $BUILD_COMMIT"

    # Full rebuild all containers (rollback must guarantee correct images)
    log_info "Rebuilding ALL containers with --no-cache..."
    build_with_keepalive docker compose build --no-cache

    log_info "Starting containers (--force-recreate)..."
    docker compose up -d --force-recreate

    log_ok "Rollback complete (full rebuild)"
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

    # Pending migrations
    local MIGRATION_DIR="$DEPLOY_DIR/migrations"
    if [ -d "$MIGRATION_DIR" ]; then
        local DB_USER DB_PASS DB_NAME
        DB_USER=$(grep "^DB_USER=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
        DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
        DB_NAME=$(grep "^DB_NAME=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
        local APPLIED
        APPLIED=$(docker exec nexus-php-db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
            -N -e "SELECT migration_name FROM migrations WHERE migration_name IS NOT NULL;" 2>/dev/null || echo "")
        local PENDING_COUNT=0
        for SQL_FILE in "$MIGRATION_DIR"/*.sql; do
            [ -f "$SQL_FILE" ] || continue
            local BASENAME
            BASENAME=$(basename "$SQL_FILE")
            if ! echo "$APPLIED" | grep -qxF "$BASENAME"; then
                PENDING_COUNT=$((PENDING_COUNT + 1))
            fi
        done
        if [ $PENDING_COUNT -eq 0 ]; then
            log_ok "Migrations: all up to date"
        else
            log_warn "Migrations: $PENDING_COUNT pending"
        fi
    fi

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

# Parse mode and flags
MODE="${1:-quick}"
LARAVEL_MIGRATE="${LARAVEL_MIGRATE:-0}"

# Check for --migrate flag in remaining args
shift 2>/dev/null || true
for arg in "$@"; do
    case "$arg" in
        --migrate) LARAVEL_MIGRATE=1 ;;
    esac
done

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

# Enable maintenance mode before any changes
MAINTENANCE_DEFERRED=0
enable_maintenance_mode

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
        log_info "Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status] [--migrate]"
        exit 1
        ;;
esac

# Run Laravel artisan migrate if requested (--migrate flag or LARAVEL_MIGRATE=1)
if [ "$LARAVEL_MIGRATE" = "1" ]; then
    if ! run_laravel_artisan_migrate; then
        log_err "Laravel artisan migration failed — consider rollback"
        exit 1
    fi

    # Refresh the schema dump so it stays current with the latest migrations.
    # This file is committed to git — new contributors get a working DB from it.
    log_step "=== Refreshing Schema Dump ==="
    if DEPLOY_ENV=production DB_USER="${DB_USER:-nexus}" DB_PASS="${DB_PASS:-}" DB_NAME="${DB_NAME:-nexus}" \
       bash "$DEPLOY_DIR/scripts/refresh-schema-dump.sh" --production 2>&1 | tee -a "$LOG_FILE"; then
        log_ok "Schema dump refreshed at database/schema/mysql-schema.sql"
    else
        log_info "Schema dump refresh failed (non-fatal) — regenerate manually"
    fi
fi

# Verify production images (catches dev-on-prod bug)
if ! verify_production_images; then
    log_err "ABORTING: Dev images detected on production"
    log_err "Fix: sudo bash scripts/safe-deploy.sh full"
    exit 1
fi

# Run smoke tests
if run_smoke_tests; then
    # Update last successful deployment
    NEW_COMMIT=$(git rev-parse HEAD)
    echo "$NEW_COMMIT" > "$LAST_DEPLOY_FILE"

    # Write build version file into httpdocs/ (bind-mounted into Docker container)
    DEPLOY_TS=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
    COMMIT_MSG=$(git log -1 --format='%s')
    cat > "$DEPLOY_DIR/httpdocs/.build-version" <<VEOF
{
    "service": "nexus-php-api",
    "commit": "$NEW_COMMIT",
    "commit_short": "${NEW_COMMIT:0:8}",
    "commit_message": "$COMMIT_MSG",
    "deployed_at": "$DEPLOY_TS",
    "deploy_mode": "$MODE"
}
VEOF
    log_ok "Build version file written (httpdocs/.build-version)"

    # Purge Cloudflare cache (all 8 domains)
    purge_cloudflare_cache

    # Remove dangling Docker images (prevents disk bloat over time)
    prune_docker_images

    # Disable maintenance mode — deploy succeeded, go live
    disable_maintenance_mode

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
    log_err "MAINTENANCE MODE IS STILL ON — platform is NOT live"
    log_warn "The platform remains in maintenance mode for safety."
    log_warn "Fix the issue, then either:"
    log_warn "  1. Re-deploy:  sudo bash scripts/safe-deploy.sh full"
    log_warn "  2. Rollback:   sudo bash scripts/safe-deploy.sh rollback"
    log_warn "  3. Force live:  sudo bash scripts/maintenance.sh off"
    log_info "Log saved to: $LOG_FILE"
    exit 1
fi
