<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_logs')) {
            return;
        }

        Schema::table('vol_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('vol_logs', 'assigned_to')) {
                $table->unsignedInteger('assigned_to')->nullable()->after('feedback')->index();
            }
            if (!Schema::hasColumn('vol_logs', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('vol_logs', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('assigned_at')->index();
            }
            if (!Schema::hasColumn('vol_logs', 'escalation_note')) {
                $table->text('escalation_note')->nullable()->after('escalated_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_logs')) {
            return;
        }

        Schema::table('vol_logs', function (Blueprint $table) {
            foreach (['assigned_to', 'assigned_at', 'escalated_at', 'escalation_note'] as $column) {
                if (Schema::hasColumn('vol_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
