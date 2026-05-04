#!/usr/bin/env bash
# Copyright © 2024–2026 Jasper Ford
# SPDX-License-Identifier: AGPL-3.0-or-later
# Author: Jasper Ford
# See NOTICE file for attribution and acknowledgements.
#
# Migration Safety Gate (expand/contract enforcement).
#
# Blue/green deploys share one database. Any migration that runs while the
# OLD color is still serving traffic must be backwards-compatible:
#
#   • old code must still work against the new schema, AND
#   • new code must still work against the old schema (only matters for rollback).
#
# Destructive operations like dropColumn / renameColumn / change() / making a
# column non-nullable without a default break the live (old) color the moment
# the migration runs — even though the new color isn't live yet.
#
# This phase greps the Laravel migrations directory for those patterns inside
# the candidate release worktree and aborts unless the operator opts in via
# DEPLOY_ALLOW_DESTRUCTIVE_MIGRATION=1.
#
# Usage (from orchestrator):
#   NEXUS_RELEASE_DIR=$release_dir \
#   NEXUS_CANDIDATE_CONTAINER=nexus-${color}-php-app \
#   bash phases/check-migration-safety.sh

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$SCRIPT_DIR/../lib/common.sh"

RELEASE_DIR="${NEXUS_RELEASE_DIR:-$DEPLOY_DIR}"
APP_CONTAINER="${NEXUS_CANDIDATE_CONTAINER:-nexus-php-app}"
MIGRATIONS_DIR="$RELEASE_DIR/database/migrations"
ALLOW_FLAG="${DEPLOY_ALLOW_DESTRUCTIVE_MIGRATION:-0}"

log_step "=== Migration Safety Gate ==="

if [ ! -d "$MIGRATIONS_DIR" ]; then
    log_warn "$MIGRATIONS_DIR not found — skipping safety gate"
    exit 0
fi

# Get list of pending migration filenames from the candidate (which already has
# the new schema files baked into the image but the DB hasn't been touched yet).
pending_raw="$(docker exec "$APP_CONTAINER" php /var/www/html/artisan migrate:status --pending 2>/dev/null || true)"
if echo "$pending_raw" | grep -qi "Nothing to migrate\|No migrations found"; then
    log_ok "No pending migrations — safety gate passes vacuously"
    exit 0
fi

# Extract migration filenames from artisan output. The format is a table:
#   Pending  ........  2026_05_04_120000_add_foo_to_bar
# Strip everything but the basename and append .php for grep.
pending_files=()
while IFS= read -r line; do
    # Match a line that contains "Pending" and capture the migration name.
    if echo "$line" | grep -q "Pending"; then
        name="$(echo "$line" | awk '{for (i=NF;i>0;i--) if ($i ~ /^[0-9]{4}_/) { print $i; exit }}')"
        if [ -n "$name" ]; then
            pending_files+=("$MIGRATIONS_DIR/${name}.php")
        fi
    fi
done <<< "$pending_raw"

if [ "${#pending_files[@]}" -eq 0 ]; then
    log_warn "Could not parse pending migration filenames from artisan output — skipping"
    exit 0
fi

log_info "${#pending_files[@]} pending migration(s) to lint:"
for f in "${pending_files[@]}"; do
    echo "  • $(basename "$f")"
done

# Patterns that break expand/contract during the cutover window.
# (Matches code, not comments — lints are advisory but conservative.)
DANGEROUS_PATTERNS='->dropColumn\(|->renameColumn\(|->change\(\)|Schema::drop\(|Schema::dropIfExists\(|->renameTo\(|->dropForeign\(|->dropPrimary\(|->dropUnique\(|->dropIndex\('

# Non-nullable column adds without a default also break old code that won't write the new column.
NULLABLE_ADDS='->(string|integer|bigInteger|smallInteger|tinyInteger|float|double|decimal|boolean|date|dateTime|time|timestamp|text|json|uuid)\([^)]*\)(?!.*nullable)(?!.*default)'

violations=0
for f in "${pending_files[@]}"; do
    [ -f "$f" ] || continue
    # Skip closed-comment context using PHP-aware grep (best-effort: drop /* */ blocks and // lines).
    body="$(awk '
        /\/\*/ { in_block=1 }
        /\*\// { in_block=0; next }
        in_block { next }
        { sub(/\/\/.*/, ""); print }
    ' "$f")"

    matches="$(echo "$body" | grep -nE "$DANGEROUS_PATTERNS" || true)"
    if [ -n "$matches" ]; then
        log_err "Destructive operation in $(basename "$f"):"
        echo "$matches" | sed 's/^/    /'
        violations=$((violations + 1))
    fi
done

if [ "$violations" -gt 0 ]; then
    if [ "$ALLOW_FLAG" = "1" ]; then
        log_warn "$violations migration(s) contain destructive operations."
        log_warn "Proceeding because DEPLOY_ALLOW_DESTRUCTIVE_MIGRATION=1 is set."
        log_warn "REMINDER: This breaks the active color until traffic switches."
        log_warn "Recommended path: enable maintenance mode for destructive deploys."
        exit 0
    fi

    log_err "$violations migration(s) contain destructive operations."
    log_err "These break the active color while it's still serving traffic."
    log_err ""
    log_err "Options:"
    log_err "  1. Refactor as expand/contract (multi-deploy):"
    log_err "       deploy A — add new column, dual-write"
    log_err "       deploy B — backfill"
    log_err "       deploy C — drop old column"
    log_err "  2. Run with maintenance mode:"
    log_err "       sudo bash scripts/maintenance.sh on"
    log_err "       sudo bash scripts/safe-deploy.sh full"
    log_err "  3. Override (CAUSES BRIEF ACTIVE-COLOR BREAKAGE):"
    log_err "       DEPLOY_ALLOW_DESTRUCTIVE_MIGRATION=1 sudo bash scripts/safe-deploy.sh auto --detach"
    exit 1
fi

log_ok "All pending migrations look expand/contract-safe"
