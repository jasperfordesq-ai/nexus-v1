<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingFeaturedService � Laravel DI wrapper for legacy \Nexus\Services\ListingFeaturedService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingFeaturedService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingFeaturedService::feature().
     */
    public function feature(int $tenantId, int $listingId, ?string $until = null): bool
    {
        return \Nexus\Services\ListingFeaturedService::feature($tenantId, $listingId, $until);
    }

    /**
     * Delegates to legacy ListingFeaturedService::unfeature().
     */
    public function unfeature(int $tenantId, int $listingId): bool
    {
        return \Nexus\Services\ListingFeaturedService::unfeature($tenantId, $listingId);
    }

    /**
     * Delegates to legacy ListingFeaturedService::getFeatured().
     */
    public function getFeatured(int $tenantId, int $limit = 5): array
    {
        return \Nexus\Services\ListingFeaturedService::getFeatured($tenantId, $limit);
    }

    /**
     * Delegates to legacy ListingFeaturedService::featureListing().
     */
    public function featureListing(int $listingId, ?int $days = null): array
    {
        return \Nexus\Services\ListingFeaturedService::featureListing($listingId, $days);
    }

    /**
     * Delegates to legacy ListingFeaturedService::unfeatureListing().
     */
    public function unfeatureListing(int $listingId): array
    {
        return \Nexus\Services\ListingFeaturedService::unfeatureListing($listingId);
    }
}
