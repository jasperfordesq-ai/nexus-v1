<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge the `tenant_admin` role into `admin`.
 *
 * Audit finding: every authorization gate treated `role='tenant_admin'` and
 * `role='admin'` identically — the real tenant-super-admin power lives in the
 * separate `is_tenant_super_admin` flag column, which is set/cleared
 * independently of the role. So `tenant_admin` as a role string carried no
 * capability beyond `admin`, and `AdminUsersController::setSuperAdmin` revoke
 * used to leave a `role='tenant_admin'` with the flag cleared (a powerless
 * "zombie tenant_admin").
 *
 * This normalises every `tenant_admin` user to `admin` WITHOUT touching the
 * `is_tenant_super_admin` flag — so genuine tenant super-admins keep every
 * power (via the flag), and flagless "zombies" become the plain admins they
 * already effectively were. `tenant_admin` remains accepted in the
 * authorization accept-lists as an inert legacy alias, so any row created
 * before this migration runs still resolves to admin access.
 *
 * `users.role` is a varchar(50), not an enum — data-only change, no schema
 * alteration required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        // Only the role string changes; is_tenant_super_admin is deliberately
        // left as-is so real tenant super-admins retain their privileges.
        DB::table('users')
            ->where('role', 'tenant_admin')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Irreversible: once merged we cannot tell which `admin` rows were
        // previously `tenant_admin`. The flag (is_tenant_super_admin) — the
        // only thing that ever carried real power — was never altered.
    }
};
