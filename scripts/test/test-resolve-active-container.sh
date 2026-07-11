#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d -t nexus-active-container-XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT

# shellcheck source=../resolve-active-container.sh
source "$REPO_ROOT/scripts/resolve-active-container.sh"
export NEXUS_BLUEGREEN_STATE_FILE="$TMP_DIR/active"

RUNNING_NAMES=""
nexus_running_container_names() {
    printf '%s\n' "$RUNNING_NAMES"
}

RUNNING_NAMES=$'nexus-blue-php-app\nnexus-green-php-app\nnexus-blue-react\nnexus-green-react'
printf 'green\n' > "$NEXUS_BLUEGREEN_STATE_FILE"
[ "$(resolve_active_nexus_container php-app)" = 'nexus-green-php-app' ]
[ "$(resolve_active_nexus_container react)" = 'nexus-green-react' ]

rm -f "$NEXUS_BLUEGREEN_STATE_FILE"
RUNNING_NAMES=$'nexus-blue-php-app\nnexus-blue-react'
[ "$(resolve_active_nexus_container php-app)" = 'nexus-blue-php-app' ]
[ "$(resolve_active_nexus_container react)" = 'nexus-blue-react' ]

RUNNING_NAMES=$'nexus-blue-php-app\nnexus-green-php-app'
if resolve_active_nexus_container php-app >/dev/null 2>&1; then
    echo 'FAIL: ambiguous blue/green containers were accepted without active state' >&2
    exit 1
fi

RUNNING_NAMES=$'nexus-blue-php-app\nnexus-php-app'
if resolve_active_nexus_container php-app >/dev/null 2>&1; then
    echo 'FAIL: mixed blue/green and legacy containers were accepted without active state' >&2
    exit 1
fi

printf 'green\n' > "$NEXUS_BLUEGREEN_STATE_FILE"
RUNNING_NAMES='nexus-blue-php-app'
if resolve_active_nexus_container php-app >/dev/null 2>&1; then
    echo 'FAIL: resolver ignored a valid active state whose container is down' >&2
    exit 1
fi

printf 'invalid\n' > "$NEXUS_BLUEGREEN_STATE_FILE"
RUNNING_NAMES='nexus-green-php-app'
[ "$(resolve_active_nexus_container php-app)" = 'nexus-green-php-app' ]

rm -f "$NEXUS_BLUEGREEN_STATE_FILE"
RUNNING_NAMES=$'nexus-php-app\nnexus-react-prod'
[ "$(resolve_active_nexus_container php-app)" = 'nexus-php-app' ]
[ "$(resolve_active_nexus_container react)" = 'nexus-react-prod' ]

for consumer in \
    "$REPO_ROOT/scripts/prerender-job-processor.sh" \
    "$REPO_ROOT/scripts/prerender-tenants.sh" \
    "$REPO_ROOT/scripts/deploy/phases/install-prerender-cron.sh"; do
    if grep -Fq 'head -1' "$consumer"; then
        echo "FAIL: ambiguous docker ps ordering remains in $consumer" >&2
        exit 1
    fi
done
grep -Fq 'resolve_active_nexus_container php-app' "$REPO_ROOT/scripts/prerender-job-processor.sh"
grep -Fq 'resolve_active_nexus_container react' "$REPO_ROOT/scripts/prerender-tenants.sh"
grep -Fq '/bin/bash $REAPER_SCRIPT' "$REPO_ROOT/scripts/deploy/phases/install-prerender-cron.sh"
grep -Fq -- '--heartbeat-id="$JOB_ID"' "$REPO_ROOT/scripts/prerender-job-processor.sh"
grep -Fq 'PRERENDER_MAX_RUN_SECONDS' "$REPO_ROOT/scripts/prerender-job-processor.sh"
grep -Fq 'PLAYWRIGHT_NPM_VERSION="${PLAYWRIGHT_NPM_VERSION:-1.59.1}"' "$REPO_ROOT/scripts/prerender-tenants.sh"
grep -Fq -- '--memory "$PRERENDER_MEMORY_LIMIT"' "$REPO_ROOT/scripts/prerender-tenants.sh"

echo 'PASS: active container resolution and prerender runtime guards are fail-closed'
