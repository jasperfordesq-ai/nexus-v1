#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

cd "$DEPLOY_DIR"

# Update last successful deployment
NEW_COMMIT=$(git rev-parse HEAD)
echo "$NEW_COMMIT" > "$LAST_DEPLOY_FILE"

# Write build version file into httpdocs/ (bind-mounted into Docker container)
DEPLOY_TS=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
COMMIT_MSG=$(git log -1 --format='%s')
cat > "$DEPLOY_DIR/httpdocs/.build-version" <<VEOF
{
    "service": "nexus-php-api",
    "commit": "$NEW_COMMIT",
    "commit_short": "${NEW_COMMIT:0:8}",
    "commit_message": "$COMMIT_MSG",
    "deployed_at": "$DEPLOY_TS",
    "deploy_mode": "$MODE"
}
VEOF
log_ok "Build version file written (httpdocs/.build-version)"
