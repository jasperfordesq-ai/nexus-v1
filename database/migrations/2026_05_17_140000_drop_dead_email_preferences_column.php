<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead `users.email_preferences` JSON column.
 *
 * The column was an early prototype that was superseded by
 * `users.notification_preferences` before any code path was wired up to
 * read or write it. A code-wide grep confirms zero readers / writers in
 * `app/`. Keeping it around clutters the schema and confuses anyone
 * reading the users table.
 *
 * Safe to drop — guarded by `hasColumn` so re-running on a database that
 * never had it is a no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }
        if (!Schema::hasColumn('users', 'email_preferences')) {
            return;
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('email_preferences');
        });
    }

    public function down(): void
    {
        // Restore the column as JSON nullable, but don't backfill data —
        // there was none in the first place.
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'email_preferences')) {
            Schema::table('users', function (Blueprint $t) {
                $t->json('email_preferences')->nullable();
            });
        }
    }
};
