<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_opportunities')) {
            return;
        }

        Schema::table('vol_opportunities', function (Blueprint $table): void {
            if (!Schema::hasColumn('vol_opportunities', 'is_federated')) {
                $table->boolean('is_federated')->default(false)->after('updated_at');
            }
            if (!Schema::hasColumn('vol_opportunities', 'external_partner_id')) {
                $table->unsignedBigInteger('external_partner_id')->nullable()->after('is_federated');
            }
            if (!Schema::hasColumn('vol_opportunities', 'external_id')) {
                $table->string('external_id', 128)->nullable()->after('external_partner_id');
            }
            if (!Schema::hasColumn('vol_opportunities', 'source_tenant_id')) {
                $table->integer('source_tenant_id')->nullable()->after('external_id');
            }
        });

        // Federated opportunities have no local organisation, so organization_id
        // must accept NULL. Existing FK is ON DELETE CASCADE which is compatible
        // with a nullable column.
        if (Schema::hasColumn('vol_opportunities', 'organization_id')) {
            DB::statement('ALTER TABLE `vol_opportunities` MODIFY `organization_id` INT(11) NULL');
        }

        // Indexes (idempotent via information_schema check).
        $this->addIndexIfMissing(
            'vol_opportunities',
            'uk_vol_opp_partner_ext',
            'CREATE UNIQUE INDEX `uk_vol_opp_partner_ext` ON `vol_opportunities` (`external_partner_id`, `external_id`)'
        );
        $this->addIndexIfMissing(
            'vol_opportunities',
            'idx_vol_opp_federated',
            'CREATE INDEX `idx_vol_opp_federated` ON `vol_opportunities` (`is_federated`, `tenant_id`)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_opportunities')) {
            return;
        }

        $this->dropIndexIfExists('vol_opportunities', 'uk_vol_opp_partner_ext');
        $this->dropIndexIfExists('vol_opportunities', 'idx_vol_opp_federated');

        Schema::table('vol_opportunities', function (Blueprint $table): void {
            foreach (['source_tenant_id', 'external_id', 'external_partner_id', 'is_federated'] as $col) {
                if (Schema::hasColumn('vol_opportunities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Revert organization_id back to NOT NULL only if no rows have NULL.
        $nullCount = (int) DB::table('vol_opportunities')->whereNull('organization_id')->count();
        if ($nullCount === 0 && Schema::hasColumn('vol_opportunities', 'organization_id')) {
            DB::statement('ALTER TABLE `vol_opportunities` MODIFY `organization_id` INT(11) NOT NULL');
        }
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
