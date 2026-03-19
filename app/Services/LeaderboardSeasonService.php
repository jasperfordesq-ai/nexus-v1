<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * LeaderboardSeasonService � Laravel DI wrapper for legacy \Nexus\Services\LeaderboardSeasonService.
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
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return null; }
        return \Nexus\Services\LeaderboardSeasonService::getCurrentSeason($tenantId);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getSeasonLeaderboard().
     */
    public function getSeasonLeaderboard(int $tenantId, int $seasonId, int $limit = 20): array
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return []; }
        return \Nexus\Services\LeaderboardSeasonService::getSeasonLeaderboard($tenantId, $seasonId, $limit);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::endSeason().
     */
    public function endSeason(int $tenantId, int $seasonId): bool
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return false; }
        return \Nexus\Services\LeaderboardSeasonService::endSeason($tenantId, $seasonId);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getAllSeasons().
     *
     * Returns all seasons for the current tenant, ordered by start_date DESC.
     */
    public function getAllSeasons(int $limit = 12): array
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return []; }
        return \Nexus\Services\LeaderboardSeasonService::getAllSeasons($limit);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getSeasonWithUserData().
     *
     * Returns the current season with user-specific rank, leaderboard, and rewards.
     */
    public function getSeasonWithUserData(int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return null; }
        return \Nexus\Services\LeaderboardSeasonService::getSeasonWithUserData($userId);
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getOrCreateCurrentSeason().
     */
    public function getOrCreateCurrentSeason(): ?array
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return null; }
        return \Nexus\Services\LeaderboardSeasonService::getOrCreateCurrentSeason();
    }

    /**
     * Delegates to legacy LeaderboardSeasonService::getUserSeasonRank().
     */
    public function getUserSeasonRank(int $userId, ?int $seasonId = null): ?array
    {
        if (!class_exists('\Nexus\Services\LeaderboardSeasonService')) { return null; }
        return \Nexus\Services\LeaderboardSeasonService::getUserSeasonRank($userId, $seasonId);
    }
}
