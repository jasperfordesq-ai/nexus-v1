#!/bin/bash
# =============================================================================
# Production Readiness Check
# =============================================================================
# Run this ON THE PRODUCTION SERVER to verify safe-deploy.sh will work
# Usage: ssh to server, then: sudo bash scripts/verify-production-ready.sh
# =============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASS=0
FAIL=0
WARN=0

check_pass() { PASS=$((PASS + 1)); echo -e "${GREEN}[PASS]${NC} $1"; }
check_fail() { FAIL=$((FAIL + 1)); echo -e "${RED}[FAIL]${NC} $1"; }
check_warn() { WARN=$((WARN + 1)); echo -e "${YELLOW}[WARN]${NC} $1"; }

echo "============================================"
echo "  Production Readiness Check"
echo "============================================"
echo ""

# 1. Check deploy directory
if [ -d "/opt/nexus-php" ]; then
    check_pass "Deploy directory exists: /opt/nexus-php"
else
    check_fail "Deploy directory missing: /opt/nexus-php"
fi

# 2. Check .env file
if [ -f "/opt/nexus-php/.env" ]; then
    check_pass ".env file exists"

    # Check for DB_PASSWORD field
    if grep -q "^DB_PASSWORD=" "/opt/nexus-php/.env"; then
        check_pass ".env contains DB_PASSWORD field"
    else
        check_warn ".env missing DB_PASSWORD field (will use nexus_secret)"
    fi
else
    check_fail ".env file missing"
fi

# 3. Check compose.prod.yml
if [ -f "/opt/nexus-php/compose.prod.yml" ]; then
    check_pass "compose.prod.yml exists"
else
    check_fail "compose.prod.yml missing"
fi

# 4. Check logs directory
if [ -d "/opt/nexus-php/logs" ]; then
    check_pass "logs directory exists"
else
    check_warn "logs directory missing (will be created)"
fi

# 5. Check git repository
if [ -d "/opt/nexus-php/.git" ]; then
    check_pass "Git repository initialized"

    # Check remote
    if cd /opt/nexus-php && git remote get-url origin > /dev/null 2>&1; then
        REMOTE=$(git remote get-url origin)
        check_pass "Git remote configured: $REMOTE"
    else
        check_fail "Git remote not configured"
    fi
else
    check_fail "Not a git repository"
fi

# 6. Check containers
if docker ps --filter "name=nexus-php-app" --format "{{.Names}}" | grep -q "nexus-php-app"; then
    check_pass "nexus-php-app container running"
else
    check_fail "nexus-php-app container not running"
fi

if docker ps --filter "name=nexus-php-db" --format "{{.Names}}" | grep -q "nexus-php-db"; then
    check_pass "nexus-php-db container running"
else
    check_fail "nexus-php-db container not running"
fi

if docker ps --filter "name=nexus-php-redis" --format "{{.Names}}" | grep -q "nexus-php-redis"; then
    check_pass "nexus-php-redis container running"
else
    check_warn "nexus-php-redis container not running"
fi

# 7. Check disk space
AVAILABLE_MB=$(df -m /opt/nexus-php | tail -1 | awk '{print $4}')
if [ "$AVAILABLE_MB" -gt 1024 ]; then
    check_pass "Disk space sufficient: ${AVAILABLE_MB}MB available"
else
    check_fail "Insufficient disk space: ${AVAILABLE_MB}MB (need >1GB)"
fi

# 8. Check if safe-deploy.sh exists
if [ -f "/opt/nexus-php/scripts/safe-deploy.sh" ]; then
    check_pass "safe-deploy.sh script exists"
else
    check_fail "safe-deploy.sh script missing"
fi

# Summary
echo ""
echo "============================================"
echo "  Summary"
echo "============================================"
echo -e "${GREEN}PASS:${NC} $PASS"
echo -e "${YELLOW}WARN:${NC} $WARN"
echo -e "${RED}FAIL:${NC} $FAIL"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}✅ Production is READY for safe-deploy.sh${NC}"
    echo ""
    echo "You can now run:"
    echo "  sudo bash scripts/safe-deploy.sh status"
    exit 0
else
    echo -e "${RED}❌ Production is NOT READY${NC}"
    echo ""
    echo "Fix the failed checks above before using safe-deploy.sh"
    exit 1
fi
