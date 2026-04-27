<?php
// Copyright (c) 2024-2026 Jasper Ford
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
        if (!Schema::hasTable('vol_logs')) {
            return;
        }

        Schema::table('vol_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('vol_logs', 'caring_support_relationship_id')) {
                $table->unsignedBigInteger('caring_support_relationship_id')->nullable()->after('opportunity_id')->index('idx_vol_logs_caring_relationship');
            }
            if (!Schema::hasColumn('vol_logs', 'support_recipient_id')) {
                $table->unsignedInteger('support_recipient_id')->nullable()->after('caring_support_relationship_id')->index('idx_vol_logs_support_recipient');
            }
        });

        if (Schema::hasColumn('vol_logs', 'organization_id')) {
            try {
                DB::statement('ALTER TABLE `vol_logs` MODIFY `organization_id` int(11) NULL');
            } catch (\Throwable $e) {
                logger()->warning('Could not make vol_logs.organization_id nullable: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_logs')) {
            return;
        }

        Schema::table('vol_logs', function (Blueprint $table): void {
            foreach (['support_recipient_id', 'caring_support_relationship_id'] as $column) {
                if (Schema::hasColumn('vol_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
