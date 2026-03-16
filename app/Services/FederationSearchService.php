<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationSearchService — Laravel DI wrapper for legacy \Nexus\Services\FederationSearchService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationSearchService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationSearchService::cachedSearchMembers().
     */
    public function cachedSearchMembers(array $partnerTenantIds, array $filters): array
    {
        return \Nexus\Services\FederationSearchService::cachedSearchMembers($partnerTenantIds, $filters);
    }

    /**
     * Delegates to legacy FederationSearchService::searchMembers().
     */
    public function searchMembers(array $partnerTenantIds, array $filters): array
    {
        return \Nexus\Services\FederationSearchService::searchMembers($partnerTenantIds, $filters);
    }

    /**
     * Delegates to legacy FederationSearchService::getAvailableSkills().
     */
    public function getAvailableSkills(array $partnerTenantIds, string $query = '', int $limit = 20): array
    {
        return \Nexus\Services\FederationSearchService::getAvailableSkills($partnerTenantIds, $query, $limit);
    }

    /**
     * Delegates to legacy FederationSearchService::getAvailableLocations().
     */
    public function getAvailableLocations(array $partnerTenantIds, string $query = '', int $limit = 20): array
    {
        return \Nexus\Services\FederationSearchService::getAvailableLocations($partnerTenantIds, $query, $limit);
    }

    /**
     * Delegates to legacy FederationSearchService::getSearchStats().
     */
    public function getSearchStats(array $partnerTenantIds): array
    {
        return \Nexus\Services\FederationSearchService::getSearchStats($partnerTenantIds);
    }
}
