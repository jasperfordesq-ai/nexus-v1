#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Schema drift detection.
#
# Compares the committed database/schema/mysql-schema.sql against a freshly
# generated dump produced by `php artisan schema:dump`. If they diverge, the
# schema dump needs refreshing (bash scripts/refresh-schema-dump.sh) and
# committing so new contributors get a working database.
#
# Exit 1 if drift detected, 0 otherwise. Safe to call with `|| true` in CI for
# warning-only gating while we stabilise the bridge between legacy and Laravel
# migrations.
#
# Usage: bash scripts/check-schema-drift.sh

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

COMMITTED="database/schema/mysql-schema.sql"

if [[ ! -f "$COMMITTED" ]]; then
    echo -e "${YELLOW}⚠ ${COMMITTED} not found — skipping drift check.${NC}"
    exit 0
fi

# Require Docker to run artisan in the app container. If the container isn't
# running (e.g. in CI without docker-compose up), exit 0 with a notice so this
# script can stay in CI as a soft gate.
if ! command -v docker >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠ docker not available — skipping schema drift check.${NC}"
    exit 0
fi

if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -q '^nexus-php-app$'; then
    echo -e "${YELLOW}⚠ nexus-php-app container not running — skipping schema drift check.${NC}"
    echo "   Start it with: docker compose up -d"
    exit 0
fi

TMP_DUMP=$(mktemp)
trap 'rm -f "$TMP_DUMP"' EXIT

echo "→ Generating fresh schema dump via artisan schema:dump..."
if ! docker exec nexus-php-app php artisan schema:dump --path="/tmp/schema-drift.sql" >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠ artisan schema:dump failed — cannot check drift. Skipping.${NC}"
    exit 0
fi

docker exec nexus-php-app cat /tmp/schema-drift.sql > "$TMP_DUMP" 2>/dev/null || {
    echo -e "${YELLOW}⚠ Could not read generated dump — skipping drift check.${NC}"
    exit 0
}

# Normalise both files before diffing: strip timestamps, AUTO_INCREMENT counters,
# and trailing whitespace so cosmetic differences don't cause false positives.
normalise() {
    sed -E \
        -e 's/AUTO_INCREMENT=[0-9]+//g' \
        -e 's/-- Dump completed on .*//g' \
        -e 's/-- MySQL dump.*//g' \
        -e 's/[[:space:]]+$//' \
        "$1" | grep -v '^[[:space:]]*$' || true
}

COMMITTED_NORM=$(normalise "$COMMITTED")
FRESH_NORM=$(normalise "$TMP_DUMP")

if [[ "$COMMITTED_NORM" == "$FRESH_NORM" ]]; then
    echo -e "${GREEN}✓ Schema dump is in sync with the live database.${NC}"
    exit 0
fi

echo -e "${RED}✗ Schema drift detected between ${COMMITTED} and the live database.${NC}"
echo ""
echo "Refresh the dump and commit:"
echo "  bash scripts/refresh-schema-dump.sh"
echo "  git add ${COMMITTED}"
echo ""
echo "Diff preview (first 40 lines):"
diff <(echo "$COMMITTED_NORM") <(echo "$FRESH_NORM") | head -40 || true
exit 1
