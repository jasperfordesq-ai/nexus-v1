#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Zero-downtime blue/green deploy orchestrator for the production VM.
# This script intentionally does not use GitHub Actions or GitHub CLI.

set -euo pipefail

SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$(cd "$SELF_DIR/../.." && pwd)"
export DEPLOY_DIR

. "$SELF_DIR/lib/common.sh"
. "$SELF_DIR/lib/state.sh"
. "$SELF_DIR/lib/lock.sh"
. "$SELF_DIR/lib/db-backup.sh"

mkdir -p "$LOG_DIR"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
LOG_FILE="${NEXUS_BLUEGREEN_LOG_FILE:-$LOG_DIR/bluegreen-deploy-$TIMESTAMP.log}"
export LOG_FILE

STATE_FILE="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
STATUS_FILE="${NEXUS_BLUEGREEN_STATUS_FILE:-$DEPLOY_DIR/.bluegreen-status}"
LATEST_LOG_FILE="${NEXUS_BLUEGREEN_LATEST_LOG_FILE:-$DEPLOY_DIR/.bluegreen-latest-log}"
RELEASES_DIR="${NEXUS_RELEASES_DIR:-$(dirname "$DEPLOY_DIR")/nexus-releases}"
APACHE_ROUTES_FILE="${NEXUS_APACHE_ROUTES_FILE:-}"

# Auto-detect routes file — sudo strips environment variables, so we cannot
# rely on NEXUS_APACHE_ROUTES_FILE being present. Check the canonical path.
if [ -z "$APACHE_ROUTES_FILE" ]; then
    _CANDIDATE="/etc/apache2/conf-enabled/nexus-active-upstreams.conf"
    if [ -f "$_CANDIDATE" ]; then
        APACHE_ROUTES_FILE="$_CANDIDATE"
        export NEXUS_APACHE_ROUTES_FILE="$_CANDIDATE"
    fi
    unset _CANDIDATE
fi

APACHE_CONFIGTEST="${NEXUS_APACHE_CONFIGTEST:-apachectl configtest}"
APACHE_RELOAD="${NEXUS_APACHE_RELOAD:-systemctl reload apache2}"
ACTIVE_COLOR_DEFAULT="${NEXUS_ACTIVE_COLOR_DEFAULT:-blue}"
# Migrations run automatically by default. Pass --no-migrate to skip
# (e.g. for emergency rollback deploys where the schema must stay).
# This is the canonical behaviour — migrations are part of the deploy unit.
LARAVEL_MIGRATE=1
DETACH=0
SKIP_PRERENDER=0
FORCE_PRERENDER=0
PRERENDER_TENANT=""
PRERENDER_ROUTES=""
PREPARED_COMMIT=""
PREPARED_RELEASE_DIR=""
CURRENT_ACTIVE=""
CURRENT_TARGET=""
CURRENT_COMMIT=""

BLUE_API_PORT="${NEXUS_BLUE_API_PORT:-8090}"
BLUE_FRONTEND_PORT="${NEXUS_BLUE_FRONTEND_PORT:-3000}"
BLUE_SALES_PORT="${NEXUS_BLUE_SALES_PORT:-3003}"
GREEN_API_PORT="${NEXUS_GREEN_API_PORT:-8190}"
GREEN_FRONTEND_PORT="${NEXUS_GREEN_FRONTEND_PORT:-3400}"
GREEN_SALES_PORT="${NEXUS_GREEN_SALES_PORT:-3103}"

usage() {
    cat <<'USAGE'
Usage:
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --migrate
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --skip-prerender
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --force-prerender
  sudo bash scripts/deploy/bluegreen-deploy.sh rollback
  sudo bash scripts/deploy/bluegreen-deploy.sh status
  sudo bash scripts/deploy/bluegreen-deploy.sh logs
  sudo bash scripts/deploy/bluegreen-deploy.sh logs -f
  sudo bash scripts/deploy/bluegreen-deploy.sh monitor

Required server env for deploy/rollback:
  NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf

Optional:
  NEXUS_APACHE_CONFIGTEST="apachectl configtest"
  NEXUS_APACHE_RELOAD="systemctl reload apache2"
USAGE
}

parse_flags() {
    shift || true
    while [ "$#" -gt 0 ]; do
        case "$1" in
            --migrate) LARAVEL_MIGRATE=1 ;;        # accepted as no-op (default)
            --no-migrate) LARAVEL_MIGRATE=0 ;;     # opt out (rare)
            --detach|-d) DETACH=1 ;;
            --skip-prerender) SKIP_PRERENDER=1 ;;
            --force-prerender) FORCE_PRERENDER=1 ;;
            --prerender-tenant) PRERENDER_TENANT="${2:-}"; shift ;;
            --prerender-routes) PRERENDER_ROUTES="${2:-}"; shift ;;
            *)
                log_err "Unknown flag: $1"
                usage
                exit 2
                ;;
        esac
        shift
    done
}

write_deploy_status() {
    local status="$1"
    local phase="$2"
    local active="${3:-$(read_active_color 2>/dev/null || echo unknown)}"
    local target="${4:-}"
    local commit="${5:-}"

    cat > "$STATUS_FILE" <<STATUS
status=$status
phase=$phase
active=$active
target=$target
commit=$commit
log=$LOG_FILE
updated_at=$(date -Iseconds)
STATUS
    printf '%s\n' "$LOG_FILE" > "$LATEST_LOG_FILE"
}

phase() {
    local label="$1"
    local active="${2:-$(read_active_color 2>/dev/null || echo unknown)}"
    local target="${3:-}"
    local commit="${4:-}"
    write_deploy_status "running" "$label" "$active" "$target" "$commit"
    log_step "=== $label ==="
}

deploy_exit_trap() {
    local code=$?
    local _end_ts duration_s
    _end_ts="$(date +%s)"
    duration_s=$(( _end_ts - ${DEPLOY_START_TS:-_end_ts} ))

    if [ "$code" -eq 0 ]; then
        write_deploy_status "success" "complete" "${CURRENT_TARGET:-$(read_active_color 2>/dev/null || echo unknown)}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"
    else
        write_deploy_status "failed" "failed" "${CURRENT_ACTIVE:-$(read_active_color 2>/dev/null || echo unknown)}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"
    fi

    # Append telemetry record (deploys.jsonl) — change-failure-rate / MTTR source
    local _status _subcommand _color
    [ "$code" -eq 0 ] && _status="success" || _status="failed"
    _subcommand="${CURRENT_SUBCOMMAND:-deploy}"
    _color="${CURRENT_TARGET:-}"
    bash "$SELF_DIR/phases/record-deploy-metrics.sh" \
        "$_status" "$_subcommand" \
        "${CURRENT_COMMIT:-}" "${CURRENT_PREV_COMMIT:-}" \
        "$_color" "$duration_s" \
        2>/dev/null || true

    # Post-deploy notification (non-blocking)
    bash "$SELF_DIR/phases/notify-deploy.sh" \
        "$_status" \
        "${CURRENT_COMMIT:0:12}" \
        "$(git -C "$DEPLOY_DIR" log -1 --format='%s' "${CURRENT_COMMIT:-HEAD}" 2>/dev/null || true)" \
        "${CURRENT_TARGET:-}" \
        "${duration_s}s" \
        2>/dev/null || true
    cleanup
    exit "$code"
}

detach_if_requested() {
    local mode="$1"
    shift || true

    if [ "$DETACH" != "1" ] || [ -n "${__NEXUS_BLUEGREEN_DETACHED__:-}" ]; then
        return 0
    fi

    local child_args=("$mode")
    [ "$LARAVEL_MIGRATE" = "1" ] && child_args+=("--migrate")
    [ "$SKIP_PRERENDER" = "1" ] && child_args+=("--skip-prerender")
    [ "$FORCE_PRERENDER" = "1" ] && child_args+=("--force-prerender")
    [ -n "$PRERENDER_TENANT" ] && child_args+=("--prerender-tenant" "$PRERENDER_TENANT")
    [ -n "$PRERENDER_ROUTES" ] && child_args+=("--prerender-routes" "$PRERENDER_ROUTES")

    LOG_FILE="$LOG_DIR/bluegreen-deploy-$TIMESTAMP.log"
    export LOG_FILE __NEXUS_BLUEGREEN_DETACHED__=1
    printf '%s\n' "$LOG_FILE" > "$LATEST_LOG_FILE"
    write_deploy_status "starting" "detached deploy queued" "$(read_active_color)" "" ""

    nohup bash "$0" "${child_args[@]}" > "$LOG_FILE" 2>&1 &
    local pid=$!

    log_ok "Blue/green deploy started in background (PID $pid)"
    log_info "Log: $LOG_FILE"
    log_info "Watch: sudo bash scripts/deploy/bluegreen-deploy.sh monitor"
    log_info "Tail:  sudo bash scripts/deploy/bluegreen-deploy.sh logs -f"
    exit 0
}

read_active_color() {
    local color
    if [ -f "$STATE_FILE" ]; then
        color="$(tr -d '[:space:]' < "$STATE_FILE")"
    else
        color="$ACTIVE_COLOR_DEFAULT"
    fi

    case "$color" in
        blue|green) echo "$color" ;;
        *)
            log_warn "Invalid active color '$color'; defaulting to $ACTIVE_COLOR_DEFAULT"
            echo "$ACTIVE_COLOR_DEFAULT"
            ;;
    esac
}

inactive_color() {
    local active="$1"
    case "$active" in
        blue) echo "green" ;;
        green) echo "blue" ;;
        *)
            log_err "Invalid active color: $active"
            return 1
            ;;
    esac
}

ports_for_color() {
    local color="$1"
    if [ "$color" = "blue" ]; then
        echo "$BLUE_API_PORT $BLUE_FRONTEND_PORT $BLUE_SALES_PORT"
    else
        echo "$GREEN_API_PORT $GREEN_FRONTEND_PORT $GREEN_SALES_PORT"
    fi
}

require_route_switching() {
    if [ -z "$APACHE_ROUTES_FILE" ]; then
        log_err "NEXUS_APACHE_ROUTES_FILE is not set."
        log_info "Set it to the Apache/Plesk include that controls app/api/sales upstream ports."
        exit 2
    fi
}

compose_for_release() {
    local release_dir="$1"
    shift
    docker compose \
        --env-file "$DEPLOY_DIR/.env" \
        -p "nexus-$NEXUS_COLOR" \
        -f "$release_dir/compose.bluegreen.yml" \
        "$@"
}

container_name() {
    local color="$1"
    local service="$2"
    case "$service" in
        app) echo "nexus-$color-php-app" ;;
        frontend) echo "nexus-$color-react" ;;
        sales) echo "nexus-$color-sales" ;;
        queue) echo "nexus-$color-php-queue" ;;
        scheduler) echo "nexus-$color-php-scheduler" ;;
        *)
            log_err "Unknown service: $service"
            return 1
            ;;
    esac
}

wait_for_container_health() {
    local container="$1"
    local deadline=$((SECONDS + 180))
    local grace_until=$((SECONDS + 30))
    local status

    while [ "$SECONDS" -lt "$deadline" ]; do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || echo missing)"
        case "$status" in
            healthy|running)
                log_ok "$container is $status"
                return 0
                ;;
            unhealthy|exited|dead)
                # During the first 30s we forgive "unhealthy" — fresh containers
                # often report stale status from before the new instance had a
                # chance to run its first health probe. After grace, treat it
                # as a real failure.
                if [ "$SECONDS" -lt "$grace_until" ]; then
                    sleep 3
                    continue
                fi
                log_err "$container is $status"
                docker logs --tail 80 "$container" 2>/dev/null || true
                return 1
                ;;
            missing)
                # Docker may briefly report missing during a recreate.
                : # fall through to sleep
                ;;
        esac
        sleep 3
    done

    log_err "$container did not become healthy before timeout"
    docker logs --tail 80 "$container" 2>/dev/null || true
    return 1
}

wait_for_color() {
    local color="$1"
    wait_for_container_health "$(container_name "$color" app)"
    wait_for_container_health "$(container_name "$color" frontend)"
    wait_for_container_health "$(container_name "$color" sales)"
}

prepare_release() {
    phase "Prepare Release Worktree" "${CURRENT_ACTIVE:-}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"

    mkdir -p "$RELEASES_DIR"
    git fetch origin main

    local commit release_dir
    commit="$(git rev-parse origin/main)"
    release_dir="$RELEASES_DIR/$commit"

    if [ ! -e "$release_dir/.git" ]; then
        log_info "Creating release worktree: $release_dir"
        git worktree prune
        git worktree add --detach "$release_dir" "$commit"
    else
        log_info "Release worktree already exists: $release_dir"
    fi

    if [ ! -f "$release_dir/compose.bluegreen.yml" ] || [ ! -f "$release_dir/Dockerfile.bluegreen" ]; then
        log_err "Release does not contain blue/green deployment files."
        log_err "Push this deployment upgrade first, then run the blue/green deploy."
        exit 1
    fi

    PREPARED_COMMIT="$commit"
    PREPARED_RELEASE_DIR="$release_dir"
    CURRENT_COMMIT="$commit"
}

color_release_file() {
    local color="$1"
    echo "$DEPLOY_DIR/.bluegreen-$color-release"
}

write_color_release() {
    local color="$1"
    local commit="$2"
    local release_dir="$3"
    printf '%s|%s\n' "$commit" "$release_dir" > "$(color_release_file "$color")"
}

read_color_release() {
    local color="$1"
    local file
    file="$(color_release_file "$color")"
    if [ ! -f "$file" ]; then
        return 1
    fi
    cat "$file"
}

optimize_candidate_laravel() {
    local color="$1"
    local app_container
    app_container="$(container_name "$color" app)"

    phase "Candidate Laravel Cache ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    docker exec "$app_container" php /var/www/html/artisan config:clear
    docker exec "$app_container" php /var/www/html/artisan route:clear
    docker exec "$app_container" php /var/www/html/artisan event:clear
    docker exec "$app_container" php /var/www/html/artisan view:clear
    docker exec "$app_container" php /var/www/html/artisan optimize
    # Signal any already-running workers for this color to gracefully reload
    docker exec "$app_container" php /var/www/html/artisan queue:restart 2>/dev/null || true
    docker exec "$app_container" php /var/www/html/artisan storage:link || true
    log_ok "Candidate Laravel caches rebuilt"
}

run_candidate_migrations() {
    local color="$1"
    local app_container
    app_container="$(container_name "$color" app)"

    if [ "$LARAVEL_MIGRATE" != "1" ]; then
        log_info "Skipping database migrations (--no-migrate)"
        return 0
    fi

    log_step "=== Candidate Laravel Migrations ($color) ==="
    write_deploy_status "running" "Candidate Laravel Migrations ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"

    # Skip backup + migrate entirely when nothing is pending — no point dumping
    # the DB just to verify the schema is already current.
    local pending
    pending="$(db_pending_migration_count "$app_container")"
    if [ "${pending:-0}" -eq 0 ]; then
        log_ok "No pending migrations — skipping backup and migrate"
        return 0
    fi

    log_warn "$pending pending migration(s) detected. Running expand/contract-safe migrations online."
    log_info "Taking pre-migration database snapshot..."
    if ! db_backup_with_offsite "$app_container"; then
        log_err "Pre-migration backup failed — aborting migration to prevent unrecoverable data loss"
        return 1
    fi

    docker exec "$app_container" php /var/www/html/artisan migrate --force
    log_ok "Laravel migrations completed"
}

free_target_color_ports() {
    local color="$1"
    local release_dir="$2"

    log_step "=== Free Inactive Color ($color) ==="
    write_deploy_status "running" "Free Inactive Color ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    compose_for_release "$release_dir" down --remove-orphans >/dev/null 2>&1 || true

    if [ "$color" = "blue" ]; then
        # First-generation production containers used the blue ports without
        # color names. Once green is active, stop them so blue can be rebuilt.
        docker stop nexus-php-app nexus-react-prod nexus-sales-site >/dev/null 2>&1 || true
    fi

    log_ok "Inactive $color ports are available"
}

write_apache_routes() {
    local color="$1"
    local api_port frontend_port sales_port tmp_file backup_file
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")
    tmp_file="$(mktemp)"
    backup_file="$(mktemp)"

    cat > "$tmp_file" <<ROUTES
# Managed by scripts/deploy/bluegreen-deploy.sh
# Active color: $color
Define NEXUS_API_PORT $api_port
Define NEXUS_FRONTEND_PORT $frontend_port
Define NEXUS_SALES_PORT $sales_port
ROUTES

    if [ -f "$APACHE_ROUTES_FILE" ]; then
        cp "$APACHE_ROUTES_FILE" "$backup_file"
    else
        : > "$backup_file"
    fi

    install -m 0644 "$tmp_file" "$APACHE_ROUTES_FILE"
    rm -f "$tmp_file"

    log_info "Testing Apache configuration..."
    write_deploy_status "running" "Apache configtest for $color" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    if ! bash -lc "$APACHE_CONFIGTEST"; then
        log_err "Apache configtest failed; restoring previous route file"
        if [ -s "$backup_file" ]; then
            install -m 0644 "$backup_file" "$APACHE_ROUTES_FILE"
        else
            rm -f "$APACHE_ROUTES_FILE"
        fi
        rm -f "$backup_file"
        return 1
    fi
    log_info "Gracefully reloading Apache..."
    write_deploy_status "running" "Apache graceful reload to $color" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    if ! bash -lc "$APACHE_RELOAD"; then
        log_err "Apache reload failed; restoring previous route file"
        if [ -s "$backup_file" ]; then
            install -m 0644 "$backup_file" "$APACHE_ROUTES_FILE"
            bash -lc "$APACHE_CONFIGTEST" >/dev/null 2>&1 || true
        else
            rm -f "$APACHE_ROUTES_FILE"
        fi
        rm -f "$backup_file"
        return 1
    fi
    rm -f "$backup_file"

    echo "$color" > "$STATE_FILE"
    log_ok "Traffic switched to $color ($api_port/$frontend_port/$sales_port)"
}

smoke_color() {
    local color="$1"
    local api_port frontend_port sales_port html bootstrap
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")

    log_step "=== Candidate Smoke Tests ($color) ==="
    write_deploy_status "running" "Candidate Smoke Tests ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"

    curl -sf "http://127.0.0.1:$api_port/up" >/dev/null
    log_ok "API health passed on $api_port"

    bootstrap="$(curl -sf -H "X-Tenant-Slug: hour-timebank" "http://127.0.0.1:$api_port/api/v2/tenant/bootstrap" || true)"
    if ! echo "$bootstrap" | grep -q '"hour-timebank"'; then
        log_err "Tenant bootstrap failed on candidate API"
        return 1
    fi
    log_ok "Tenant bootstrap passed"

    html="$(curl -sf "http://127.0.0.1:$frontend_port/" || true)"
    if ! echo "$html" | grep -q 'id="root"'; then
        log_err "Frontend did not serve the React root"
        return 1
    fi
    log_ok "Frontend passed on $frontend_port"

    curl -sf "http://127.0.0.1:$sales_port/" >/dev/null
    log_ok "Sales site passed on $sales_port"
}

deploy_candidate() {
    local color="$1"
    local release_dir="$2"
    local commit="$3"
    local api_port frontend_port sales_port
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")

    phase "Build Candidate ($color)" "${CURRENT_ACTIVE:-}" "$color" "$commit"
    log_info "Release: ${commit:0:12}"
    log_info "Inactive ports: API=$api_port frontend=$frontend_port sales=$sales_port"

    export NEXUS_COLOR="$color"
    export NEXUS_API_PORT="$api_port"
    export NEXUS_FRONTEND_PORT="$frontend_port"
    export NEXUS_SALES_PORT="$sales_port"
    export NEXUS_ENV_FILE="$DEPLOY_DIR/.env"
    export BUILD_COMMIT="${commit:0:12}"

    free_target_color_ports "$color" "$release_dir"
    compose_for_release "$release_dir" up -d --build app frontend sales
    wait_for_color "$color"
    optimize_candidate_laravel "$color"
    verify_candidate_images "$color"
    write_candidate_build_version "$color"
    check_candidate_migration_safety "$color" "$release_dir"
    run_candidate_migrations "$color"
    verify_candidate_build_version "$color"
}

verify_candidate_images() {
    local color="$1"
    phase "Candidate Image Verification ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    NEXUS_VERIFY_COLOR="$color" BUILD_COMMIT="${CURRENT_COMMIT:0:12}" \
        bash "$SELF_DIR/phases/verify-images.sh"
}

write_candidate_build_version() {
    local color="$1"
    phase "Bake Build Version ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    NEXUS_BUILD_VERSION_COLOR="$color" MODE=bluegreen \
        bash "$SELF_DIR/phases/write-build-version.sh"
}

check_candidate_migration_safety() {
    local color="$1"
    local release_dir="$2"
    phase "Migration Safety Gate ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    NEXUS_RELEASE_DIR="$release_dir" \
    NEXUS_CANDIDATE_CONTAINER="$(container_name "$color" app)" \
        bash "$SELF_DIR/phases/check-migration-safety.sh"
}

# Hits the candidate's local API port directly (bypassing Apache + Cloudflare)
# and asserts /version.php returns the commit we just built. Catches the case
# where the right image was tagged but a stale layer was reused.
verify_candidate_build_version() {
    local color="$1"
    local api_port _ _2
    read -r api_port _ _2 < <(ports_for_color "$color")
    phase "Verify Candidate Build Version ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"
    local response served_commit
    response="$(curl -sf "http://127.0.0.1:$api_port/version.php" 2>/dev/null || true)"
    if [ -z "$response" ]; then
        log_err "Candidate /version.php did not respond on port $api_port"
        return 1
    fi
    served_commit="$(echo "$response" | sed -n 's/.*"commit"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')"
    if [ "$served_commit" != "$CURRENT_COMMIT" ]; then
        log_err "Candidate served commit '$served_commit' but expected '$CURRENT_COMMIT'"
        log_err "The image was built or tagged with the wrong commit. Aborting before cutover."
        return 1
    fi
    log_ok "Candidate /version.php confirms commit $served_commit"
}

start_workers_for_color() {
    local color="$1"
    local release_dir="$2"
    local commit="$3"
    local api_port frontend_port sales_port
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")

    export NEXUS_COLOR="$color"
    export NEXUS_API_PORT="$api_port"
    export NEXUS_FRONTEND_PORT="$frontend_port"
    export NEXUS_SALES_PORT="$sales_port"
    export NEXUS_ENV_FILE="$DEPLOY_DIR/.env"
    export BUILD_COMMIT="${commit:0:12}"

    phase "Start Workers ($color)" "${CURRENT_ACTIVE:-}" "$color" "$commit"

    # Remove any stale worker containers (e.g. left in Exited state by a prior
    # aborted deploy or built against an image tag that no longer exists).
    # `compose up -d` won't recreate a container that's just stopped — it tries
    # to `docker start` it, which fails if the image is gone, leaving a stuck
    # "Exited" container that blocks the new one. `docker rm -f` is idempotent.
    docker rm -f "$(container_name "$color" queue)" >/dev/null 2>&1 || true
    docker rm -f "$(container_name "$color" scheduler)" >/dev/null 2>&1 || true

    # --force-recreate guarantees a clean container even if compose decides the
    # config is unchanged. Without it, an existing healthy-but-stale container
    # could be reused.
    compose_for_release "$release_dir" up -d --force-recreate queue scheduler
    wait_for_container_health "$(container_name "$color" queue)"
    wait_for_container_health "$(container_name "$color" scheduler)"
    log_ok "Queue and scheduler workers are healthy for $color"
}

# Stops the OLD color's workers AFTER the new color has taken traffic. Keeping
# them split avoids the window where new web code enqueues jobs that an old
# worker handles with stale code.
stop_workers_for_color() {
    local color="$1"
    phase "Stop Old Workers ($color)" "${CURRENT_ACTIVE:-}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"
    docker stop "$(container_name "$color" queue)" >/dev/null 2>&1 || true
    docker stop "$(container_name "$color" scheduler)" >/dev/null 2>&1 || true
    # Legacy single-color names from before blue/green
    docker stop nexus-php-queue >/dev/null 2>&1 || true
    docker stop nexus-php-scheduler >/dev/null 2>&1 || true
    log_ok "Old workers stopped"
}

post_cutover_smoke() {
    phase "Public Post-Cutover Smoke Tests" "${CURRENT_ACTIVE:-}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"

    curl -sf https://api.project-nexus.ie/up >/dev/null
    log_ok "Public API health passed"

    # CRITICAL: prove the cutover is real. Without this, a misconfigured Apache
    # include or a Cloudflare cache hit can keep the OLD color live and every
    # other smoke test still passes. Compare the live commit to CURRENT_COMMIT.
    local response served_commit
    # Cache-Control: no-store on /version.php is set by httpdocs/version.php, but
    # add a no-cache header anyway in case Cloudflare edge ignores it.
    response="$(curl -sf -H 'Cache-Control: no-cache' -H 'Pragma: no-cache' \
        "https://api.project-nexus.ie/version.php?_t=$(date +%s)" 2>/dev/null || true)"
    if [ -z "$response" ]; then
        log_err "Public /version.php did not respond"
        return 1
    fi
    served_commit="$(echo "$response" | sed -n 's/.*"commit"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')"
    if [ "$served_commit" != "$CURRENT_COMMIT" ]; then
        log_err "Public API serves commit '$served_commit' but cutover targeted '$CURRENT_COMMIT'"
        log_err "Apache route file may not have switched, or Cloudflare is caching /version.php."
        return 1
    fi
    log_ok "Public /version.php confirms live commit is $served_commit"

    local bootstrap
    bootstrap="$(curl -sf -H "X-Tenant-Slug: hour-timebank" https://api.project-nexus.ie/api/v2/tenant/bootstrap || true)"
    if ! echo "$bootstrap" | grep -q '"hour-timebank"'; then
        log_err "Public tenant bootstrap failed"
        return 1
    fi
    log_ok "Public tenant bootstrap passed"

    curl -sf https://app.project-nexus.ie/ >/dev/null
    log_ok "Public React frontend passed"

    curl -sf https://project-nexus.ie/ >/dev/null
    log_ok "Public sales site passed"
}

run_prerender_for_color() {
    local color="$1"
    local release_dir="$2"
    local frontend_container
    frontend_container="$(container_name "$color" frontend)"

    if [ "$SKIP_PRERENDER" = "1" ]; then
        log_info "Skipping per-tenant pre-rendering (--skip-prerender set)"
        return 0
    fi

    phase "Per-Tenant Pre-Rendering ($color)" "${CURRENT_ACTIVE:-}" "$color" "${CURRENT_COMMIT:-}"

    if [ ! -f "$release_dir/scripts/deploy/phases/prerender-tenants.sh" ]; then
        log_warn "Pre-render phase not found; run manually if needed"
        return 0
    fi

    export PRERENDER_BASE_COMMIT="${PRERENDER_BASE_COMMIT:-}"
    if [ -z "$PRERENDER_BASE_COMMIT" ] && [ -f "$LAST_PRERENDER_FILE" ]; then
        PRERENDER_BASE_COMMIT="$(cat "$LAST_PRERENDER_FILE" 2>/dev/null || true)"
    elif [ -z "$PRERENDER_BASE_COMMIT" ] && [ -f "$LAST_DEPLOY_FILE" ]; then
        PRERENDER_BASE_COMMIT="$(cat "$LAST_DEPLOY_FILE" 2>/dev/null || true)"
    fi

    FRONTEND_CONTAINER="$frontend_container" \
    NGINX_CONTAINER="$frontend_container" \
    PRERENDER_DEPLOY_DIR="$release_dir" \
    PRERENDER_CODE_DIR="$release_dir" \
    PRERENDER_CONFIG_DIR="$DEPLOY_DIR" \
    FORCE_PRERENDER="$FORCE_PRERENDER" \
    PRERENDER_TENANT="$PRERENDER_TENANT" \
    PRERENDER_ROUTES="$PRERENDER_ROUTES" \
    bash "$release_dir/scripts/deploy/phases/prerender-tenants.sh" || true
}

schedule_followup_health_check() {
    if [ -f "$DEPLOY_DIR/scripts/deploy/phases/schedule-health-check.sh" ]; then
        bash "$DEPLOY_DIR/scripts/deploy/phases/schedule-health-check.sh" || true
    fi
}

cmd_status() {
    local active api_port frontend_port sales_port
    active="$(read_active_color)"
    read -r api_port frontend_port sales_port < <(ports_for_color "$active")
    log_info "Active color: $active"
    log_info "Active ports: API=$api_port frontend=$frontend_port sales=$sales_port"
    if [ -f "$STATUS_FILE" ]; then
        log_info "Latest deployment status:"
        sed -n '1,20p' "$STATUS_FILE"
    else
        log_warn "No blue/green deployment status file yet"
    fi
    if [ -n "$APACHE_ROUTES_FILE" ] && [ -f "$APACHE_ROUTES_FILE" ]; then
        log_info "Apache route file: $APACHE_ROUTES_FILE"
        sed -n '1,20p' "$APACHE_ROUTES_FILE"
    else
        log_warn "NEXUS_APACHE_ROUTES_FILE not configured or file does not exist"
    fi
}

latest_log_path() {
    if [ -f "$LATEST_LOG_FILE" ]; then
        cat "$LATEST_LOG_FILE"
    else
        ls -t "$LOG_DIR"/bluegreen-deploy-*.log 2>/dev/null | head -n 1 || true
    fi
}

cmd_logs() {
    local follow="${1:-}"
    local log_path
    log_path="$(latest_log_path)"
    if [ -z "$log_path" ] || [ ! -f "$log_path" ]; then
        log_err "No blue/green deploy log found"
        exit 1
    fi

    log_info "Log: $log_path"
    if [ "$follow" = "-f" ] || [ "$follow" = "--follow" ]; then
        tail -n 80 -f "$log_path"
    else
        tail -n 120 "$log_path"
    fi
}

cmd_monitor() {
    local log_path status
    while true; do
        echo ""
        echo "===== Project NEXUS Blue/Green Deploy Monitor $(date -Iseconds) ====="
        if [ -f "$STATUS_FILE" ]; then
            cat "$STATUS_FILE"
            status="$(grep '^status=' "$STATUS_FILE" | cut -d= -f2- || true)"
        else
            echo "status=unknown"
            status="unknown"
        fi

        echo ""
        echo "Containers:"
        docker ps --format '{{.Names}}\t{{.Status}}' | grep -E 'nexus-(blue|green)|nexus-php-(db|redis)|nexus-meilisearch' || true

        log_path="$(latest_log_path)"
        if [ -n "$log_path" ] && [ -f "$log_path" ]; then
            echo ""
            echo "Recent log lines: $log_path"
            tail -n 18 "$log_path"
        fi

        case "$status" in
            success|failed)
                exit 0
                ;;
        esac
        sleep 5
    done
}

cmd_deploy() {
    DEPLOY_START_TS="$(date +%s)"
    CURRENT_SUBCOMMAND="deploy"
    CURRENT_PREV_COMMIT="$(cat "$LAST_DEPLOY_FILE" 2>/dev/null || echo "")"
    require_route_switching
    state_init
    state_set DEPLOY_SUCCESS 0
    state_set MAINTENANCE_ENABLED_BY_US 0
    check_lock
    create_lock
    trap deploy_exit_trap EXIT

    # Validate environment before doing anything irreversible
    . "$SELF_DIR/phases/validate-env.sh"
    validate_required_env_vars
    validate_dockerfiles

    local active target commit release_dir release_meta
    active="$(read_active_color)"
    target="$(inactive_color "$active")"
    CURRENT_ACTIVE="$active"
    CURRENT_TARGET="$target"

    log_info "Current active color: $active"
    log_info "Deploy target color: $target"
    write_deploy_status "running" "starting deploy" "$active" "$target" ""

    prepare_release
    commit="$PREPARED_COMMIT"
    release_dir="$PREPARED_RELEASE_DIR"
    CURRENT_COMMIT="$commit"

    deploy_candidate "$target" "$release_dir" "$commit"
    smoke_color "$target"

    # Start the NEW color's workers BEFORE web traffic switches. This eliminates
    # the window where new web code enqueues jobs that old workers process with
    # stale code expectations. Both colors' workers run in parallel briefly —
    # safe because they pull from the same DB-backed queue and the new code is
    # required to be backwards-compatible (see Migration Safety Gate).
    if ! start_workers_for_color "$target" "$release_dir" "$commit"; then
        log_err "Could not start $target workers — aborting before web cutover. Active color $active is unaffected."
        docker stop "$(container_name "$target" queue)" >/dev/null 2>&1 || true
        docker stop "$(container_name "$target" scheduler)" >/dev/null 2>&1 || true
        exit 1
    fi

    write_apache_routes "$target"
    if ! post_cutover_smoke; then
        log_err "Public smoke failed after cutover; reverting traffic to $active"
        write_apache_routes "$active"
        # New workers started, but cutover failed. Stop them; old workers were
        # never stopped (we hadn't reached stop_workers_for_color "$active" yet)
        # so the active color is fully restored.
        docker stop "$(container_name "$target" queue)" >/dev/null 2>&1 || true
        docker stop "$(container_name "$target" scheduler)" >/dev/null 2>&1 || true
        exit 1
    fi

    # Cutover succeeded. Stop the OLD color's workers — the new color owns the
    # queue from this point on.
    stop_workers_for_color "$active"

    # Purge Cloudflare cache after traffic switch — must run after smoke tests
    # pass so CF doesn't cache the old content right after purge.
    if [ -f "$DEPLOY_DIR/scripts/deploy/phases/purge-cloudflare.sh" ]; then
        phase "Cloudflare Cache Purge" "${CURRENT_ACTIVE:-}" "$target" "${CURRENT_COMMIT:-}"
        bash "$DEPLOY_DIR/scripts/deploy/phases/purge-cloudflare.sh" 2>&1 | tee -a "$LOG_FILE" || \
            log_warn "Cloudflare purge had errors — run manually: sudo bash scripts/purge-cloudflare-cache.sh"
    else
        log_warn "purge-cloudflare.sh phase not found — Cloudflare cache NOT purged"
    fi
    write_color_release "$target" "$commit" "$release_dir"
    run_prerender_for_color "$target" "$release_dir"
    schedule_followup_health_check
    git rev-parse origin/main > "$LAST_DEPLOY_FILE" 2>/dev/null || true
    # Prune old release worktrees — keep the 3 most recent commits (current + 2 rollback candidates)
    if [ -d "$RELEASES_DIR" ]; then
        local keep_commits
        keep_commits="$(git log --format='%H' -n 3 origin/main 2>/dev/null || true)"
        for rel_dir in "$RELEASES_DIR"/*/; do
            local rel_commit
            rel_commit="$(basename "$rel_dir")"
            if [ -z "$rel_commit" ] || [ "$rel_commit" = "*" ]; then continue; fi
            if ! echo "$keep_commits" | grep -qF "$rel_commit"; then
                log_info "Pruning old release worktree: $rel_commit"
                git worktree remove --force "$rel_dir" 2>/dev/null || rm -rf "$rel_dir" || true
            fi
        done
        git worktree prune 2>/dev/null || true
    fi
    state_set DEPLOY_SUCCESS 1

    # Write build version file — records commit/timestamp into httpdocs/.build-version
    phase "Write Build Version" "${CURRENT_TARGET:-}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"
    MODE=bluegreen bash "$SELF_DIR/phases/write-build-version.sh"

    # Prune dangling Docker images to reclaim disk space
    phase "Docker Image Cleanup" "${CURRENT_TARGET:-}" "${CURRENT_TARGET:-}" "${CURRENT_COMMIT:-}"
    bash "$SELF_DIR/phases/prune-images.sh"

    # Prune deploy log files older than 30 days to prevent disk accumulation
    find "$LOG_DIR" -name "bluegreen-deploy-*.log" -mtime +30 -delete 2>/dev/null || true
}

cmd_rollback() {
    DEPLOY_START_TS="$(date +%s)"
    CURRENT_SUBCOMMAND="rollback"
    CURRENT_PREV_COMMIT="$(cat "$LAST_DEPLOY_FILE" 2>/dev/null || echo "")"
    require_route_switching
    state_init
    state_set DEPLOY_SUCCESS 0
    state_set MAINTENANCE_ENABLED_BY_US 0
    check_lock
    create_lock
    trap deploy_exit_trap EXIT

    local active target release_meta commit release_dir
    active="$(read_active_color)"
    target="$(inactive_color "$active")"
    CURRENT_ACTIVE="$active"
    CURRENT_TARGET="$target"

    log_warn "Rolling back from $active to $target"
    write_deploy_status "running" "starting rollback" "$active" "$target" ""
    smoke_color "$target"

    # Verify rollback target is healthy before switching traffic
    log_info "Verifying rollback target ($target) is healthy before cutover..."
    ROLLBACK_APP="nexus-${target}-php-app"
    if ! docker inspect --format='{{.State.Health.Status}}' "$ROLLBACK_APP" 2>/dev/null | grep -q "healthy"; then
        log_err "Rollback target $ROLLBACK_APP is not healthy — aborting rollback to prevent double-failure"
        log_err "Run: docker ps -a | grep nexus-${target} to investigate"
        exit 1
    fi
    log_ok "Rollback target $ROLLBACK_APP is healthy — proceeding with traffic switch"

    # Same ordering as cmd_deploy: bring rollback workers up FIRST, switch
    # web traffic SECOND, stop the (failing) current workers LAST.
    if release_meta="$(read_color_release "$target")"; then
        commit="${release_meta%%|*}"
        release_dir="${release_meta#*|}"
        CURRENT_COMMIT="$commit"
        if ! start_workers_for_color "$target" "$release_dir" "$commit"; then
            log_err "Rollback worker start failed — keeping current active color $active"
            exit 1
        fi
    else
        log_warn "No release metadata found for $target workers; trying legacy containers"
        docker start nexus-php-queue >/dev/null 2>&1 || true
        docker start nexus-php-scheduler >/dev/null 2>&1 || true
    fi

    write_apache_routes "$target"
    if ! post_cutover_smoke; then
        log_err "Rollback public smoke failed; restoring web traffic to $active"
        write_apache_routes "$active"
        # Stop the workers we just brought up — old workers were never stopped
        docker stop "$(container_name "$target" queue)" >/dev/null 2>&1 || true
        docker stop "$(container_name "$target" scheduler)" >/dev/null 2>&1 || true
        exit 1
    fi
    # Cutover succeeded — stop the failing color's workers
    stop_workers_for_color "$active"
    # Purge Cloudflare cache after rollback traffic switch — prevents CF from
    # continuing to serve cached responses from the broken new deployment
    if [ -f "$DEPLOY_DIR/scripts/deploy/phases/purge-cloudflare.sh" ]; then
        phase "Cloudflare Cache Purge" "${CURRENT_ACTIVE:-}" "$target" "${CURRENT_COMMIT:-}"
        bash "$DEPLOY_DIR/scripts/deploy/phases/purge-cloudflare.sh" 2>&1 | tee -a "$LOG_FILE" || \
            log_warn "Cloudflare purge had errors — run manually: sudo bash scripts/purge-cloudflare-cache.sh"
    else
        log_warn "purge-cloudflare.sh phase not found — Cloudflare cache NOT purged"
    fi
    state_set DEPLOY_SUCCESS 1
}

case "${1:-}" in
    deploy) parse_flags "$@"; detach_if_requested deploy "$@"; cmd_deploy ;;
    rollback) parse_flags "$@"; detach_if_requested rollback "$@"; cmd_rollback ;;
    status) cmd_status ;;
    logs) shift; cmd_logs "${1:-}" ;;
    monitor) cmd_monitor ;;
    -h|--help|help|"") usage ;;
    *)
        usage
        exit 2
        ;;
esac
