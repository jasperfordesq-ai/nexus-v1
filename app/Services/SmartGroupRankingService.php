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
class SmartGroupRankingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SmartGroupRankingService::updateFeaturedLocalHubs().
     */
    public static function updateFeaturedLocalHubs($tenantId = null, $limit = 6)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy SmartGroupRankingService::updateFeaturedCommunityGroups().
     */
    public static function updateFeaturedCommunityGroups($tenantId = null, $limit = 6)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy SmartGroupRankingService::updateAllFeaturedGroups().
     */
    public static function updateAllFeaturedGroups($tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy SmartGroupRankingService::getFeaturedGroupsWithScores().
     */
    public static function getFeaturedGroupsWithScores($type = 'local_hubs', $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy SmartGroupRankingService::getLastUpdateTime().
     */
    public static function getLastUpdateTime($tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
