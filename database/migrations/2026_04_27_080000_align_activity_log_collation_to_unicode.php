<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align activity_log collation with org_audit_log so cross-table UNIONs
 * succeed without explicit COLLATE workarounds.
 *
 * Background: production logs on 2026-04-26 surfaced ER 1271 ("Illegal
 * mix of collations for operation 'UNION'") on the broker dashboard's
 * recent_activity feed. activity_log was created with
 * utf8mb4_general_ci (table default) and org_audit_log with
 * utf8mb4_unicode_ci. Their `action` columns inherited the table
 * default; their `details` columns drifted further apart
 * (general_ci vs bin).
 *
 * Tactical patch already shipped in commit 2cf344346 added explicit
 * COLLATE clauses to the controller's UNION query. This migration
 * removes the underlying mismatch so the workaround is no longer
 * load-bearing — and so any future UNION/JOIN crossing these tables
 * doesn't have to remember the same trick.
 *
 * Scope: change ONLY the two columns the UNION reads (action,
 * details). The other string columns on activity_log
 * (action_type, entity_type, ip_address, link_url, distance,
 * user_agent) are left on utf8mb4_general_ci — they are not part
 * of the UNION and converting them is unnecessary risk.
 *
 * Choice of target collations:
 * - action → utf8mb4_unicode_ci. Action is an ASCII enum key, but
 *   matching org_audit_log.action's collation is the goal. The
 *   surrounding 423 nexus tables also use utf8mb4_unicode_ci.
 * - details → utf8mb4_bin. Matches org_audit_log.details. `bin` is
 *   the correct collation for a JSON-bearing TEXT column —
 *   case-sensitive byte comparison preserves JSON semantics.
 *
 * Size: activity_log is small in production (~1.4k rows). The
 * ALTER is near-instant and fully online on InnoDB for these
 * column-level changes.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        // Idempotent: only ALTER if the current collation differs from
        // the target. Re-running the migration on an already-aligned
        // schema is a no-op, which matters because the schema dump
        // baseline ships pre-migrated DBs to fresh dev setups.
        $cols = DB::select(
            "SELECT column_name, collation_name
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'activity_log'
               AND column_name IN ('action', 'details')"
        );
        $current = [];
        foreach ($cols as $c) {
            $current[$c->column_name ?? $c->COLUMN_NAME] = $c->collation_name ?? $c->COLLATION_NAME;
        }

        if (($current['action'] ?? null) !== 'utf8mb4_unicode_ci') {
            DB::statement(
                "ALTER TABLE activity_log
                 MODIFY COLUMN `action` varchar(255)
                   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                   NOT NULL"
            );
        }

        if (($current['details'] ?? null) !== 'utf8mb4_bin') {
            DB::statement(
                "ALTER TABLE activity_log
                 MODIFY COLUMN `details` text
                   CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
                   DEFAULT NULL"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        // Roll back to the original general_ci collations the table
        // shipped with. Down-migrations on production are mostly
        // theoretical, but keeping reversibility honest.
        DB::statement(
            "ALTER TABLE activity_log
             MODIFY COLUMN `action` varchar(255)
               CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
               NOT NULL"
        );

        DB::statement(
            "ALTER TABLE activity_log
             MODIFY COLUMN `details` text
               CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
               DEFAULT NULL"
        );
    }
};
