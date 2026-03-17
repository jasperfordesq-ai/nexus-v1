<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantVisibilityService — Laravel DI wrapper for legacy \Nexus\Services\TenantVisibilityService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantVisibilityService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TenantVisibilityService::getVisibleTenantIds().
     */
    public function getVisibleTenantIds(): array
    {
        return \Nexus\Services\TenantVisibilityService::getVisibleTenantIds();
    }

    /**
     * Delegates to legacy TenantVisibilityService::getTenantList().
     */
    public function getTenantList(array $filters = []): array
    {
        return \Nexus\Services\TenantVisibilityService::getTenantList($filters);
    }

    /**
     * Delegates to legacy TenantVisibilityService::getTenant().
     */
    public function getTenant(int $tenantId): ?array
    {
        return \Nexus\Services\TenantVisibilityService::getTenant($tenantId);
    }

    /**
     * Delegates to legacy TenantVisibilityService::getUserList().
     */
    public function getUserList(array $filters = []): array
    {
        return \Nexus\Services\TenantVisibilityService::getUserList($filters);
    }

    /**
     * Delegates to legacy TenantVisibilityService::getTenantAdmins().
     */
    public function getTenantAdmins(int $tenantId): array
    {
        return \Nexus\Services\TenantVisibilityService::getTenantAdmins($tenantId);
    }

    /**
     * Delegates to legacy TenantVisibilityService::getHierarchyTree().
     */
    public function getHierarchyTree(): array
    {
        return \Nexus\Services\TenantVisibilityService::getHierarchyTree();
    }

    /**
     * Delegates to legacy TenantVisibilityService::getDashboardStats().
     */
    public function getDashboardStats(): array
    {
        return \Nexus\Services\TenantVisibilityService::getDashboardStats();
    }

    /**
     * Delegates to legacy TenantVisibilityService::getAvailableParents().
     */
    public function getAvailableParents(): array
    {
        return \Nexus\Services\TenantVisibilityService::getAvailableParents();
    }
}
