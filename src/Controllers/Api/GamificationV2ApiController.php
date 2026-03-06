<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\DailyRewardService;
use Nexus\Services\ChallengeService;
use Nexus\Services\BadgeCollectionService;
use Nexus\Services\XPShopService;
use Nexus\Services\GamificationService;
use Nexus\Services\LeaderboardSeasonService;
use Nexus\Services\LeaderboardService;
use Nexus\Services\NexusScoreCacheService;
use Nexus\Services\NexusScoreService;
use Nexus\Models\UserBadge;

/**
 * GamificationV2ApiController - RESTful API v2 for gamification features
 *
 * Provides gamification endpoints with standardized v2 response format.
 * Includes points, levels, badges, leaderboards, challenges, and XP shop.
 *
 * Endpoints:
 * - GET  /api/v2/gamification/profile      - Get user's gamification profile
 * - GET  /api/v2/gamification/badges       - Get user's badges
 * - GET  /api/v2/gamification/badges/{key} - Get specific badge details
 * - GET  /api/v2/gamification/leaderboard  - Get leaderboard
 * - GET  /api/v2/gamification/challenges   - Get active challenges
 * - GET  /api/v2/gamification/collections  - Get badge collections
 * - GET  /api/v2/gamification/daily-reward - Get daily reward status
 * - POST /api/v2/gamification/daily-reward - Claim daily reward
 * - GET  /api/v2/gamification/shop         - Get XP shop items
 * - POST /api/v2/gamification/shop/purchase - Purchase shop item
 * - PUT  /api/v2/gamification/showcase     - Update badge showcase
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 *
 * @package Nexus\Controllers\Api
 */
class GamificationV2ApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/gamification/profile
     *
     * Get the current user's gamification profile including XP, level,
     * progress to next level, and summary statistics.
     *
     * Query Parameters:
     * - user_id: int (optional, view another user's public profile)
     *
     * Response: 200 OK with profile data
     */
    public function profile(): void
    {
        $currentUserId = $this->getUserId();
        $targetUserId = $this->queryInt('user_id', $currentUserId);

        $this->rateLimit('gamification_profile', 60, 60);

        $user = Database::query(
            "SELECT id, first_name, last_name, avatar_url, xp, level FROM users WHERE id = ? AND tenant_id = ?",
            [$targetUserId, TenantContext::getId()]
        )->fetch();

        if (!$user) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'User not found',
                null,
                404
            );
        }

        $xp = (int)($user['xp'] ?? 0);
        $level = (int)($user['level'] ?? 1);

        // Calculate level progress
        $levelProgress = GamificationService::getLevelProgress($xp, $level);

        // Get badge count
        $badges = UserBadge::getForUser($targetUserId);
        $showcasedBadges = UserBadge::getShowcased($targetUserId);

        // Enrich showcased badges with definitions
        foreach ($showcasedBadges as &$badge) {
            $def = GamificationService::getBadgeByKey($badge['badge_key']);
            if ($def) {
                $badge = array_merge($badge, $def);
            }
        }

        $profile = [
            'user' => [
                'id' => $user['id'],
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'avatar_url' => $user['avatar_url'],
            ],
            'xp' => $xp,
            'level' => $level,
            'level_progress' => $levelProgress,
            'badges_count' => count($badges),
            'showcased_badges' => $showcasedBadges,
            'is_own_profile' => ($targetUserId === $currentUserId),
        ];

        // Add XP breakdown for own profile
        if ($targetUserId === $currentUserId) {
            $profile['xp_values'] = GamificationService::XP_VALUES;
            $profile['level_thresholds'] = GamificationService::LEVEL_THRESHOLDS;
        }

        $this->respondWithData($profile);
    }

    /**
     * GET /api/v2/gamification/badges
     *
     * Get user's earned badges with optional filtering.
     *
     * Query Parameters:
     * - user_id: int (optional, view another user's badges)
     * - type: string (optional, filter by badge type)
     * - showcased: bool (optional, only return showcased badges)
     *
     * Response: 200 OK with badges array
     */
    public function badges(): void
    {
        $currentUserId = $this->getUserId();
        $targetUserId = $this->queryInt('user_id', $currentUserId);
        $type = $this->query('type');
        $showcasedOnly = $this->inputBool('showcased', false);

        $this->rateLimit('gamification_badges', 60, 60);

        // Get all badges for user
        if ($showcasedOnly) {
            $badges = UserBadge::getShowcased($targetUserId);
        } else {
            $badges = UserBadge::getForUser($targetUserId);
        }

        // Enrich with badge definitions
        $enrichedBadges = [];
        foreach ($badges as $badge) {
            $def = GamificationService::getBadgeByKey($badge['badge_key']);
            if ($def) {
                $enriched = array_merge($badge, $def);
                $enriched['description'] = $enriched['msg'] ?? $enriched['description'] ?? null;

                // Filter by type if specified
                if ($type && ($enriched['type'] ?? '') !== $type) {
                    continue;
                }

                $enrichedBadges[] = $enriched;
            }
        }

        // Get available badge types for filtering
        $allDefinitions = GamificationService::getBadgeDefinitions();
        $availableTypes = array_unique(array_column($allDefinitions, 'type'));

        $this->respondWithData($enrichedBadges, [
            'total' => count($enrichedBadges),
            'available_types' => array_values($availableTypes),
        ]);
    }

    /**
     * GET /api/v2/gamification/badges/{key}
     *
     * Get details for a specific badge including whether the user has earned it.
     *
     * Response: 200 OK with badge details
     */
    public function showBadge(string $key): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_badge_detail', 60, 60);

        $definition = GamificationService::getBadgeByKey($key);

        if (!$definition) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Badge not found',
                null,
                404
            );
        }

        // Check if user has this badge
        $userBadge = Database::query(
            "SELECT * FROM user_badges WHERE user_id = ? AND badge_key = ?",
            [$userId, $key]
        )->fetch();

        $badge = array_merge($definition, [
            'earned' => !empty($userBadge),
            'earned_at' => $userBadge['awarded_at'] ?? null,
            'is_showcased' => !empty($userBadge['is_showcased'] ?? false),
        ]);
        $badge['description'] = $badge['msg'] ?? $badge['description'] ?? null;

        $this->respondWithData($badge);
    }

    /**
     * GET /api/v2/gamification/leaderboard
     *
     * Get the leaderboard filtered by type and period.
     *
     * Query Parameters:
     * - type:   string ('xp', 'volunteer_hours', 'credits_earned') - default 'xp'
     * - period: string ('all', 'season', 'month', 'week') - default 'all'
     * - limit:  int (default 20, max 100)
     *
     * Response: 200 OK with leaderboard entries.
     * Each entry includes a `score` field whose value corresponds to the
     * requested type (XP points, volunteer hours, or time credits earned).
     */
    public function leaderboard(): void
    {
        $userId = $this->getUserId();
        $period = $this->query('period', 'all');
        $limit  = $this->queryInt('limit', 20, 1, 100);
        $type   = $this->query('type', 'xp');

        $this->rateLimit('gamification_leaderboard', 30, 60);

        $tenantId = TenantContext::getId();

        // ── Map frontend type → LeaderboardService type ──────────────────────
        // Supported frontend values: 'xp', 'volunteer_hours', 'credits_earned', 'nexus_score'
        $typeMap = [
            'xp'             => 'xp',
            'volunteer_hours' => 'vol_hours',
            'credits_earned' => 'credits_earned',
            'nexus_score'    => 'nexus_score',
        ];
        $serviceType = $typeMap[$type] ?? 'xp';

        // ── Map frontend period → LeaderboardService period ──────────────────
        // 'season' is managed by a separate service; treat it the same as
        // all_time at the data layer (season boundaries are applied by the
        // seasons feature, not the base leaderboard query).
        $periodMap = [
            'all'    => 'all_time',
            'season' => 'all_time',
            'month'  => 'monthly',
            'week'   => 'weekly',
        ];
        $servicePeriod = $periodMap[$period] ?? 'all_time';

        // ── NexusScore leaderboard (reads from nexus_score_cache directly) ──────
        if ($serviceType === 'nexus_score') {
            $tableCheck = Database::query("SHOW TABLES LIKE 'nexus_score_cache'")->fetch();
            if (!$tableCheck) {
                $this->respondWithData([], ['period' => $period, 'type' => $type, 'your_position' => null, 'total_entries' => 0]);
                return;
            }

            $placeholders = implode(',', array_fill(0, $limit, '?'));
            $rows = Database::query(
                "SELECT n.user_id, n.total_score, n.percentile, u.name, u.avatar_url, u.xp, u.level,
                        @rownum := @rownum + 1 AS rank_pos
                 FROM nexus_score_cache n
                 JOIN users u ON u.id = n.user_id
                 JOIN (SELECT @rownum := 0) r
                 WHERE n.tenant_id = ? AND u.tenant_id = ? AND u.is_approved = 1
                 ORDER BY n.total_score DESC
                 LIMIT ?",
                [$tenantId, $tenantId, $limit]
            )->fetchAll();

            $leaderboard = [];
            foreach ($rows as $pos => $row) {
                $leaderboard[] = [
                    'position'        => $pos + 1,
                    'user'            => [
                        'id'         => (int)$row['user_id'],
                        'name'       => trim($row['name'] ?? ''),
                        'avatar_url' => $row['avatar_url'] ?? null,
                    ],
                    'xp'              => (int)($row['xp'] ?? 0),
                    'level'           => (int)($row['level'] ?? 1),
                    'score'           => (float)$row['total_score'],
                    'is_current_user' => ((int)$row['user_id'] === $userId),
                ];
            }

            $currentUserPosition = null;
            foreach ($leaderboard as $entry) {
                if ($entry['is_current_user']) {
                    $currentUserPosition = $entry['position'];
                    break;
                }
            }

            $this->respondWithData($leaderboard, [
                'period'        => $period,
                'type'          => $type,
                'your_position' => $currentUserPosition,
                'total_entries' => count($leaderboard),
            ]);
            return;
        }

        // ── Fetch via LeaderboardService ──────────────────────────────────────
        // LeaderboardService::getLeaderboard() already scopes by tenant_id,
        // applies the correct date filter, and returns a `score` field for
        // every type (including 'xp').
        $rawEntries = LeaderboardService::getLeaderboard(
            $serviceType,
            $servicePeriod,
            $limit,
            true // include current user's row even if outside top N
        );

        // ── Format to match the shape the React frontend expects ──────────────
        $leaderboard = [];
        foreach ($rawEntries as $entry) {
            $score = isset($entry['score']) ? (float)$entry['score'] : 0;
            $xp    = ($serviceType === 'xp')
                ? (int)$score
                : (int)($entry['xp'] ?? 0);

            $leaderboard[] = [
                'position'       => (int)($entry['rank'] ?? 0),
                'user'           => [
                    'id'         => (int)$entry['user_id'],
                    'name'       => trim(($entry['name'] ?? '')),
                    'avatar_url' => $entry['avatar_url'] ?? null,
                ],
                'xp'             => $xp,
                'level'          => (int)($entry['level'] ?? 1),
                'score'          => $score,
                'is_current_user' => (bool)($entry['is_current_user'] ?? false),
            ];
        }

        // ── Determine current user's position for the meta banner ─────────────
        $currentUserPosition = null;
        foreach ($leaderboard as $entry) {
            if ($entry['is_current_user']) {
                $currentUserPosition = $entry['position'];
                break;
            }
        }

        // If user is not in the list at all, fall back to a simple count query
        // (only reliable for xp; for other types LeaderboardService already
        // appends the user row via getUserRank()).
        if ($currentUserPosition === null && $serviceType === 'xp') {
            $positionResult = Database::query(
                "SELECT COUNT(*) + 1 AS position
                 FROM users
                 WHERE tenant_id = ? AND is_approved = 1 AND xp > (
                     SELECT COALESCE(xp, 0) FROM users WHERE id = ?
                 )",
                [$tenantId, $userId]
            )->fetch();
            $currentUserPosition = (int)($positionResult['position'] ?? 0) ?: null;
        }

        $this->respondWithData($leaderboard, [
            'period'        => $period,
            'type'          => $type,
            'your_position' => $currentUserPosition,
            'total_entries' => count($leaderboard),
        ]);
    }

    /**
     * GET /api/v2/gamification/challenges
     *
     * Get active challenges with user progress.
     *
     * Response: 200 OK with challenges array
     */
    public function challenges(): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_challenges', 30, 60);

        try {
            $challenges = ChallengeService::getChallengesWithProgress($userId);

            $this->respondWithData($challenges);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load challenges',
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/gamification/collections
     *
     * Get badge collections with user progress.
     *
     * Response: 200 OK with collections array
     */
    public function collections(): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_collections', 30, 60);

        try {
            $collections = BadgeCollectionService::getCollectionsWithProgress($userId);

            $this->respondWithData($collections);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load collections',
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/gamification/daily-reward
     *
     * Get daily reward status.
     *
     * Response: 200 OK with daily reward status
     */
    public function dailyRewardStatus(): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_daily_status', 30, 60);

        try {
            $status = DailyRewardService::getTodayStatus($userId);

            $this->respondWithData($status);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Daily rewards not available',
                null,
                500
            );
        }
    }

    /**
     * POST /api/v2/gamification/daily-reward
     *
     * Claim daily reward.
     *
     * Response: 200 OK with reward data, or error if already claimed
     */
    public function claimDailyReward(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $this->rateLimit('gamification_daily_claim', 10, 60);

        try {
            $reward = DailyRewardService::checkAndAwardDailyReward($userId);

            if ($reward === null) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_CONFLICT,
                    'Daily reward already claimed today',
                    null,
                    409
                );
            }

            $this->respondWithData([
                'claimed' => true,
                'reward' => $reward,
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to claim daily reward',
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/gamification/shop
     *
     * Get XP shop items available for purchase.
     *
     * Response: 200 OK with shop items
     */
    public function shop(): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_shop', 30, 60);

        try {
            $data = XPShopService::getItemsWithUserStatus($userId);

            $this->respondWithData($data['items'] ?? $data, ['user_xp' => $data['user_xp'] ?? null]);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load shop',
                null,
                500
            );
        }
    }

    /**
     * POST /api/v2/gamification/shop/purchase
     *
     * Purchase an item from the XP shop.
     *
     * Request Body (JSON):
     * {
     *   "item_id": int (required)
     * }
     *
     * Response: 200 OK with purchase result
     */
    public function purchase(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $this->rateLimit('gamification_purchase', 10, 60);

        $itemId = $this->input('item_id');

        if (empty($itemId)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Item ID is required',
                'item_id',
                400
            );
        }

        try {
            $result = XPShopService::purchase($userId, $itemId);

            if ($result['success'] ?? false) {
                $this->respondWithData($result);
            } else {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_CONFLICT,
                    $result['error'] ?? 'Purchase failed',
                    null,
                    400
                );
            }
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Purchase failed',
                null,
                500
            );
        }
    }

    /**
     * PUT /api/v2/gamification/showcase
     *
     * Update which badges are showcased on user's profile.
     *
     * Request Body (JSON):
     * {
     *   "badge_keys": ["badge_key_1", "badge_key_2", ...] (max 5)
     * }
     *
     * Response: 200 OK with updated showcase
     */
    public function updateShowcase(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $this->rateLimit('gamification_showcase', 10, 60);

        $badgeKeys = $this->input('badge_keys', []);

        if (!is_array($badgeKeys)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'badge_keys must be an array',
                'badge_keys',
                400
            );
        }

        if (count($badgeKeys) > 5) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Maximum 5 badges can be showcased',
                'badge_keys',
                400
            );
        }

        // Verify user owns these badges
        $userBadges = UserBadge::getForUser($userId);
        $userBadgeKeys = array_column($userBadges, 'badge_key');

        $invalidKeys = array_diff($badgeKeys, $userBadgeKeys);
        if (!empty($invalidKeys)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'You do not own some of the specified badges',
                'badge_keys',
                400
            );
        }

        try {
            UserBadge::updateShowcase($userId, $badgeKeys);

            // Return updated showcase
            $showcased = UserBadge::getShowcased($userId);
            foreach ($showcased as &$badge) {
                $def = GamificationService::getBadgeByKey($badge['badge_key']);
                if ($def) {
                    $badge = array_merge($badge, $def);
                }
            }

            $this->respondWithData([
                'message' => 'Showcase updated',
                'showcased_badges' => $showcased,
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to update showcase',
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/gamification/seasons
     *
     * Get all leaderboard seasons.
     *
     * Response: 200 OK with seasons array
     */
    public function seasons(): void
    {
        $this->rateLimit('gamification_seasons', 30, 60);

        try {
            $seasons = LeaderboardSeasonService::getAllSeasons();

            $this->respondWithData($seasons);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load seasons',
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/gamification/seasons/current
     *
     * Get current season with user's data.
     *
     * Response: 200 OK with current season data
     */
    public function currentSeason(): void
    {
        $userId = $this->getUserId();

        $this->rateLimit('gamification_current_season', 30, 60);

        try {
            $data = LeaderboardSeasonService::getSeasonWithUserData($userId);

            $this->respondWithData($data);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load current season',
                null,
                500
            );
        }
    }

    /**
     * POST /api/v2/gamification/challenges/{id}/claim
     *
     * Claim reward for a completed challenge.
     *
     * Response: 200 OK with reward data
     */
    /**
     * GET /api/v2/gamification/nexus-score
     * Returns the current user's full NexusScore breakdown (own score only).
     */
    public function nexusScore(): void
    {
        $userId   = $this->getUserId();
        $tenantId = TenantContext::getId();

        $this->rateLimit('nexus_score', 30, 60);

        try {
            $db           = Database::getInstance();
            $cacheService = new NexusScoreCacheService($db);
            $scoreData    = $cacheService->getScore($userId, $tenantId);

            // Tier may be returned as an array (name/color/icon) — normalise to flat fields
            $tier = $scoreData['tier'];
            if (is_array($tier)) {
                $tierName  = $tier['name']  ?? 'Novice';
                $tierIcon  = $tier['icon']  ?? '';
                $tierColor = $tier['color'] ?? '';
            } else {
                $tierName  = (string) $tier;
                $tierIcon  = '';
                $tierColor = '';
            }

            $breakdown = $scoreData['breakdown'] ?? [];
            $formatted = [];
            $categoryMeta = [
                'engagement' => ['label' => 'Community Engagement', 'max' => 250],
                'quality'    => ['label' => 'Contribution Quality',  'max' => 200],
                'volunteer'  => ['label' => 'Volunteer Hours',        'max' => 200],
                'activity'   => ['label' => 'Platform Activity',      'max' => 150],
                'badges'     => ['label' => 'Badges & Achievements',  'max' => 100],
                'impact'     => ['label' => 'Social Impact',          'max' => 100],
            ];
            foreach ($categoryMeta as $key => $meta) {
                $cat = $breakdown[$key] ?? [];
                $formatted[] = [
                    'key'        => $key,
                    'label'      => $meta['label'],
                    'score'      => (int) ($cat['score'] ?? 0),
                    'max'        => $meta['max'],
                    'percentage' => $meta['max'] > 0
                        ? round((($cat['score'] ?? 0) / $meta['max']) * 100, 1)
                        : 0,
                    'details'    => $cat['details'] ?? [],
                ];
            }

            $this->respondWithData([
                'total_score' => (int) ($scoreData['total_score'] ?? 0),
                'max_score'   => 1000,
                'percentage'  => (float) ($scoreData['percentage'] ?? 0),
                'percentile'  => (float) ($scoreData['percentile'] ?? 0),
                'tier'        => [
                    'name'  => $tierName,
                    'icon'  => $tierIcon,
                    'color' => $tierColor,
                ],
                'breakdown'   => $formatted,
                'insights'    => array_map(function ($i) {
                    return is_array($i) ? ($i['message'] ?? $i['title'] ?? '') : (string) $i;
                }, $scoreData['insights'] ?? []),
            ]);
        } catch (\Throwable $e) {
            error_log("NexusScore error for user {$userId}: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to load NexusScore',
                null,
                500
            );
        }
    }

    public function claimChallenge(int $id): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        $this->rateLimit('gamification_claim_challenge', 10, 60);

        try {
            // Get challenge details
            $challenge = ChallengeService::getById($id);

            if (!$challenge) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_NOT_FOUND,
                    'Challenge not found',
                    null,
                    404
                );
                return;
            }

            // Check if user has completed it
            $progress = Database::query(
                "SELECT * FROM challenge_progress WHERE challenge_id = ? AND user_id = ?",
                [$id, $userId]
            )->fetch();

            if (!$progress) {
                $this->respondWithError('CHALLENGE_NOT_STARTED', 'You have not started this challenge', null, 400);
                return;
            }

            if ($progress['status'] === 'claimed') {
                $this->respondWithError('CHALLENGE_ALREADY_CLAIMED', 'You have already claimed this reward', null, 400);
                return;
            }

            // Mark as claimed
            Database::query(
                "UPDATE challenge_progress SET status = 'claimed', claimed_at = NOW() WHERE challenge_id = ? AND user_id = ?",
                [$id, $userId]
            );

            // Award XP if configured
            $xpReward = (int) ($challenge['xp_reward'] ?? 0);
            if ($xpReward > 0) {
                GamificationService::awardXP($userId, $xpReward, 'challenge_complete', "Completed challenge: {$challenge['title']}");
            }

            $this->respondWithData([
                'claimed' => true,
                'challenge_id' => $id,
                'reward' => [
                    'xp' => $xpReward,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Failed to claim challenge reward',
                null,
                500
            );
        }
    }
}
