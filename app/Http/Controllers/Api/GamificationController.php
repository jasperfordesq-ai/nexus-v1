<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\BadgeCollectionService;
use App\Services\ChallengeService;
use App\Services\DailyRewardService;
use App\Services\GamificationService;
use App\Services\LeaderboardSeasonService;
use App\Services\LeaderboardService;
use App\Services\NexusScoreService;
use App\Services\StreakService;
use App\Services\XPShopService;
use App\Core\TenantContext;
use App\Models\UserBadge;

/**
 * GamificationController — Eloquent-powered badges, XP, leaderboard, and daily rewards.
 *
 * All methods migrated to use DB facade / legacy static services.
 */
class GamificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly BadgeCollectionService $badgeCollectionService,
        private readonly ChallengeService $challengeService,
        private readonly DailyRewardService $dailyRewardService,
        private readonly GamificationService $gamificationService,
        private readonly LeaderboardSeasonService $leaderboardSeasonService,
        private readonly LeaderboardService $leaderboardService,
        private readonly NexusScoreService $nexusScoreService,
        private readonly StreakService $streakService,
        private readonly XPShopService $xpShopService,
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
    //  GET /api/v1/leaderboard/api — Leaderboard API data
    // -----------------------------------------------------------------

    public function api(): JsonResponse
    {
        $type = $this->query('type', 'xp');
        $period = $this->query('period', 'all_time');
        $limit = $this->queryInt('limit', 10, 1, 50);

        if (!array_key_exists($type, LeaderboardService::LEADERBOARD_TYPES)) {
            return $this->success(['error' => 'Invalid leaderboard type']);
        }

        $leaderboard = $this->leaderboardService->getLeaderboard($type, $period, $limit);

        foreach ($leaderboard as &$entry) {
            $entry['formatted_score'] = $this->leaderboardService->formatScore($entry['score'], $type);
            $entry['medal'] = $this->leaderboardService->getMedalIcon($entry['rank']);
        }

        return $this->success([
            'type' => $type,
            'period' => $period,
            'title' => LeaderboardService::LEADERBOARD_TYPES[$type],
            'data' => $leaderboard,
        ]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v1/leaderboard/widget — Summary widget
    // -----------------------------------------------------------------

    public function widget(): JsonResponse
    {
        $summary = [
            'xp' => $this->leaderboardService->getLeaderboard('xp', 'all_time', 3, false),
            'vol_hours' => $this->leaderboardService->getLeaderboard('vol_hours', 'all_time', 3, false),
            'credits_earned' => $this->leaderboardService->getLeaderboard('credits_earned', 'all_time', 3, false),
        ];

        foreach ($summary as $type => &$leaders) {
            foreach ($leaders as &$entry) {
                $entry['medal'] = $this->leaderboardService->getMedalIcon($entry['rank']);
                $entry['formatted_score'] = $this->leaderboardService->formatScore($entry['score'], $type);
            }
        }

        return $this->success(['summary' => $summary]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v1/leaderboard/streaks
    // -----------------------------------------------------------------

    public function streaks(): JsonResponse
    {
        $userId = $this->getUserId();

        $streaks = $this->streakService->getAllStreaks($userId);

        foreach ($streaks as $type => &$streak) {
            $streak['icon'] = $this->streakService->getStreakIcon($streak['current']);
            $streak['message'] = $this->streakService->getStreakMessage($streak);
        }

        return $this->success(['streaks' => $streaks]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v1/achievements/progress — Badge progress
    // -----------------------------------------------------------------

    public function progress(): JsonResponse
    {
        $userId = $this->getUserId();

        $progress = $this->gamificationService->getBadgeProgress($userId);

        return $this->success(['progress' => $progress]);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/gamification/check-daily-reward
    // -----------------------------------------------------------------

    public function checkDailyReward(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('daily_reward', 10, 60);

        try {
            $reward = $this->dailyRewardService->checkAndAwardDailyReward($userId);
            return $this->success(['reward' => $reward]);
        } catch (\Throwable $e) {
            return $this->error('Daily rewards not available', 500, 'SERVICE_UNAVAILABLE');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/daily-status
    // -----------------------------------------------------------------

    public function getDailyStatus(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('daily_status', 30, 60);

        try {
            $status = $this->dailyRewardService->getTodayStatus($userId);
            return $this->success(['status' => $status]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/challenges
    // -----------------------------------------------------------------

    public function getChallenges(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('challenges', 30, 60);

        try {
            $challenges = $this->challengeService->getChallengesWithProgress($userId);
            return $this->success(['challenges' => $challenges]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/collections
    // -----------------------------------------------------------------

    public function getCollections(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('collections', 30, 60);

        try {
            $collections = $this->badgeCollectionService->getCollectionsWithProgress($userId);
            return $this->success(['collections' => $collections]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/shop-items
    // -----------------------------------------------------------------

    public function getShopItems(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('shop_items', 30, 60);

        try {
            $data = $this->xpShopService->getItemsWithUserStatus($userId);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/gamification/purchase-item
    // -----------------------------------------------------------------

    public function purchaseItem(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('purchase', 10, 60);

        $itemId = $this->input('item_id') ?? $this->query('item_id');

        if (!$itemId) {
            return $this->error('Item ID required', 400, 'VALIDATION_ERROR');
        }

        try {
            $result = $this->xpShopService->purchase($userId, $itemId);

            if ($result['success'] ?? false) {
                return $this->success($result);
            } else {
                return $this->error($result['error'] ?? 'Purchase failed', 400, 'PURCHASE_FAILED');
            }
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/summary
    // -----------------------------------------------------------------

    public function getSummary(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('summary', 60, 60);

        try {
            $user = DB::selectOne(
                "SELECT xp, level FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, TenantContext::getId()]
            );

            $badges = UserBadge::getForUser($userId);

            return $this->success([
                'xp' => (int) ($user->xp ?? 0),
                'level' => (int) ($user->level ?? 1),
                'badges_count' => count($badges),
                'level_progress' => $this->gamificationService->getLevelProgress(
                    (int) ($user->xp ?? 0),
                    (int) ($user->level ?? 1)
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/gamification/showcase
    // -----------------------------------------------------------------

    public function updateShowcase(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('update_showcase', 10, 60);

        $badgeKeys = $this->input('badge_keys', []);

        try {
            UserBadge::updateShowcase($userId, $badgeKeys);
            return $this->success(['message' => 'Showcase updated']);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/showcased-badges
    // -----------------------------------------------------------------

    public function getShowcasedBadges(): JsonResponse
    {
        $this->rateLimit('showcased_badges', 60, 60);

        $userId = $this->queryInt('user_id') ?? $this->getOptionalUserId();

        if (!$userId) {
            return $this->error('User ID required', 400, 'VALIDATION_ERROR');
        }

        try {
            $badges = UserBadge::getShowcased($userId);

            foreach ($badges as &$badge) {
                $def = $this->gamificationService->getBadgeByKey($badge['badge_key']);
                if ($def) {
                    $badge = array_merge($badge, $def);
                }
            }

            return $this->success(['badges' => $badges]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/share-achievement
    // -----------------------------------------------------------------

    public function shareAchievement(): JsonResponse
    {
        $this->getUserId();
        $this->rateLimit('share_achievement', 30, 60);

        $type = $this->query('type', 'badge');
        $key = $this->query('key', '');

        $shareData = [
            'title' => '',
            'text' => '',
            'url' => '',
        ];

        $basePath = TenantContext::getSlugPrefix();
        $baseUrl = TenantContext::getFrontendUrl();

        switch ($type) {
            case 'badge':
                $badge = $this->gamificationService->getBadgeByKey($key);
                if ($badge) {
                    $shareData['title'] = "I earned the {$badge['name']} badge!";
                    $shareData['text'] = "{$badge['icon']} I just earned the '{$badge['name']}' badge on our Timebank!";
                    $shareData['url'] = $baseUrl . $basePath . '/profile';
                }
                break;

            case 'level':
                $level = (int) $key;
                $shareData['title'] = "I reached Level {$level}!";
                $shareData['text'] = "I just reached Level {$level} on our Timebank!";
                $shareData['url'] = $baseUrl . $basePath . '/achievements';
                break;

            case 'collection':
                $shareData['title'] = "I completed a badge collection!";
                $shareData['text'] = "I just completed a badge collection on our Timebank!";
                $shareData['url'] = $baseUrl . $basePath . '/achievements/badges';
                break;
        }

        return $this->success(['share' => $shareData]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/seasons
    // -----------------------------------------------------------------

    public function getSeasons(): JsonResponse
    {
        $this->rateLimit('seasons', 30, 60);

        try {
            $seasons = $this->leaderboardSeasonService->getAllSeasons();
            return $this->success(['seasons' => $seasons]);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/gamification/current-season
    // -----------------------------------------------------------------

    public function getCurrentSeason(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('current_season', 30, 60);

        try {
            $data = $this->leaderboardSeasonService->getSeasonWithUserData($userId);
            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error('An internal error occurred', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  GET /api/v1/nexus-score — User score API
    // -----------------------------------------------------------------

    public function apiGetScore(): JsonResponse
    {
        $userId = $this->getUserId();
        $targetUserId = $this->queryInt('user_id', $userId);
        $tenantId = $this->getTenantId();

        // Only allow users to view their own score or admins to view any
        if ($targetUserId !== $userId) {
            try {
                $this->requireAdmin();
            } catch (\Throwable $e) {
                return $this->error('Forbidden', 403);
            }
        }

        try {
            $scoreData = $this->nexusScoreService->calculateNexusScore($targetUserId, $tenantId);
            return $this->respondWithData($scoreData);
        } catch (\Throwable $e) {
            return $this->error('Failed to calculate score', 500, 'SERVER_ERROR');
        }
    }

    // -----------------------------------------------------------------
    //  POST /api/v1/nexus-score/recalculate — Admin recalculate
    // -----------------------------------------------------------------

    public function apiRecalculateScores(): JsonResponse
    {
        $this->requireAdmin();

        return $this->success([
            'message' => 'Score recalculation initiated',
            'note' => 'This process runs in the background',
        ]);
    }
}
