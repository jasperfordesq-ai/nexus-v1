<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_opportunities')) {
            return;
        }

        foreach (['tenant_id', 'external_partner_id', 'external_id'] as $column) {
            if (!Schema::hasColumn('vol_opportunities', $column)) {
                return;
            }
        }

        $this->dropIndexIfExists('vol_opportunities', 'uk_vol_opp_partner_ext');
        $this->addIndexIfMissing(
            'vol_opportunities',
            'uk_vol_opp_tenant_partner_ext',
            'CREATE UNIQUE INDEX `uk_vol_opp_tenant_partner_ext` ON `vol_opportunities` (`tenant_id`, `external_partner_id`, `external_id`)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_opportunities')) {
            return;
        }

        foreach (['external_partner_id', 'external_id'] as $column) {
            if (!Schema::hasColumn('vol_opportunities', $column)) {
                return;
            }
        }

        $this->dropIndexIfExists('vol_opportunities', 'uk_vol_opp_tenant_partner_ext');
        $this->addIndexIfMissing(
            'vol_opportunities',
            'uk_vol_opp_partner_ext',
            'CREATE UNIQUE INDEX `uk_vol_opp_partner_ext` ON `vol_opportunities` (`external_partner_id`, `external_id`)'
        );
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) === 0) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        }
    }
};
