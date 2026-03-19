<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingRankingService — Laravel DI wrapper for legacy \Nexus\Services\ListingRankingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingRankingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingRankingService::getConfig().
     */
    public function getConfig(): array
    {
        if (!class_exists('\Nexus\Services\ListingRankingService')) { return []; }
        return \Nexus\Services\ListingRankingService::getConfig();
    }

    /**
     * Delegates to legacy ListingRankingService::isEnabled().
     */
    public function isEnabled(): bool
    {
        if (!class_exists('\Nexus\Services\ListingRankingService')) { return false; }
        return \Nexus\Services\ListingRankingService::isEnabled();
    }

    /**
     * Delegates to legacy ListingRankingService::clearCache().
     */
    public function clearCache(): void
    {
        if (!class_exists('\Nexus\Services\ListingRankingService')) { return; }
        \Nexus\Services\ListingRankingService::clearCache();
    }

    /**
     * Delegates to legacy ListingRankingService::rankListings().
     */
    public function rankListings(array $listings, ?int $viewerId = null, array $options = []): array
    {
        if (!class_exists('\Nexus\Services\ListingRankingService')) { return []; }
        return \Nexus\Services\ListingRankingService::rankListings($listings, $viewerId, $options);
    }

    /**
     * Delegates to legacy ListingRankingService::buildRankedQuery().
     */
    public function buildRankedQuery(?int $viewerId = null, array $filters = []): array
    {
        if (!class_exists('\Nexus\Services\ListingRankingService')) { return []; }
        return \Nexus\Services\ListingRankingService::buildRankedQuery($viewerId, $filters);
    }
}
