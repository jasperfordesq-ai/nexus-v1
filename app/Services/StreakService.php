<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * StreakService � Laravel DI wrapper for legacy \Nexus\Services\StreakService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class StreakService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy StreakService::getCurrentStreak().
     */
    public function getCurrentStreak(int $tenantId, int $userId): int
    {
        return \Nexus\Services\StreakService::getCurrentStreak($tenantId, $userId);
    }

    /**
     * Delegates to legacy StreakService::recordActivity().
     */
    public function recordActivity(int $tenantId, int $userId): bool
    {
        return \Nexus\Services\StreakService::recordActivity($tenantId, $userId);
    }

    /**
     * Delegates to legacy StreakService::getLongestStreak().
     */
    public function getLongestStreak(int $tenantId, int $userId): int
    {
        return \Nexus\Services\StreakService::getLongestStreak($tenantId, $userId);
    }

    /**
     * Delegates to legacy StreakService::getStreakLeaderboard().
     */
    public function getStreakLeaderboard(int $tenantId, int $limit = 10): array
    {
        return \Nexus\Services\StreakService::getStreakLeaderboard($tenantId, $limit);
    }

    /**
     * Delegates to legacy StreakService::getAllStreaks().
     *
     * Returns all streak types (login, activity, giving, volunteer) with current data.
     */
    public function getAllStreaks(int $userId): array
    {
        return \Nexus\Services\StreakService::getAllStreaks($userId);
    }

    /**
     * Delegates to legacy StreakService::getStreakIcon().
     *
     * Returns an emoji icon based on streak length.
     */
    public function getStreakIcon(int $streakLength): string
    {
        return \Nexus\Services\StreakService::getStreakIcon($streakLength);
    }

    /**
     * Delegates to legacy StreakService::getStreakMessage().
     *
     * Returns a motivational message based on streak data.
     */
    public function getStreakMessage(?array $streak): string
    {
        return \Nexus\Services\StreakService::getStreakMessage($streak);
    }

    /**
     * Delegates to legacy StreakService::getStreak().
     */
    public function getStreak(int $userId, string $streakType = 'activity'): ?array
    {
        return \Nexus\Services\StreakService::getStreak($userId, $streakType);
    }

    /**
     * Delegates to legacy StreakService::recordLogin().
     */
    public function recordLogin(int $userId)
    {
        return \Nexus\Services\StreakService::recordLogin($userId);
    }
}
