<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the municipality_announcer role with feed permissions.
 *
 * Idempotent — uses INSERT IGNORE so it is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Insert the role (idempotent via INSERT IGNORE on unique name)
        DB::statement("
            INSERT IGNORE INTO roles (name, display_name, description, level, is_system, tenant_id)
            VALUES ('municipality_announcer', 'Municipality Announcer',
                    'Verified municipal authority that can post pinned official notices to the community feed.',
                    5, 1, NULL)
        ");

        $roleId = DB::table('roles')->where('name', 'municipality_announcer')->value('id');
        if (!$roleId) {
            return;
        }

        // 2. Ensure the required permissions exist
        $permissions = [
            ['name' => 'feed.post',          'display_name' => 'Post to Feed',            'category' => 'feed'],
            ['name' => 'feed.pin',           'display_name' => 'Pin Feed Posts',           'category' => 'feed'],
            ['name' => 'feed.badge_official','display_name' => 'Badge Posts as Official',  'category' => 'feed'],
            ['name' => 'members.view',       'display_name' => 'View Members',             'category' => 'members'],
        ];

        foreach ($permissions as $perm) {
            DB::statement(
                "INSERT IGNORE INTO permissions (name, display_name, category) VALUES (?, ?, ?)",
                [$perm['name'], $perm['display_name'], $perm['category']]
            );
        }

        // 3. Attach permissions to role
        foreach ($permissions as $perm) {
            $permId = DB::table('permissions')->where('name', $perm['name'])->value('id');
            if ($permId) {
                DB::statement(
                    "INSERT IGNORE INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, NULL)",
                    [$roleId, $permId]
                );
            }
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'municipality_announcer')->value('id');
        if ($roleId) {
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
            DB::table('user_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
