#!/bin/bash
# =============================================================================
# Project NEXUS - Prerender.io Cache Recache
# =============================================================================
# Clears stale Prerender.io cache and triggers re-rendering of all public pages.
# Called automatically after every deployment by safe-deploy.sh.
#
# CRITICAL: Prerender.io caches whatever it sees — if it visits during
# maintenance mode, it caches the maintenance page with noindex/nofollow,
# causing Google to de-index all public pages. This script forces a fresh
# recache AFTER maintenance mode is off.
#
# Usage:
#   bash scripts/recache-prerender.sh              # Recache static + sitemap
#   bash scripts/recache-prerender.sh --domain X   # Recache specific domain
#   bash scripts/recache-prerender.sh --static-only # Static routes only (fast)
# =============================================================================

# --- Configuration ---
PRERENDER_TOKEN="IXb37NUbV39Zq6OKT9s6"
PRERENDER_API="https://api.prerender.io/recache"

# All public domains that serve the React frontend
DOMAINS=(
    "hour-timebank.ie"
    "app.project-nexus.ie"
)

# Core public routes that MUST always be recached (no auth required)
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

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# --- Parse args ---
FILTER_DOMAIN=""
STATIC_ONLY=0
while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)
            FILTER_DOMAIN="$2"
            shift 2
            ;;
        --static-only)
            STATIC_ONLY=1
            shift
            ;;
        *)
            shift
            ;;
    esac
done

if [ -n "$FILTER_DOMAIN" ]; then
    DOMAINS=("$FILTER_DOMAIN")
fi

# --- Helper: recache a single URL ---
recache_url() {
    local URL="$1"
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$PRERENDER_API" \
        -H "Content-Type: application/json" \
        --data "{\"prerenderToken\": \"${PRERENDER_TOKEN}\", \"url\": \"${URL}\"}" \
        2>/dev/null)

    HTTP_CODE=$(echo "$RESPONSE" | tail -1)
    TOTAL=$((TOTAL + 1))

    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "201" ]; then
        SUCCESS=$((SUCCESS + 1))
        return 0
    else
        echo -e "  ${RED}[FAIL]${NC} $URL (HTTP $HTTP_CODE)"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

# --- Recache ---
TOTAL=0
SUCCESS=0
FAILED=0

for DOMAIN in "${DOMAINS[@]}"; do
    # Phase 1: Always recache core static public routes
    echo -e "${CYAN}[INFO]${NC} Phase 1: Recaching ${#PUBLIC_ROUTES[@]} static pages for $DOMAIN..."

    for ROUTE in "${PUBLIC_ROUTES[@]}"; do
        recache_url "https://${DOMAIN}${ROUTE}"
    done

    echo -e "  ${GREEN}[OK]${NC}   $DOMAIN — ${#PUBLIC_ROUTES[@]} static pages queued"

    # Phase 2: Pull dynamic URLs from the sitemap and recache those too
    if [ "$STATIC_ONLY" -eq 0 ]; then
        echo -e "${CYAN}[INFO]${NC} Phase 2: Fetching sitemap for $DOMAIN..."

        SITEMAP_XML=$(curl -s "https://${DOMAIN}/sitemap.xml" 2>/dev/null)

        if [ -n "$SITEMAP_XML" ]; then
            # Extract URLs from sitemap XML (grep for <loc> tags) and strip
            # query strings + fragments so tracking params (utm_*, fbclid,
            # etc.) don't each produce a separately-cached prerender entry.
            SITEMAP_URLS=$(echo "$SITEMAP_XML" | grep -oP '(?<=<loc>)[^<]+' | sed 's/[?#].*$//' | sort -u | head -200)
            URL_COUNT=$(echo "$SITEMAP_URLS" | wc -l)

            echo -e "  ${CYAN}[INFO]${NC} Found $URL_COUNT URLs in sitemap, recaching..."

            while IFS= read -r SITEMAP_URL; do
                # Skip URLs we already recached in Phase 1
                SKIP=0
                for ROUTE in "${PUBLIC_ROUTES[@]}"; do
                    if [ "https://${DOMAIN}${ROUTE}" = "$SITEMAP_URL" ]; then
                        SKIP=1
                        break
                    fi
                done

                if [ "$SKIP" -eq 0 ] && [ -n "$SITEMAP_URL" ]; then
                    recache_url "$SITEMAP_URL"
                fi
            done <<< "$SITEMAP_URLS"

            echo -e "  ${GREEN}[OK]${NC}   $DOMAIN — sitemap URLs queued"
        else
            echo -e "  ${YELLOW}[WARN]${NC} Could not fetch sitemap for $DOMAIN"
        fi
    fi
done

echo ""
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}[OK]${NC}   All $SUCCESS pages queued for recache"
else
    echo -e "${YELLOW}[WARN]${NC} $SUCCESS succeeded, $FAILED failed out of $TOTAL total"
fi
