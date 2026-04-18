#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

prune_docker_images() {
    log_step "=== Docker Image Cleanup ==="
    local RECLAIMED
    RECLAIMED=$(docker image prune -f 2>&1 | grep 'Total reclaimed' || echo 'Total reclaimed space: 0B')
    log_ok "Dangling images removed -- $RECLAIMED"
}

prune_docker_images
