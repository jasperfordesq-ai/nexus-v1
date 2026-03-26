<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove hardcoded EUR currency defaults from volunteering tables.
 *
 * Project NEXUS is a global platform — currency should be set explicitly
 * by the application based on tenant configuration, not defaulted to EUR.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vol_expenses', 'currency')) {
            Schema::table('vol_expenses', function (Blueprint $table) {
                $table->string('currency', 10)->nullable()->default(null)->change();
            });
        }

        if (Schema::hasColumn('vol_donations', 'currency')) {
            Schema::table('vol_donations', function (Blueprint $table) {
                $table->string('currency', 10)->nullable()->default(null)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vol_expenses', 'currency')) {
            Schema::table('vol_expenses', function (Blueprint $table) {
                $table->string('currency', 10)->default('EUR')->change();
            });
        }

        if (Schema::hasColumn('vol_donations', 'currency')) {
            Schema::table('vol_donations', function (Blueprint $table) {
                $table->string('currency', 10)->default('EUR')->change();
            });
        }
    }
};
