<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_roles') && !Schema::hasColumn('user_roles', 'scope_organization_id')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->unsignedInteger('scope_organization_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->comment('Optional vol_organizations.id scope for Verein admins.');
                $table->index(['tenant_id', 'scope_organization_id'], 'user_roles_tenant_scope_org_idx');
            });
        }

        if ($this->indexExists('user_roles', 'unique_user_role')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->dropUnique('unique_user_role');
            });
        }

        if (!$this->indexExists('user_roles', 'user_roles_user_role_tenant_scope_unique')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'role_id', 'tenant_id', 'scope_organization_id'],
                    'user_roles_user_role_tenant_scope_unique'
                );
            });
        }

        DB::statement("
            INSERT IGNORE INTO roles (name, display_name, description, level, is_system, tenant_id)
            VALUES (
                'verein_admin',
                'Verein Admin',
                'Scoped association administrator for importing and managing members of one Verein.',
                4,
                1,
                NULL
            )
        ");

        $permissions = [
            ['verein.members.import', 'Import Verein Members', 'vereine'],
            ['verein.members.manage', 'Manage Verein Members', 'vereine'],
            ['verein.view', 'View Verein', 'vereine'],
        ];

        foreach ($permissions as [$name, $displayName, $category]) {
            DB::statement(
                'INSERT IGNORE INTO permissions (name, display_name, category) VALUES (?, ?, ?)',
                [$name, $displayName, $category]
            );
        }

        $roleId = DB::table('roles')->where('name', 'verein_admin')->value('id');
        if (!$roleId) {
            return;
        }

        foreach ($permissions as [$name]) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if ($permissionId) {
                DB::statement(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, NULL)',
                    [$roleId, $permissionId]
                );
            }
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'verein_admin')->value('id');
        if ($roleId) {
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
            DB::table('user_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        if (Schema::hasTable('user_roles') && $this->indexExists('user_roles', 'user_roles_user_role_tenant_scope_unique')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->dropUnique('user_roles_user_role_tenant_scope_unique');
            });
        }

        if (Schema::hasTable('user_roles') && Schema::hasColumn('user_roles', 'scope_organization_id')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                if ($this->indexExists('user_roles', 'user_roles_tenant_scope_org_idx')) {
                    $table->dropIndex('user_roles_tenant_scope_org_idx');
                }
                $table->dropColumn('scope_organization_id');
            });
        }

        if (Schema::hasTable('user_roles') && !$this->indexExists('user_roles', 'unique_user_role')) {
            Schema::table('user_roles', function (Blueprint $table): void {
                $table->unique(['user_id', 'role_id'], 'unique_user_role');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
