<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\DailyRewardService;
use Nexus\Services\ChallengeService;
use Nexus\Services\BadgeCollectionService;
use Nexus\Services\XPShopService;
use Nexus\Services\GamificationService;
use Nexus\Services\LeaderboardSeasonService;

/**
 * GamificationApiController - Gamification API endpoints
 *
 * Handles daily rewards, challenges, badges, XP shop, and leaderboards.
 */
class GamificationApiController extends BaseApiController
{
    /**
     * Check and award daily reward
     */
    public function checkDailyReward()
    {
        $userId = $this->getUserId();
        $this->rateLimit('daily_reward', 10, 60);

        try {
            $reward = DailyRewardService::checkAndAwardDailyReward($userId);
            $this->success(['reward' => $reward]); // null if already claimed
        } catch (\Throwable $e) {
            $this->error('Daily rewards not available', 500, 'SERVICE_UNAVAILABLE');
        }
    }

    /**
     * Get daily reward status
     */
    public function getDailyStatus()
    {
        $userId = $this->getUserId();
        $this->rateLimit('daily_status', 30, 60);

        try {
            $status = DailyRewardService::getTodayStatus($userId);
            $this->success(['status' => $status]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get active challenges with progress
     */
    public function getChallenges()
    {
        $userId = $this->getUserId();
        $this->rateLimit('challenges', 30, 60);

        try {
            $challenges = ChallengeService::getChallengesWithProgress($userId);
            $this->success(['challenges' => $challenges]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get badge collections with progress
     */
    public function getCollections()
    {
        $userId = $this->getUserId();
        $this->rateLimit('collections', 30, 60);

        try {
            $collections = BadgeCollectionService::getCollectionsWithProgress($userId);
            $this->success(['collections' => $collections]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get XP shop items
     */
    public function getShopItems()
    {
        $userId = $this->getUserId();
        $this->rateLimit('shop_items', 30, 60);

        try {
            $data = XPShopService::getItemsWithUserStatus($userId);
            $this->success($data);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Purchase shop item
     */
    public function purchaseItem()
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('purchase', 10, 60);

        $itemId = $this->input('item_id') ?? $this->query('item_id');

        if (!$itemId) {
            $this->error('Item ID required', 400, 'VALIDATION_ERROR');
        }

        try {
            $result = XPShopService::purchase($userId, $itemId);

            if ($result['success'] ?? false) {
                $this->success($result);
            } else {
                $this->error($result['error'] ?? 'Purchase failed', 400, 'PURCHASE_FAILED');
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get user's gamification summary (for widgets)
     */
    public function getSummary()
    {
        $userId = $this->getUserId();
        $this->rateLimit('summary', 60, 60);

        try {
            // Get basic stats
            $user = \Nexus\Core\Database::query(
                "SELECT xp, level FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            $badges = \Nexus\Models\UserBadge::getForUser($userId);

            $this->success([
                'xp' => (int)($user['xp'] ?? 0),
                'level' => (int)($user['level'] ?? 1),
                'badges_count' => count($badges),
                'level_progress' => GamificationService::getLevelProgress(
                    (int)($user['xp'] ?? 0),
                    (int)($user['level'] ?? 1)
                )
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Update badge showcase
     */
    public function updateShowcase()
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('update_showcase', 10, 60);

        $badgeKeys = $this->input('badge_keys', []);

        try {
            \Nexus\Models\UserBadge::updateShowcase($userId, $badgeKeys);
            $this->success(['message' => 'Showcase updated']);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get showcased badges for a user
     */
    public function getShowcasedBadges()
    {
        $this->rateLimit('showcased_badges', 60, 60);

        $userId = $this->queryInt('user_id') ?? $this->getOptionalUserId();

        if (!$userId) {
            $this->error('User ID required', 400, 'VALIDATION_ERROR');
        }

        try {
            $badges = \Nexus\Models\UserBadge::getShowcased($userId);

            // Enrich with badge definitions
            foreach ($badges as &$badge) {
                $def = GamificationService::getBadgeByKey($badge['badge_key']);
                if ($def) {
                    $badge = array_merge($badge, $def);
                }
            }

            $this->success(['badges' => $badges]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Share achievement (returns share data)
     */
    public function shareAchievement()
    {
        $this->getUserId();
        $this->rateLimit('share_achievement', 30, 60);

        $type = $this->query('type', 'badge');
        $key = $this->query('key', '');

        $shareData = [
            'title' => '',
            'text' => '',
            'url' => ''
        ];

        $basePath = TenantContext::getBasePath();
        $baseUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];

        switch ($type) {
            case 'badge':
                $badge = GamificationService::getBadgeByKey($key);
                if ($badge) {
                    $shareData['title'] = "I earned the {$badge['name']} badge!";
                    $shareData['text'] = "{$badge['icon']} I just earned the '{$badge['name']}' badge on our Timebank!";
                    $shareData['url'] = $baseUrl . $basePath . '/profile/me';
                }
                break;

            case 'level':
                $level = (int)$key;
                $shareData['title'] = "I reached Level {$level}!";
                $shareData['text'] = "🎊 I just reached Level {$level} on our Timebank!";
                $shareData['url'] = $baseUrl . $basePath . '/achievements';
                break;

            case 'collection':
                $shareData['title'] = "I completed a badge collection!";
                $shareData['text'] = "📚 I just completed a badge collection on our Timebank!";
                $shareData['url'] = $baseUrl . $basePath . '/achievements/badges';
                break;
        }

        $this->success(['share' => $shareData]);
    }

    /**
     * Get all seasons
     */
    public function getSeasons()
    {
        $this->rateLimit('seasons', 30, 60);

        try {
            $seasons = LeaderboardSeasonService::getAllSeasons();
            $this->success(['seasons' => $seasons]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }

    /**
     * Get current season with user data
     */
    public function getCurrentSeason()
    {
        $userId = $this->getUserId();
        $this->rateLimit('current_season', 30, 60);

        try {
            $data = LeaderboardSeasonService::getSeasonWithUserData($userId);
            $this->success($data);
        } catch (\Throwable $e) {
            $this->error($e->getMessage(), 500, 'SERVER_ERROR');
        }
    }
}
