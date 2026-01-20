#!/bin/bash

# Header Rendering Verification Script
# Run after completing header refactoring to verify fixes are active

echo "======================================================"
echo "CivicOne Header Rendering Verification"
echo "======================================================"
echo ""

echo "✅ STEP 1: Verifying CSS visibility fixes are in place"
echo "-------------------------------------------------------"

# Check if visibility fixes exist in civicone-header.css
if grep -q "CRITICAL FIX: Service Navigation Visibility" httpdocs/assets/css/civicone-header.css; then
    echo "✅ Visibility fixes found in civicone-header.css"

    # Count !important declarations in the fix
    IMPORTANT_COUNT=$(grep -A 30 "CRITICAL FIX: Service Navigation Visibility" httpdocs/assets/css/civicone-header.css | grep -c "!important")
    echo "   → Found $IMPORTANT_COUNT !important declarations"
else
    echo "❌ Visibility fixes NOT FOUND in civicone-header.css"
    echo "   Run fix-header-visibility.sh first"
    exit 1
fi

echo ""
echo "✅ STEP 2: Verifying utility bar cleanup"
echo "-----------------------------------------"

# Check if Create dropdown was removed
if grep -q "REMOVED: Create Dropdown" views/layouts/civicone/partials/utility-bar.php; then
    echo "✅ Create dropdown removed from utility bar"
else
    echo "❌ Create dropdown still present"
fi

# Check if Partner Communities dropdown was removed
if grep -q "REMOVED: Federation Dropdown" views/layouts/civicone/partials/utility-bar.php; then
    echo "✅ Partner Communities dropdown removed from utility bar"
else
    echo "❌ Partner Communities dropdown still present"
fi

# Check if Admin/Ranking moved to user dropdown
if grep -q "REMOVED: Admin and Ranking links" views/layouts/civicone/partials/utility-bar.php; then
    echo "✅ Admin/Ranking moved from top-level utility bar"
else
    echo "❌ Admin/Ranking still at top level"
fi

echo ""
echo "✅ STEP 3: Verifying federation scope switcher"
echo "-----------------------------------------------"

# Check if federation scope switcher is included in site-header.php
if grep -q "federation-scope-switcher.php" views/layouts/civicone/partials/site-header.php; then
    echo "✅ Federation scope switcher included in site-header.php"
else
    echo "❌ Federation scope switcher NOT included"
fi

# Check if partial exists
if [ -f "views/layouts/civicone/partials/federation-scope-switcher.php" ]; then
    echo "✅ Federation scope switcher partial exists"
else
    echo "❌ Federation scope switcher partial missing"
fi

echo ""
echo "✅ STEP 4: Verifying hero is conditional"
echo "-----------------------------------------"

# Check if hero is now conditional
if grep -q "if (\$showHero ?? false)" views/layouts/civicone/header.php; then
    echo "✅ Hero is conditional (only shows when \$showHero = true)"
else
    echo "❌ Hero is still global (not conditional)"
fi

echo ""
echo "✅ STEP 5: Verifying CSS minification"
echo "--------------------------------------"

# Check if minified CSS exists and is recent
if [ -f "httpdocs/assets/css/civicone-header.min.css" ]; then
    MIN_SIZE=$(stat -c%s "httpdocs/assets/css/civicone-header.min.css" 2>/dev/null || stat -f%z "httpdocs/assets/css/civicone-header.min.css" 2>/dev/null || echo "unknown")
    echo "✅ Minified CSS exists (size: $MIN_SIZE bytes)"

    # Check if minified CSS is newer than source
    if [ "httpdocs/assets/css/civicone-header.min.css" -nt "httpdocs/assets/css/civicone-header.css" ]; then
        echo "✅ Minified CSS is up to date"
    else
        echo "⚠️  WARNING: Minified CSS is OLDER than source - run: node scripts/minify-css.js"
    fi
else
    echo "❌ Minified CSS missing - run: node scripts/minify-css.js"
fi

echo ""
echo "======================================================"
echo "Browser Verification Checklist"
echo "======================================================"
echo ""
echo "Now test in browser (clear cache first: Ctrl+F5):"
echo ""
echo "At 1920px viewport:"
echo "  [ ] Service navigation visible: [Logo] Feed Members Groups Listings..."
echo "  [ ] Active page highlighted in navigation"
echo "  [ ] Utility bar clean: Platform | Contrast | Layout | Messages | Notifications | User"
echo "  [ ] NO Create dropdown in utility bar"
echo "  [ ] NO Partner Communities dropdown in utility bar"
echo "  [ ] NO Admin/Ranking at top level (should be in user dropdown)"
echo "  [ ] Search bar visible below service navigation"
echo "  [ ] NO large purple hero section (unless \$showHero = true on specific page)"
echo ""
echo "At 375px viewport:"
echo "  [ ] Hamburger menu visible (☰)"
echo "  [ ] Logo visible"
echo "  [ ] Mobile search toggle visible (🔍)"
echo "  [ ] No horizontal scroll"
echo ""
echo "Keyboard navigation:"
echo "  [ ] Tab key navigates through header items in correct order"
echo "  [ ] Focus indicators visible (yellow outline)"
echo "  [ ] Enter key activates links/buttons"
echo "  [ ] Escape key closes dropdowns"
echo ""
echo "======================================================"
echo "If service navigation is STILL not visible:"
echo "======================================================"
echo ""
echo "1. Open browser DevTools (F12)"
echo "2. Go to Elements/Inspector tab"
echo "3. Find element: <header class=\"civicone-header\">"
echo "4. Check computed styles for:"
echo "   - display: should be 'block'"
echo "   - visibility: should be 'visible'"
echo "   - z-index: should be '100'"
echo ""
echo "5. Find element: <nav class=\"civicone-service-navigation\">"
echo "6. Check computed styles for:"
echo "   - display: should be 'block'"
echo "   - visibility: should be 'visible'"
echo ""
echo "7. Find element: <ul class=\"civicone-service-navigation__list\">"
echo "8. Check computed styles for:"
echo "   - display: should be 'flex' (at 768px+)"
echo "   - visibility: should be 'visible'"
echo ""
echo "9. If any of these are wrong, another CSS rule is overriding"
echo "   → Look for specificity issues in DevTools 'Styles' panel"
echo "   → Check if another CSS file is loaded after civicone-header.css"
echo ""
echo "======================================================"
echo "Next Steps After Browser Verification"
echo "======================================================"
echo ""
echo "If header looks correct:"
echo "  1. Run accessibility audit: node scripts/test-accessibility.js"
echo "  2. Run Lighthouse in Chrome DevTools"
echo "  3. Test with screen reader (NVDA on Windows, VoiceOver on Mac)"
echo "  4. Document visual verification in: docs/HEADER_VISUAL_VERIFICATION_2026-01-20.md"
echo ""
echo "If header still has issues:"
echo "  1. Take screenshot showing the issue"
echo "  2. Use DevTools to identify conflicting CSS"
echo "  3. Document findings in: docs/HEADER_ISSUES_FOUND_2026-01-20.md"
echo "  4. Request further assistance with specific CSS conflict details"
echo ""
echo "======================================================"
