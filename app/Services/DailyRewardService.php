<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
    public static function claim(int $tenantId, int $userId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DailyRewardService::canClaim().
     */
    public static function canClaim(int $tenantId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy DailyRewardService::getStreak().
     */
    public static function getStreak(int $tenantId, int $userId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy DailyRewardService::getRewardConfig().
     */
    public static function getRewardConfig(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy DailyRewardService::checkAndAwardDailyReward().
     *
     * Returns reward data if awarded, null if already claimed today.
     */
    public static function checkAndAwardDailyReward(int $userId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy DailyRewardService::getTodayStatus().
     *
     * Returns today's reward status for the given user.
     */
    public static function getTodayStatus(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy DailyRewardService::getHistory().
     */
    public static function getHistory(int $userId, int $limit = 30): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy DailyRewardService::getTotalEarned().
     */
    public static function getTotalEarned(int $userId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
