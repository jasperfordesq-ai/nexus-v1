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
 * Enforces one broker_message_copies row per (tenant_id, original_message_id).
 *
 * Without this, BrokerMessageVisibilityService::copyMessageForBroker() races
 * between the existence check and the insert — a retried queue job or a
 * duplicate event dispatch could create multiple copies of the same message,
 * triggering duplicate broker notifications and duplicate emails.
 *
 * The service has been switched to firstOrCreate() to rely on this index for
 * atomicity.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('broker_message_copies')) {
            return;
        }

        // Dedupe any pre-existing duplicates: keep the row with the lowest id
        // per (tenant_id, original_message_id) and remove the rest. MariaDB
        // supports multi-table DELETE with self-join.
        DB::statement(
            'DELETE bmc1 FROM broker_message_copies bmc1 '
            . 'INNER JOIN broker_message_copies bmc2 '
            . '  ON bmc1.tenant_id = bmc2.tenant_id '
            . ' AND bmc1.original_message_id = bmc2.original_message_id '
            . ' AND bmc1.id > bmc2.id'
        );

        Schema::table('broker_message_copies', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'original_message_id'],
                'broker_message_copies_tenant_original_uniq'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('broker_message_copies')) {
            return;
        }

        Schema::table('broker_message_copies', function (Blueprint $table) {
            $table->dropUnique('broker_message_copies_tenant_original_uniq');
        });
    }
};
