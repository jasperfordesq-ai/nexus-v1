<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * LeaderboardSeasonService — Laravel DI wrapper for legacy \Nexus\Services\LeaderboardSeasonService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class LeaderboardSeasonService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getCurrentSeason().
     */
    public function getCurrentSeason(int $tenantId): ?array
    {
        return \Nexus\Services\LeaderboardSeasonService::getCurrentSeason($tenantId);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getSeasonLeaderboard().
     */
    public function getSeasonLeaderboard(int $tenantId, int $seasonId, int $limit = 20): array
    {
        return \Nexus\Services\LeaderboardSeasonService::getSeasonLeaderboard($tenantId, $seasonId, $limit);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::endSeason().
     */
    public function endSeason(int $tenantId, int $seasonId): bool
    {
        return \Nexus\Services\LeaderboardSeasonService::endSeason($tenantId, $seasonId);
    }
}
