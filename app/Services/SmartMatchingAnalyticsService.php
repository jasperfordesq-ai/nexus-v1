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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getOverallStats().
     */
    public function getOverallStats(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getScoreDistribution().
     */
    public function getScoreDistribution(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getDistanceDistribution().
     */
    public function getDistanceDistribution(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SmartMatchingAnalyticsService::getConversionFunnel().
     */
    public function getConversionFunnel(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
