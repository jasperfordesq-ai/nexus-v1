#!/bin/bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INSTALLER="$ROOT/scripts/deploy/phases/install-prerender-cron.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/deploy/scripts" "$TMP/logs"
for script in prerender-job-processor.sh prerender-reap-stale.sh resolve-active-container.sh; do
    printf '#!/bin/bash\nexit 0\n' > "$TMP/deploy/scripts/$script"
    chmod +x "$TMP/deploy/scripts/$script"
done

CRON_FILE="$TMP/nexus-prerender-processor"
DEPLOY_DIR="$TMP/deploy" \
PRERENDER_CRON_FILE="$CRON_FILE" \
PRERENDER_LOG_DIR="$TMP/logs" \
PRERENDER_REAPER_INTERVAL_MINUTES=7 \
bash "$INSTALLER"

grep -Fq '*/7 * * * * root /bin/bash' "$CRON_FILE"
grep -Fq 'Wrapping it in a second flock' "$CRON_FILE"
if grep -Fq 'Wrapping it in a second ' "$CRON_FILE" && ! grep -Fq 'Wrapping it in a second flock' "$CRON_FILE"; then
    echo 'flock disappeared from generated cron comment' >&2
    exit 1
fi

for invalid in 0 60 abc '*/5'; do
    if DEPLOY_DIR="$TMP/deploy" \
        PRERENDER_CRON_FILE="$CRON_FILE" \
        PRERENDER_LOG_DIR="$TMP/logs" \
        PRERENDER_REAPER_INTERVAL_MINUTES="$invalid" \
        bash "$INSTALLER" >/dev/null 2>&1; then
        echo "invalid interval was accepted: $invalid" >&2
        exit 1
    fi
done

echo 'prerender cron installer runtime checks passed'
