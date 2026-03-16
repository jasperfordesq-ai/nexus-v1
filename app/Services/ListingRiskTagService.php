<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingRiskTagService — Laravel DI wrapper for legacy \Nexus\Services\ListingRiskTagService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingRiskTagService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingRiskTagService::tagListing().
     */
    public function tagListing(int $listingId, array $data, int $brokerId): ?int
    {
        return \Nexus\Services\ListingRiskTagService::tagListing($listingId, $data, $brokerId);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getTagForListing().
     */
    public function getTagForListing(int $listingId): ?array
    {
        return \Nexus\Services\ListingRiskTagService::getTagForListing($listingId);
    }

    /**
     * Delegates to legacy ListingRiskTagService::removeTag().
     */
    public function removeTag(int $listingId, ?int $removedBy = null): bool
    {
        return \Nexus\Services\ListingRiskTagService::removeTag($listingId, $removedBy);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getTaggedListings().
     */
    public function getTaggedListings(?string $riskLevel = null, int $page = 1, int $perPage = 20): array
    {
        return \Nexus\Services\ListingRiskTagService::getTaggedListings($riskLevel, $page, $perPage);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getHighRiskListings().
     */
    public function getHighRiskListings(): array
    {
        return \Nexus\Services\ListingRiskTagService::getHighRiskListings();
    }
}
