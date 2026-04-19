#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

purge_cloudflare_cache() {
    log_step "=== Cloudflare Cache Purge (All Domains) ==="

    if [ ! -f "$DEPLOY_DIR/scripts/purge-cloudflare-cache.sh" ]; then
        log_err "purge-cloudflare-cache.sh not found — Cloudflare cache NOT purged!"
        log_err "Users may see stale content. Run manually: sudo bash scripts/purge-cloudflare-cache.sh"
        return 1
    fi

    # Try up to 2 times (network can be flaky)
    local attempt
    for attempt in 1 2; do
        if bash "$DEPLOY_DIR/scripts/purge-cloudflare-cache.sh" 2>&1 | tee -a "$LOG_FILE"; then
            log_ok "Cloudflare cache purged for all domains"
            return 0
        fi
        if [ "$attempt" -eq 1 ]; then
            log_warn "Cloudflare purge failed — retrying in 5 seconds..."
            sleep 5
        fi
    done

    log_err "Cloudflare cache purge FAILED after 2 attempts — users may see stale content"
    log_err "Run manually: sudo bash scripts/purge-cloudflare-cache.sh"
    return 1  # Non-blocking for deploy (maintenance mode still disabled) but clearly logged
}

purge_cloudflare_cache
