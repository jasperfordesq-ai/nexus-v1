<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupAchievementService — Laravel DI wrapper for legacy \Nexus\Services\GroupAchievementService.
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
    public function getGroupAchievements($groupId)
    {
        return \Nexus\Services\GroupAchievementService::getGroupAchievements($groupId);
    }

    /**
     * Delegates to legacy GroupAchievementService::calculateProgress().
     */
    public function calculateProgress($groupId, $targetType, $targetValue)
    {
        return \Nexus\Services\GroupAchievementService::calculateProgress($groupId, $targetType, $targetValue);
    }

    /**
     * Delegates to legacy GroupAchievementService::getEarnedAchievements().
     */
    public function getEarnedAchievements($groupId)
    {
        return \Nexus\Services\GroupAchievementService::getEarnedAchievements($groupId);
    }

    /**
     * Delegates to legacy GroupAchievementService::checkAndAwardAchievements().
     */
    public function checkAndAwardAchievements($groupId)
    {
        return \Nexus\Services\GroupAchievementService::checkAndAwardAchievements($groupId);
    }

    /**
     * Delegates to legacy GroupAchievementService::awardAchievement().
     */
    public function awardAchievement($groupId, $achievementKey, $achievement)
    {
        return \Nexus\Services\GroupAchievementService::awardAchievement($groupId, $achievementKey, $achievement);
    }
}
