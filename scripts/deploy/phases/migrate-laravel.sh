#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

run_laravel_artisan_migrate() {
    log_step "=== Laravel Artisan Migrations ==="

    if ! docker exec nexus-php-app test -f /var/www/html/artisan 2>/dev/null; then
        log_info "artisan not found — skipping Laravel migrations"
        return 0
    fi

    log_info "Taking pre-migration database snapshot..."
    local BACKUP_DIR="/opt/nexus-php/backups"
    mkdir -p "$BACKUP_DIR"
    local BACKUP_FILE="$BACKUP_DIR/pre-migrate-$(date +%Y%m%d-%H%M%S)-laravel.sql.gz"
    local DB_PASS
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | sed 's/^DB_PASS=//' | tr -d "\"'")
    if docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysqldump -u nexus nexus 2>/dev/null | gzip > "$BACKUP_FILE"; then
        log_ok "Database backed up to $BACKUP_FILE ($(du -sh "$BACKUP_FILE" | cut -f1))"
        find "$BACKUP_DIR" -name "pre-migrate-*.sql.gz" -mtime +7 -delete
    else
        log_err "Database backup failed — aborting migration to prevent unrecoverable data loss"
        rm -f "$BACKUP_FILE"
        return 1
    fi

    log_info "Running php artisan migrate --force..."
    if docker exec nexus-php-app php /var/www/html/artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
        log_ok "Laravel artisan migrations completed"
    else
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
