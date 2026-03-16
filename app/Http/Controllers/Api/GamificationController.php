<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GamificationService;

/**
 * GamificationController -- Badges, XP, leaderboard, and daily rewards.
 */
class GamificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /** GET /api/v2/gamification/profile */
    public function profile(): JsonResponse
    {
        $userId = $this->requireAuth();
        $profile = $this->gamificationService->getProfile($userId, $this->getTenantId());
        
        return $this->respondWithData($profile);
    }

    /** GET /api/v2/gamification/badges */
    public function badges(): JsonResponse
    {
        $userId = $this->requireAuth();
        $badges = $this->gamificationService->getBadges($userId, $this->getTenantId());
        
        return $this->respondWithData($badges);
    }

    /** GET /api/v2/gamification/leaderboard */
    public function leaderboard(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $period = $this->query('period', 'all_time');
        $limit = $this->queryInt('limit', 20, 1, 100);
        
        $leaderboard = $this->gamificationService->getLeaderboard($tenantId, $period, $limit);
        
        return $this->respondWithData($leaderboard);
    }

    /** POST /api/v2/gamification/claim-daily-reward */
    public function claimDailyReward(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('daily_reward', 3, 3600);
        
        $result = $this->gamificationService->claimDailyReward($userId, $this->getTenantId());
        
        if ($result === null) {
            return $this->respondWithError('ALREADY_CLAIMED', 'Daily reward already claimed today');
        }
        
        return $this->respondWithData($result);
    }

}
