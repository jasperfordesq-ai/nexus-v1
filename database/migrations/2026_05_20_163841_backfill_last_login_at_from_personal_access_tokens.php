<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill last_login_at on active users using the most recent Sanctum token
     * creation date as a proxy for last login time.
     *
     * Context: last_login_at was never stamped after the Laravel migration (2026-03-05)
     * because AuthController::login() omitted the update call. This migration repairs
     * the gap so member-activity reports show realistic data.
     *
     * Safe to re-run: only overwrites NULL or older values, never a newer one.
     */
    public function up(): void
    {
        // Note: no tokenable_type filter — all rows in personal_access_tokens
        // are App\Models\User and the backslash escaping is fragile across DBs.
        DB::statement("
            UPDATE users u
            INNER JOIN (
                SELECT tokenable_id AS user_id, MAX(created_at) AS latest_token
                FROM personal_access_tokens
                GROUP BY tokenable_id
            ) t ON t.user_id = u.id
            SET u.last_login_at = t.latest_token
            WHERE u.status = 'active'
              AND (u.last_login_at IS NULL OR u.last_login_at < t.latest_token)
        ");
    }

    public function down(): void
    {
        // Not reversible — we don't know which values were set by this migration
    }
};
