<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SmartMatchingAnalyticsService — Laravel DI wrapper for legacy \Nexus\Services\SmartMatchingAnalyticsService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SmartMatchingAnalyticsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getDashboardSummary().
     */
    public function getDashboardSummary(): array
    {
        return \Nexus\Services\SmartMatchingAnalyticsService::getDashboardSummary();
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getOverallStats().
     */
    public function getOverallStats(): array
    {
        return \Nexus\Services\SmartMatchingAnalyticsService::getOverallStats();
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getScoreDistribution().
     */
    public function getScoreDistribution(): array
    {
        return \Nexus\Services\SmartMatchingAnalyticsService::getScoreDistribution();
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getDistanceDistribution().
     */
    public function getDistanceDistribution(): array
    {
        return \Nexus\Services\SmartMatchingAnalyticsService::getDistanceDistribution();
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getConversionFunnel().
     */
    public function getConversionFunnel(): array
    {
        return \Nexus\Services\SmartMatchingAnalyticsService::getConversionFunnel();
    }
}
