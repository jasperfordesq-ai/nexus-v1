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
        if (!Schema::hasTable('verein_event_shares')) {
            return;
        }

        DB::statement("
            DELETE s FROM verein_event_shares s
            INNER JOIN verein_event_shares keep
                ON keep.tenant_id = s.tenant_id
               AND keep.event_id = s.event_id
               AND keep.target_organization_id = s.target_organization_id
               AND keep.id < s.id
        ");

        if (!$this->indexExists('verein_event_shares', 'verein_event_shares_unique_target')) {
            Schema::table('verein_event_shares', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'event_id', 'target_organization_id'],
                    'verein_event_shares_unique_target'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('verein_event_shares')) {
            return;
        }

        if ($this->indexExists('verein_event_shares', 'verein_event_shares_unique_target')) {
            Schema::table('verein_event_shares', function (Blueprint $table): void {
                $table->dropUnique('verein_event_shares_unique_target');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($index);
    }
};
