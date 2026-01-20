#!/bin/bash
# Dashboard Refactor Verification Script
# Run this to verify all files are in place

echo "🔍 Verifying Dashboard Refactor..."
echo ""

# Check view files
echo "📁 Checking view files..."
FILES=(
  "views/civicone/dashboard.php"
  "views/civicone/dashboard/partials/_overview.php"
  "views/civicone/dashboard/notifications.php"
  "views/civicone/dashboard/hubs.php"
  "views/civicone/dashboard/listings.php"
  "views/civicone/dashboard/wallet.php"
  "views/civicone/dashboard/events.php"
)

for file in "${FILES[@]}"; do
  if [ -f "$file" ]; then
    echo "  ✅ $file"
  else
    echo "  ❌ MISSING: $file"
  fi
done

# Check layout partial
echo ""
echo "📁 Checking layout partial..."
if [ -f "views/layouts/civicone/partials/account-navigation.php" ]; then
  echo "  ✅ account-navigation.php"
else
  echo "  ❌ MISSING: account-navigation.php"
fi

# Check CSS files
echo ""
echo "🎨 Checking CSS files..."
if [ -f "httpdocs/assets/css/civicone-account-nav.css" ]; then
  SIZE=$(du -h "httpdocs/assets/css/civicone-account-nav.css" | cut -f1)
  echo "  ✅ civicone-account-nav.css ($SIZE)"
else
  echo "  ❌ MISSING: civicone-account-nav.css"
fi

if [ -f "httpdocs/assets/css/civicone-account-nav.min.css" ]; then
  SIZE=$(du -h "httpdocs/assets/css/civicone-account-nav.min.css" | cut -f1)
  echo "  ✅ civicone-account-nav.min.css ($SIZE)"
else
  echo "  ❌ MISSING: civicone-account-nav.min.css"
fi

# Check routes
echo ""
echo "🛣️  Checking routes..."
ROUTE_COUNT=$(grep -c "dashboard/notifications\|dashboard/hubs\|dashboard/listings\|dashboard/wallet\|dashboard/events" httpdocs/routes.php)
if [ "$ROUTE_COUNT" -eq 5 ]; then
  echo "  ✅ All 5 new routes found in routes.php"
else
  echo "  ⚠️  Expected 5 routes, found $ROUTE_COUNT"
fi

# Check controller methods
echo ""
echo "🎛️  Checking controller methods..."
METHOD_COUNT=$(grep -c "public function notifications\|public function hubs\|public function listings\|public function wallet\|public function events" src/Controllers/DashboardController.php)
if [ "$METHOD_COUNT" -eq 5 ]; then
  echo "  ✅ All 5 new methods found in DashboardController.php"
else
  echo "  ⚠️  Expected 5 methods, found $METHOD_COUNT"
fi

# Check for redirect logic
echo ""
echo "🔄 Checking backward compatibility..."
if grep -q "Backward compatibility: Redirect old tab URLs" src/Controllers/DashboardController.php; then
  echo "  ✅ Redirect logic found in DashboardController.php"
else
  echo "  ❌ MISSING: Redirect logic"
fi

# Check CSS is loaded in header
echo ""
echo "📦 Checking CSS loading..."
if grep -q "civicone-account-nav.min.css" views/layouts/civicone/partials/assets-css.php; then
  echo "  ✅ CSS loaded in assets-css.php"
else
  echo "  ❌ MISSING: CSS not loaded in assets-css.php"
fi

# Check minify script
echo ""
echo "🔧 Checking build script..."
if grep -q "civicone-account-nav.css" scripts/minify-css.js; then
  echo "  ✅ CSS added to minify-css.js"
else
  echo "  ❌ MISSING: CSS not in minify-css.js"
fi

echo ""
echo "✅ Verification complete!"
echo ""
echo "Next steps:"
echo "  1. Test the dashboard at http://localhost/dashboard"
echo "  2. Test navigation links work correctly"
echo "  3. Test old tab URLs redirect (e.g., /dashboard?tab=notifications)"
echo "  4. Test keyboard navigation (Tab key)"
echo "  5. Check browser console for errors"
echo ""
