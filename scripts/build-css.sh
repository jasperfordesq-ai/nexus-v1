#!/bin/bash
# ===========================================
# NEXUS TimeBank - CSS Build Pipeline
# ===========================================
# Runs PurgeCSS to remove unused CSS
# Usage: bash scripts/build-css.sh
# ===========================================

set -e

echo ""
echo "=========================================="
echo "  NEXUS CSS Build Pipeline"
echo "=========================================="
echo ""

# Check if node is available
if ! command -v node &> /dev/null; then
    echo "[ERROR] Node.js not found. Please install Node.js first."
    exit 1
fi

# Check if purgecss is installed
if [ ! -d "node_modules/purgecss" ]; then
    echo "[ERROR] PurgeCSS not installed. Run: npm install purgecss --save-dev"
    exit 1
fi

echo "[1/2] Running PurgeCSS..."
echo "     Scanning PHP/JS files for used CSS classes..."
echo "     Processing 260+ CSS files..."
echo ""

# Run PurgeCSS
node scripts/run-purgecss.js

echo ""
echo "[2/2] Build complete!"
echo ""

# Calculate savings
if command -v du &> /dev/null; then
    echo "Calculating size savings..."
    before=$(find httpdocs/assets/css -maxdepth 1 -name "*.css" ! -name "purged" -type f -exec du -cb {} + 2>/dev/null | tail -1 | awk '{print $1}')
    after=$(find httpdocs/assets/css/purged -name "*.css" -type f -exec du -cb {} + 2>/dev/null | tail -1 | awk '{print $1}')

    if [ -n "$before" ] && [ -n "$after" ]; then
        saved=$((before - after))
        percent=$(awk "BEGIN {printf \"%.1f\", ($saved / $before) * 100}")

        echo "  Before: $(awk "BEGIN {printf \"%.2f\", $before/1048576}") MB"
        echo "  After:  $(awk "BEGIN {printf \"%.2f\", $after/1048576}") MB"
        echo "  Saved:  $(awk "BEGIN {printf \"%.2f\", $saved/1048576}") MB ($percent%)"
    fi
fi

echo ""
echo "=========================================="
echo "  CSS Build Complete!"
echo "=========================================="
echo ""
echo "Purged CSS files are in: httpdocs/assets/css/purged/"
echo ""

exit 0
