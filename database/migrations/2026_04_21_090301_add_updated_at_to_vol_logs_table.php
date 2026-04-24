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
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('vol_logs', 'updated_at')) {
            Schema::table('vol_logs', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vol_logs', 'updated_at')) {
            Schema::table('vol_logs', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
