<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * LeaderboardService � Laravel DI wrapper for legacy \Nexus\Services\LeaderboardService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class LeaderboardService
{
    /**
     * Mirror of legacy LeaderboardService::LEADERBOARD_TYPES constant.
     */
    public const LEADERBOARD_TYPES = [
        'credits_earned' => 'Time Credits Earned',
        'credits_spent'  => 'Time Credits Spent',
        'vol_hours'      => 'Volunteer Hours',
        'badges'         => 'Badges Earned',
        'xp'             => 'Experience Points',
        'connections'    => 'Connections Made',
        'reviews'        => 'Reviews Given',
        'posts'          => 'Posts Created',
        'streak'         => 'Login Streak',
    ];

    public function __construct()
    {
    }

    /**
     * Delegates to legacy LeaderboardService::getLeaderboard().
     */
    public function getLeaderboard(int $tenantId, string $period = 'monthly', int $limit = 20): array
    {
        if (!class_exists('\Nexus\Services\LeaderboardService')) { return []; }
        return \Nexus\Services\LeaderboardService::getLeaderboard($tenantId, $period, $limit);
    }

    /**
     * Delegates to legacy LeaderboardService::getUserRank().
     */
    public function getUserRank(int $tenantId, int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\LeaderboardService')) { return null; }
        return \Nexus\Services\LeaderboardService::getUserRank($tenantId, $userId);
    }

    /**
     * Delegates to legacy LeaderboardService::getTopMembers().
     */
    public function getTopMembers(int $tenantId, int $limit = 10): array
    {
        if (!class_exists('\Nexus\Services\LeaderboardService')) { return []; }
        return \Nexus\Services\LeaderboardService::getTopMembers($tenantId, $limit);
    }

    /**
     * Delegates to legacy LeaderboardService::formatScore().
     *
     * Formats a numeric score for display based on leaderboard type.
     */
    public function formatScore($score, string $type): string
    {
        if (!class_exists('\Nexus\Services\LeaderboardService')) { return ''; }
        return \Nexus\Services\LeaderboardService::formatScore($score, $type);
    }

    /**
     * Delegates to legacy LeaderboardService::getMedalIcon().
     *
     * Returns a medal emoji icon for the given rank position.
     */
    public function getMedalIcon(int $rank): string
    {
        if (!class_exists('\Nexus\Services\LeaderboardService')) { return ''; }
        return \Nexus\Services\LeaderboardService::getMedalIcon($rank);
    }
}
