#!/bin/bash
# =============================================================================
# Project NEXUS - Post-Swarm Feature Verification Script
# =============================================================================
# Run this after any agent swarm completes to verify all integration points
# are properly wired. Pass the feature/module name as an argument.
#
# Usage:
#   ./scripts/verify-feature.sh <feature-name>
#   ./scripts/verify-feature.sh   (checks all integration files for consistency)
#
# Examples:
#   ./scripts/verify-feature.sh volunteering
#   ./scripts/verify-feature.sh group-exchanges
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

FEATURE="${1:-}"
ERRORS=0
WARNINGS=0

echo "=============================================="
echo " Project NEXUS — Feature Integration Verifier"
echo "=============================================="
echo ""

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 1: TypeScript Compilation
# ─────────────────────────────────────────────────────────────────────────────
echo "=== CHECK 1: TypeScript Compilation ==="
if cd react-frontend && npx tsc --noEmit 2>&1; then
    echo -e "${GREEN}PASS${NC}: TypeScript compiles without errors"
else
    echo -e "${RED}FAIL${NC}: TypeScript compilation errors found"
    ((ERRORS++))
fi
cd ..

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 2: Vite Build
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=== CHECK 2: Vite Build ==="
if cd react-frontend && npm run build 2>&1 > /dev/null; then
    echo -e "${GREEN}PASS${NC}: React frontend builds successfully"
else
    echo -e "${RED}FAIL${NC}: React frontend build failed"
    ((ERRORS++))
fi
cd ..

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 3: PHP Syntax
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=== CHECK 3: PHP Syntax Check ==="
PHP_ERRORS=$(find src -name "*.php" -exec php -l {} \; 2>&1 | grep -c "Parse error" || true)
if [ "$PHP_ERRORS" -eq 0 ]; then
    echo -e "${GREEN}PASS${NC}: All PHP files have valid syntax"
else
    echo -e "${RED}FAIL${NC}: $PHP_ERRORS PHP files have syntax errors"
    find src -name "*.php" -exec php -l {} \; 2>&1 | grep "Parse error"
    ((ERRORS++))
fi

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 4: Integration File Completeness
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=== CHECK 4: Integration File Wiring ==="

INTEGRATION_FILES=(
    "httpdocs/routes.php"
    "react-frontend/src/App.tsx"
    "react-frontend/src/types/api.ts"
    "react-frontend/src/lib/tenant-routing.ts"
    "react-frontend/src/contexts/TenantContext.tsx"
    "react-frontend/src/components/layout/Navbar.tsx"
    "react-frontend/src/components/layout/MobileDrawer.tsx"
)

# Check each integration file exists
for file in "${INTEGRATION_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "  ${GREEN}OK${NC}: $file exists"
    else
        echo -e "  ${RED}MISSING${NC}: $file"
        ((ERRORS++))
    fi
done

# If a feature name was given, check it's referenced in key files
if [ -n "$FEATURE" ]; then
    echo ""
    echo "--- Checking references to '$FEATURE' ---"

    # Check routes.php for API endpoints
    if grep -qi "$FEATURE" httpdocs/routes.php 2>/dev/null; then
        echo -e "  ${GREEN}OK${NC}: routes.php references '$FEATURE'"
    else
        echo -e "  ${YELLOW}WARN${NC}: routes.php does NOT reference '$FEATURE' — missing API routes?"
        ((WARNINGS++))
    fi

    # Check App.tsx for frontend routes
    if grep -qi "$FEATURE" react-frontend/src/App.tsx 2>/dev/null; then
        echo -e "  ${GREEN}OK${NC}: App.tsx references '$FEATURE'"
    else
        echo -e "  ${YELLOW}WARN${NC}: App.tsx does NOT reference '$FEATURE' — missing frontend routes?"
        ((WARNINGS++))
    fi

    # Check types/api.ts for TypeScript types
    if grep -qi "$FEATURE" react-frontend/src/types/api.ts 2>/dev/null; then
        echo -e "  ${GREEN}OK${NC}: types/api.ts references '$FEATURE'"
    else
        echo -e "  ${YELLOW}WARN${NC}: types/api.ts does NOT reference '$FEATURE' — missing type definitions?"
        ((WARNINGS++))
    fi
fi

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 5: Common Regression Patterns
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=== CHECK 5: Common Regression Patterns ==="

# Check for data.data ?? data (wrong unwrapping)
WRONG_UNWRAP=$(grep -rn 'data\.data \?\?' react-frontend/src/ --include="*.ts" --include="*.tsx" 2>/dev/null | grep -v node_modules | wc -l || true)
if [ "$WRONG_UNWRAP" -gt 0 ]; then
    echo -e "  ${RED}FAIL${NC}: Found $WRONG_UNWRAP instances of 'data.data ??' (should use \"'data' in data ? data.data : data\")"
    grep -rn 'data\.data \?\?' react-frontend/src/ --include="*.ts" --include="*.tsx" 2>/dev/null | grep -v node_modules | head -5
    ((ERRORS++))
else
    echo -e "  ${GREEN}PASS${NC}: No wrong unwrapping patterns (data.data ??)"
fi

# Check for undefined ApiErrorCodes constants
UNDEFINED_CODES=$(grep -rn 'ApiErrorCodes::' src/ --include="*.php" 2>/dev/null | grep -v 'class ApiErrorCodes' | while read -r line; do
    CONST=$(echo "$line" | grep -oP 'ApiErrorCodes::\K[A-Z_]+')
    if [ -n "$CONST" ] && ! grep -q "const $CONST" src/Core/ApiErrorCodes.php 2>/dev/null; then
        echo "$line"
    fi
done | wc -l || true)
if [ "$UNDEFINED_CODES" -gt 0 ]; then
    echo -e "  ${YELLOW}WARN${NC}: Found $UNDEFINED_CODES references to potentially undefined ApiErrorCodes constants"
    ((WARNINGS++))
else
    echo -e "  ${GREEN}PASS${NC}: All ApiErrorCodes references look valid"
fi

# Check for DELETE queries without tenant_id on tenant-scoped tables
UNSCOPED_DELETES=$(grep -rn 'DELETE FROM' src/ --include="*.php" 2>/dev/null | grep -v 'tenant_id' | grep -v 'error_404_log\|login_attempts\|password_resets\|revoked_tokens\|sessions\|migrations' | wc -l || true)
if [ "$UNSCOPED_DELETES" -gt 0 ]; then
    echo -e "  ${YELLOW}WARN${NC}: Found $UNSCOPED_DELETES DELETE queries that may be missing tenant_id"
    ((WARNINGS++))
else
    echo -e "  ${GREEN}PASS${NC}: DELETE queries appear to include tenant_id"
fi

# Check for 'as any' TypeScript casts
AS_ANY=$(grep -rn 'as any' react-frontend/src/ --include="*.ts" --include="*.tsx" 2>/dev/null | grep -v node_modules | wc -l || true)
if [ "$AS_ANY" -gt 5 ]; then
    echo -e "  ${YELLOW}WARN${NC}: Found $AS_ANY 'as any' TypeScript casts (consider proper typing)"
    ((WARNINGS++))
else
    echo -e "  ${GREEN}PASS${NC}: Minimal 'as any' casts ($AS_ANY)"
fi

# ─────────────────────────────────────────────────────────────────────────────
# CHECK 6: Tenant Scoping in New/Modified Services
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=== CHECK 6: Tenant Scoping ==="

# Find services that don't reference TenantContext
SERVICES_WITHOUT_TENANT=$(find src/Services -name "*.php" -newer src/Services/TokenService.php 2>/dev/null | while read -r file; do
    if ! grep -q 'TenantContext' "$file" 2>/dev/null; then
        echo "$file"
    fi
done | wc -l || true)
if [ "$SERVICES_WITHOUT_TENANT" -gt 0 ]; then
    echo -e "  ${YELLOW}WARN${NC}: $SERVICES_WITHOUT_TENANT recently modified service(s) don't reference TenantContext"
    ((WARNINGS++))
else
    echo -e "  ${GREEN}PASS${NC}: All recent services reference TenantContext"
fi

# ─────────────────────────────────────────────────────────────────────────────
# SUMMARY
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "=============================================="
echo " RESULTS"
echo "=============================================="
echo -e "  Errors:   ${RED}$ERRORS${NC}"
echo -e "  Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ "$ERRORS" -gt 0 ]; then
    echo -e "${RED}DEPLOYMENT BLOCKED — Fix $ERRORS error(s) before deploying.${NC}"
    exit 1
elif [ "$WARNINGS" -gt 0 ]; then
    echo -e "${YELLOW}PROCEED WITH CAUTION — $WARNINGS warning(s) should be reviewed.${NC}"
    exit 0
else
    echo -e "${GREEN}ALL CHECKS PASSED — Safe to deploy.${NC}"
    exit 0
fi
