<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupRecommendationEngine — Laravel DI wrapper for legacy \Nexus\Services\GroupRecommendationEngine.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupRecommendationEngine
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupRecommendationEngine::getRecommendations().
     */
    public function getRecommendations($userId, $limit = 10, $options = [])
    {
        return \Nexus\Services\GroupRecommendationEngine::getRecommendations($userId, $limit, $options);
    }

    /**
     * Delegates to legacy GroupRecommendationEngine::trackInteraction().
     */
    public function trackInteraction($userId, $groupId, $action)
    {
        return \Nexus\Services\GroupRecommendationEngine::trackInteraction($userId, $groupId, $action);
    }

    /**
     * Delegates to legacy GroupRecommendationEngine::getPerformanceMetrics().
     */
    public function getPerformanceMetrics($days = 30)
    {
        return \Nexus\Services\GroupRecommendationEngine::getPerformanceMetrics($days);
    }
}
