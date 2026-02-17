#!/usr/bin/env bash
# =============================================================================
# check-regression-patterns.sh
# Scans the codebase for known regression patterns and anti-patterns.
# Exits 0 if no FAILs, 1 if any FAIL is found. WARNs do not cause failure.
#
# Usage:
#   ./scripts/check-regression-patterns.sh
#   bash scripts/check-regression-patterns.sh
#
# Works on Linux (bash), macOS, Git Bash (Windows), and CI environments.
# =============================================================================

set -uo pipefail

# ---------------------------------------------------------------------------
# Resolve project root
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

REACT_SRC="$PROJECT_ROOT/react-frontend/src"
PHP_SRC="$PROJECT_ROOT/src"

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
AS_ANY_THRESHOLD=20

# Tenant-scoped tables that should always have WHERE tenant_id in DELETE queries.
# This is not exhaustive but covers the most critical tables.
# Tables that MUST have tenant_id in DELETE queries.
# Excludes: connections (uses requester_id/receiver_id), vol_applications (no tenant_id),
# vol_logs (no tenant_id), activity_log (no tenant_id)
TENANT_TABLES=(
    "users"
    "listings"
    "transactions"
    "feed_posts"
    "groups"
    "events"
    "messages"
    "notifications"
    "reviews"
    "polls"
    "goals"
    "categories"
    "resource_items"
    "blog_posts"
    "posts"
    "pages"
    "vol_opportunities"
    "reports"
    "challenges"
    "likes"
    "comments"
    "reactions"
    "user_badges"
)

# Files that are INTENTIONALLY cross-tenant (super admin operations)
EXCLUDE_FILES=(
    "MasterController.php"
)

# ---------------------------------------------------------------------------
# Counters
# ---------------------------------------------------------------------------
fails=0
warns=0
passes=0

echo "============================================================"
echo "  Regression Pattern Check"
echo "============================================================"
echo ""

# ---------------------------------------------------------------------------
# Check 1: data.data ?? pattern (wrong API response unwrapping)
# The correct pattern is: 'data' in data ? data.data : data
# The wrong pattern is:   data.data ?? data
# ---------------------------------------------------------------------------
echo "--- Check 1: Wrong API response unwrapping (data.data ??) ---"

if [[ -d "$REACT_SRC" ]]; then
    # Search for the dangerous pattern: data.data ?? (with optional trailing "data")
    wrong_unwrap_results=$(grep -rn 'data\.data ??' "$REACT_SRC" --include="*.ts" --include="*.tsx" 2>/dev/null || true)

    if [[ -z "$wrong_unwrap_results" ]]; then
        echo "[PASS] No 'data.data ??' patterns found"
        passes=$((passes + 1))
    else
        count=$(echo "$wrong_unwrap_results" | wc -l | tr -d ' ')
        echo "[FAIL] ${count} 'data.data ??' pattern(s) found (should use 'data' in data ? data.data : data):"
        echo "$wrong_unwrap_results" | while IFS= read -r line; do
            echo "  $line"
        done
        fails=$((fails + 1))
    fi
else
    echo "[WARN] React source directory not found at $REACT_SRC, skipping"
    warns=$((warns + 1))
fi

echo ""

# ---------------------------------------------------------------------------
# Check 2: data.data ?? data pattern (specific variant)
# ---------------------------------------------------------------------------
echo "--- Check 2: Specific 'data.data ?? data' pattern ---"

if [[ -d "$REACT_SRC" ]]; then
    specific_unwrap=$(grep -rn 'data\.data ?? data' "$REACT_SRC" --include="*.ts" --include="*.tsx" 2>/dev/null || true)

    if [[ -z "$specific_unwrap" ]]; then
        echo "[PASS] No 'data.data ?? data' patterns found"
        passes=$((passes + 1))
    else
        count=$(echo "$specific_unwrap" | wc -l | tr -d ' ')
        echo "[FAIL] ${count} 'data.data ?? data' pattern(s) found:"
        echo "$specific_unwrap" | while IFS= read -r line; do
            echo "  $line"
        done
        fails=$((fails + 1))
    fi
else
    echo "[WARN] React source directory not found at $REACT_SRC, skipping"
    warns=$((warns + 1))
fi

echo ""

# ---------------------------------------------------------------------------
# Check 3: 'as any' cast count
# ---------------------------------------------------------------------------
echo "--- Check 3: TypeScript 'as any' casts (threshold: ${AS_ANY_THRESHOLD}) ---"

if [[ -d "$REACT_SRC" ]]; then
    as_any_count=$(grep -r 'as any' "$REACT_SRC" --include="*.ts" --include="*.tsx" 2>/dev/null | wc -l | tr -d ' ')

    if (( as_any_count == 0 )); then
        echo "[PASS] No 'as any' casts found"
        passes=$((passes + 1))
    elif (( as_any_count <= AS_ANY_THRESHOLD )); then
        echo "[WARN] ${as_any_count} 'as any' cast(s) found (threshold: ${AS_ANY_THRESHOLD})"
        warns=$((warns + 1))
    else
        echo "[FAIL] ${as_any_count} 'as any' cast(s) found, exceeds threshold of ${AS_ANY_THRESHOLD}"
        # Show the locations
        grep -rn 'as any' "$REACT_SRC" --include="*.ts" --include="*.tsx" 2>/dev/null | while IFS= read -r line; do
            echo "  $line"
        done
        fails=$((fails + 1))
    fi
else
    echo "[WARN] React source directory not found at $REACT_SRC, skipping"
    warns=$((warns + 1))
fi

echo ""

# ---------------------------------------------------------------------------
# Check 4: DELETE queries without tenant_id on tenant-scoped tables
# ---------------------------------------------------------------------------
echo "--- Check 4: Unscoped DELETE queries on tenant-scoped tables ---"

if [[ -d "$PHP_SRC" ]]; then
    unscoped_deletes=""
    unscoped_count=0

    for table in "${TENANT_TABLES[@]}"; do
        # Find DELETE FROM <table> WHERE ... without tenant_id
        # Strategy: find lines with DELETE FROM <table>, then check if tenant_id
        # appears on the same line or the next few lines of that statement.
        #
        # We use a two-pass approach:
        # 1. Find all DELETE FROM <table> lines
        # 2. For each, check if tenant_id appears nearby in the same statement
        # Build grep exclusion for intentionally cross-tenant files
        exclude_args=""
        for excl in "${EXCLUDE_FILES[@]}"; do
            exclude_args="$exclude_args --exclude=$excl"
        done
        matches=$(grep -rn -i "DELETE\s\+FROM\s\+\`\?${table}\`\?" "$PHP_SRC" --include="*.php" $exclude_args 2>/dev/null || true)

        if [[ -n "$matches" ]]; then
            while IFS= read -r match_line; do
                file_path=$(echo "$match_line" | cut -d: -f1)
                line_num=$(echo "$match_line" | cut -d: -f2)
                line_content=$(echo "$match_line" | cut -d: -f3-)

                # Check if tenant_id appears in the DELETE statement context
                # Look at the matched line plus surrounding lines (SQL may span lines,
                # or tenant_id may be added via a dynamic query builder above)
                start=$((line_num > 15 ? line_num - 15 : 1))
                context=$(sed -n "${start},$((line_num + 5))p" "$file_path" 2>/dev/null || true)

                # Also check if this is a dynamic query builder (implode + conditions)
                # where tenant_id is added to $conditions earlier in the function
                is_dynamic_builder=$(echo "$line_content" | grep -c 'implode' || true)

                if ! echo "$context" | grep -qi "tenant_id" && [[ "$is_dynamic_builder" -eq 0 ]]; then
                    unscoped_deletes="${unscoped_deletes}  ${file_path}:${line_num}:${line_content}"$'\n'
                    unscoped_count=$((unscoped_count + 1))
                fi
            done <<< "$matches"
        fi
    done

    if (( unscoped_count == 0 )); then
        echo "[PASS] No unscoped DELETE queries found on tenant-scoped tables"
        passes=$((passes + 1))
    else
        echo "[FAIL] ${unscoped_count} unscoped DELETE query/queries found (missing tenant_id):"
        echo -n "$unscoped_deletes"
        fails=$((fails + 1))
    fi
else
    echo "[WARN] PHP source directory not found at $PHP_SRC, skipping"
    warns=$((warns + 1))
fi

echo ""

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo "============================================================"
echo "  Summary: ${passes} PASS, ${warns} WARN, ${fails} FAIL"
echo "============================================================"

if (( fails > 0 )); then
    echo ""
    echo "RESULT: ${fails} check(s) FAILED. Please fix the issues above."
    exit 1
else
    if (( warns > 0 )); then
        echo ""
        echo "RESULT: All checks passed (${warns} warning(s) to review)."
    else
        echo ""
        echo "RESULT: All checks passed with no warnings."
    fi
    exit 0
fi
