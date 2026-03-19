<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add columns that Laravel/Sanctum expects but the legacy schema may lack.
 *
 * - users.email_verified_at — already exists in production (added Jan 2026),
 *   but we guard with hasColumn() for fresh installs where the baseline
 *   already includes it.
 * - users.remember_token — needed by Laravel's "remember me" auth feature.
 *
 * All additions are idempotent via Schema::hasColumn() checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            // Nothing to alter — the users table will be created by the
            // baseline migration on fresh databases (which already includes
            // these columns).
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
        });

        // Separate call because Schema::hasColumn() must be evaluated
        // outside the closure for the second column.
        if (! Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->rememberToken()->after('password_hash');
            });
        }
    }

    public function down(): void
    {
        // Only drop columns that we added — never drop email_verified_at
        // because it pre-dates this migration in production.
        if (Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }
    }
};
