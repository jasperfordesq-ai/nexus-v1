#!/bin/bash
# WebP Image Conversion Script for Linux
# Converts JPG and PNG images to WebP format

echo "WebP Image Conversion Script"
echo "============================"
echo ""

# Check if cwebp is available
if ! command -v cwebp &> /dev/null; then
    echo "ERROR: cwebp not found!"
    echo ""
    echo "Please install cwebp:"
    echo "Ubuntu/Debian: sudo apt-get install webp"
    echo "CentOS/RHEL:   sudo yum install libwebp-tools"
    echo ""
    exit 1
fi

echo "cwebp found: $(which cwebp)"
echo ""

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BASE_DIR="$(dirname "$SCRIPT_DIR")"

# Configuration
SEARCH_PATHS=(
    "$BASE_DIR/httpdocs/assets/img"
    "$BASE_DIR/uploads"
)

QUALITY=85
TOTAL_CONVERTED=0
TOTAL_ORIGINAL_SIZE=0
TOTAL_WEBP_SIZE=0
SKIPPED=0

# Extensions to convert
EXTENSIONS=("jpg" "jpeg" "png")

for base_path in "${SEARCH_PATHS[@]}"; do
    if [ ! -d "$base_path" ]; then
        echo "Skipping (not found): $base_path"
        continue
    fi

    echo "Processing: $base_path"
    echo ""

    for ext in "${EXTENSIONS[@]}"; do
        # Find all files with this extension
        while IFS= read -r -d '' file; do
            webp_path="${file%.*}.webp"

            # Skip if WebP already exists and is newer
            if [ -f "$webp_path" ] && [ "$webp_path" -nt "$file" ]; then
                ((SKIPPED++))
                continue
            fi

            # Convert to WebP
            if cwebp -q "$QUALITY" "$file" -o "$webp_path" 2>/dev/null; then
                if [ -f "$webp_path" ]; then
                    original_size=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
                    webp_size=$(stat -f%z "$webp_path" 2>/dev/null || stat -c%s "$webp_path" 2>/dev/null)

                    if [ -n "$original_size" ] && [ -n "$webp_size" ]; then
                        savings=$(awk "BEGIN {printf \"%.1f\", (($original_size - $webp_size) / $original_size) * 100}")

                        TOTAL_ORIGINAL_SIZE=$((TOTAL_ORIGINAL_SIZE + original_size))
                        TOTAL_WEBP_SIZE=$((TOTAL_WEBP_SIZE + webp_size))
                        ((TOTAL_CONVERTED++))

                        relative_path="${file#$BASE_DIR/}"
                        original_kb=$(awk "BEGIN {printf \"%.1f\", $original_size / 1024}")
                        webp_kb=$(awk "BEGIN {printf \"%.1f\", $webp_size / 1024}")

                        echo "✓ $relative_path"
                        echo "  $original_kb KB → $webp_kb KB ($savings% smaller)"
                    fi
                fi
            else
                echo "✗ Failed: $(basename "$file")"
            fi
        done < <(find "$base_path" -type f -iname "*.$ext" -print0)
    done
done

echo ""
echo "================================"
echo "Conversion Complete!"
echo "================================"
echo "Images converted: $TOTAL_CONVERTED"
echo "Images skipped: $SKIPPED"

if [ $TOTAL_CONVERTED -gt 0 ]; then
    total_savings_kb=$(awk "BEGIN {printf \"%.1f\", ($TOTAL_ORIGINAL_SIZE - $TOTAL_WEBP_SIZE) / 1024}")
    total_savings_mb=$(awk "BEGIN {printf \"%.1f\", ($TOTAL_ORIGINAL_SIZE - $TOTAL_WEBP_SIZE) / 1048576}")
    savings_percent=$(awk "BEGIN {printf \"%.1f\", (($TOTAL_ORIGINAL_SIZE - $TOTAL_WEBP_SIZE) / $TOTAL_ORIGINAL_SIZE) * 100}")
    original_mb=$(awk "BEGIN {printf \"%.1f\", $TOTAL_ORIGINAL_SIZE / 1048576}")
    webp_mb=$(awk "BEGIN {printf \"%.1f\", $TOTAL_WEBP_SIZE / 1048576}")

    echo ""
    echo "Total original size: $original_mb MB"
    echo "Total WebP size: $webp_mb MB"
    echo "Total savings: $total_savings_mb MB ($total_savings_kb KB)"
    echo "Percentage saved: $savings_percent%"
fi

echo ""
echo "Next steps:"
echo "1. Update your HTML to use <picture> tags with WebP"
echo "2. Or use the automatic WebP helper function"
echo "================================"
