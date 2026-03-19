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
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingRiskTagService::tagListing().
     */
    public function tagListing(int $listingId, array $data, int $brokerId): ?int
    {
        if (!class_exists('\Nexus\Services\ListingRiskTagService')) { return null; }
        return \Nexus\Services\ListingRiskTagService::tagListing($listingId, $data, $brokerId);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getTagForListing().
     */
    public function getTagForListing(int $listingId): ?array
    {
        if (!class_exists('\Nexus\Services\ListingRiskTagService')) { return null; }
        return \Nexus\Services\ListingRiskTagService::getTagForListing($listingId);
    }

    /**
     * Delegates to legacy ListingRiskTagService::removeTag().
     */
    public function removeTag(int $listingId, ?int $removedBy = null): bool
    {
        if (!class_exists('\Nexus\Services\ListingRiskTagService')) { return false; }
        return \Nexus\Services\ListingRiskTagService::removeTag($listingId, $removedBy);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getTaggedListings().
     */
    public function getTaggedListings(?string $riskLevel = null, int $page = 1, int $perPage = 20): array
    {
        if (!class_exists('\Nexus\Services\ListingRiskTagService')) { return []; }
        return \Nexus\Services\ListingRiskTagService::getTaggedListings($riskLevel, $page, $perPage);
    }

    /**
     * Delegates to legacy ListingRiskTagService::getHighRiskListings().
     */
    public function getHighRiskListings(): array
    {
        if (!class_exists('\Nexus\Services\ListingRiskTagService')) { return []; }
        return \Nexus\Services\ListingRiskTagService::getHighRiskListings();
    }
}
