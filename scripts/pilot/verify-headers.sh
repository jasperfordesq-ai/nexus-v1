#!/bin/bash
#
# verify-headers.sh
# Verifies that required security headers are present on a given URL
#
# Usage: ./verify-headers.sh https://example.com
#
# Exit codes:
#   0 - All required headers present
#   1 - One or more headers missing
#   2 - Usage error or curl failure
#

set -e

# Colours for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Required headers and their expected patterns
declare -A REQUIRED_HEADERS=(
    ["Strict-Transport-Security"]="max-age="
    ["X-Frame-Options"]="SAMEORIGIN\|DENY"
    ["X-Content-Type-Options"]="nosniff"
    ["Content-Security-Policy"]="default-src"
)

# Optional but recommended headers
declare -A RECOMMENDED_HEADERS=(
    ["Referrer-Policy"]="."
    ["Permissions-Policy"]="."
)

# Check arguments
if [ -z "$1" ]; then
    echo "Usage: $0 <url>"
    echo "Example: $0 https://staging.timebank.local"
    exit 2
fi

URL="$1"

echo "================================================"
echo "Security Header Verification"
echo "URL: $URL"
echo "Date: $(date)"
echo "================================================"
echo ""

# Fetch headers
echo "Fetching headers..."
HEADERS=$(curl -sI -L --max-time 30 "$URL" 2>&1)

if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: Failed to fetch headers from $URL${NC}"
    echo "$HEADERS"
    exit 2
fi

echo ""
echo "--- Raw Headers ---"
echo "$HEADERS"
echo "-------------------"
echo ""

# Track failures
FAILED=0
WARNINGS=0

# Check required headers
echo "Required Headers:"
echo "-----------------"

for header in "${!REQUIRED_HEADERS[@]}"; do
    pattern="${REQUIRED_HEADERS[$header]}"

    # Extract header value (case-insensitive)
    value=$(echo "$HEADERS" | grep -i "^$header:" | head -1 | cut -d: -f2- | tr -d '\r' | xargs)

    if [ -z "$value" ]; then
        echo -e "${RED}[FAIL]${NC} $header: MISSING"
        FAILED=$((FAILED + 1))
    elif echo "$value" | grep -qE "$pattern"; then
        echo -e "${GREEN}[PASS]${NC} $header: $value"
    else
        echo -e "${YELLOW}[WARN]${NC} $header: $value (expected pattern: $pattern)"
        WARNINGS=$((WARNINGS + 1))
    fi
done

echo ""
echo "Recommended Headers:"
echo "--------------------"

for header in "${!RECOMMENDED_HEADERS[@]}"; do
    pattern="${RECOMMENDED_HEADERS[$header]}"

    value=$(echo "$HEADERS" | grep -i "^$header:" | head -1 | cut -d: -f2- | tr -d '\r' | xargs)

    if [ -z "$value" ]; then
        echo -e "${YELLOW}[INFO]${NC} $header: Not present (recommended)"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}[PASS]${NC} $header: $value"
    fi
done

echo ""
echo "================================================"

# Check for problematic headers
echo "Checking for problematic headers..."

# Server header (information disclosure)
SERVER=$(echo "$HEADERS" | grep -i "^Server:" | head -1 | cut -d: -f2- | tr -d '\r' | xargs)
if [ -n "$SERVER" ]; then
    echo -e "${YELLOW}[INFO]${NC} Server header present: $SERVER (consider removing for security)"
fi

# X-Powered-By (information disclosure)
POWERED=$(echo "$HEADERS" | grep -i "^X-Powered-By:" | head -1 | cut -d: -f2- | tr -d '\r' | xargs)
if [ -n "$POWERED" ]; then
    echo -e "${YELLOW}[WARN]${NC} X-Powered-By header present: $POWERED (should be removed)"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""
echo "================================================"
echo "Summary"
echo "================================================"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All required security headers are present.${NC}"
else
    echo -e "${RED}$FAILED required header(s) missing.${NC}"
fi

if [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}$WARNINGS warning(s) - review recommended.${NC}"
fi

echo ""

# Exit with appropriate code
if [ $FAILED -gt 0 ]; then
    echo "Result: FAIL"
    exit 1
else
    echo "Result: PASS"
    exit 0
fi
