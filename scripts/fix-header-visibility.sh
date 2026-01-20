#!/bin/bash

# CivicOne Header Visibility Fix Script
# Diagnoses and fixes why service navigation isn't visible

echo "=================================================="
echo "CivicOne Header Visibility Diagnostic & Fix"
echo "=================================================="
echo ""

echo "STEP 1: Checking if service navigation CSS has visibility issues..."
echo "-------------------------------------------------------------------"

# Check for display: none on service navigation
if grep -q ".civicone-service-navigation {" httpdocs/assets/css/civicone-header.css; then
    echo "✅ Service navigation CSS exists in civicone-header.css"
else
    echo "❌ Service navigation CSS NOT FOUND in civicone-header.css"
fi

# Check mobile breakpoint
if grep -q "@media (min-width: 768px)" httpdocs/assets/css/civicone-header.css; then
    echo "✅ Tablet breakpoint (768px) exists"
else
    echo "❌ Tablet breakpoint missing"
fi

echo ""
echo "STEP 2: Checking service navigation list visibility..."
echo "-------------------------------------------------------"

# Show the desktop visibility CSS
echo "Current desktop visibility CSS (lines 534-545):"
sed -n '534,545p' httpdocs/assets/css/civicone-header.css

echo ""
echo "STEP 3: Temporary fix - Add !important to force visibility..."
echo "--------------------------------------------------------------"

# Create backup
cp httpdocs/assets/css/civicone-header.css httpdocs/assets/css/civicone-header.css.backup
echo "✅ Backup created: civicone-header.css.backup"

# Add temporary CSS fix to force visibility on desktop
cat >> httpdocs/assets/css/civicone-header.css << 'EOF'

/* ===================================================================
   TEMPORARY FIX: Force service navigation visibility on desktop
   Added: 2026-01-20
   Remove after investigating why default CSS isn't working
   =================================================================== */
@media (min-width: 768px) {
    .civicone-service-navigation__list {
        display: flex !important;
        align-items: center !important;
        visibility: visible !important;
    }
}

.civicone-service-navigation__container {
    display: flex !important;
    visibility: visible !important;
}

.civicone-header {
    display: block !important;
    visibility: visible !important;
    position: relative !important;
    z-index: 100 !important;
}
EOF

echo "✅ Temporary CSS fix added to civicone-header.css"

echo ""
echo "STEP 4: Regenerating minified CSS..."
echo "--------------------------------------"

# Regenerate minified CSS
node scripts/minify-css.js 2>&1 | grep "civicone-header.css"

echo ""
echo "STEP 5: Checking hero.php for overlapping issues..."
echo "----------------------------------------------------"

if [ -f "views/layouts/civicone/partials/hero.php" ]; then
    # Check if hero has position absolute or fixed that might overlap
    if grep -q "position:\s*absolute\|position:\s*fixed" views/layouts/civicone/partials/hero.php; then
        echo "⚠️  WARNING: hero.php has absolute/fixed positioning - may overlap header"
    else
        echo "✅ hero.php doesn't have positioning issues"
    fi

    # Check hero height
    HERO_LINES=$(wc -l < views/layouts/civicone/partials/hero.php)
    if [ "$HERO_LINES" -gt 50 ]; then
        echo "⚠️  WARNING: hero.php is large ($HERO_LINES lines) - may be pushing content down"
    else
        echo "✅ hero.php size is reasonable ($HERO_LINES lines)"
    fi
else
    echo "❌ hero.php not found"
fi

echo ""
echo "STEP 6: Verification checklist..."
echo "----------------------------------"
echo ""
echo "After running this fix, please:"
echo "1. Clear browser cache (Ctrl+Shift+Delete)"
echo "2. Reload homepage (Ctrl+F5 for hard refresh)"
echo "3. Check if service navigation is now visible"
echo ""
echo "Expected result at 1920px viewport:"
echo "  [Logo: Project NEXUS]  Feed  Members  Groups  Listings  Volunteering  Events"
echo ""
echo "If service navigation is STILL not visible:"
echo "  1. Open browser DevTools (F12)"
echo "  2. Go to Elements tab"
echo "  3. Search for 'civicone-service-navigation__list'"
echo "  4. Check computed styles to see what's hiding it"
echo "  5. Report findings in docs/HEADER_ISSUES_FOUND_2026-01-20.md"
echo ""
echo "=================================================="
echo "Fix complete. Test in browser now."
echo "=================================================="
