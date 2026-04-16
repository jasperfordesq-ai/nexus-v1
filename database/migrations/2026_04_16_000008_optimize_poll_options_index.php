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
        Schema::table('poll_options', function (Blueprint $table) {
            // Add (poll_id, tenant_id) compound index optimised for poll-scoped lookups.
            // The existing idx_poll_options_tenant is (tenant_id, poll_id) — useful for
            // tenant-first scans. This new index covers poll_id-first queries efficiently.
            if (!$this->indexExists('poll_options', 'idx_poll_options_poll_tenant')) {
                $table->index(['poll_id', 'tenant_id'], 'idx_poll_options_poll_tenant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('poll_options', function (Blueprint $table) {
            if ($this->indexExists('poll_options', 'idx_poll_options_poll_tenant')) {
                $table->dropIndex('idx_poll_options_poll_tenant');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $indexes = DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        );
        return !empty($indexes);
    }
};
