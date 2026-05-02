#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

run_laravel_cache() {
    log_step "=== Laravel Cache Optimization ==="

    if ! docker exec "$PHP_CONTAINER" test -f /var/www/html/artisan 2>/dev/null; then
        log_info "artisan not found — skipping Laravel cache steps"
        return 0
    fi

    # Clear stale caches first
    log_info "Clearing Laravel caches..."
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan config:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan route:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan event:clear 2>&1 | tee -a "$LOG_FILE" || true
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan view:clear 2>&1 | tee -a "$LOG_FILE" || true

    # Rebuild caches for production via optimize (runs config/route/event/view:cache
    # plus any app-registered Artisan::optimizeUsing() callbacks in correct order)
    log_info "Rebuilding Laravel caches..."
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan optimize 2>&1 | tee -a "$LOG_FILE"

    # Signal queue workers to gracefully reload new code (workers finish current job then restart)
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan queue:restart 2>&1 | tee -a "$LOG_FILE" || true

    # Ensure storage:link exists
    docker exec "$PHP_CONTAINER" php /var/www/html/artisan storage:link 2>&1 | tee -a "$LOG_FILE" || true

    log_ok "Laravel caches rebuilt"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    run_laravel_cache
fi
