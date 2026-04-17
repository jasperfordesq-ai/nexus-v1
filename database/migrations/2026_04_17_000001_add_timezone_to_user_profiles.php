<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add timezone column to users table.
 *
 * user_profiles does not exist in this schema; timezone is stored directly on
 * the users table so the FeedRankingService can apply context-timing boosts
 * in the viewer's local timezone (Signal 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('timezone', 64)->nullable()->default('UTC')
                    ->after('preferred_language')
                    ->comment('IANA timezone name used for context-timing feed ranking');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};
