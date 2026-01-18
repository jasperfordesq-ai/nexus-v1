<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\DailyRewardService;
use Nexus\Services\ChallengeService;
use Nexus\Services\BadgeCollectionService;
use Nexus\Services\XPShopService;
use Nexus\Services\GamificationService;
use Nexus\Services\LeaderboardSeasonService;

class GamificationApiController
{
    /**
     * Check and award daily reward
     */
    public function checkDailyReward()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        try {
            $reward = DailyRewardService::checkAndAwardDailyReward($_SESSION['user_id']);

            echo json_encode([
                'success' => true,
                'reward' => $reward // null if already claimed
            ]);
        } catch (\Throwable $e) {
            // Table might not exist yet
            echo json_encode([
                'success' => false,
                'error' => 'Daily rewards not available'
            ]);
        }
    }

    /**
     * Get daily reward status
     */
    public function getDailyStatus()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $status = DailyRewardService::getTodayStatus($_SESSION['user_id']);
            echo json_encode(['success' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get active challenges with progress
     */
    public function getChallenges()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $challenges = ChallengeService::getChallengesWithProgress($_SESSION['user_id']);
            echo json_encode(['success' => true, 'challenges' => $challenges]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get badge collections with progress
     */
    public function getCollections()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $collections = BadgeCollectionService::getCollectionsWithProgress($_SESSION['user_id']);
            echo json_encode(['success' => true, 'collections' => $collections]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get XP shop items
     */
    public function getShopItems()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $data = XPShopService::getItemsWithUserStatus($_SESSION['user_id']);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Purchase shop item
     */
    public function purchaseItem()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        $itemId = $_POST['item_id'] ?? $_GET['item_id'] ?? null;

        if (!$itemId) {
            echo json_encode(['success' => false, 'error' => 'Item ID required']);
            return;
        }

        try {
            $result = XPShopService::purchase($_SESSION['user_id'], $itemId);
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get user's gamification summary (for widgets)
     */
    public function getSummary()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $userId = $_SESSION['user_id'];

            // Get basic stats
            $user = \Nexus\Core\Database::query(
                "SELECT xp, level FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            $badges = \Nexus\Models\UserBadge::getForUser($userId);

            echo json_encode([
                'success' => true,
                'summary' => [
                    'xp' => (int)($user['xp'] ?? 0),
                    'level' => (int)($user['level'] ?? 1),
                    'badges_count' => count($badges),
                    'level_progress' => GamificationService::getLevelProgress(
                        (int)($user['xp'] ?? 0),
                        (int)($user['level'] ?? 1)
                    )
                ]
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Update badge showcase
     */
    public function updateShowcase()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        $badgeKeys = $_POST['badge_keys'] ?? [];

        try {
            \Nexus\Models\UserBadge::updateShowcase($_SESSION['user_id'], $badgeKeys);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get showcased badges for a user
     */
    public function getShowcasedBadges()
    {
        header('Content-Type: application/json');

        $userId = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;

        if (!$userId) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            return;
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

            echo json_encode(['success' => true, 'badges' => $badges]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Share achievement (returns share data)
     */
    public function shareAchievement()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        $type = $_GET['type'] ?? 'badge';
        $key = $_GET['key'] ?? '';

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

        echo json_encode(['success' => true, 'share' => $shareData]);
    }

    /**
     * Get all seasons
     */
    public function getSeasons()
    {
        header('Content-Type: application/json');

        try {
            $seasons = LeaderboardSeasonService::getAllSeasons();
            echo json_encode(['success' => true, 'seasons' => $seasons]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get current season with user data
     */
    public function getCurrentSeason()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        try {
            $data = LeaderboardSeasonService::getSeasonWithUserData($_SESSION['user_id']);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
