<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency backstop for Credit Commons transfers: a relayed/created
 * transaction is uniquely identified by (tenant_id, transaction_uuid). Without
 * this, a repeat delivery of the same transaction inserted a second ledger row
 * and double-applied the balance change. The unique index makes a duplicate
 * insert fail at the DB layer so it can be handled as an idempotent replay.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('federation_cc_entries')) {
            return;
        }

        // Idempotent re-run guard (MariaDB/MySQL).
        $exists = collect(DB::select(
            "SHOW INDEX FROM federation_cc_entries WHERE Key_name = 'fed_cc_entries_tenant_uuid_unique'"
        ))->isNotEmpty();
        if ($exists) {
            return;
        }

        Schema::table('federation_cc_entries', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'transaction_uuid'], 'fed_cc_entries_tenant_uuid_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_cc_entries')) {
            return;
        }
        $exists = collect(DB::select(
            "SHOW INDEX FROM federation_cc_entries WHERE Key_name = 'fed_cc_entries_tenant_uuid_unique'"
        ))->isNotEmpty();
        if ($exists) {
            Schema::table('federation_cc_entries', function (Blueprint $table): void {
                $table->dropUnique('fed_cc_entries_tenant_uuid_unique');
            });
        }
    }
};
