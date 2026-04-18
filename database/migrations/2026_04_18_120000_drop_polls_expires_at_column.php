<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('polls') || !Schema::hasColumn('polls', 'expires_at')) {
            return;
        }

        // Backfill end_date from expires_at where end_date is null but expires_at has a value
        DB::statement("UPDATE polls SET end_date = expires_at WHERE end_date IS NULL AND expires_at IS NOT NULL");

        Schema::table('polls', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('polls') || Schema::hasColumn('polls', 'expires_at')) {
            return;
        }

        Schema::table('polls', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('created_at');
        });

        DB::statement("UPDATE polls SET expires_at = end_date WHERE end_date IS NOT NULL");
    }
};
