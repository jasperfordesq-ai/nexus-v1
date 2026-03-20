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
     * Add rate limit tracking columns to federation tables.
     *
     * Mirrors legacy SQL: migrations/2026_02_04_add_rate_limit_tracking_columns.sql
     */
    public function up(): void
    {
        if (!Schema::hasColumn('federation_api_keys', 'rate_limit_hour')) {
            Schema::table('federation_api_keys', function (Blueprint $table) {
                $table->dateTime('rate_limit_hour')->nullable()->default(null);
                $table->unsignedInteger('hourly_request_count')->default(0);

                $table->index('rate_limit_hour', 'idx_rate_limit_hour');
            });
        }

        if (!Schema::hasColumn('federation_api_logs', 'auth_method')) {
            Schema::table('federation_api_logs', function (Blueprint $table) {
                $table->string('auth_method', 20)->default('api_key');
                $table->boolean('signature_valid')->nullable()->default(null);
            });
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasColumn('federation_api_keys', 'rate_limit_hour')) {
            Schema::table('federation_api_keys', function (Blueprint $table) {
                $table->dropIndex('idx_rate_limit_hour');
                $table->dropColumn(['rate_limit_hour', 'hourly_request_count']);
            });
        }

        if (Schema::hasColumn('federation_api_logs', 'auth_method')) {
            Schema::table('federation_api_logs', function (Blueprint $table) {
                $table->dropColumn(['auth_method', 'signature_valid']);
            });
        }
    }
};
