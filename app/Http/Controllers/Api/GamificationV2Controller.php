<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * GamificationV2Controller -- Gamification v2: profile, badges, leaderboard, challenges, shop, seasons.
 *
 * Delegates to legacy controller during migration.
 */
class GamificationV2Controller extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    /** GET /api/v2/gamification/profile */
    public function profile(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'profile');
    }

    /** GET /api/v2/gamification/badges */
    public function badges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'badges');
    }

    /** GET /api/v2/gamification/badges/{key} */
    public function showBadge(string $key): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'showBadge', [$key]);
    }

    /** GET /api/v2/gamification/leaderboard */
    public function leaderboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'leaderboard');
    }

    /** GET /api/v2/gamification/challenges */
    public function challenges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'challenges');
    }

    /** POST /api/v2/gamification/challenges/{id}/claim */
    public function claimChallenge(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'claimChallenge', [$id]);
    }

    /** GET /api/v2/gamification/collections */
    public function collections(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'collections');
    }

    /** GET /api/v2/gamification/daily-reward */
    public function dailyRewardStatus(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'dailyRewardStatus');
    }

    /** POST /api/v2/gamification/daily-reward */
    public function claimDailyReward(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'claimDailyReward');
    }

    /** GET /api/v2/gamification/shop */
    public function shop(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'shop');
    }

    /** POST /api/v2/gamification/shop/purchase */
    public function purchase(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'purchase');
    }

    /** PUT /api/v2/gamification/showcase */
    public function updateShowcase(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'updateShowcase');
    }

    /** GET /api/v2/gamification/seasons */
    public function seasons(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'seasons');
    }

    /** GET /api/v2/gamification/seasons/current */
    public function currentSeason(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'currentSeason');
    }

    /** GET /api/v2/gamification/nexus-score */
    public function nexusScore(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GamificationV2ApiController::class, 'nexusScore');
    }
}
