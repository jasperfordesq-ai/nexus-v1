<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantHierarchyService — Laravel DI wrapper for legacy \Nexus\Services\TenantHierarchyService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantHierarchyService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TenantHierarchyService::createTenant().
     */
    public function createTenant(array $data, int $parentId): array
    {
        return \Nexus\Services\TenantHierarchyService::createTenant($data, $parentId);
    }

    /**
     * Delegates to legacy TenantHierarchyService::updateTenant().
     */
    public function updateTenant(int $tenantId, array $data): array
    {
        return \Nexus\Services\TenantHierarchyService::updateTenant($tenantId, $data);
    }

    /**
     * Delegates to legacy TenantHierarchyService::deleteTenant().
     */
    public function deleteTenant(int $tenantId, bool $hardDelete = false): array
    {
        return \Nexus\Services\TenantHierarchyService::deleteTenant($tenantId, $hardDelete);
    }

    /**
     * Delegates to legacy TenantHierarchyService::moveTenant().
     */
    public function moveTenant(int $tenantId, int $newParentId): array
    {
        return \Nexus\Services\TenantHierarchyService::moveTenant($tenantId, $newParentId);
    }

    /**
     * Delegates to legacy TenantHierarchyService::toggleSubtenantCapability().
     */
    public function toggleSubtenantCapability(int $tenantId, bool $enable): array
    {
        return \Nexus\Services\TenantHierarchyService::toggleSubtenantCapability($tenantId, $enable);
    }

    /**
     * Delegates to legacy TenantHierarchyService::assignTenantSuperAdmin().
     */
    public function assignTenantSuperAdmin(int $userId, int $tenantId): array
    {
        return \Nexus\Services\TenantHierarchyService::assignTenantSuperAdmin($userId, $tenantId);
    }

    /**
     * Delegates to legacy TenantHierarchyService::revokeTenantSuperAdmin().
     */
    public function revokeTenantSuperAdmin(int $userId): array
    {
        return \Nexus\Services\TenantHierarchyService::revokeTenantSuperAdmin($userId);
    }
}
