#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"
. "$SCRIPT_DIR/../lib/db-backup.sh"

run_laravel_artisan_migrate() {
    log_step "=== Laravel Artisan Migrations ==="

    local app_container="${PHP_CONTAINER:-nexus-php-app}"

    if ! docker exec "$app_container" test -f /var/www/html/artisan 2>/dev/null; then
        log_info "artisan not found — skipping Laravel migrations"
        return 0
    fi

    local pending
    pending="$(db_pending_migration_count "$app_container")"
    if [ "${pending:-0}" -eq 0 ]; then
        log_ok "No pending migrations — skipping backup and migrate"
        return 0
    fi

    log_warn "$pending pending migration(s) detected. Backing up before migrate."
    if ! db_backup_with_offsite "$app_container"; then
        log_err "Pre-migration backup failed — aborting migration to prevent unrecoverable data loss"
        return 1
    fi

    log_info "Running php artisan migrate --force..."
    repair_laravel_runtime_ownership "$app_container"
    if docker_exec_app_user "$app_container" php /var/www/html/artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
        repair_laravel_runtime_ownership "$app_container"
        log_ok "Laravel artisan migrations completed"
    else
        repair_laravel_runtime_ownership "$app_container"
        log_err "Laravel artisan migrate failed"
        return 1
    fi
}

if ! run_laravel_artisan_migrate; then
    log_err "Laravel artisan migration failed — consider rollback"
    exit 1
fi

# Refresh the schema dump so it stays current with the latest migrations.
# This file is committed to git — new contributors get a working DB from it.
log_step "=== Refreshing Schema Dump ==="
if DEPLOY_ENV=production DB_USER="${DB_USER:-nexus}" DB_PASS="${DB_PASS:-}" DB_NAME="${DB_NAME:-nexus}" \
   bash "$DEPLOY_DIR/scripts/refresh-schema-dump.sh" --production 2>&1 | tee -a "$LOG_FILE"; then
    log_ok "Schema dump refreshed at database/schema/mysql-schema.sql"
else
    log_info "Schema dump refresh failed (non-fatal) — regenerate manually"
fi
