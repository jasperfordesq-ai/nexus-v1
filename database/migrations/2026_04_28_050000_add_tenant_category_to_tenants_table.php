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
 * Adds the `tenant_category` column to the `tenants` table.
 *
 * This is a soft taxonomy used for cross-tenant aggregation in
 * super-admin tools (e.g. national KISS dashboard, federation roll-ups).
 * It is NOT used to gate features — features still live on `tenants.features`.
 *
 * Allowed values are conventional, not enforced (so future categories don't
 * require a schema migration). Known values today:
 *
 *   - 'community'         (default — generic timebank community)
 *   - 'kiss_cooperative'  (Swiss KISS-network cooperative node)
 *   - 'caring_community'  (caring-community-focused tenant)
 *   - 'agoris_node'       (AGORIS network node)
 *   - 'foundation'        (national foundation tenant — read-only roll-up host)
 *
 * Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (! Schema::hasColumn('tenants', 'tenant_category')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->string('tenant_category', 50)
                    ->default('community')
                    ->after('slug')
                    ->comment('Soft taxonomy for cross-tenant aggregation (kiss_cooperative, caring_community, agoris_node, community, foundation).');
                $table->index('tenant_category', 'idx_tenants_category');
            });
        }

        // Mark the agoris demo tenant as a KISS cooperative for the K4
        // national dashboard demo. Idempotent — runs only if the row exists.
        DB::table('tenants')
            ->where('slug', 'agoris')
            ->update(['tenant_category' => 'kiss_cooperative']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (Schema::hasColumn('tenants', 'tenant_category')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropIndex('idx_tenants_category');
                $table->dropColumn('tenant_category');
            });
        }
    }
};
