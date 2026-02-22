#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# CI Migration Gate
# Detects schema-changing patterns in PHP source and ensures a corresponding
# migration file exists in the PR. Runs in CI (no DB access needed).
#
# Checks:
# 1. If PHP source references new tables/columns, a migration file should exist
# 2. Migration files must use IF NOT EXISTS / IF EXISTS for idempotency
# 3. Migration files must include tenant_id for new tables (unless exempt)
# 4. schema.sql must be up-to-date with migrations (warning only)
#
# Usage: bash scripts/check-migration-ci.sh [base-branch]
#        Default base-branch: origin/main

set -euo pipefail

BASE_BRANCH="${1:-origin/main}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

success() { echo -e "${GREEN}✓ $1${NC}"; }
error()   { echo -e "${RED}✗ $1${NC}"; }
warn()    { echo -e "${YELLOW}⚠ $1${NC}"; }
info()    { echo -e "${CYAN}→ $1${NC}"; }

echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║         CI MIGRATION GATE                                 ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

ERRORS=0
WARNINGS=0

# ─── Check 1: Schema changes require migrations ──────────────
info "Check 1: Detecting schema changes in diff..."

# Get diff against base branch
DIFF=$(git diff "${BASE_BRANCH}"...HEAD -- 'src/' 'httpdocs/' 2>/dev/null || git diff HEAD~1 -- 'src/' 'httpdocs/' 2>/dev/null || echo "")

# Schema-change patterns in PHP source (added lines only)
SCHEMA_PATTERNS="CREATE TABLE|ALTER TABLE|DROP TABLE|ADD COLUMN|DROP COLUMN|MODIFY COLUMN|CREATE INDEX|DROP INDEX"
SCHEMA_HITS=$(echo "$DIFF" | grep -E "^\+" | grep -iE "$SCHEMA_PATTERNS" | grep -v "^+++" || true)

# Check if migrations were added in this PR
NEW_MIGRATIONS=$(git diff --name-only "${BASE_BRANCH}"...HEAD -- 'migrations/' 2>/dev/null || git diff --name-only HEAD~1 -- 'migrations/' 2>/dev/null || echo "")

if [[ -n "$SCHEMA_HITS" ]]; then
    if [[ -z "$NEW_MIGRATIONS" ]]; then
        error "Schema-changing SQL detected in PHP source, but NO migration file added!"
        echo ""
        echo "  Detected patterns:"
        echo "$SCHEMA_HITS" | head -10 | sed 's/^/    /'
        echo ""
        echo "  Fix: Create a migration file in migrations/ with the schema change."
        echo "  Example: migrations/$(date +%Y_%m_%d)_description.sql"
        echo ""
        ERRORS=$((ERRORS + 1))
    else
        success "Schema changes detected AND migration file(s) present:"
        echo "$NEW_MIGRATIONS" | sed 's/^/    /'
    fi
else
    success "No schema-changing SQL in PHP source diff"
fi

# ─── Check 2: Migration idempotency ──────────────────────────
info "Check 2: Checking migration idempotency..."

if [[ -n "$NEW_MIGRATIONS" ]]; then
    while IFS= read -r migration_file; do
        if [[ -f "$migration_file" ]]; then
            CONTENT=$(cat "$migration_file")

            # Check CREATE TABLE has IF NOT EXISTS
            CREATES=$(echo "$CONTENT" | grep -i "CREATE TABLE" | grep -iv "IF NOT EXISTS" | grep -iv "^\s*--" || true)
            if [[ -n "$CREATES" ]]; then
                warn "${migration_file}: CREATE TABLE without IF NOT EXISTS (may fail on re-run)"
                echo "$CREATES" | head -3 | sed 's/^/    /'
                WARNINGS=$((WARNINGS + 1))
            fi

            # Check ADD COLUMN has IF NOT EXISTS
            ADDS=$(echo "$CONTENT" | grep -i "ADD COLUMN" | grep -iv "IF NOT EXISTS" | grep -iv "^\s*--" || true)
            if [[ -n "$ADDS" ]]; then
                warn "${migration_file}: ADD COLUMN without IF NOT EXISTS (may fail on re-run)"
                echo "$ADDS" | head -3 | sed 's/^/    /'
                WARNINGS=$((WARNINGS + 1))
            fi

            # Check for DROP without IF EXISTS
            DROPS=$(echo "$CONTENT" | grep -iE "DROP (TABLE|COLUMN|INDEX)" | grep -iv "IF EXISTS" | grep -iv "^\s*--" || true)
            if [[ -n "$DROPS" ]]; then
                warn "${migration_file}: DROP without IF EXISTS (may fail on re-run)"
                echo "$DROPS" | head -3 | sed 's/^/    /'
                WARNINGS=$((WARNINGS + 1))
            fi

            if [[ -z "$CREATES" && -z "$ADDS" && -z "$DROPS" ]]; then
                success "${migration_file}: Idempotent (IF [NOT] EXISTS used)"
            fi
        fi
    done <<< "$NEW_MIGRATIONS"
else
    info "No new migration files to check"
fi

# ─── Check 3: Tenant scoping in new tables ───────────────────
info "Check 3: Checking tenant scoping in new tables..."

# Tables that are allowed to NOT have tenant_id
EXEMPT_TABLES="tenants|migrations|sessions|password_resets|cache|jobs|failed_jobs|system_settings|global_"

if [[ -n "$NEW_MIGRATIONS" ]]; then
    while IFS= read -r migration_file; do
        if [[ -f "$migration_file" ]]; then
            # Find CREATE TABLE statements
            TABLES=$(grep -ioP "CREATE TABLE\s+(IF NOT EXISTS\s+)?(\w+)" "$migration_file" | grep -ioP "\w+$" || true)
            while IFS= read -r table; do
                if [[ -n "$table" ]]; then
                    # Check if exempt
                    if echo "$table" | grep -iqE "$EXEMPT_TABLES"; then
                        continue
                    fi
                    # Check if table definition includes tenant_id
                    if ! grep -q "tenant_id" "$migration_file"; then
                        error "${migration_file}: New table '${table}' missing tenant_id column!"
                        echo "    All tenant-scoped tables MUST include tenant_id."
                        ERRORS=$((ERRORS + 1))
                    else
                        success "${migration_file}: Table '${table}' includes tenant_id"
                    fi
                fi
            done <<< "$TABLES"
        fi
    done <<< "$NEW_MIGRATIONS"
else
    info "No new tables to check"
fi

# ─── Check 4: Migration naming convention ────────────────────
info "Check 4: Checking migration naming convention..."

if [[ -n "$NEW_MIGRATIONS" ]]; then
    while IFS= read -r migration_file; do
        BASENAME=$(basename "$migration_file")
        if [[ "$BASENAME" =~ ^[0-9]{4}_[0-9]{2}_[0-9]{2}_ ]]; then
            success "${BASENAME}: Follows naming convention (YYYY_MM_DD_description.sql)"
        else
            warn "${BASENAME}: Does not follow naming convention (expected YYYY_MM_DD_description.sql)"
            WARNINGS=$((WARNINGS + 1))
        fi
    done <<< "$NEW_MIGRATIONS"
fi

# ─── Summary ─────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════════════════════"
if [[ $ERRORS -gt 0 ]]; then
    error "FAILED: ${ERRORS} error(s), ${WARNINGS} warning(s)"
    echo "═══════════════════════════════════════════════════════════"
    exit 1
elif [[ $WARNINGS -gt 0 ]]; then
    warn "PASSED with ${WARNINGS} warning(s)"
    echo "═══════════════════════════════════════════════════════════"
    exit 0
else
    success "ALL CHECKS PASSED"
    echo "═══════════════════════════════════════════════════════════"
    exit 0
fi
