#!/bin/bash
# =============================================================================
# Project NEXUS - Safe Production Deploy Script (Orchestrator)
# =============================================================================
# Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status|logs] [--migrate] [--detach]
#
# Modes:
#   quick     - Git pull + rebuild frontend + restart all (DEFAULT)
#   full      - Git pull + rebuild ALL containers (--no-cache)
#   rollback  - Rollback to last successful deploy (full rebuild)
#   status    - Show current deployment status (no changes)
#   logs      - Tail the latest deploy log (follow mode: logs -f)
#
# Flags:
#   --migrate  - Also run `php artisan migrate --force` (Laravel migrations)
#   --no-cache - Force Docker builds without layer caching (default for full mode)
#   --detach   - Run deploy in background, detached from terminal/SSH.
# =============================================================================

set -eo pipefail

# Resolve own directory and deploy dir
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SELF_DIR/.." && pwd)"
export DEPLOY_DIR

DEPLOY_SCRIPTS="$SELF_DIR/deploy"

# Source core libs
. "$DEPLOY_SCRIPTS/lib/common.sh"
. "$DEPLOY_SCRIPTS/lib/state.sh"

cd "$DEPLOY_DIR" || {
    echo "ERROR: Cannot cd to $DEPLOY_DIR"
    exit 1
}

# Create log directory
mkdir -p "$LOG_DIR"

# Parse mode and flags
MODE="${1:-quick}"
LARAVEL_MIGRATE="${LARAVEL_MIGRATE:-0}"
DETACH=0
FORCE_NO_CACHE=0

shift 2>/dev/null || true
for arg in "$@"; do
    case "$arg" in
        --migrate) LARAVEL_MIGRATE=1 ;;
        --detach|-d) DETACH=1 ;;
        --no-cache) FORCE_NO_CACHE=1 ;;
    esac
done

# Handle logs and status subcommands (read-only, no lock/cleanup needed)
if [ "$MODE" = "logs" ]; then
    exec bash "$DEPLOY_SCRIPTS/subcommands/logs.sh" "$@"
fi

if [ "$MODE" = "status" ]; then
    exec bash "$DEPLOY_SCRIPTS/subcommands/status.sh"
fi

# Handle --detach: re-exec in background and return immediately
# Skip if we're already the detached child (marker is set)
if [ "$DETACH" = "1" ] && [ -z "${__NEXUS_DEPLOY_DETACHED__:-}" ]; then
    CHILD_ARGS=""
    [ "$LARAVEL_MIGRATE" = "1" ] && CHILD_ARGS="$CHILD_ARGS --migrate"
    [ "$FORCE_NO_CACHE" = "1" ] && CHILD_ARGS="$CHILD_ARGS --no-cache"
    bash "$DEPLOY_SCRIPTS/subcommands/detach.sh" "$MODE" $CHILD_ARGS
    exit 0
fi

# Set up LOG_FILE
TIMESTAMP=$(date +%Y-%m-%d_%H-%M-%S)
if [ -n "${__NEXUS_DEPLOY_DETACHED__:-}" ]; then
    # Detached child: stdout/stderr already go to the log file via detach redirect.
    # Point LOG_FILE at /dev/null so tee doesn't double-write.
    LOG_FILE="/dev/null"
else
    LOG_FILE="$LOG_DIR/deploy-$TIMESTAMP.log"
fi
export LOG_FILE MODE LARAVEL_MIGRATE FORCE_NO_CACHE

echo "============================================" | tee "$LOG_FILE"
echo "  Project NEXUS - Safe Production Deploy"    | tee -a "$LOG_FILE"
echo "  Started: $(date '+%Y-%m-%d %H:%M:%S')"      | tee -a "$LOG_FILE"
echo "============================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Initialise cross-phase state and reset flags
state_init
state_set DEPLOY_SUCCESS 0
state_set MAINTENANCE_ENABLED_BY_US 0

# Source lock/cleanup (uses common.sh + state.sh already loaded)
. "$DEPLOY_SCRIPTS/lib/lock.sh"

check_lock
create_lock
trap cleanup EXIT

# Helper: run a phase subprocess; on failure, print banner + exit 1 (cleanup trap fires)
run_phase() {
    local phase_script="$1"
    shift
    bash "$phase_script" "$@"
}

# Run pre-deploy validation (except for rollback)
if [ "$MODE" != "rollback" ]; then
    run_phase "$DEPLOY_SCRIPTS/phases/validate-env.sh"
fi

# Enable maintenance mode before any changes
run_phase "$DEPLOY_SCRIPTS/phases/maintenance-on.sh"

# Execute deployment based on mode
case "$MODE" in
    quick)
        run_phase "$DEPLOY_SCRIPTS/phases/build-quick.sh"
        ;;
    full)
        run_phase "$DEPLOY_SCRIPTS/phases/build-full.sh"
        ;;
    rollback)
        run_phase "$DEPLOY_SCRIPTS/phases/rollback.sh"
        ;;
    *)
        log_err "Invalid mode: $MODE"
        log_info "Usage: sudo bash scripts/safe-deploy.sh [quick|full|rollback|status] [--migrate]"
        exit 1
        ;;
esac

# Run Laravel artisan migrate if requested (--migrate flag or LARAVEL_MIGRATE=1)
if [ "$LARAVEL_MIGRATE" = "1" ]; then
    run_phase "$DEPLOY_SCRIPTS/phases/migrate-laravel.sh"
fi

# Verify production images (catches dev-on-prod bug) — hard failure
run_phase "$DEPLOY_SCRIPTS/phases/verify-images.sh"

# Run smoke tests
if run_phase "$DEPLOY_SCRIPTS/phases/smoke-tests.sh"; then
    # Write build version
    run_phase "$DEPLOY_SCRIPTS/phases/write-build-version.sh"

    # Disable maintenance mode FIRST — deploy succeeded, go live
    # IMPORTANT: Must happen BEFORE Cloudflare purge, otherwise CF caches
    # the 503 maintenance response right after purge, then keeps serving it
    # even after maintenance is disabled.
    run_phase "$DEPLOY_SCRIPTS/phases/maintenance-off.sh"

    # Purge Cloudflare cache AFTER maintenance is off
    CF_PURGE_OK=true
    bash "$DEPLOY_SCRIPTS/phases/purge-cloudflare.sh" || CF_PURGE_OK=false

    # Pre-render all tenant public pages with real data
    run_phase "$DEPLOY_SCRIPTS/phases/prerender-tenants.sh"

    # Remove dangling Docker images (prevents disk bloat over time)
    run_phase "$DEPLOY_SCRIPTS/phases/prune-images.sh"

    DEPLOYED_COMMIT=$(git log -1 --format='%h')
    DEPLOYED_SUBJECT=$(git log -1 --format='%s')
    DEPLOYED_TIME=$(date -u '+%Y-%m-%d %H:%M:%S UTC')
    CF_STATUS=$( [ "$CF_PURGE_OK" = "true" ] && echo "Purged (all domains)" || echo "PARTIAL — check logs (exchangemembers.com 403)" )

    echo "" | tee -a "$LOG_FILE"
    echo "============================================" | tee -a "$LOG_FILE"
    echo "  Deployment Successful!" | tee -a "$LOG_FILE"
    echo "============================================" | tee -a "$LOG_FILE"
    echo "  Commit:    $DEPLOYED_COMMIT — $DEPLOYED_SUBJECT" | tee -a "$LOG_FILE"
    echo "  Live at:   $DEPLOYED_TIME" | tee -a "$LOG_FILE"
    echo "  CF Purge:  $CF_STATUS" | tee -a "$LOG_FILE"
    echo "============================================" | tee -a "$LOG_FILE"
    echo "" | tee -a "$LOG_FILE"
    log_info "Log saved to: $LOG_FILE"

    # Schedule post-deploy health check (background, non-blocking)
    run_phase "$DEPLOY_SCRIPTS/phases/schedule-health-check.sh"

    state_clear
    exit 0
else
    echo "" | tee -a "$LOG_FILE"
    log_err "Deployment completed but smoke tests failed"
    log_err "MAINTENANCE MODE IS STILL ON — platform is NOT live"
    # Intentionally keep maintenance on — don't let the trap auto-disable it
    state_set MAINTENANCE_ENABLED_BY_US 0
    log_warn "The platform remains in maintenance mode for safety."
    log_warn "Fix the issue, then either:"
    log_warn "  1. Re-deploy:  sudo bash scripts/safe-deploy.sh full"
    log_warn "  2. Rollback:   sudo bash scripts/safe-deploy.sh rollback"
    log_warn "  3. Force live:  sudo bash scripts/maintenance.sh off"
    log_info "Log saved to: $LOG_FILE"
    exit 1
fi
