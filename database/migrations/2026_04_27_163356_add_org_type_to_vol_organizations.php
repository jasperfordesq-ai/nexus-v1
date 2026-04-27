<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add org_type column to vol_organizations.
 *
 * Allows distinguishing volunteering organisations from Vereine (clubs/associations).
 * Values: 'organisation' (default, backward-compatible) | 'club'
 *
 * Idempotent — guarded with Schema::hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vol_organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('vol_organizations', 'org_type')) {
                $table->string('org_type', 50)->nullable()->default('organisation')->after('status')
                    ->comment('organisation | club');
            }
            if (!Schema::hasColumn('vol_organizations', 'meeting_schedule')) {
                $table->string('meeting_schedule', 255)->nullable()->after('org_type')
                    ->comment('Free-text meeting schedule for clubs (e.g. "Every Tuesday 19:00")');
            }
        });

        // Index for the /clubs directory endpoint
        Schema::table('vol_organizations', function (Blueprint $table) {
            if (!collect(\DB::select("SHOW INDEX FROM vol_organizations WHERE Key_name = 'idx_vol_org_type'"))->count()) {
                $table->index(['tenant_id', 'org_type', 'status'], 'idx_vol_org_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vol_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('vol_organizations', 'meeting_schedule')) {
                $table->dropColumn('meeting_schedule');
            }
            if (Schema::hasColumn('vol_organizations', 'org_type')) {
                $table->dropColumn('org_type');
            }
        });
    }
};
