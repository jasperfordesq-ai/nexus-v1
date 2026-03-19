<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * DailyRewardService � Laravel DI wrapper for legacy \Nexus\Services\DailyRewardService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class DailyRewardService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy DailyRewardService::claim().
     */
    public function claim(int $tenantId, int $userId): ?array
    {
        return \Nexus\Services\DailyRewardService::claim($tenantId, $userId);
    }

    /**
     * Delegates to legacy DailyRewardService::canClaim().
     */
    public function canClaim(int $tenantId, int $userId): bool
    {
        return \Nexus\Services\DailyRewardService::canClaim($tenantId, $userId);
    }

    /**
     * Delegates to legacy DailyRewardService::getStreak().
     */
    public function getStreak(int $tenantId, int $userId): int
    {
        return \Nexus\Services\DailyRewardService::getStreak($tenantId, $userId);
    }

    /**
     * Delegates to legacy DailyRewardService::getRewardConfig().
     */
    public function getRewardConfig(int $tenantId): array
    {
        return \Nexus\Services\DailyRewardService::getRewardConfig($tenantId);
    }

    /**
     * Delegates to legacy DailyRewardService::checkAndAwardDailyReward().
     *
     * Returns reward data if awarded, null if already claimed today.
     */
    public function checkAndAwardDailyReward(int $userId): ?array
    {
        return \Nexus\Services\DailyRewardService::checkAndAwardDailyReward($userId);
    }

    /**
     * Delegates to legacy DailyRewardService::getTodayStatus().
     *
     * Returns today's reward status for the given user.
     */
    public function getTodayStatus(int $userId): array
    {
        return \Nexus\Services\DailyRewardService::getTodayStatus($userId);
    }

    /**
     * Delegates to legacy DailyRewardService::getHistory().
     */
    public function getHistory(int $userId, int $limit = 30): array
    {
        return \Nexus\Services\DailyRewardService::getHistory($userId, $limit);
    }

    /**
     * Delegates to legacy DailyRewardService::getTotalEarned().
     */
    public function getTotalEarned(int $userId): int
    {
        return \Nexus\Services\DailyRewardService::getTotalEarned($userId);
    }
}
