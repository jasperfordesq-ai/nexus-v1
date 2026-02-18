#!/bin/bash
# =============================================================================
# Project NEXUS - Clean Deploy Script
# =============================================================================
# Run ON THE PRODUCTION SERVER: sudo bash scripts/deploy-clean.sh
#
# Pulls latest code, rebuilds the React frontend and PHP app images
# (--no-cache), restarts containers, and runs health checks.
#
# Does NOT touch the database, Redis, other projects, or prune anything.
# =============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_ok()   { echo -e "  ${GREEN}[OK]${NC} $1"; }
log_info() { echo -e "  ${CYAN}[INFO]${NC} $1"; }
log_err()  { echo -e "  ${RED}[FAIL]${NC} $1"; }

DEPLOY_DIR="/opt/nexus-php"
cd "$DEPLOY_DIR" || { echo "Cannot cd to $DEPLOY_DIR"; exit 1; }

STARTED=$(date +%s)

echo -e "\n${BOLD}${CYAN}=== Project NEXUS — Clean Deploy ===${NC}\n"
log_info "Started at $(date)"
log_info "Current commit: $(git log --oneline -1 2>/dev/null || echo 'unknown')"

# --- Phase 1: Git Pull ---
echo -e "\n${BOLD}Step 1: Git pull${NC}"
git pull origin main
log_ok "Git pull complete"
log_info "Now at: $(git log --oneline -1)"

# --- Phase 2: Restore production compose ---
echo -e "\n${BOLD}Step 2: Restore compose.yml${NC}"
cp compose.prod.yml compose.yml
log_ok "compose.prod.yml → compose.yml"

# --- Phase 3: Rebuild frontend ---
echo -e "\n${BOLD}Step 3: Rebuild React frontend (--no-cache)${NC}"
docker compose build --no-cache frontend
log_ok "Frontend image rebuilt"

# --- Phase 4: Rebuild PHP app ---
echo -e "\n${BOLD}Step 4: Rebuild PHP app (--no-cache)${NC}"
docker compose build --no-cache app
log_ok "PHP app image rebuilt"

# --- Phase 5: Restart ---
echo -e "\n${BOLD}Step 5: Restart containers${NC}"
docker compose up -d
log_ok "Containers restarted"

# --- Phase 6: Health checks ---
echo -e "\n${BOLD}Step 6: Health checks (waiting 10s)${NC}"
sleep 10

PASS=0
FAIL=0

check() {
    local name="$1" url="$2"
    local code
    code=$(curl -sf -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")
    if [ "$code" = "200" ]; then
        log_ok "$name — HTTP $code"
        PASS=$((PASS + 1))
    else
        log_err "$name — HTTP $code"
        FAIL=$((FAIL + 1))
    fi
}

check "PHP API"          "http://127.0.0.1:8090/health.php"
check "React Frontend"   "http://127.0.0.1:3000/"

# --- Summary ---
ENDED=$(date +%s)
ELAPSED=$((ENDED - STARTED))

echo -e "\n${BOLD}${CYAN}=== Deploy Summary ===${NC}"
echo -e "  Commit:  $(git log --oneline -1)"
echo -e "  Time:    ${ELAPSED}s"
echo -e "  Health:  ${GREEN}${PASS} passed${NC}, ${RED}${FAIL} failed${NC}"

if [ "$FAIL" -eq 0 ]; then
    echo -e "\n  ${GREEN}${BOLD}Deploy successful.${NC}\n"
else
    echo -e "\n  ${RED}${BOLD}Deploy completed with failures — check logs:${NC}"
    echo -e "  sudo docker compose logs --tail=30 app"
    echo -e "  sudo docker compose logs --tail=30 frontend\n"
    exit 1
fi
