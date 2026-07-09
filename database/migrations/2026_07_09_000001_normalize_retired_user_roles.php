<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the non-functional 'moderator' and 'newsletter_admin' placeholder
 * user roles. Neither was ever wired to a backend capability, so any user
 * still carrying one behaved exactly like a plain 'member'. Normalise them to
 * 'member' so no orphaned role values remain now that the admin role selector
 * and the AdminUsersController allow-lists no longer offer them (a stale value
 * would otherwise make the user un-editable via the role dropdown).
 *
 * `users.role` is a varchar(50), not an enum, so this is a data-only change —
 * no schema alteration is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        // Cross-tenant data hygiene: the retired roles granted nothing anywhere,
        // so this is safe to apply globally in a single statement.
        DB::table('users')
            ->whereIn('role', ['moderator', 'newsletter_admin'])
            ->update(['role' => 'member']);
    }

    public function down(): void
    {
        // Irreversible: the placeholder roles carried no capability, so the
        // pre-migration value cannot be reconstructed and there is nothing
        // meaningful to restore.
    }
};
