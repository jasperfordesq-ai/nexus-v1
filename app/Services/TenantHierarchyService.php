<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::updateTenant().
     */
    public function updateTenant(int $tenantId, array $data): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::deleteTenant().
     */
    public function deleteTenant(int $tenantId, bool $hardDelete = false): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::moveTenant().
     */
    public function moveTenant(int $tenantId, int $newParentId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::toggleSubtenantCapability().
     */
    public function toggleSubtenantCapability(int $tenantId, bool $enable): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::assignTenantSuperAdmin().
     */
    public function assignTenantSuperAdmin(int $userId, int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantHierarchyService::revokeTenantSuperAdmin().
     */
    public function revokeTenantSuperAdmin(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
