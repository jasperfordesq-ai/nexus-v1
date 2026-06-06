#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# predeploy-check.sh — static-analysis gate, run BEFORE a production deploy.
#
# Runs larastan/PHPStan against the code in the local `nexus-php-app` container
# (Linux + the same PHP extensions as production = no false alarms). It passes
# when there are no findings beyond phpstan-baseline.neon, and FAILS on any NEW
# finding — the same class of bug (e.g. code using a DB column that doesn't
# exist) that silently broke job offers. This is the cheap, local equivalent of
# the CI check, aimed at the moment that actually matters for this project: the
# deploy.
#
# Exit 0 = safe to deploy. Exit 1 = new errors (deploy should stop).
# Override (e.g. if it ever misfires): ALLOW_PHPSTAN_FAIL=1 bash scripts/predeploy-check.sh
set -uo pipefail

CONTAINER="${NEXUS_PHP_CONTAINER:-nexus-php-app}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
    echo "[predeploy] ⚠ Container '$CONTAINER' is not running — cannot run the checker."
    echo "[predeploy]   Start it with: docker compose --profile docker-php up -d app"
    echo "[predeploy]   (or set NEXUS_PHP_CONTAINER to a running PHP container)"
    exit 2
fi

# Self-heal: make sure the dev tools (larastan) are present in the container.
if ! docker exec "$CONTAINER" test -f vendor/larastan/larastan/extension.neon 2>/dev/null; then
    echo "[predeploy] larastan not found in container — installing dev dependencies..."
    if ! docker exec "$CONTAINER" composer install --no-interaction --no-progress; then
        echo "[predeploy] ✗ composer install failed; cannot run the checker."
        exit 2
    fi
fi

echo "[predeploy] Running larastan/PHPStan (only NEW findings beyond the baseline will block)..."
if docker exec "$CONTAINER" php vendor/bin/phpstan analyse \
        --configuration phpstan.neon --memory-limit=2G --no-progress; then
    echo "[predeploy] ✓ No new static-analysis errors — safe to deploy."
    exit 0
fi

echo ""
echo "[predeploy] ✗ NEW static-analysis errors found (listed above)."
echo "[predeploy]   These weren't there before — fix them before shipping."
if [ "${ALLOW_PHPSTAN_FAIL:-0}" = "1" ]; then
    echo "[predeploy]   ALLOW_PHPSTAN_FAIL=1 is set — proceeding anyway."
    exit 0
fi
echo "[predeploy]   To deploy anyway (e.g. if this is a false alarm): ALLOW_PHPSTAN_FAIL=1 before the deploy command."
exit 1
