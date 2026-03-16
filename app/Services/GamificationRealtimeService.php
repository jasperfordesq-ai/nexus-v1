<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GamificationRealtimeService — Laravel DI wrapper for legacy \Nexus\Services\GamificationRealtimeService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GamificationRealtimeService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GamificationRealtimeService::broadcastBadgeEarned().
     */
    public function broadcastBadgeEarned(int $userId, array $badge): bool
    {
        return \Nexus\Services\GamificationRealtimeService::broadcastBadgeEarned($userId, $badge);
    }

    /**
     * Delegates to legacy GamificationRealtimeService::broadcastXPGained().
     */
    public function broadcastXPGained(int $userId, int $amount, string $reason, array $levelInfo = []): bool
    {
        return \Nexus\Services\GamificationRealtimeService::broadcastXPGained($userId, $amount, $reason, $levelInfo);
    }

    /**
     * Delegates to legacy GamificationRealtimeService::broadcastLevelUp().
     */
    public function broadcastLevelUp(int $userId, int $newLevel, array $rewards = []): bool
    {
        return \Nexus\Services\GamificationRealtimeService::broadcastLevelUp($userId, $newLevel, $rewards);
    }

    /**
     * Delegates to legacy GamificationRealtimeService::broadcastChallengeCompleted().
     */
    public function broadcastChallengeCompleted(int $userId, array $challenge): bool
    {
        return \Nexus\Services\GamificationRealtimeService::broadcastChallengeCompleted($userId, $challenge);
    }

    /**
     * Delegates to legacy GamificationRealtimeService::broadcastCollectionCompleted().
     */
    public function broadcastCollectionCompleted(int $userId, array $collection): bool
    {
        return \Nexus\Services\GamificationRealtimeService::broadcastCollectionCompleted($userId, $collection);
    }
}
