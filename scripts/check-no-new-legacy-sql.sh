#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Block new legacy SQL migrations.
#
# The legacy /migrations/*.sql system is FROZEN. All new schema changes must
# use Laravel migrations (php artisan make:migration ...). This guard detects
# any *.sql file added under /migrations/ in the current diff and fails.
#
# Usage:
#   bash scripts/check-no-new-legacy-sql.sh                  # compare HEAD vs origin/main
#   bash scripts/check-no-new-legacy-sql.sh <base-ref>       # compare HEAD vs custom ref
#   bash scripts/check-no-new-legacy-sql.sh --diff "<range>" # pass a git diff range explicitly
#
# CI usage:
#   bash scripts/check-no-new-legacy-sql.sh origin/${GITHUB_BASE_REF:-main}

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_REF="${1:-origin/main}"

# Collect files added under /migrations/ ending in .sql. Use --diff-filter=A
# so renames/deletes don't count — only genuinely NEW files.
if git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
    NEW_SQL=$(git diff --name-only --diff-filter=A "${BASE_REF}...HEAD" -- 'migrations/*.sql' 2>/dev/null || true)
else
    # Fallback: compare to previous commit if base ref isn't available (e.g. shallow clone)
    echo -e "${YELLOW}Base ref '${BASE_REF}' not available, falling back to HEAD~1${NC}" >&2
    NEW_SQL=$(git diff --name-only --diff-filter=A HEAD~1 -- 'migrations/*.sql' 2>/dev/null || true)
fi

if [[ -n "$NEW_SQL" ]]; then
    echo -e "${RED}✗ New legacy SQL migrations detected — this system is FROZEN.${NC}"
    echo ""
    echo "Offending files:"
    echo "$NEW_SQL" | sed 's/^/  /'
    echo ""
    echo "Fix: Delete the .sql file and create a Laravel migration instead:"
    echo ""
    echo "  php artisan make:migration describe_your_change"
    echo ""
    echo "The generated file will appear under database/migrations/."
    echo "See LARAVEL_MIGRATION_PLAN.md for full migration guidance."
    exit 1
fi

echo -e "${GREEN}✓ No new legacy SQL migrations added.${NC}"
exit 0
