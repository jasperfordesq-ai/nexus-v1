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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SocialGamificationService::getFriendsLeaderboard().
     */
    public function getFriendsLeaderboard(int $userId, int $limit = 10): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SocialGamificationService::createFriendChallenge().
     */
    public function createFriendChallenge(int $challengerId, int $challengedId, array $challengeData): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy SocialGamificationService::acceptChallenge().
     */
    public function acceptChallenge(int $challengeId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy SocialGamificationService::declineChallenge().
     */
    public function declineChallenge(int $challengeId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
