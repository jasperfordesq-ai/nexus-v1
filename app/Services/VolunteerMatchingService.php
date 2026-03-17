<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerMatchingService � Laravel DI wrapper for legacy \Nexus\Services\VolunteerMatchingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerMatchingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerMatchingService::findMatches().
     */
    public function findMatches(int $tenantId, int $opportunityId, int $limit = 10): array
    {
        return \Nexus\Services\VolunteerMatchingService::findMatches($tenantId, $opportunityId, $limit);
    }

    /**
     * Delegates to legacy VolunteerMatchingService::suggestOpportunities().
     */
    public function suggestOpportunities(int $tenantId, int $userId, int $limit = 10): array
    {
        return \Nexus\Services\VolunteerMatchingService::suggestOpportunities($tenantId, $userId, $limit);
    }

    /**
     * Delegates to legacy VolunteerMatchingService::getMatchScore().
     */
    public function getMatchScore(int $tenantId, int $opportunityId, int $userId): float
    {
        return \Nexus\Services\VolunteerMatchingService::getMatchScore($tenantId, $opportunityId, $userId);
    }

    /**
     * Delegates to legacy VolunteerMatchingService::getRecommendedShifts().
     */
    public function getRecommendedShifts(int $userId, array $options = []): array
    {
        return \Nexus\Services\VolunteerMatchingService::getRecommendedShifts($userId, $options);
    }
}
