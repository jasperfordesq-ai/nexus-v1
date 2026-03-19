<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SocialGamificationService — Laravel DI wrapper for legacy \Nexus\Services\SocialGamificationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SocialGamificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SocialGamificationService::getFriendComparison().
     */
    public function getFriendComparison(int $userId, int $friendId): array
    {
        return \Nexus\Services\SocialGamificationService::getFriendComparison($userId, $friendId);
    }

    /**
     * Delegates to legacy SocialGamificationService::getFriendsLeaderboard().
     */
    public function getFriendsLeaderboard(int $userId, int $limit = 10): array
    {
        return \Nexus\Services\SocialGamificationService::getFriendsLeaderboard($userId, $limit);
    }

    /**
     * Delegates to legacy SocialGamificationService::createFriendChallenge().
     */
    public function createFriendChallenge(int $challengerId, int $challengedId, array $challengeData): ?int
    {
        return \Nexus\Services\SocialGamificationService::createFriendChallenge($challengerId, $challengedId, $challengeData);
    }

    /**
     * Delegates to legacy SocialGamificationService::acceptChallenge().
     */
    public function acceptChallenge(int $challengeId, int $userId): bool
    {
        return \Nexus\Services\SocialGamificationService::acceptChallenge($challengeId, $userId);
    }

    /**
     * Delegates to legacy SocialGamificationService::declineChallenge().
     */
    public function declineChallenge(int $challengeId, int $userId): bool
    {
        return \Nexus\Services\SocialGamificationService::declineChallenge($challengeId, $userId);
    }
}
