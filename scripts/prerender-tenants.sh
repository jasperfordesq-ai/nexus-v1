#!/bin/bash
# =============================================================================
# Project NEXUS - Per-Tenant Server-Side Pre-Rendering
# =============================================================================
# Plans and refreshes only the tenant pages that need it:
#   - missing cache files
#   - cache files that reference frontend assets no longer in the active image
#   - explicitly scoped tenant/routes
#   - all pages when --force is supplied
#
# Usage:
#   sudo bash scripts/prerender-tenants.sh
#   sudo bash scripts/prerender-tenants.sh --force
#   sudo bash scripts/prerender-tenants.sh --tenant hour-timebank
#   sudo bash scripts/prerender-tenants.sh --routes /about,/privacy
#   sudo bash scripts/prerender-tenants.sh --tenant hour-timebank --routes /about
#
# Requirements:
#   - Target frontend container must be running
#   - API must be responding (maintenance mode OFF)
#   - Docker must be available (uses mcr.microsoft.com/playwright image)
# =============================================================================

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/nexus-php}"
PRERENDER_CONFIG_DIR="${PRERENDER_CONFIG_DIR:-$DEPLOY_DIR}"
PRERENDER_CODE_DIR="${PRERENDER_CODE_DIR:-$DEPLOY_DIR}"
NGINX_CONTAINER="${NGINX_CONTAINER:-nexus-react-prod}"
PRERENDER_DIR="/usr/share/nginx/html/prerendered"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.59.1-noble}"
WORKER_SCRIPT="$PRERENDER_CODE_DIR/scripts/prerender-worker.mjs"
OUTPUT_DIR="/tmp/nexus-prerender-$$"
WORK_DIR="$PRERENDER_CONFIG_DIR/.prerender-worker"
PRERENDER_CONCURRENCY="${PRERENDER_CONCURRENCY:-4}"
PRERENDER_FAILURE_BACKOFF_SECONDS="${PRERENDER_FAILURE_BACKOFF_SECONDS:-21600}"
LOCK_DIR="$PRERENDER_CONFIG_DIR/.prerender-lock"

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

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${CYAN}[INFO]${NC} $*"; }
log_ok()   { echo -e "${GREEN}[OK]${NC}   $*"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_err()  { echo -e "${RED}[FAIL]${NC} $*"; }

FILTER_TENANT=""
FILTER_ROUTES=""
FORCE_RENDER=0
DRY_RUN=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tenant) FILTER_TENANT="${2:-}"; shift 2 ;;
        --routes) FILTER_ROUTES="${2:-}"; shift 2 ;;
        --force) FORCE_RENDER=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        *) log_err "Unknown pre-render option: $1"; exit 2 ;;
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

cleanup() {
    rm -rf "$OUTPUT_DIR" 2>/dev/null || true
    if [ "${LOCK_ACQUIRED:-0}" -eq 1 ]; then
        rmdir "$LOCK_DIR" 2>/dev/null || true
    fi
}
trap cleanup EXIT

acquire_lock() {
    if mkdir "$LOCK_DIR" 2>/dev/null; then
        LOCK_ACQUIRED=1
        return 0
    fi

    log_err "Another pre-render run is already active ($LOCK_DIR exists)"
    exit 1
}

list_contains() {
    local NEEDLE="$1"
    local HAYSTACK="$2"
    printf '%s\n' "$HAYSTACK" | grep -Fxq "$NEEDLE"
}

append_query_param() {
    local URL="$1"
    local PARAM="$2"

    if [[ "$URL" == *\?* ]]; then
        echo "${URL}&${PARAM}"
    else
        echo "${URL}?${PARAM}"
    fi
}

json_escape() {
    local VALUE="$1"
    VALUE=${VALUE//\\/\\\\}
    VALUE=${VALUE//\"/\\\"}
    VALUE=${VALUE//$'\t'/\\t}
    VALUE=${VALUE//$'\r'/\\r}
    VALUE=${VALUE//$'\n'/\\n}
    printf '%s' "$VALUE"
}

route_cache_path() {
    local HOST="$1"
    local OUT_ROUTE="$2"

    if [ "$OUT_ROUTE" = "/" ]; then
        echo "${HOST}/index.html"
    else
        echo "${HOST}${OUT_ROUTE}/index.html"
    fi
}

get_frontend_host() {
    local FRONTEND_URL
    FRONTEND_URL=$(grep "^FRONTEND_URL=" "$PRERENDER_CONFIG_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || true)
    FRONTEND_URL="${FRONTEND_URL:-https://app.project-nexus.ie}"
    echo "$FRONTEND_URL" | sed 's|https\?://||' | sed 's|/.*||'
}

get_tenants() {
    local DB_USER DB_PASS DB_NAME QUERY
    DB_USER=$(grep "^DB_USER=" "$PRERENDER_CONFIG_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    DB_PASS=$(grep "^DB_PASS=" "$PRERENDER_CONFIG_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || true)
    DB_NAME=$(grep "^DB_NAME=" "$PRERENDER_CONFIG_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")

    # Tenant 1 is the platform/sales root. project-nexus.ie is served by the
    # sales-site container, not the React tenant frontend.
    QUERY="SELECT id, slug, COALESCE(domain, '') as domain FROM tenants WHERE is_active = 1 AND id <> 1"
    if [ -n "$FILTER_TENANT" ]; then
        QUERY="$QUERY AND slug = '$FILTER_TENANT'"
    fi
    QUERY="$QUERY ORDER BY id"

    docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db \
        mysql -u"$DB_USER" "$DB_NAME" -N -e "$QUERY" 2>/dev/null
}

load_existing_cache_paths() {
    docker exec -e PRERENDER_DIR="$PRERENDER_DIR" "$NGINX_CONTAINER" sh -c '
        find "$PRERENDER_DIR" -name index.html -type f 2>/dev/null \
            | sed "s#^$PRERENDER_DIR/##" \
            | sort
    ' 2>/dev/null || true
}

load_stale_cache_paths() {
    docker exec -e PRERENDER_DIR="$PRERENDER_DIR" "$NGINX_CONTAINER" sh -c '
        ROOT="/usr/share/nginx/html"
        find "$PRERENDER_DIR" -name index.html -type f 2>/dev/null | while IFS= read -r file; do
            missing=0
            grep -hoE "/assets/[^\"<>[:space:]]+\.(js|css)" "$file" 2>/dev/null \
                | sed "s/[?#].*$//" \
                | sort -u \
                | while IFS= read -r asset; do
                    [ -f "$ROOT$asset" ] || { echo missing > "/tmp/nexus-prerender-missing.$$"; break; }
                done

            if [ -f "/tmp/nexus-prerender-missing.$$" ]; then
                rm -f "/tmp/nexus-prerender-missing.$$"
                echo "${file#$PRERENDER_DIR/}"
            fi
        done | sort
    ' 2>/dev/null || true
}

load_recent_failure_paths() {
    docker exec \
        -e PRERENDER_DIR="$PRERENDER_DIR" \
        -e PRERENDER_FAILURE_BACKOFF_SECONDS="$PRERENDER_FAILURE_BACKOFF_SECONDS" \
        "$NGINX_CONTAINER" sh -c '
            FILE="$PRERENDER_DIR/.failures.tsv"
            NOW="$(date +%s)"
            [ -f "$FILE" ] || exit 0
            awk -v now="$NOW" -v ttl="$PRERENDER_FAILURE_BACKOFF_SECONDS" \
                '"'"'$1 ~ /^[0-9]+$/ && $2 != "" && now - $1 < ttl { print $2 }'"'"' "$FILE" \
                | sort -u
        ' 2>/dev/null || true
}

load_current_assets() {
    docker exec "$NGINX_CONTAINER" sh -c '
        find /usr/share/nginx/html/assets -type f \( -name "*.js" -o -name "*.css" \) 2>/dev/null \
            | sed "s#^/usr/share/nginx/html##" \
            | sort
    ' 2>/dev/null || true
}

should_render_cache_path() {
    local CACHE_PATH="$1"

    if [ "$FORCE_RENDER" -eq 1 ]; then
        return 0
    fi

    if [ -n "$FILTER_TENANT" ] || [ -n "$FILTER_ROUTES" ]; then
        return 0
    fi

    if ! list_contains "$CACHE_PATH" "$EXISTING_CACHE_PATHS"; then
        if list_contains "$CACHE_PATH" "$RECENT_FAILURE_PATHS"; then
            return 1
        fi
        return 0
    fi

    if list_contains "$CACHE_PATH" "$STALE_CACHE_PATHS"; then
        if list_contains "$CACHE_PATH" "$RECENT_FAILURE_PATHS"; then
            return 1
        fi
        return 0
    fi

    return 1
}

build_manifest() {
    local TENANTS="$1"
    local APP_HOST="$2"
    local MANIFEST_FILE="$3"
    local FIRST=1
    local COUNT=0
    local TOTAL=0

    {
        echo '{"urls":['

        while IFS=$'\t' read -r TENANT_ID SLUG DOMAIN; do
            [ -n "$TENANT_ID" ] || continue

            local HOST PREFIX
            if [ -n "$DOMAIN" ]; then
                HOST="$DOMAIN"
                PREFIX=""
            elif [ -n "$SLUG" ]; then
                HOST="$APP_HOST"
                PREFIX="/${SLUG}"
            else
                continue
            fi

            for ROUTE in "${PUBLIC_ROUTES[@]}"; do
                TOTAL=$((TOTAL + 1))
                local FULL_URL RENDER_URL OUT_ROUTE OUT_PATH CACHE_PATH
                FULL_URL="https://${HOST}${PREFIX}${ROUTE}"
                RENDER_URL=$(append_query_param "$FULL_URL" "nexus_prerender_bypass=1")
                OUT_ROUTE="${PREFIX}${ROUTE}"
                CACHE_PATH=$(route_cache_path "$HOST" "$OUT_ROUTE")
                OUT_PATH="/output/${CACHE_PATH}"

                if ! should_render_cache_path "$CACHE_PATH"; then
                    continue
                fi

                if [ "$FIRST" -eq 1 ]; then
                    FIRST=0
                else
                    echo ","
                fi

                printf '{"url":"%s","canonicalUrl":"%s","output":"%s","cachePath":"%s","tenantId":"%s","tenantSlug":"%s","host":"%s","route":"%s"}' \
                    "$(json_escape "$RENDER_URL")" \
                    "$(json_escape "$FULL_URL")" \
                    "$(json_escape "$OUT_PATH")" \
                    "$(json_escape "$CACHE_PATH")" \
                    "$(json_escape "$TENANT_ID")" \
                    "$(json_escape "$SLUG")" \
                    "$(json_escape "$HOST")" \
                    "$(json_escape "$ROUTE")"
                COUNT=$((COUNT + 1))
            done
        done <<< "$TENANTS"

        echo ']}'
    } > "$MANIFEST_FILE"

    SELECTED_COUNT="$COUNT"
    TOTAL_COUNT="$TOTAL"
}

validate_output_assets() {
    local CURRENT_ASSETS="$1"
    local INVALID=0

    while IFS= read -r FILE; do
        [ -n "$FILE" ] || continue
        local BAD_ASSET=""

        while IFS= read -r ASSET; do
            [ -n "$ASSET" ] || continue
            if ! list_contains "$ASSET" "$CURRENT_ASSETS"; then
                BAD_ASSET="$ASSET"
                break
            fi
        done < <(grep -hoE '/assets/[^"<>[:space:]]+\.(js|css)' "$FILE" 2>/dev/null | sed 's/[?#].*$//' | sort -u)

        if [ -n "$BAD_ASSET" ]; then
            log_warn "Discarding $(realpath --relative-to="$OUTPUT_DIR" "$FILE" 2>/dev/null || echo "$FILE") because it references missing asset $BAD_ASSET" >&2
            rm -f "$FILE"
            INVALID=$((INVALID + 1))
        fi
    done < <(find "$OUTPUT_DIR" -name index.html -type f 2>/dev/null)

    echo "$INVALID"
}

update_failure_registry() {
    local SUCCESS_FILE="$OUTPUT_DIR/.prerender-successes.txt"
    local FAILURE_FILE="$OUTPUT_DIR/.prerender-failures.txt"

    if [ ! -f "$SUCCESS_FILE" ] && [ ! -f "$FAILURE_FILE" ]; then
        return 0
    fi

    local TMP_DIR="${PRERENDER_DIR}/.failure-update-$(date +%s)-$$"
    docker exec "$NGINX_CONTAINER" mkdir -p "$TMP_DIR"

    [ -f "$SUCCESS_FILE" ] && docker cp "$SUCCESS_FILE" "${NGINX_CONTAINER}:${TMP_DIR}/successes.txt"
    [ -f "$FAILURE_FILE" ] && docker cp "$FAILURE_FILE" "${NGINX_CONTAINER}:${TMP_DIR}/failures.txt"

    docker exec \
        -e PRERENDER_DIR="$PRERENDER_DIR" \
        -e TMP_DIR="$TMP_DIR" \
        -e PRERENDER_FAILURE_BACKOFF_SECONDS="$PRERENDER_FAILURE_BACKOFF_SECONDS" \
        "$NGINX_CONTAINER" sh -c '
            set -eu
            REGISTRY="$PRERENDER_DIR/.failures.tsv"
            TMP_REGISTRY="$PRERENDER_DIR/.failures.tsv.tmp"
            NOW="$(date +%s)"
            touch "$REGISTRY"
            touch "$TMP_DIR/successes.txt" "$TMP_DIR/failures.txt"

            awk -v now="$NOW" -v ttl="$PRERENDER_FAILURE_BACKOFF_SECONDS" \
                '"'"'FNR == NR { success[$0]=1; next }
                   $1 ~ /^[0-9]+$/ && $2 != "" && now - $1 < ttl && !($2 in success) { print $0 }'"'"' \
                "$TMP_DIR/successes.txt" "$REGISTRY" > "$TMP_REGISTRY" || true

            while IFS= read -r cache_path; do
                [ -n "$cache_path" ] || continue
                printf "%s\t%s\n" "$NOW" "$cache_path" >> "$TMP_REGISTRY"
            done < "$TMP_DIR/failures.txt"

            awk '"'"'{ latest[$2]=$1 } END { for (path in latest) print latest[path] "\t" path }'"'"' "$TMP_REGISTRY" > "$REGISTRY"
            rm -rf "$TMP_DIR" "$TMP_REGISTRY"
        '
}

inject_rendered_pages() {
    local FILE_COUNT="$1"
    local INCOMING_DIR="${PRERENDER_DIR}/.incoming-$(date +%s)-$$"

    docker exec "$NGINX_CONTAINER" mkdir -p "$INCOMING_DIR"
    docker cp "$OUTPUT_DIR/." "${NGINX_CONTAINER}:${INCOMING_DIR}/"

    docker exec -e PRERENDER_DIR="$PRERENDER_DIR" -e INCOMING_DIR="$INCOMING_DIR" "$NGINX_CONTAINER" sh -c '
        set -eu

        find "$INCOMING_DIR" -name index.html -type f | while IFS= read -r file; do
            rel="${file#$INCOMING_DIR/}"
            dest="$PRERENDER_DIR/$rel"
            mkdir -p "$(dirname "$dest")"
            mv -f "$file" "$dest"
        done

        if [ -f "$INCOMING_DIR/.prerender-results.json" ]; then
            mv -f "$INCOMING_DIR/.prerender-results.json" "$PRERENDER_DIR/.last-run.json"
        fi

        rm -f "$INCOMING_DIR/.prerender-successes.txt" "$INCOMING_DIR/.prerender-failures.txt"

        if [ -f "$INCOMING_DIR/manifest.json" ]; then
            mv -f "$INCOMING_DIR/manifest.json" "$PRERENDER_DIR/.last-manifest.json"
        fi

        rm -rf "$INCOMING_DIR"
    '

    log_ok "$FILE_COUNT pre-rendered page(s) injected into $NGINX_CONTAINER"
}

main() {
    log_info "=== Per-Tenant Server-Side Pre-Rendering ==="
    echo ""

    if ! docker ps --format '{{.Names}}' | grep -q "^${NGINX_CONTAINER}$"; then
        log_err "Container $NGINX_CONTAINER is not running"
        exit 1
    fi

    if [ ! -f "$WORKER_SCRIPT" ]; then
        log_err "Worker script not found: $WORKER_SCRIPT"
        exit 1
    fi

    acquire_lock

    local TENANTS
    TENANTS=$(get_tenants)

    if [ -z "$TENANTS" ]; then
        log_err "No active tenant frontend targets found"
        exit 1
    fi

    mkdir -p "$OUTPUT_DIR"

    local APP_HOST MANIFEST_FILE
    APP_HOST=$(get_frontend_host)
    MANIFEST_FILE="$OUTPUT_DIR/manifest.json"

    log_info "Planning cache refresh..."
    EXISTING_CACHE_PATHS=$(load_existing_cache_paths)
    STALE_CACHE_PATHS=$(load_stale_cache_paths)
    RECENT_FAILURE_PATHS=$(load_recent_failure_paths)
    CURRENT_ASSETS=$(load_current_assets)

    if [ -z "$CURRENT_ASSETS" ]; then
        log_err "Could not read current frontend assets from $NGINX_CONTAINER"
        exit 1
    fi

    SELECTED_COUNT=0
    TOTAL_COUNT=0
    build_manifest "$TENANTS" "$APP_HOST" "$MANIFEST_FILE"

    log_info "Planned $SELECTED_COUNT page(s) to refresh out of $TOTAL_COUNT candidate page(s)"
    if [ -n "$RECENT_FAILURE_PATHS" ] && [ "$FORCE_RENDER" -ne 1 ] && [ -z "$FILTER_TENANT" ] && [ -z "$FILTER_ROUTES" ]; then
        log_warn "Skipping recent failed route(s) for ${PRERENDER_FAILURE_BACKOFF_SECONDS}s backoff; use --force or --tenant/--routes to retry immediately"
    fi

    if [ "$SELECTED_COUNT" -eq 0 ]; then
        log_ok "Pre-render cache is already current"
        exit 0
    fi

    if [ "$DRY_RUN" -eq 1 ]; then
        log_ok "Dry run complete; no pages rendered"
        exit 0
    fi

    log_info "Starting Playwright render container with concurrency $PRERENDER_CONCURRENCY..."

    mkdir -p "$WORK_DIR"
    cp "$WORKER_SCRIPT" "$WORK_DIR/worker.mjs"

    set +e
    docker run --rm -i \
        --network host \
        -v "$WORK_DIR:/work" \
        -v "$OUTPUT_DIR:/output" \
        -w /work \
        -e "PRERENDER_CONCURRENCY=$PRERENDER_CONCURRENCY" \
        "$PLAYWRIGHT_IMAGE" \
        bash -c "if [ ! -d node_modules/playwright ]; then npm init -y >/dev/null 2>&1 && npm install --no-save playwright >/dev/null 2>&1; fi; node worker.mjs" \
        < "$MANIFEST_FILE"
    local EXIT_CODE=$?
    set -e

    if [ $EXIT_CODE -ne 0 ]; then
        log_warn "Playwright worker exited with code $EXIT_CODE (successful pages will still be injected)"
    fi

    update_failure_registry

    local INVALID_COUNT
    INVALID_COUNT=$(validate_output_assets "$CURRENT_ASSETS")
    if [ "$INVALID_COUNT" -gt 0 ]; then
        log_warn "$INVALID_COUNT rendered page(s) discarded because their asset references were stale"
    fi

    local FILE_COUNT
    FILE_COUNT=$(find "$OUTPUT_DIR" -name index.html -type f 2>/dev/null | wc -l | tr -d ' ')

    if [ "$FILE_COUNT" -gt 0 ]; then
        log_info "Injecting rendered pages atomically into $NGINX_CONTAINER..."
        inject_rendered_pages "$FILE_COUNT"
        docker exec "$NGINX_CONTAINER" nginx -s reload 2>/dev/null || true
    else
        log_warn "No rendered pages were valid enough to inject"
    fi

    if [ "$FILE_COUNT" -eq 0 ]; then
        exit 1
    fi

    if [ $EXIT_CODE -ne 0 ] || [ "$INVALID_COUNT" -gt 0 ] || [ "$FILE_COUNT" -lt "$SELECTED_COUNT" ]; then
        log_warn "Pre-rendering completed with partial output: $FILE_COUNT/$SELECTED_COUNT page(s) refreshed"
        exit 2
    fi

    log_ok "Pre-rendering complete"
}

main "$@"
