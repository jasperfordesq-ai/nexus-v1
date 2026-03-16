<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * AchievementUnlockablesService — Laravel DI wrapper for legacy \Nexus\Services\AchievementUnlockablesService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AchievementUnlockablesService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AchievementUnlockablesService::getAllUnlockables().
     */
    public function getAllUnlockables(): array
    {
        return \Nexus\Services\AchievementUnlockablesService::getAllUnlockables();
    }

    /**
     * Delegates to legacy AchievementUnlockablesService::getUserUnlockables().
     */
    public function getUserUnlockables(int $userId): array
    {
        return \Nexus\Services\AchievementUnlockablesService::getUserUnlockables($userId);
    }

    /**
     * Delegates to legacy AchievementUnlockablesService::getUserActiveUnlockables().
     */
    public function getUserActiveUnlockables(int $userId): array
    {
        return \Nexus\Services\AchievementUnlockablesService::getUserActiveUnlockables($userId);
    }

    /**
     * Delegates to legacy AchievementUnlockablesService::setActiveUnlockable().
     */
    public function setActiveUnlockable(int $userId, string $type, string $key): bool
    {
        return \Nexus\Services\AchievementUnlockablesService::setActiveUnlockable($userId, $type, $key);
    }

    /**
     * Delegates to legacy AchievementUnlockablesService::removeActiveUnlockable().
     */
    public function removeActiveUnlockable(int $userId, string $type): bool
    {
        return \Nexus\Services\AchievementUnlockablesService::removeActiveUnlockable($userId, $type);
    }
}
