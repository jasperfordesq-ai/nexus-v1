<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupAchievementService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupAchievementService::getGroupAchievements().
     */
    public static function getGroupAchievements($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAchievementService::calculateProgress().
     */
    public static function calculateProgress($groupId, $targetType, $targetValue)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAchievementService::getEarnedAchievements().
     */
    public static function getEarnedAchievements($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAchievementService::checkAndAwardAchievements().
     */
    public static function checkAndAwardAchievements($groupId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAchievementService::awardAchievement().
     */
    public static function awardAchievement($groupId, $achievementKey, $achievement)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
