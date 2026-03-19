<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SmartGroupRankingService — Laravel DI wrapper for legacy \Nexus\Services\SmartGroupRankingService.
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
    public function updateFeaturedLocalHubs($tenantId = null, $limit = 6)
    {
        return \Nexus\Services\SmartGroupRankingService::updateFeaturedLocalHubs($tenantId, $limit);
    }

    /**
     * Delegates to legacy SmartGroupRankingService::updateFeaturedCommunityGroups().
     */
    public function updateFeaturedCommunityGroups($tenantId = null, $limit = 6)
    {
        return \Nexus\Services\SmartGroupRankingService::updateFeaturedCommunityGroups($tenantId, $limit);
    }

    /**
     * Delegates to legacy SmartGroupRankingService::updateAllFeaturedGroups().
     */
    public function updateAllFeaturedGroups($tenantId = null)
    {
        return \Nexus\Services\SmartGroupRankingService::updateAllFeaturedGroups($tenantId);
    }

    /**
     * Delegates to legacy SmartGroupRankingService::getFeaturedGroupsWithScores().
     */
    public function getFeaturedGroupsWithScores($type = 'local_hubs', $tenantId = null)
    {
        return \Nexus\Services\SmartGroupRankingService::getFeaturedGroupsWithScores($type, $tenantId);
    }

    /**
     * Delegates to legacy SmartGroupRankingService::getLastUpdateTime().
     */
    public function getLastUpdateTime($tenantId = null)
    {
        return \Nexus\Services\SmartGroupRankingService::getLastUpdateTime($tenantId);
    }
}
