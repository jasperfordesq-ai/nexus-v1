<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Enterprise;

/**
 * PermissionService — Thin delegate forwarding to \App\Services\Enterprise\PermissionService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Enterprise\PermissionService
 */
class PermissionService
{

    public function can(int $userId, string $permission, $resource = null, bool $logCheck = true): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->can($userId, $permission, $resource, $logCheck);
    }

    public function canAll(int $userId, array $permissions): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->canAll($userId, $permissions);
    }

    public function canAny(int $userId, array $permissions): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->canAny($userId, $permissions);
    }

    public function getUserPermissions(int $userId): array
    {
        return (new \App\Services\Enterprise\PermissionService())->getUserPermissions($userId);
    }

    public function getUserRoles(int $userId): array
    {
        return (new \App\Services\Enterprise\PermissionService())->getUserRoles($userId);
    }

    public function assignRole(int $userId, int $roleId, int $assignedBy, ?string $expiresAt = null): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->assignRole($userId, $roleId, $assignedBy, $expiresAt);
    }

    public function revokeRole(int $userId, int $roleId, int $revokedBy): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->revokeRole($userId, $roleId, $revokedBy);
    }

    public function grantPermission(int $userId, int $permissionId, int $grantedBy, ?string $reason = null, ?string $expiresAt = null): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->grantPermission($userId, $permissionId, $grantedBy, $reason, $expiresAt);
    }

    public function revokePermission(int $userId, int $permissionId, int $revokedBy, ?string $reason = null): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->revokePermission($userId, $permissionId, $revokedBy, $reason);
    }

    public function clearUserPermissionCache(int $userId): void
    {
        (new \App\Services\Enterprise\PermissionService())->clearUserPermissionCache($userId);
    }

    public function getPermissionByName(string $name): ?array
    {
        return (new \App\Services\Enterprise\PermissionService())->getPermissionByName($name);
    }

    public function getAllPermissions(): array
    {
        return (new \App\Services\Enterprise\PermissionService())->getAllPermissions();
    }

    public function createRole(string $name, string $displayName, string $description, int $level = 0, bool $isSystem = false): ?int
    {
        return (new \App\Services\Enterprise\PermissionService())->createRole($name, $displayName, $description, $level, $isSystem);
    }

    public function attachPermissionsToRole(int $roleId, array $permissionIds, int $grantedBy): bool
    {
        return (new \App\Services\Enterprise\PermissionService())->attachPermissionsToRole($roleId, $permissionIds, $grantedBy);
    }

    public function getAllRoles(): array
    {
        return (new \App\Services\Enterprise\PermissionService())->getAllRoles();
    }

    public function disableAudit(): void
    {
        (new \App\Services\Enterprise\PermissionService())->disableAudit();
    }

    public function enableAudit(): void
    {
        (new \App\Services\Enterprise\PermissionService())->enableAudit();
    }
}
