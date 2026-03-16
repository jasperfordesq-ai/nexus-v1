<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationDirectoryService — Laravel DI wrapper for legacy \Nexus\Services\FederationDirectoryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationDirectoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationDirectoryService::getDiscoverableTimebanks().
     */
    public function getDiscoverableTimebanks(int $currentTenantId, array $filters = []): array
    {
        return \Nexus\Services\FederationDirectoryService::getDiscoverableTimebanks($currentTenantId, $filters);
    }

    /**
     * Delegates to legacy FederationDirectoryService::getAvailableRegions().
     */
    public function getAvailableRegions(): array
    {
        return \Nexus\Services\FederationDirectoryService::getAvailableRegions();
    }

    /**
     * Delegates to legacy FederationDirectoryService::getAvailableCategories().
     */
    public function getAvailableCategories(): array
    {
        return \Nexus\Services\FederationDirectoryService::getAvailableCategories();
    }

    /**
     * Delegates to legacy FederationDirectoryService::getTimebankProfile().
     */
    public function getTimebankProfile(int $tenantId): ?array
    {
        return \Nexus\Services\FederationDirectoryService::getTimebankProfile($tenantId);
    }

    /**
     * Delegates to legacy FederationDirectoryService::updateDirectoryProfile().
     */
    public function updateDirectoryProfile(int $tenantId, array $data): bool
    {
        return \Nexus\Services\FederationDirectoryService::updateDirectoryProfile($tenantId, $data);
    }
}
