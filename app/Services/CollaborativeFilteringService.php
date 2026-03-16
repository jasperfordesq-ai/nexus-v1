<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CollaborativeFilteringService — Laravel DI wrapper for legacy \Nexus\Services\CollaborativeFilteringService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CollaborativeFilteringService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CollaborativeFilteringService::getSimilarListings().
     */
    public function getSimilarListings(int $listingId, int $tenantId, int $limit = 5): array
    {
        return \Nexus\Services\CollaborativeFilteringService::getSimilarListings($listingId, $tenantId, $limit);
    }

    /**
     * Delegates to legacy CollaborativeFilteringService::getSuggestedMembers().
     */
    public function getSuggestedMembers(int $userId, int $tenantId, int $limit = 5): array
    {
        return \Nexus\Services\CollaborativeFilteringService::getSuggestedMembers($userId, $tenantId, $limit);
    }

    /**
     * Delegates to legacy CollaborativeFilteringService::getSuggestedListingsForUser().
     */
    public function getSuggestedListingsForUser(int $userId, int $tenantId, int $limit = 10): array
    {
        return \Nexus\Services\CollaborativeFilteringService::getSuggestedListingsForUser($userId, $tenantId, $limit);
    }
}
