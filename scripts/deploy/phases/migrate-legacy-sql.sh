#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

run_pending_migrations() {
    log_step "=== Database Migrations ==="

    local MIGRATION_DIR="$DEPLOY_DIR/migrations"

    if [ ! -d "$MIGRATION_DIR" ]; then
        log_warn "migrations/ directory not found — skipping"
        return 0
    fi

    # Read DB credentials from .env
    local DB_USER DB_PASS DB_NAME
    DB_USER=$(grep "^DB_USER=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")
    DB_PASS=$(grep "^DB_PASS=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep "^DB_NAME=" "$DEPLOY_DIR/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || echo "nexus")

    # Ensure the migrations tracking table exists
    docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysql -u"$DB_USER" "$DB_NAME" -e "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            backups VARCHAR(255) DEFAULT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    " 2>/dev/null

    # Get list of already-applied migrations
    local APPLIED
    APPLIED=$(docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysql -u"$DB_USER" "$DB_NAME" \
        -N -e "SELECT migration_name FROM migrations WHERE migration_name IS NOT NULL;" 2>/dev/null || echo "")

    # Find pending .sql files (sorted alphabetically)
    local PENDING_COUNT=0
    local PENDING_FILES=()

    for SQL_FILE in "$MIGRATION_DIR"/*.sql; do
        [ -f "$SQL_FILE" ] || continue
        local BASENAME
        BASENAME=$(basename "$SQL_FILE")
        if ! echo "$APPLIED" | grep -qxF "$BASENAME"; then
            PENDING_FILES+=("$SQL_FILE")
            PENDING_COUNT=$((PENDING_COUNT + 1))
        fi
    done

    if [ $PENDING_COUNT -eq 0 ]; then
        log_ok "All migrations are up to date"
        return 0
    fi

    log_info "$PENDING_COUNT pending migration(s) to run:"
    for F in "${PENDING_FILES[@]}"; do
        echo "  • $(basename "$F")" | tee -a "$LOG_FILE"
    done
    echo "" | tee -a "$LOG_FILE"

    # Execute each pending migration
    local RAN=0
    local FAILED=0

    for SQL_FILE in "${PENDING_FILES[@]}"; do
        local BASENAME
        BASENAME=$(basename "$SQL_FILE")

        # Scan for dangerous operations (log only, don't block in automated deploy)
        if grep -qiE '(DROP TABLE|DROP DATABASE|TRUNCATE)' "$SQL_FILE" 2>/dev/null; then
            log_warn "$BASENAME contains DROP/TRUNCATE operations"
        fi

        log_info "Running: $BASENAME"
        if docker exec -i -e MYSQL_PWD="$DB_PASS" nexus-php-db mysql -u"$DB_USER" "$DB_NAME" < "$SQL_FILE" 2>&1 | tee -a "$LOG_FILE"; then
            # Record as applied
            docker exec -e MYSQL_PWD="$DB_PASS" nexus-php-db mysql -u"$DB_USER" "$DB_NAME" -e "
                INSERT IGNORE INTO migrations (migration_name, backups, executed_at)
                VALUES ('$BASENAME', '$BASENAME', NOW());
            " 2>/dev/null
            log_ok "Applied: $BASENAME"
            RAN=$((RAN + 1))
        else
            log_err "FAILED: $BASENAME"
            FAILED=1
            break
        fi
    done

    echo "" | tee -a "$LOG_FILE"
    if [ $FAILED -eq 1 ]; then
        log_err "Migration failed — deploy halted. $RAN migration(s) applied before failure."
        log_err "Fix the failed migration and re-run the deploy."
        return 1
    fi

    log_ok "$RAN migration(s) applied successfully"
    return 0
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    run_pending_migrations
fi
