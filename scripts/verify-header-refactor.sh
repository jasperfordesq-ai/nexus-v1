#!/bin/bash

# CivicOne Header Refactor Verification Script
# Based on: docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md Section 9A.8
# Date: 2026-01-20

echo "=================================================="
echo "CivicOne Header Refactor Verification"
echo "=================================================="
echo ""

PASS_COUNT=0
FAIL_COUNT=0
CHECK_COUNT=0

# Function to print results
pass() {
    echo "✅ PASS: $1"
    ((PASS_COUNT++))
}

fail() {
    echo "❌ FAIL: $1"
    ((FAIL_COUNT++))
}

check() {
    echo "⚠️  CHECK: $1"
    ((CHECK_COUNT++))
}

echo "1. FILE STRUCTURE VERIFICATION"
echo "-------------------------------"

# Check header.php exists and is small (should be < 100 lines)
if [ -f "views/layouts/civicone/header.php" ]; then
    LINE_COUNT=$(wc -l < views/layouts/civicone/header.php)
    if [ "$LINE_COUNT" -lt 100 ]; then
        pass "header.php exists and is concise ($LINE_COUNT lines)"
    else
        fail "header.php too large ($LINE_COUNT lines, should be < 100)"
    fi
else
    fail "header.php not found"
fi

# Check all required partials exist
PARTIALS=(
    "document-open.php"
    "assets-css.php"
    "body-open.php"
    "skip-link-and-banner.php"
    "utility-bar.php"
    "site-header.php"
    "service-navigation.php"
    "hero.php"
    "main-open.php"
    "header-scripts.php"
)

for partial in "${PARTIALS[@]}"; do
    if [ -f "views/layouts/civicone/partials/$partial" ]; then
        pass "Partial exists: $partial"
    else
        fail "Partial missing: $partial"
    fi
done

# Check header-cached.php synced
if [ -f "views/layouts/civicone/header-cached.php" ]; then
    if grep -q "require __DIR__ . '/header.php'" views/layouts/civicone/header-cached.php; then
        pass "header-cached.php synced (includes header.php)"
    else
        fail "header-cached.php NOT synced (doesn't include header.php)"
    fi
else
    fail "header-cached.php not found"
fi

echo ""
echo "2. CSS BUILD ARTIFACTS VERIFICATION"
echo "------------------------------------"

# Check civicone-header.css exists
if [ -f "httpdocs/assets/css/civicone-header.css" ]; then
    pass "civicone-header.css exists"
else
    fail "civicone-header.css not found"
fi

# Check .min.css exists and is recent
if [ -f "httpdocs/assets/css/civicone-header.min.css" ]; then
    # Check if .min.css is newer than source (or same age)
    if [ "httpdocs/assets/css/civicone-header.min.css" -nt "httpdocs/assets/css/civicone-header.css" ] || \
       [ "httpdocs/assets/css/civicone-header.min.css" -ef "httpdocs/assets/css/civicone-header.css" ]; then
        pass "civicone-header.min.css is up to date"
    else
        check "civicone-header.min.css may be stale (older than source)"
    fi
else
    fail "civicone-header.min.css not found"
fi

# Check purged CSS exists
if [ -f "httpdocs/assets/css/purged/civicone-header.min.css" ]; then
    pass "Purged CSS exists: purged/civicone-header.min.css"
else
    check "Purged CSS missing: purged/civicone-header.min.css (may not be generated yet)"
fi

echo ""
echo "3. SEMANTIC STRUCTURE VERIFICATION"
echo "-----------------------------------"

# Check service-navigation.php has correct structure
if [ -f "views/layouts/civicone/partials/service-navigation.php" ]; then
    # Check for GOV.UK pattern
    if grep -q "civicone-service-navigation" views/layouts/civicone/partials/service-navigation.php; then
        pass "Service navigation uses correct class naming"
    else
        fail "Service navigation missing GOV.UK class naming"
    fi

    # Check for aria-label
    if grep -q 'aria-label="Main navigation"' views/layouts/civicone/partials/service-navigation.php; then
        pass "Service navigation has aria-label"
    else
        fail "Service navigation missing aria-label"
    fi

    # Check for aria-current="page"
    if grep -q 'aria-current="page"' views/layouts/civicone/partials/service-navigation.php; then
        pass "Active page marked with aria-current"
    else
        fail "Active page not marked with aria-current"
    fi

    # Check for mobile toggle
    if grep -q 'aria-expanded' views/layouts/civicone/partials/service-navigation.php; then
        pass "Mobile toggle has aria-expanded"
    else
        fail "Mobile toggle missing aria-expanded"
    fi
fi

# Check site-header.php has width container
if [ -f "views/layouts/civicone/partials/site-header.php" ]; then
    if grep -q "civicone-width-container" views/layouts/civicone/partials/site-header.php; then
        pass "Header has width container (max-width: 1020px)"
    else
        fail "Header missing width container"
    fi

    # Check for role="banner"
    if grep -q 'role="banner"' views/layouts/civicone/partials/site-header.php; then
        pass "Header has role=banner landmark"
    else
        fail "Header missing role=banner landmark"
    fi
fi

# Check skip link exists
if [ -f "views/layouts/civicone/partials/skip-link-and-banner.php" ]; then
    if grep -q 'href="#main-content"' views/layouts/civicone/partials/skip-link-and-banner.php; then
        pass "Skip link targets #main-content"
    else
        fail "Skip link doesn't target #main-content"
    fi
fi

echo ""
echo "4. JAVASCRIPT VERIFICATION"
echo "--------------------------"

if [ -f "views/layouts/civicone/partials/header-scripts.php" ]; then
    # Check for service nav toggle handler
    if grep -q "civicone-service-nav-toggle" views/layouts/civicone/partials/header-scripts.php; then
        pass "Service nav toggle handler exists"
    else
        fail "Service nav toggle handler missing"
    fi

    # Check for Escape key handler
    if grep -q "Escape" views/layouts/civicone/partials/header-scripts.php; then
        pass "Escape key handler exists"
    else
        fail "Escape key handler missing"
    fi

    # Check for Arrow key navigation
    if grep -q "ArrowDown\\|ArrowUp" views/layouts/civicone/partials/header-scripts.php; then
        pass "Arrow key navigation exists"
    else
        check "Arrow key navigation may be missing"
    fi

    # Check for focus management
    if grep -q "focus()" views/layouts/civicone/partials/header-scripts.php; then
        pass "Focus management exists"
    else
        fail "Focus management missing"
    fi
fi

echo ""
echo "5. CSS SCOPING VERIFICATION"
echo "----------------------------"

if [ -f "httpdocs/assets/css/civicone-header.css" ]; then
    # Check for proper scoping
    if grep -q "civicone-service-navigation__" httpdocs/assets/css/civicone-header.css; then
        pass "CSS uses proper BEM naming (.civicone-service-navigation__*)"
    else
        fail "CSS doesn't use proper BEM naming"
    fi

    # Check for GOV.UK tokens
    if grep -q "govuk-text-colour\\|govuk-focus-colour\\|civicone-spacing-" httpdocs/assets/css/civicone-header.css; then
        pass "CSS uses GOV.UK design tokens"
    else
        check "CSS may not use GOV.UK design tokens"
    fi

    # Check for focus styles
    if grep -q ":focus-visible" httpdocs/assets/css/civicone-header.css; then
        pass "CSS has :focus-visible styles"
    else
        check "CSS may be missing :focus-visible styles"
    fi
fi

echo ""
echo "6. LAYER ORDER VERIFICATION"
echo "----------------------------"

# Check header.php includes partials in correct order
if [ -f "views/layouts/civicone/header.php" ]; then
    ORDER=(
        "document-open.php"
        "assets-css.php"
        "body-open.php"
        "skip-link-and-banner.php"
        "utility-bar.php"
        "site-header.php"
        "hero.php"
        "main-open.php"
        "header-scripts.php"
    )

    prev_line=0
    order_correct=true

    for partial in "${ORDER[@]}"; do
        line_num=$(grep -n "$partial" views/layouts/civicone/header.php | head -1 | cut -d: -f1)
        if [ -n "$line_num" ]; then
            if [ "$line_num" -gt "$prev_line" ]; then
                prev_line=$line_num
            else
                order_correct=false
                break
            fi
        else
            order_correct=false
            break
        fi
    done

    if [ "$order_correct" = true ]; then
        pass "Header partials in correct layer order"
    else
        fail "Header partials NOT in correct layer order"
    fi
fi

echo ""
echo "7. NAVIGATION ITEMS VERIFICATION"
echo "---------------------------------"

if [ -f "views/layouts/civicone/partials/service-navigation.php" ]; then
    # Check for Feed nav item
    if grep -q "'Feed'" views/layouts/civicone/partials/service-navigation.php; then
        pass "Feed nav item exists"
    else
        fail "Feed nav item missing"
    fi

    # Check for Members nav item
    if grep -q "'Members'" views/layouts/civicone/partials/service-navigation.php; then
        pass "Members nav item exists"
    else
        fail "Members nav item missing"
    fi

    # Check for Groups nav item
    if grep -q "'Groups'" views/layouts/civicone/partials/service-navigation.php; then
        pass "Groups nav item exists"
    else
        fail "Groups nav item missing"
    fi

    # Check for Listings nav item
    if grep -q "'Listings'" views/layouts/civicone/partials/service-navigation.php; then
        pass "Listings nav item exists"
    else
        fail "Listings nav item missing"
    fi

    # Check nav items are top-level sections (no CTAs like "Join", "Create")
    if grep -q "'Join'\\|'Create Group'\\|'Post Listing'" views/layouts/civicone/partials/service-navigation.php; then
        fail "Primary nav contains CTAs (should be in utility bar or page content)"
    else
        pass "Primary nav contains only top-level sections (no CTAs)"
    fi
fi

echo ""
echo "=================================================="
echo "RESULTS SUMMARY"
echo "=================================================="
echo ""
echo "✅ PASS:  $PASS_COUNT"
echo "❌ FAIL:  $FAIL_COUNT"
echo "⚠️  CHECK: $CHECK_COUNT"
echo ""

TOTAL=$((PASS_COUNT + FAIL_COUNT + CHECK_COUNT))
if [ $TOTAL -gt 0 ]; then
    PASS_PERCENT=$((PASS_COUNT * 100 / TOTAL))
    echo "Score: $PASS_PERCENT% ($PASS_COUNT/$TOTAL)"
else
    echo "Score: N/A (no tests run)"
fi

echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo "🎉 All critical checks passed!"
    echo ""
    echo "Next steps:"
    echo "1. Visual verification in browser (see docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md)"
    echo "2. Keyboard walkthrough (Tab, Enter, Escape)"
    echo "3. Accessibility audit (axe DevTools, Lighthouse)"
    echo "4. Screen reader testing (NVDA/VoiceOver)"
    exit 0
else
    echo "⚠️  Some checks failed. Review output above and fix issues."
    echo ""
    echo "See docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md for detailed guidance."
    exit 1
fi
