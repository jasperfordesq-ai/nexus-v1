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

STATE_FILE="${NEXUS_BLUEGREEN_STATE_FILE:-$DEPLOY_DIR/.bluegreen-active}"
RELEASES_DIR="${NEXUS_RELEASES_DIR:-$(dirname "$DEPLOY_DIR")/nexus-releases}"
APACHE_ROUTES_FILE="${NEXUS_APACHE_ROUTES_FILE:-}"
APACHE_CONFIGTEST="${NEXUS_APACHE_CONFIGTEST:-apachectl configtest}"
APACHE_RELOAD="${NEXUS_APACHE_RELOAD:-systemctl reload apache2}"
ACTIVE_COLOR_DEFAULT="${NEXUS_ACTIVE_COLOR_DEFAULT:-blue}"
LARAVEL_MIGRATE=0
PREPARED_COMMIT=""
PREPARED_RELEASE_DIR=""

BLUE_API_PORT="${NEXUS_BLUE_API_PORT:-8090}"
BLUE_FRONTEND_PORT="${NEXUS_BLUE_FRONTEND_PORT:-3000}"
BLUE_SALES_PORT="${NEXUS_BLUE_SALES_PORT:-3003}"
GREEN_API_PORT="${NEXUS_GREEN_API_PORT:-8190}"
GREEN_FRONTEND_PORT="${NEXUS_GREEN_FRONTEND_PORT:-3100}"
GREEN_SALES_PORT="${NEXUS_GREEN_SALES_PORT:-3103}"

usage() {
    cat <<'USAGE'
Usage:
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy
  sudo bash scripts/deploy/bluegreen-deploy.sh deploy --migrate
  sudo bash scripts/deploy/bluegreen-deploy.sh rollback
  sudo bash scripts/deploy/bluegreen-deploy.sh status

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
            --migrate) LARAVEL_MIGRATE=1 ;;
            *)
                log_err "Unknown flag: $1"
                usage
                exit 2
                ;;
        esac
        shift
    done
}

read_active_color() {
    if [ -f "$STATE_FILE" ]; then
        tr -d '[:space:]' < "$STATE_FILE"
    else
        echo "$ACTIVE_COLOR_DEFAULT"
    fi
}

inactive_color() {
    local active="$1"
    if [ "$active" = "blue" ]; then
        echo "green"
    else
        echo "blue"
    fi
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
        *)
            log_err "Unknown service: $service"
            return 1
            ;;
    esac
}

wait_for_container_health() {
    local container="$1"
    local deadline=$((SECONDS + 180))
    local status

    while [ "$SECONDS" -lt "$deadline" ]; do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container" 2>/dev/null || echo missing)"
        case "$status" in
            healthy|running)
                log_ok "$container is $status"
                return 0
                ;;
            unhealthy|exited|dead|missing)
                if [ "$status" != "missing" ]; then
                    log_err "$container is $status"
                    docker logs --tail 80 "$container" 2>/dev/null || true
                    return 1
                fi
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
    log_step "=== Prepare Release Worktree ==="

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

    log_step "=== Candidate Laravel Cache ($color) ==="
    docker exec "$app_container" php /var/www/html/artisan config:clear
    docker exec "$app_container" php /var/www/html/artisan route:clear
    docker exec "$app_container" php /var/www/html/artisan event:clear
    docker exec "$app_container" php /var/www/html/artisan view:clear
    docker exec "$app_container" php /var/www/html/artisan config:cache
    docker exec "$app_container" php /var/www/html/artisan route:cache
    docker exec "$app_container" php /var/www/html/artisan event:cache
    docker exec "$app_container" php /var/www/html/artisan storage:link || true
    log_ok "Candidate Laravel caches rebuilt"
}

run_candidate_migrations() {
    local color="$1"
    local app_container
    app_container="$(container_name "$color" app)"

    if [ "$LARAVEL_MIGRATE" != "1" ]; then
        log_info "Skipping database migrations. Use --migrate for backwards-compatible migrations."
        return 0
    fi

    log_step "=== Candidate Laravel Migrations ($color) ==="
    log_warn "Running migrations online. Only expand/contract-safe migrations belong in this path."
    docker exec "$app_container" php /var/www/html/artisan migrate --force
    log_ok "Laravel migrations completed"
}

free_target_color_ports() {
    local color="$1"
    local release_dir="$2"

    log_step "=== Free Inactive Color ($color) ==="
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
    local api_port frontend_port sales_port tmp_file
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")
    tmp_file="$(mktemp)"

    cat > "$tmp_file" <<ROUTES
# Managed by scripts/deploy/bluegreen-deploy.sh
# Active color: $color
Define NEXUS_API_PORT $api_port
Define NEXUS_FRONTEND_PORT $frontend_port
Define NEXUS_SALES_PORT $sales_port
ROUTES

    install -m 0644 "$tmp_file" "$APACHE_ROUTES_FILE"
    rm -f "$tmp_file"

    log_info "Testing Apache configuration..."
    bash -lc "$APACHE_CONFIGTEST"

    log_info "Gracefully reloading Apache..."
    bash -lc "$APACHE_RELOAD"

    echo "$color" > "$STATE_FILE"
    log_ok "Traffic switched to $color ($api_port/$frontend_port/$sales_port)"
}

smoke_color() {
    local color="$1"
    local api_port frontend_port sales_port html bootstrap
    read -r api_port frontend_port sales_port < <(ports_for_color "$color")

    log_step "=== Candidate Smoke Tests ($color) ==="

    curl -sf "http://127.0.0.1:$api_port/health.php" >/dev/null
    log_ok "API health passed on $api_port"

    bootstrap="$(curl -sf -H "X-Tenant-Slug: hour-timebank" "http://127.0.0.1:$api_port/api/v2/tenant/bootstrap" || true)"
    if ! echo "$bootstrap" | grep -q '"tenant"'; then
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

    log_step "=== Build Candidate ($color) ==="
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
    run_candidate_migrations "$color"
}

start_queue_for_color() {
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

    log_step "=== Queue Cutover ($color) ==="
    compose_for_release "$release_dir" up -d queue
    wait_for_container_health "$(container_name "$color" queue)"

    docker stop nexus-php-queue >/dev/null 2>&1 || true
    docker stop "$(container_name "$(inactive_color "$color")" queue)" >/dev/null 2>&1 || true
    log_ok "Queue worker is running for $color"
}

post_cutover_smoke() {
    log_step "=== Public Post-Cutover Smoke Tests ==="

    curl -sf https://api.project-nexus.ie/health.php >/dev/null
    log_ok "Public API health passed"

    local bootstrap
    bootstrap="$(curl -sf -H "X-Tenant-Slug: hour-timebank" https://api.project-nexus.ie/api/v2/tenant/bootstrap || true)"
    if ! echo "$bootstrap" | grep -q '"tenant"'; then
        log_err "Public tenant bootstrap failed"
        return 1
    fi
    log_ok "Public tenant bootstrap passed"

    curl -sf https://app.project-nexus.ie/ >/dev/null
    log_ok "Public React frontend passed"

    curl -sf https://project-nexus.ie/ >/dev/null
    log_ok "Public sales site passed"
}

cmd_status() {
    local active api_port frontend_port sales_port
    active="$(read_active_color)"
    read -r api_port frontend_port sales_port < <(ports_for_color "$active")
    log_info "Active color: $active"
    log_info "Active ports: API=$api_port frontend=$frontend_port sales=$sales_port"
    if [ -n "$APACHE_ROUTES_FILE" ] && [ -f "$APACHE_ROUTES_FILE" ]; then
        log_info "Apache route file: $APACHE_ROUTES_FILE"
        sed -n '1,20p' "$APACHE_ROUTES_FILE"
    else
        log_warn "NEXUS_APACHE_ROUTES_FILE not configured or file does not exist"
    fi
}

cmd_deploy() {
    require_route_switching
    state_init
    state_set DEPLOY_SUCCESS 0
    state_set MAINTENANCE_ENABLED_BY_US 0
    check_lock
    create_lock
    trap cleanup EXIT

    local active target commit release_dir release_meta
    active="$(read_active_color)"
    target="$(inactive_color "$active")"

    log_info "Current active color: $active"
    log_info "Deploy target color: $target"

    prepare_release
    commit="$PREPARED_COMMIT"
    release_dir="$PREPARED_RELEASE_DIR"

    deploy_candidate "$target" "$release_dir" "$commit"
    smoke_color "$target"
    write_apache_routes "$target"
    write_color_release "$target" "$commit" "$release_dir"
    start_queue_for_color "$target" "$release_dir" "$commit"
    if ! post_cutover_smoke; then
        log_err "Public smoke failed after cutover; reverting traffic to $active"
        write_apache_routes "$active"
        if release_meta="$(read_color_release "$active")"; then
            commit="${release_meta%%|*}"
            release_dir="${release_meta#*|}"
            start_queue_for_color "$active" "$release_dir" "$commit"
        else
            docker start nexus-php-queue >/dev/null 2>&1 || true
            docker stop "$(container_name "$target" queue)" >/dev/null 2>&1 || true
        fi
        exit 1
    fi
    state_set DEPLOY_SUCCESS 1
}

cmd_rollback() {
    require_route_switching
    state_init
    state_set DEPLOY_SUCCESS 0
    state_set MAINTENANCE_ENABLED_BY_US 0
    check_lock
    create_lock
    trap cleanup EXIT

    local active target release_meta commit release_dir
    active="$(read_active_color)"
    target="$(inactive_color "$active")"

    log_warn "Rolling back from $active to $target"
    smoke_color "$target"
    write_apache_routes "$target"
    if release_meta="$(read_color_release "$target")"; then
        commit="${release_meta%%|*}"
        release_dir="${release_meta#*|}"
        start_queue_for_color "$target" "$release_dir" "$commit"
    else
        log_warn "No release metadata found for $target queue; trying legacy queue container"
        docker start nexus-php-queue >/dev/null 2>&1 || true
        docker stop "$(container_name "$active" queue)" >/dev/null 2>&1 || true
    fi
    post_cutover_smoke
    state_set DEPLOY_SUCCESS 1
}

case "${1:-}" in
    deploy) parse_flags "$@"; cmd_deploy ;;
    rollback) parse_flags "$@"; cmd_rollback ;;
    status) cmd_status ;;
    -h|--help|help|"") usage ;;
    *)
        usage
        exit 2
        ;;
esac
