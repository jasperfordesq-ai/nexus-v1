#!/bin/bash
# =============================================================================
# Project NEXUS - Nuclear Clean Deploy Script
# =============================================================================
# Run ON THE PRODUCTION SERVER: sudo bash scripts/nuclear-deploy.sh
#
# This destroys ALL Docker images and build cache, then rebuilds everything
# from scratch. Database, Redis, and uploads volumes are PRESERVED.
#
# Use this when you suspect stale Docker layers are serving old code.
# =============================================================================

set -e

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

log_header() { echo -e "\n${BOLD}${CYAN}=== $1 ===${NC}\n"; }
log_ok()     { echo -e "  ${GREEN}[OK]${NC} $1"; }
log_warn()   { echo -e "  ${YELLOW}[WARN]${NC} $1"; }
log_err()    { echo -e "  ${RED}[FAIL]${NC} $1"; }
log_info()   { echo -e "  ${CYAN}[INFO]${NC} $1"; }

# --- Must run from /opt/nexus-php ---
DEPLOY_DIR="/opt/nexus-php"
cd "$DEPLOY_DIR" || { echo "Cannot cd to $DEPLOY_DIR"; exit 1; }

# =============================================================================
# WARNING
# =============================================================================
log_header "NUCLEAR CLEAN DEPLOY"
echo -e "${RED}${BOLD}"
echo "  ╔══════════════════════════════════════════════════════════════╗"
echo "  ║                    ⚠ DESTRUCTIVE OPERATION ⚠                ║"
echo "  ║                                                              ║"
echo "  ║  This will:                                                  ║"
echo "  ║    • Stop ALL containers                                     ║"
echo "  ║    • Delete ALL Docker images and build cache                ║"
echo "  ║    • Git reset --hard to origin/main                         ║"
echo "  ║    • Rebuild ALL images from scratch (no cache)              ║"
echo "  ║                                                              ║"
echo "  ║  PRESERVED: Database, Redis, Uploads, .env                   ║"
echo "  ╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

read -p "Type 'NUCLEAR' to confirm: " confirm
if [ "$confirm" != "NUCLEAR" ]; then
    echo "Aborted."
    exit 0
fi

STARTED=$(date +%s)

# =============================================================================
# Phase 1: Safety Checks & Backups
# =============================================================================
log_header "Phase 1: Safety Checks & Backups"

# Check critical files
if [ ! -f .env ]; then
    log_err ".env is MISSING — cannot proceed without secrets!"
    exit 1
fi
log_ok ".env exists"

if [ ! -f compose.prod.yml ]; then
    log_err "compose.prod.yml is MISSING — cannot proceed!"
    exit 1
fi
log_ok "compose.prod.yml exists"

# Backup .env
ENV_BACKUP=".env.nuclear-backup-$(date +%Y%m%d-%H%M%S)"
cp .env "$ENV_BACKUP"
log_ok ".env backed up to $ENV_BACKUP"

# Backup compose.yml (if different from prod)
if [ -f compose.yml ]; then
    cp compose.yml "compose.yml.pre-nuclear-backup"
    log_ok "compose.yml backed up"
fi

# Note current state
log_info "Current git commit: $(git log --oneline -1 2>/dev/null || echo 'unknown')"
log_info "Current containers:"
docker ps --format "  {{.Names}}: {{.Status}}" 2>/dev/null || echo "  (none running)"

# =============================================================================
# Phase 2: Stop Everything
# =============================================================================
log_header "Phase 2: Stopping All Containers"

docker compose down 2>/dev/null || true
log_ok "docker compose down"

# Double-check nothing is lingering
REMAINING=$(docker ps -q --filter "name=nexus-" 2>/dev/null | wc -l)
if [ "$REMAINING" -gt 0 ]; then
    log_warn "Found $REMAINING lingering nexus containers — force stopping..."
    docker ps -q --filter "name=nexus-" | xargs docker stop 2>/dev/null || true
    docker ps -q --filter "name=nexus-" | xargs docker rm -f 2>/dev/null || true
fi
log_ok "All containers stopped"

# =============================================================================
# Phase 3: Nuke Docker Cache (Preserve Volumes!)
# =============================================================================
log_header "Phase 3: Pruning Docker Images & Build Cache"

log_info "Removing ALL unused images..."
docker image prune -af 2>/dev/null || true
log_ok "Unused images pruned"

log_info "Removing ALL build cache..."
docker builder prune -af 2>/dev/null || true
log_ok "Build cache pruned"

log_info "Removing dangling networks..."
docker network prune -f 2>/dev/null || true
log_ok "Networks pruned"

# Explicitly remove nexus images if still present
log_info "Force-removing any remaining nexus images..."
docker images --format "{{.Repository}}:{{.Tag}}" | grep -i nexus | xargs docker rmi -f 2>/dev/null || true
log_ok "Nexus images removed"

log_info "Docker disk usage after prune:"
docker system df 2>/dev/null || true

# Verify volumes survived
echo ""
log_info "Checking volumes (must be preserved)..."
for vol in nexus-php-db-data nexus-php-redis-data nexus-php-uploads; do
    if docker volume inspect "$vol" &>/dev/null; then
        log_ok "Volume $vol exists"
    else
        log_warn "Volume $vol not found (will be created on startup)"
    fi
done

# =============================================================================
# Phase 4: Git Reset
# =============================================================================
log_header "Phase 4: Git Fetch & Reset"

log_info "Fetching origin/main..."
git fetch origin main
log_ok "Fetch complete"

log_info "Hard reset to origin/main..."
git reset --hard origin/main
log_ok "Reset complete"

log_info "Now at: $(git log --oneline -1)"

# =============================================================================
# Phase 5: Restore Production Config
# =============================================================================
log_header "Phase 5: Restoring Production Configuration"

# Restore .env
if [ ! -f .env ]; then
    cp "$ENV_BACKUP" .env
    log_ok ".env restored from backup"
else
    log_ok ".env survived git reset (already in .gitignore)"
fi

# CRITICAL: Copy production compose
cp compose.prod.yml compose.yml
log_ok "compose.prod.yml → compose.yml"

# Verify it's actually the production version
if head -5 compose.yml | grep -qi "production"; then
    log_ok "compose.yml contains 'Production' header — verified"
else
    log_err "compose.yml does NOT appear to be the production version!"
    log_err "Header:"
    head -5 compose.yml
    echo ""
    read -p "Continue anyway? (y/N): " force
    if [ "$force" != "y" ]; then
        echo "Aborted. Restore compose.yml manually."
        exit 1
    fi
fi

# =============================================================================
# Phase 6: Rebuild Everything
# =============================================================================
log_header "Phase 6: Rebuilding ALL Images (--no-cache --pull)"

log_info "This will take several minutes..."
log_info "Building: app (PHP+Apache), frontend (React+Nginx), sales (Nginx)"
echo ""

docker compose build --no-cache --pull 2>&1 | while IFS= read -r line; do
    echo "  $line"
done

if [ ${PIPESTATUS[0]} -ne 0 ]; then
    log_err "Docker build FAILED!"
    echo ""
    echo "Check the output above for errors."
    echo "Your .env is safe at: $ENV_BACKUP"
    exit 1
fi

log_ok "All images built successfully"

# =============================================================================
# Phase 7: Start Everything
# =============================================================================
log_header "Phase 7: Starting All Containers"

docker compose up -d
log_ok "docker compose up -d"

log_info "Waiting 15 seconds for services to initialize..."
sleep 15

# =============================================================================
# Phase 8: Health Checks
# =============================================================================
log_header "Phase 8: Health Checks"

HEALTH_PASS=0
HEALTH_FAIL=0

check_health() {
    local name="$1"
    local url="$2"
    local http_code

    http_code=$(curl -sf -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")

    if [ "$http_code" = "200" ]; then
        log_ok "$name — HTTP $http_code"
        HEALTH_PASS=$((HEALTH_PASS + 1))
    else
        log_err "$name — HTTP $http_code"
        HEALTH_FAIL=$((HEALTH_FAIL + 1))
    fi
}

check_health "PHP API (nexus-php-app)"        "http://127.0.0.1:8090/health.php"
check_health "React Frontend (nexus-react-prod)" "http://127.0.0.1:3000/"
check_health "Sales Site (nexus-sales-site)"   "http://127.0.0.1:3003/"

# If any health check failed, show container logs
if [ "$HEALTH_FAIL" -gt 0 ]; then
    echo ""
    log_warn "Some health checks failed — showing logs for failing containers:"
    echo ""

    # Check which containers are unhealthy
    for svc in app frontend sales; do
        container=$(docker compose ps -q "$svc" 2>/dev/null)
        if [ -n "$container" ]; then
            status=$(docker inspect --format='{{.State.Status}}' "$container" 2>/dev/null)
            if [ "$status" != "running" ]; then
                echo -e "${RED}--- Logs for $svc ($status) ---${NC}"
                docker compose logs --tail=30 "$svc" 2>/dev/null
                echo ""
            fi
        else
            echo -e "${RED}--- Container for $svc not found ---${NC}"
        fi
    done
fi

# =============================================================================
# Phase 9: Verification Summary
# =============================================================================
log_header "DEPLOYMENT SUMMARY"

ENDED=$(date +%s)
ELAPSED=$((ENDED - STARTED))

echo -e "  ${BOLD}Git Commit:${NC}    $(git log --oneline -1)"
echo -e "  ${BOLD}Deploy Time:${NC}   ${ELAPSED}s"
echo -e "  ${BOLD}Health:${NC}        ${GREEN}${HEALTH_PASS} passed${NC}, ${RED}${HEALTH_FAIL} failed${NC}"
echo ""

echo -e "  ${BOLD}Docker Images:${NC}"
docker images --format "    {{.Repository}}:{{.Tag}}  (created {{.CreatedSince}}, {{.Size}})" \
    | grep -i nexus || echo "    (no nexus images found)"
echo ""

echo -e "  ${BOLD}Container Status:${NC}"
docker ps --format "    {{.Names}}: {{.Status}}" --filter "name=nexus-" 2>/dev/null
echo ""

echo -e "  ${BOLD}compose.yml:${NC}   $(head -3 compose.yml | grep -o 'Production' || echo 'WARNING: Not production!')"
echo ""

# Cleanup backup if everything is fine
if [ "$HEALTH_FAIL" -eq 0 ]; then
    echo -e "${GREEN}${BOLD}"
    echo "  ╔══════════════════════════════════════════════════════════════╗"
    echo "  ║              ✓ NUCLEAR DEPLOY SUCCESSFUL                     ║"
    echo "  ╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    log_info "Backup .env at: $ENV_BACKUP (safe to delete)"
else
    echo -e "${RED}${BOLD}"
    echo "  ╔══════════════════════════════════════════════════════════════╗"
    echo "  ║         ⚠ DEPLOY COMPLETED WITH FAILURES                    ║"
    echo "  ╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    log_warn "Check the container logs above"
    log_info ".env backup at: $ENV_BACKUP"
fi
