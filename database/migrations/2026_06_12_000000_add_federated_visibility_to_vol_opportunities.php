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
 * Per-opportunity federation opt-in for volunteer opportunities, mirroring
 * listings.federated_visibility. `is_federated` on vol_opportunities means
 * "row was IMPORTED from a partner" and must not be repurposed as an export
 * flag — doing so caused federation echo (re-exporting imported rows).
 *
 * Backfill: existing LOCAL opportunities that were being pushed under the old
 * fuzzy gate (is_active=1 AND status='open', not imported) become 'listed' so
 * partner communities don't silently lose what they already see. Imported
 * rows stay 'none'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vol_opportunities')) {
            return;
        }

        Schema::table('vol_opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('vol_opportunities', 'federated_visibility')) {
                // NOT NULL is the Blueprint default (no ->nullable()).
                $table->enum('federated_visibility', ['none', 'listed'])
                    ->default('none')
                    ->after('is_federated');
                $table->index(['federated_visibility', 'tenant_id'], 'idx_vol_opp_fed_visibility');
            }
        });

        // Backfill: local active+open opportunities were implicitly shared by
        // the old listener fallback — keep them shared. Imported rows
        // (is_federated=1 or external_id set) must never be re-exported.
        DB::update("
            UPDATE vol_opportunities
            SET federated_visibility = 'listed'
            WHERE is_federated = 0
              AND external_id IS NULL
              AND is_active = 1
              AND status IN ('open', 'active')
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('vol_opportunities')) {
            return;
        }

        Schema::table('vol_opportunities', function (Blueprint $table) {
            if (Schema::hasColumn('vol_opportunities', 'federated_visibility')) {
                $table->dropIndex('idx_vol_opp_fed_visibility');
                $table->dropColumn('federated_visibility');
            }
        });
    }
};
