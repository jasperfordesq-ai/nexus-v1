<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add an index on password_resets.token to support O(1) lookups now that
 * tokens are stored as SHA-256 hashes (deterministic) instead of bcrypt
 * (per-row salt — not indexable).
 *
 * Also purges any pre-existing rows: legacy bcrypt hashes are incompatible
 * with the new SHA-256 lookup path. Tokens expire in 1 hour anyway, so the
 * blast radius is at most a handful of pending reset emails which the user
 * can simply re-request.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('password_resets')) {
            return;
        }

        // Wipe legacy bcrypt-hashed tokens — they cannot be matched by the
        // new SHA-256 lookup. Worst case: pending reset links become invalid
        // and the user requests a new one (1h TTL anyway).
        DB::statement("DELETE FROM password_resets");

        // Add an index on token for fast exact-match lookups.
        $indexExists = collect(DB::select("SHOW INDEX FROM password_resets"))
            ->pluck('Key_name')
            ->contains('password_resets_token_index');

        if (!$indexExists) {
            Schema::table('password_resets', function ($table) {
                $table->index('token', 'password_resets_token_index');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('password_resets')) {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM password_resets"))
            ->pluck('Key_name')
            ->contains('password_resets_token_index');

        if ($indexExists) {
            Schema::table('password_resets', function ($table) {
                $table->dropIndex('password_resets_token_index');
            });
        }
    }
};
