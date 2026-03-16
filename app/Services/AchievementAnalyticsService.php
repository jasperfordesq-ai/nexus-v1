<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * AchievementAnalyticsService — Laravel DI wrapper for legacy \Nexus\Services\AchievementAnalyticsService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AchievementAnalyticsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AchievementAnalyticsService::getOverallStats().
     */
    public function getOverallStats()
    {
        return \Nexus\Services\AchievementAnalyticsService::getOverallStats();
    }

    /**
     * Delegates to legacy AchievementAnalyticsService::getBadgeTrends().
     */
    public function getBadgeTrends($days = 30)
    {
        return \Nexus\Services\AchievementAnalyticsService::getBadgeTrends($days);
    }

    /**
     * Delegates to legacy AchievementAnalyticsService::getPopularBadges().
     */
    public function getPopularBadges($limit = 10)
    {
        return \Nexus\Services\AchievementAnalyticsService::getPopularBadges($limit);
    }

    /**
     * Delegates to legacy AchievementAnalyticsService::getRarestBadges().
     */
    public function getRarestBadges($limit = 10)
    {
        return \Nexus\Services\AchievementAnalyticsService::getRarestBadges($limit);
    }

    /**
     * Delegates to legacy AchievementAnalyticsService::getTopEarners().
     */
    public function getTopEarners($limit = 10)
    {
        return \Nexus\Services\AchievementAnalyticsService::getTopEarners($limit);
    }
}
