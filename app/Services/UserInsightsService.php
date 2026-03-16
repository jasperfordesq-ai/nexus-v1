<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * UserInsightsService — Laravel DI wrapper for legacy \Nexus\Services\UserInsightsService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class UserInsightsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy UserInsightsService::getInsights().
     */
    public function getInsights($userId, $months = null)
    {
        return \Nexus\Services\UserInsightsService::getInsights($userId, $months);
    }

    /**
     * Delegates to legacy UserInsightsService::getSummary().
     */
    public function getSummary($userId)
    {
        return \Nexus\Services\UserInsightsService::getSummary($userId);
    }

    /**
     * Delegates to legacy UserInsightsService::getTotalSpent().
     */
    public function getTotalSpent($userId)
    {
        return \Nexus\Services\UserInsightsService::getTotalSpent($userId);
    }

    /**
     * Delegates to legacy UserInsightsService::getMonthlyTrends().
     */
    public function getMonthlyTrends($userId, $months = 12)
    {
        return \Nexus\Services\UserInsightsService::getMonthlyTrends($userId, $months);
    }

    /**
     * Delegates to legacy UserInsightsService::getWeeklyTrends().
     */
    public function getWeeklyTrends($userId, $weeks = 12)
    {
        return \Nexus\Services\UserInsightsService::getWeeklyTrends($userId, $weeks);
    }
}
