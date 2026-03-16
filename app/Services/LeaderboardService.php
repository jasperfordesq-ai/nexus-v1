<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * LeaderboardService — Laravel DI wrapper for legacy \Nexus\Services\LeaderboardService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class LeaderboardService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy LeaderboardService::getLeaderboard().
     */
    public function getLeaderboard(int $tenantId, string $period = 'monthly', int $limit = 20): array
    {
        return \Nexus\Services\LeaderboardService::getLeaderboard($tenantId, $period, $limit);
    }

    /**
     * Delegates to legacy LeaderboardService::getUserRank().
     */
    public function getUserRank(int $tenantId, int $userId): ?array
    {
        return \Nexus\Services\LeaderboardService::getUserRank($tenantId, $userId);
    }

    /**
     * Delegates to legacy LeaderboardService::getTopMembers().
     */
    public function getTopMembers(int $tenantId, int $limit = 10): array
    {
        return \Nexus\Services\LeaderboardService::getTopMembers($tenantId, $limit);
    }
}
