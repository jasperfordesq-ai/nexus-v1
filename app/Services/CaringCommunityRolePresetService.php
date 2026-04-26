<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Installs KISS-style operational roles into the existing RBAC tables.
 */
class CaringCommunityRolePresetService
{
    private const PERMISSIONS = [
        'caring.view' => ['View caring community workspace', 'Open caring-community dashboards and operating views.', 'caring'],
        'caring.configure' => ['Configure caring community module', 'Manage caring-community settings and module controls.', 'caring'],
        'caring.workflow.review' => ['Review caring workflow', 'Review caring-community queue items and workflow evidence.', 'caring'],
        'caring.workflow.assign' => ['Assign caring workflow', 'Assign caring-community coordination work to trusted users.', 'caring'],
        'caring.reports.view' => ['View caring reports', 'View municipal and KISS caring-community reports.', 'caring'],
        'caring.reports.export' => ['Export caring reports', 'Export municipal and KISS caring-community evidence packs.', 'caring'],
        'volunteering.hours.review' => ['Review volunteering hours', 'Approve or decline volunteer hour logs.', 'volunteering'],
        'volunteering.organisations.manage' => ['Manage volunteering organisations', 'Manage trusted volunteering partner organisations.', 'volunteering'],
        'volunteering.opportunities.manage' => ['Manage volunteering opportunities', 'Manage volunteering programs, opportunities, and rosters.', 'volunteering'],
        'members.assisted_onboarding' => ['Assist member onboarding', 'Help members complete onboarding and coordinator-supported setup.', 'users'],
        'safeguarding.view' => ['View safeguarding signals', 'View safeguarding and sensitive-care coordination signals.', 'safeguarding'],
        'federation.nodes.view' => ['View federation nodes', 'View connected regional, canton, or cooperative nodes.', 'federation'],
    ];

    private const PRESETS = [
        'national_admin' => [
            'display_name' => 'KISS National Foundation Admin',
            'description' => 'Cross-program oversight, reporting standards, federation view, and network governance.',
            'level' => 90,
            'permissions' => [
                'caring.view',
                'caring.configure',
                'caring.workflow.review',
                'caring.workflow.assign',
                'caring.reports.view',
                'caring.reports.export',
                'volunteering.hours.review',
                'volunteering.organisations.manage',
                'members.assisted_onboarding',
                'safeguarding.view',
                'federation.nodes.view',
            ],
        ],
        'canton_admin' => [
            'display_name' => 'KISS Canton Admin',
            'description' => 'Regional operating view, municipal coordination, reporting, and trusted partner oversight.',
            'level' => 80,
            'permissions' => [
                'caring.view',
                'caring.workflow.review',
                'caring.workflow.assign',
                'caring.reports.view',
                'caring.reports.export',
                'volunteering.hours.review',
                'volunteering.organisations.manage',
                'members.assisted_onboarding',
                'federation.nodes.view',
            ],
        ],
        'municipality_admin' => [
            'display_name' => 'KISS Municipality Admin',
            'description' => 'Local participation, requests, organisations, and public-sector reporting.',
            'level' => 70,
            'permissions' => [
                'caring.view',
                'caring.workflow.review',
                'caring.reports.view',
                'caring.reports.export',
                'volunteering.hours.review',
                'volunteering.organisations.manage',
                'members.assisted_onboarding',
            ],
        ],
        'cooperative_coordinator' => [
            'display_name' => 'KISS Cooperative Coordinator',
            'description' => 'Member onboarding, matching, hour review, and sensitive support escalation.',
            'level' => 60,
            'permissions' => [
                'caring.view',
                'caring.workflow.review',
                'caring.workflow.assign',
                'volunteering.hours.review',
                'members.assisted_onboarding',
                'safeguarding.view',
            ],
        ],
        'organisation_coordinator' => [
            'display_name' => 'KISS Organisation Coordinator',
            'description' => 'Opportunity management, volunteer rosters, logged hours, and partner activity.',
            'level' => 50,
            'permissions' => [
                'caring.view',
                'caring.workflow.review',
                'volunteering.hours.review',
                'volunteering.opportunities.manage',
            ],
        ],
        'trusted_reviewer' => [
            'display_name' => 'KISS Trusted Volunteer Reviewer',
            'description' => 'Limited review authority for approved hour logs and community trust signals.',
            'level' => 40,
            'permissions' => [
                'caring.view',
                'caring.workflow.review',
                'volunteering.hours.review',
            ],
        ],
    ];

    public function status(int $tenantId): array
    {
        if (!$this->tablesAvailable()) {
            return [
                'available' => false,
                'installed_count' => 0,
                'total_count' => count(self::PRESETS),
                'presets' => [],
            ];
        }

        $presets = array_map(fn (string $key, array $preset): array => $this->presetStatus($tenantId, $key, $preset), array_keys(self::PRESETS), self::PRESETS);

        return [
            'available' => true,
            'installed_count' => count(array_filter($presets, fn (array $preset): bool => $preset['installed'])),
            'total_count' => count($presets),
            'presets' => $presets,
        ];
    }

    public function install(int $tenantId, ?string $presetKey = null): array
    {
        if (!$this->tablesAvailable()) {
            return $this->status($tenantId);
        }

        $selectedPresets = $presetKey && isset(self::PRESETS[$presetKey])
            ? [$presetKey => self::PRESETS[$presetKey]]
            : self::PRESETS;

        DB::transaction(function () use ($tenantId, $selectedPresets): void {
            $permissionIds = $this->ensurePermissions();
            foreach ($selectedPresets as $key => $preset) {
                $roleId = $this->ensureRole($tenantId, $key, $preset);
                foreach ($preset['permissions'] as $permissionName) {
                    if (!isset($permissionIds[$permissionName])) {
                        continue;
                    }

                    DB::insert(
                        'INSERT IGNORE INTO role_permissions (role_id, permission_id, granted_by, tenant_id) VALUES (?, ?, ?, ?)',
                        [$roleId, $permissionIds[$permissionName], Auth::id(), $tenantId]
                    );
                }
            }
        });

        return $this->status($tenantId);
    }

    private function tablesAvailable(): bool
    {
        return Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('role_permissions');
    }

    private function presetStatus(int $tenantId, string $key, array $preset): array
    {
        $roleName = $this->roleName($tenantId, $key);
        $role = DB::selectOne('SELECT id FROM roles WHERE tenant_id = ? AND name = ? LIMIT 1', [$tenantId, $roleName]);
        $roleId = $role ? (int) $role->id : null;
        $permissionCount = count($preset['permissions']);
        $installedPermissions = 0;

        if ($roleId !== null) {
            $installedPermissions = (int) (DB::selectOne(
                'SELECT COUNT(*) AS count FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.name IN (' . $this->placeholders($permissionCount) . ')',
                array_merge([$roleId], $preset['permissions'])
            )->count ?? 0);
        }

        return [
            'key' => $key,
            'role_name' => $roleName,
            'role_id' => $roleId,
            'installed' => $roleId !== null && $installedPermissions === $permissionCount,
            'permission_count' => $permissionCount,
            'installed_permissions' => $installedPermissions,
        ];
    }

    private function ensurePermissions(): array
    {
        foreach (self::PERMISSIONS as $name => [$displayName, $description, $category]) {
            DB::insert(
                'INSERT IGNORE INTO permissions (name, display_name, description, category, tenant_id) VALUES (?, ?, ?, ?, NULL)',
                [$name, $displayName, $description, $category]
            );
        }

        $rows = DB::select(
            'SELECT id, name FROM permissions WHERE name IN (' . $this->placeholders(count(self::PERMISSIONS)) . ')',
            array_keys(self::PERMISSIONS)
        );

        $permissionIds = [];
        foreach ($rows as $row) {
            $permissionIds[(string) $row->name] = (int) $row->id;
        }

        return $permissionIds;
    }

    private function ensureRole(int $tenantId, string $key, array $preset): int
    {
        $roleName = $this->roleName($tenantId, $key);
        DB::insert(
            'INSERT IGNORE INTO roles (name, display_name, description, level, is_system, tenant_id) VALUES (?, ?, ?, ?, 0, ?)',
            [$roleName, $preset['display_name'], $preset['description'], $preset['level'], $tenantId]
        );
        DB::update(
            'UPDATE roles SET display_name = ?, description = ?, level = ?, tenant_id = ? WHERE name = ?',
            [$preset['display_name'], $preset['description'], $preset['level'], $tenantId, $roleName]
        );

        return (int) DB::selectOne('SELECT id FROM roles WHERE name = ? LIMIT 1', [$roleName])->id;
    }

    private function roleName(int $tenantId, string $key): string
    {
        return 'kiss_' . $key . '_t' . $tenantId;
    }

    private function placeholders(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }
}
