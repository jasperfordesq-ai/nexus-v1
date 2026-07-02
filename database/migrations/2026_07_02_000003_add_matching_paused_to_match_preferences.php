<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Matching v2 — member "pause matching" switch. Paused members receive
 * no new matches (engine returns empty + paused meta) and no match emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('match_preferences') || Schema::hasColumn('match_preferences', 'matching_paused')) {
            return;
        }

        Schema::table('match_preferences', function (Blueprint $table) {
            $table->boolean('matching_paused')->default(false)->after('notify_mutual_matches');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('match_preferences') && Schema::hasColumn('match_preferences', 'matching_paused')) {
            Schema::table('match_preferences', function (Blueprint $table) {
                $table->dropColumn('matching_paused');
            });
        }
    }
};
