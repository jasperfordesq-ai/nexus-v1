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

# Auto-detect the active react container. Blue/green deploys leave only one
# color running at a time; legacy single-color setups use `nexus-react-prod`.
# Override with NGINX_CONTAINER=... if you need to target a specific one
# (e.g. warming the inactive color before a cutover).
if [ -z "${NGINX_CONTAINER:-}" ]; then
    NGINX_CONTAINER=$(docker ps --format '{{.Names}}' 2>/dev/null \
        | grep -E '^nexus-(blue|green)-react$' | head -1 || true)
    NGINX_CONTAINER="${NGINX_CONTAINER:-nexus-react-prod}"
fi
PRERENDER_DIR="/usr/share/nginx/html/prerendered"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.59.1-noble}"
WORKER_SCRIPT="$PRERENDER_CODE_DIR/scripts/prerender-worker.mjs"
OUTPUT_DIR="/tmp/nexus-prerender-$$"
WORK_DIR="$PRERENDER_CONFIG_DIR/.prerender-worker"
PRERENDER_CONCURRENCY="${PRERENDER_CONCURRENCY:-4}"
PRERENDER_FAILURE_BACKOFF_SECONDS="${PRERENDER_FAILURE_BACKOFF_SECONDS:-21600}"
LOCK_DIR="$PRERENDER_CONFIG_DIR/.prerender-lock"
LOCK_PID_FILE="$LOCK_DIR/pid"
LOCK_TAKEOVER_GRACE_SECONDS="${LOCK_TAKEOVER_GRACE_SECONDS:-10}"
# Stable container name so supersession (and crash recovery) can kill the
# Playwright worker without having to discover its container ID.
PRERENDER_DOCKER_NAME="${PRERENDER_DOCKER_NAME:-nexus-prerender-worker}"

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
    "/trust-and-safety"
    "/acceptable-use"
    "/legal"
    "/timebanking-guide"
    "/platform/terms"
    "/platform/privacy"
    "/platform/disclaimer"
    "/resources"
    "/features"
    "/changelog"
    # Feature-gated public discovery routes. These were missing from the
    # prerender list, so unauthenticated crawlers were getting an empty SPA
    # shell on important discovery pages (2026-05-13 audit).
    "/events"
    "/groups"
    "/jobs"
    "/marketplace"
    "/volunteering"
    "/pilot-inquiry"
    "/pilot-apply"
    "/developers"
    # Tenant-gated routes (TenantSlugGate). For tenants that don't match the
    # gate, React Router renders a fallback — that fallback is what gets
    # prerendered. Either way, bots no longer hit an empty SPA shell.
    "/partner"
    "/social-prescribing"
    "/impact-report"
    "/impact-summary"
    "/strategic-plan"
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

# Structured event log — JSONL, one line per event. Tail with:
#   sudo tail -f /opt/nexus-php/logs/prerender-events.jsonl | jq .
# Aggregate supersession rate, skip-on-clean rate, render durations from this.
PRERENDER_EVENT_LOG="${PRERENDER_EVENT_LOG:-$PRERENDER_CONFIG_DIR/logs/prerender-events.jsonl}"
emit_event() {
    local EVENT="$1"; shift
    local EXTRA="${1:-}"
    local DIR
    DIR="$(dirname "$PRERENDER_EVENT_LOG")"
    [ -d "$DIR" ] || mkdir -p "$DIR" 2>/dev/null || return 0
    local LINE
    LINE="$(printf '{"ts":"%s","event":"%s","pid":%d,"host":"%s","commit":"%s"%s}' \
        "$(date -Is)" \
        "$(json_escape "$EVENT")" \
        "$$" \
        "$(json_escape "$(hostname 2>/dev/null || echo unknown)")" \
        "$(json_escape "$(git -C "$PRERENDER_CODE_DIR" rev-parse --short HEAD 2>/dev/null || echo unknown)")" \
        "${EXTRA:+,$EXTRA}")"
    printf '%s\n' "$LINE" >> "$PRERENDER_EVENT_LOG" 2>/dev/null || true
}

FILTER_TENANT=""
FILTER_ROUTES=""
FORCE_RENDER=0
DRY_RUN=0
# Default: ask the PHP container for the dynamic plan (static floor + sitemap).
# Set NEXUS_PRERENDER_NO_SITEMAP=1 to fall back to the hardcoded PUBLIC_ROUTES
# floor only — useful as an emergency switch if the planner misbehaves.
USE_SITEMAP_PLAN=1
if [ "${NEXUS_PRERENDER_NO_SITEMAP:-0}" = "1" ]; then
    USE_SITEMAP_PLAN=0
fi

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tenant) FILTER_TENANT="${2:-}"; shift 2 ;;
        --routes) FILTER_ROUTES="${2:-}"; shift 2 ;;
        --force) FORCE_RENDER=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --no-sitemap) USE_SITEMAP_PLAN=0; shift ;;
        *) log_err "Unknown pre-render option: $1"; exit 2 ;;
    esac
done

if [ -n "$FILTER_ROUTES" ]; then
    # Explicit --routes overrides the sitemap plan completely.
    IFS=',' read -r -a PUBLIC_ROUTES <<< "$FILTER_ROUTES"
    USE_SITEMAP_PLAN=0
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
    # Stop the Playwright container if it's still running (we own this name
    # and only one prerender holds the lock at a time, so this is safe).
    docker stop "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
    docker rm -f "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
    rm -rf "$OUTPUT_DIR" 2>/dev/null || true
    if [ "${LOCK_ACQUIRED:-0}" -eq 1 ]; then
        rm -rf "$LOCK_DIR" 2>/dev/null || true
    fi
}
trap cleanup EXIT INT TERM

acquire_lock() {
    if mkdir "$LOCK_DIR" 2>/dev/null; then
        echo "$$" > "$LOCK_PID_FILE" 2>/dev/null || true
        LOCK_ACQUIRED=1
        return 0
    fi

    # Lock-or-cancel: a fresh deploy must never be blocked by an in-flight
    # prerender from the previous deploy. The previous run's output would also
    # need to be re-done against the new build's assets, so superseding it is
    # strictly better than waiting.
    local PRIOR_PID=""
    if [ -f "$LOCK_PID_FILE" ]; then
        PRIOR_PID="$(cat "$LOCK_PID_FILE" 2>/dev/null || true)"
    fi

    if [ -n "$PRIOR_PID" ] && [[ "$PRIOR_PID" =~ ^[0-9]+$ ]] && kill -0 "$PRIOR_PID" 2>/dev/null; then
        log_warn "Superseding in-flight pre-render (pid $PRIOR_PID)"
        emit_event "supersede" "\"prior_pid\":$PRIOR_PID,\"reason\":\"newer_deploy\""
        # `docker kill` (SIGKILL) instead of `docker stop` (SIGTERM + 10s grace):
        # the worker has nothing to clean up, and we want the container gone
        # immediately so the next deploy can claim the --name.
        docker kill "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
        docker rm -f "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
        # Then SIGTERM the bash that started it (it might still be in the
        # post-render injection step, which is cheap to interrupt).
        kill -TERM "$PRIOR_PID" 2>/dev/null || true
        local WAITED=0
        while kill -0 "$PRIOR_PID" 2>/dev/null && [ "$WAITED" -lt "$LOCK_TAKEOVER_GRACE_SECONDS" ]; do
            sleep 1
            WAITED=$((WAITED + 1))
        done
        if kill -0 "$PRIOR_PID" 2>/dev/null; then
            log_warn "Prior pre-render did not exit within ${LOCK_TAKEOVER_GRACE_SECONDS}s; sending SIGKILL"
            kill -KILL "$PRIOR_PID" 2>/dev/null || true
            sleep 1
        fi
    elif [ -n "$PRIOR_PID" ]; then
        log_warn "Stale lock from pid $PRIOR_PID (no longer running); reclaiming"
        emit_event "reclaim_stale_lock" "\"prior_pid\":$PRIOR_PID"
        docker kill "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
        docker rm -f "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
    else
        log_warn "Stale lock with no pid recorded; reclaiming"
        emit_event "reclaim_orphan_lock"
        docker kill "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
        docker rm -f "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true
    fi

    rm -rf "$LOCK_DIR" 2>/dev/null || true
    if mkdir "$LOCK_DIR" 2>/dev/null; then
        echo "$$" > "$LOCK_PID_FILE" 2>/dev/null || true
        LOCK_ACQUIRED=1
        return 0
    fi

    log_err "Could not acquire pre-render lock at $LOCK_DIR after takeover attempt"
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

# Ask the PHP container for the full per-tenant route plan (static floor +
# sitemap-derived URLs). Output: JSON to stdout. Empty string on any failure
# so the caller can fall back to the hardcoded list.
get_route_plan() {
    local APP_CONTAINER ARGS
    APP_CONTAINER=$(docker ps --format '{{.Names}}' 2>/dev/null \
        | grep -E '^nexus-(blue|green)-php-app$' \
        | head -1 || true)
    APP_CONTAINER="${APP_CONTAINER:-nexus-php-app}"

    if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
        return 0
    fi

    ARGS=(php artisan prerender:plan-routes)
    if [ -n "$FILTER_TENANT" ]; then ARGS+=(--tenant="$FILTER_TENANT"); fi

    # Strip non-JSON stderr; only the last JSON object line counts.
    docker exec "$APP_CONTAINER" "${ARGS[@]}" 2>/dev/null \
        | awk '/^\{/{ json=$0 } END{ print json }' || true
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
    # No-op since the introduction of bot-only prerender serving in nginx.
    # Snapshots are now served exclusively to crawlers, which do not execute
    # JavaScript, so build-hashed asset URLs in stale snapshots cannot break
    # any client. A snapshot stays valid until its content (DB-driven) changes.
    # Use --force to rerender unconditionally.
    return 0
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

# Emit one manifest entry. All callers use this so format stays consistent.
emit_manifest_entry() {
    local HOST="$1" PREFIX="$2" ROUTE="$3" TENANT_ID="$4" SLUG="$5"
    local FULL_URL RENDER_URL OUT_ROUTE OUT_PATH CACHE_PATH
    FULL_URL="https://${HOST}${PREFIX}${ROUTE}"
    RENDER_URL=$(append_query_param "$FULL_URL" "nexus_prerender_bypass=1")
    OUT_ROUTE="${PREFIX}${ROUTE}"
    CACHE_PATH=$(route_cache_path "$HOST" "$OUT_ROUTE")
    OUT_PATH="/output/${CACHE_PATH}"

    if ! should_render_cache_path "$CACHE_PATH"; then
        return 1
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
    return 0
}

# Legacy path: build a manifest from PUBLIC_ROUTES × tenants. Used when the
# sitemap planner is unavailable or the operator passed --routes/--no-sitemap.
build_manifest_static() {
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
                if emit_manifest_entry "$HOST" "$PREFIX" "$ROUTE" "$TENANT_ID" "$SLUG"; then
                    COUNT=$((COUNT + 1))
                fi
            done
        done <<< "$TENANTS"

        echo ']}'
    } > "$MANIFEST_FILE"

    SELECTED_COUNT="$COUNT"
    TOTAL_COUNT="$TOTAL"
}

# Sitemap path: build a manifest from the planner's per-tenant route lists.
# Parses the JSON with python3 (always available on the host's playwright base).
# Each tenant gets the union of the static floor + dynamic sitemap URLs.
build_manifest_from_plan() {
    local PLAN_JSON="$1"
    local MANIFEST_FILE="$2"
    local PARSED
    # Heredoc-in-command-substitution gotcha: bash requires the heredoc
    # delimiter to be the very last token on the line that opens it — adding
    # `2>/dev/null || true` there triggers a parse error. Wrap the whole
    # python call in an outer subshell with `|| true` to swallow non-zero
    # exits without confusing the parser.
    PARSED=$( ( echo "$PLAN_JSON" | python3 - 2>/dev/null <<'PYEOF'
import json, sys
try:
    plan = json.load(sys.stdin)
except Exception:
    sys.exit(0)
for t in plan.get('tenants', []):
    tid = str(t.get('tenant_id', ''))
    slug = t.get('slug', '') or ''
    host = t.get('host', '') or ''
    prefix = t.get('prefix', '') or ''
    if not (tid and host):
        continue
    for r in t.get('routes', []):
        if not r or not r.startswith('/'):
            continue
        print(f"{tid}\t{slug}\t{host}\t{prefix}\t{r}")
PYEOF
    ) || true )

    if [ -z "$PARSED" ]; then
        return 1
    fi

    local FIRST=1
    local COUNT=0
    local TOTAL=0

    {
        echo '{"urls":['
        while IFS=$'\t' read -r TENANT_ID SLUG HOST PREFIX ROUTE; do
            [ -n "$TENANT_ID" ] && [ -n "$HOST" ] || continue
            TOTAL=$((TOTAL + 1))
            if emit_manifest_entry "$HOST" "$PREFIX" "$ROUTE" "$TENANT_ID" "$SLUG"; then
                COUNT=$((COUNT + 1))
            fi
        done <<< "$PARSED"
        echo ']}'
    } > "$MANIFEST_FILE"

    SELECTED_COUNT="$COUNT"
    TOTAL_COUNT="$TOTAL"
    return 0
}

build_manifest() {
    local TENANTS="$1"
    local APP_HOST="$2"
    local MANIFEST_FILE="$3"

    if [ "$USE_SITEMAP_PLAN" -eq 1 ]; then
        local PLAN_JSON
        PLAN_JSON="$(get_route_plan)"
        if [ -n "$PLAN_JSON" ]; then
            if build_manifest_from_plan "$PLAN_JSON" "$MANIFEST_FILE"; then
                log_info "Route plan: sitemap-driven ($SELECTED_COUNT selected / $TOTAL_COUNT candidate)"
                emit_event "plan_source" "\"source\":\"sitemap\",\"selected\":$SELECTED_COUNT,\"total\":$TOTAL_COUNT"
                return 0
            fi
            log_warn "Sitemap plan parse failed; falling back to static route list"
        else
            log_warn "Sitemap plan unavailable (php container or artisan failed); falling back to static route list"
        fi
    fi

    build_manifest_static "$TENANTS" "$APP_HOST" "$MANIFEST_FILE"
    log_info "Route plan: static floor only ($SELECTED_COUNT selected / $TOTAL_COUNT candidate)"
    emit_event "plan_source" "\"source\":\"static\",\"selected\":$SELECTED_COUNT,\"total\":$TOTAL_COUNT"
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

        # Move _status sidecars (status code propagation, Phase 1.2) alongside
        # their index.html siblings.
        find "$INCOMING_DIR" -name _status -type f | while IFS= read -r file; do
            rel="${file#$INCOMING_DIR/}"
            dest="$PRERENDER_DIR/$rel"
            mkdir -p "$(dirname "$dest")"
            mv -f "$file" "$dest"
        done

        # Move index.md sidecars (AI-friendly Markdown variant, Round 5).
        find "$INCOMING_DIR" -name "index.md" -type f | while IFS= read -r file; do
            rel="${file#$INCOMING_DIR/}"
            dest="$PRERENDER_DIR/$rel"
            mkdir -p "$(dirname "$dest")"
            mv -f "$file" "$dest"
        done

        if [ -f "$INCOMING_DIR/.prerender-results.json" ]; then
            mv -f "$INCOMING_DIR/.prerender-results.json" "$PRERENDER_DIR/.last-run.json"
        fi
        if [ -f "$INCOMING_DIR/.prerender-status-overrides.json" ]; then
            mv -f "$INCOMING_DIR/.prerender-status-overrides.json" "$PRERENDER_DIR/.status-overrides.json"
        fi

        rm -f "$INCOMING_DIR/.prerender-successes.txt" "$INCOMING_DIR/.prerender-failures.txt"

        if [ -f "$INCOMING_DIR/manifest.json" ]; then
            mv -f "$INCOMING_DIR/manifest.json" "$PRERENDER_DIR/.last-manifest.json"
        fi

        rm -rf "$INCOMING_DIR"
    '

    log_ok "$FILE_COUNT pre-rendered page(s) injected into $NGINX_CONTAINER"
}

# Generate a per-route nginx override include from the worker's status manifest.
# The include defines a map that drives the error_page fallback in the server
# block; reloading nginx after we write it activates the new status routing.
#
# Output file inside the nginx container:
#   /etc/nginx/conf.d/prerender-status-overrides.conf
#
# Shape:
#   map "$host$uri" $nexus_prerender_status_override {
#       default "";
#       "hour-timebank.ie/community-not-found/" "404";
#       ...
#   }
write_nginx_status_overrides() {
    local MANIFEST_PATH_IN_NGINX="$PRERENDER_DIR/.status-overrides.json"
    # The file is `include`d from inside a `map` block in nginx.bluegreen.conf,
    # so it must contain ONLY data lines: `"key" "value";`. The map default is
    # already set ("") in the parent block.
    local LIST_PATH="/etc/nginx/prerender-status-overrides.list"
    local BACKUP_PATH="${LIST_PATH}.bak"

    if ! docker exec "$NGINX_CONTAINER" test -f "$MANIFEST_PATH_IN_NGINX" 2>/dev/null; then
        # No manifest from this run — leave any prior list in place. If the
        # last run had non-200 routes and they were re-rendered as 200 this
        # time, the snapshots' missing `_status` sidecar already means the
        # bot won't see the old status (snapshot body wins via try_files).
        return 0
    fi

    # The nginx alpine image has no python3, so we generate the list on the
    # HOST (which always has it) and then `docker cp` the result into the
    # container. Pull the manifest out first via `docker cp` from the volume.
    local STAGE_DIR
    STAGE_DIR="$(mktemp -d -t prerender-status.XXXXXX)" || return 1
    local HOST_MANIFEST="$STAGE_DIR/manifest.json"
    local HOST_LIST="$STAGE_DIR/overrides.list"

    docker cp "${NGINX_CONTAINER}:${MANIFEST_PATH_IN_NGINX}" "$HOST_MANIFEST" 2>/dev/null || {
        rm -rf "$STAGE_DIR"
        return 0
    }

    {
        echo "# Auto-generated by scripts/prerender-tenants.sh — do not edit."
        echo "# Data lines for the map in nginx.bluegreen.conf."
        python3 - "$HOST_MANIFEST" <<'PYEOF' || true
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(0)
for cache_path, meta in data.items():
    if not isinstance(meta, dict):
        continue
    status = meta.get("status")
    if status in (200, None):
        continue
    if not isinstance(cache_path, str) or "/" not in cache_path:
        continue
    if not cache_path.endswith("/index.html"):
        continue
    key = cache_path[:-len("index.html")]
    if not key or any(c in key for c in ["\"", "\\", "\n", "\r"]):
        continue
    # Two flavours of $uri normalisation: "host/route/" and "host/route".
    # nginx may present either depending on whether the request URI ends
    # with a slash. Emit both so the map matches.
    base = key.rstrip("/")
    if "/" in base:
        host, _, route = base.partition("/")
        route = "/" + route
    else:
        host, route = base, "/"
    full = f"{host}{route}"
    print(f"    \"{full}\" \"{status}\";")
    if not route.endswith("/"):
        print(f"    \"{full}/\" \"{status}\";")
PYEOF
    } > "$HOST_LIST"

    # Snapshot the current list so we can revert on nginx -t failure.
    docker exec "$NGINX_CONTAINER" sh -c "[ -f '$LIST_PATH' ] && cp -f '$LIST_PATH' '$BACKUP_PATH' || true" 2>/dev/null
    docker cp "$HOST_LIST" "${NGINX_CONTAINER}:${LIST_PATH}" 2>/dev/null
    rm -rf "$STAGE_DIR"

    # Validate. nginx -t loads the whole config including our list file;
    # if it fails (malformed line, etc) we revert and skip the reload.
    if docker exec "$NGINX_CONTAINER" nginx -t >/dev/null 2>&1; then
        docker exec "$NGINX_CONTAINER" nginx -s reload >/dev/null 2>&1 || true
        docker exec "$NGINX_CONTAINER" rm -f "$BACKUP_PATH" 2>/dev/null || true
        emit_event "status_overrides_applied"
    else
        log_warn "nginx -t failed after writing status overrides; reverting"
        docker exec "$NGINX_CONTAINER" sh -c "[ -f '$BACKUP_PATH' ] && mv -f '$BACKUP_PATH' '$LIST_PATH' || true" 2>/dev/null
        emit_event "status_overrides_revert" "\"reason\":\"nginx_t_failed\""
    fi
}

main() {
    local START_TS
    START_TS="$(date +%s)"
    log_info "=== Per-Tenant Server-Side Pre-Rendering ==="
    echo ""
    emit_event "start" "\"force\":$FORCE_RENDER,\"tenant\":\"$(json_escape "${FILTER_TENANT:-}")\",\"routes\":\"$(json_escape "${FILTER_ROUTES:-}")\""

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

    # Pre-emptively remove any container left behind from a prior run (defensive
    # — cleanup() and lock takeover both also do this, but a stale rm here lets
    # us recover from a corrupted state without operator intervention).
    docker rm -f "$PRERENDER_DOCKER_NAME" >/dev/null 2>&1 || true

    set +e
    docker run --rm -i \
        --name "$PRERENDER_DOCKER_NAME" \
        --network host \
        -v "$WORK_DIR:/work" \
        -v "$OUTPUT_DIR:/output" \
        -w /work \
        -e "PRERENDER_CONCURRENCY=$PRERENDER_CONCURRENCY" \
        -e "PRERENDER_VIEWPORT=${PRERENDER_VIEWPORT:-desktop}" \
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
        # Update the per-route status-code override map before reloading so
        # the reload picks up both the new snapshots and their status routing.
        write_nginx_status_overrides
        docker exec "$NGINX_CONTAINER" nginx -s reload 2>/dev/null || true
    else
        log_warn "No rendered pages were valid enough to inject"
    fi

    local DURATION
    DURATION=$(($(date +%s) - START_TS))

    if [ "$FILE_COUNT" -eq 0 ]; then
        emit_event "fail" "\"reason\":\"no_valid_output\",\"selected\":$SELECTED_COUNT,\"duration_s\":$DURATION,\"worker_exit\":$EXIT_CODE"
        exit 1
    fi

    if [ $EXIT_CODE -ne 0 ] || [ "$INVALID_COUNT" -gt 0 ] || [ "$FILE_COUNT" -lt "$SELECTED_COUNT" ]; then
        log_warn "Pre-rendering completed with partial output: $FILE_COUNT/$SELECTED_COUNT page(s) refreshed"
        emit_event "partial" "\"rendered\":$FILE_COUNT,\"selected\":$SELECTED_COUNT,\"invalid\":$INVALID_COUNT,\"duration_s\":$DURATION,\"worker_exit\":$EXIT_CODE"
        exit 2
    fi

    log_ok "Pre-rendering complete"
    emit_event "success" "\"rendered\":$FILE_COUNT,\"duration_s\":$DURATION"
}

# Allow this script to be sourced for testing without invoking main(). When
# executed directly, BASH_SOURCE[0] equals $0 and main runs normally.
if [ "${BASH_SOURCE[0]:-$0}" = "${0}" ]; then
    main "$@"
fi
