<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GamificationService;

/**
 * GamificationController — Eloquent-powered badges, XP, leaderboard, and daily rewards.
 *
 * Core methods (profile, badges, leaderboard, claimDailyReward) are fully
 * migrated to Eloquent. Remaining endpoints delegate to legacy controllers
 * that have complex dependencies not yet ported.
 */
class GamificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/profile
    // -----------------------------------------------------------------

    public function profile(): JsonResponse
    {
        $userId = $this->requireAuth();
        $targetUserId = $this->queryInt('user_id', $userId);

        $this->rateLimit('gamification_profile', 60, 60);

        $profile = $this->gamificationService->getProfile($targetUserId, $this->getTenantId());

        if (empty($profile)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'User not found', null, 404);
        }

        $profile['is_own_profile'] = ($targetUserId === $userId);

        return $this->respondWithData($profile);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/badges
    // -----------------------------------------------------------------

    public function badges(): JsonResponse
    {
        $userId = $this->requireAuth();
        $targetUserId = $this->queryInt('user_id', $userId);

        $this->rateLimit('gamification_badges', 60, 60);

        $badges = $this->gamificationService->getBadges($targetUserId, $this->getTenantId());

        return $this->respondWithData($badges, ['total' => count($badges)]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/leaderboard
    // -----------------------------------------------------------------

    public function leaderboard(): JsonResponse
    {
        $userId = $this->getUserId();
        $period = $this->query('period', 'all');
        $limit = $this->queryInt('limit', 20, 1, 100);
        $type = $this->query('type', 'xp');

        $this->rateLimit('gamification_leaderboard', 30, 60);

        $leaderboard = $this->gamificationService->getLeaderboard($this->getTenantId(), $period, $limit);

        // Mark current user
        foreach ($leaderboard as &$entry) {
            $entry['is_current_user'] = ((int) ($entry['user']['id'] ?? 0) === $userId);
        }

        $currentUserPosition = null;
        foreach ($leaderboard as $entry) {
            if ($entry['is_current_user']) {
                $currentUserPosition = $entry['position'];
                break;
            }
        }

        return $this->respondWithData($leaderboard, [
            'period'        => $period,
            'type'          => $type,
            'your_position' => $currentUserPosition,
            'total_entries' => count($leaderboard),
        ]);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/gamification/daily-reward
    // -----------------------------------------------------------------

    public function claimDailyReward(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('daily_reward', 3, 3600);

        $result = $this->gamificationService->claimDailyReward($userId, $this->getTenantId());

        if ($result === null) {
            return $this->respondWithError('ALREADY_CLAIMED', 'Daily reward already claimed today', null, 409);
        }

        return $this->respondWithData($result);
    }

    // -----------------------------------------------------------------
    //  Delegated endpoints (complex legacy services not yet ported)
    // -----------------------------------------------------------------

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function api(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LeaderboardController::class, 'api');
    }

    public function widget(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LeaderboardController::class, 'widget');
    }

    public function streaks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LeaderboardController::class, 'streaks');
    }

    public function progress(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\AchievementsController::class, 'progress');
    }

    public function checkDailyReward(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'checkDailyReward');
    }

    public function getDailyStatus(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getDailyStatus');
    }

    public function getChallenges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getChallenges');
    }

    public function getCollections(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getCollections');
    }

    public function getShopItems(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getShopItems');
    }

    public function purchaseItem(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'purchaseItem');
    }

    public function getSummary(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getSummary');
    }

    public function updateShowcase(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'updateShowcase');
    }

    public function getShowcasedBadges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getShowcasedBadges');
    }

    public function shareAchievement(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'shareAchievement');
    }

    public function getSeasons(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getSeasons');
    }

    public function getCurrentSeason(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationApiController::class, 'getCurrentSeason');
    }

    public function apiGetScore(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\NexusScoreController::class, 'apiGetScore');
    }

    public function apiRecalculateScores(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\NexusScoreController::class, 'apiRecalculateScores');
    }
}
