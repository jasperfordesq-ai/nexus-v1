<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingAnalyticsService � Laravel DI wrapper for legacy \Nexus\Services\ListingAnalyticsService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingAnalyticsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingAnalyticsService::getStats().
     */
    public function getStats(int $tenantId, int $listingId): array
    {
        return \Nexus\Services\ListingAnalyticsService::getStats($tenantId, $listingId);
    }

    /**
     * Delegates to legacy ListingAnalyticsService::recordView().
     */
    public function recordView(int $tenantId, int $listingId, ?int $userId = null): void
    {
        \Nexus\Services\ListingAnalyticsService::recordView($tenantId, $listingId, $userId);
    }

    /**
     * Delegates to legacy ListingAnalyticsService::getPopular().
     */
    public function getPopular(int $tenantId, int $limit = 10): array
    {
        return \Nexus\Services\ListingAnalyticsService::getPopular($tenantId, $limit);
    }

    /**
     * Get analytics data for a listing over a given number of days.
     *
     * Delegates to legacy ListingAnalyticsService::getAnalytics().
     */
    public function getAnalytics(int $listingId, int $days = 30): array
    {
        return \Nexus\Services\ListingAnalyticsService::getAnalytics($listingId, $days);
    }

    /**
     * Update the save/favourite count for a listing.
     *
     * Delegates to legacy ListingAnalyticsService::updateSaveCount().
     */
    public function updateSaveCount(int $listingId, bool $increment): void
    {
        \Nexus\Services\ListingAnalyticsService::updateSaveCount($listingId, $increment);
    }
}
