<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\GamificationService;
use Nexus\Services\LeaderboardService;
use Nexus\Services\StreakService;
use Nexus\Services\ChallengeService;
use Nexus\Services\BadgeCollectionService;
use Nexus\Services\XPShopService;
use Nexus\Services\LeaderboardSeasonService;
use Nexus\Helpers\UrlHelper;

class AchievementsController
{
    /**
     * Display the user's gamification dashboard
     */
    public function index()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $dashboardData = GamificationService::getDashboardData($userId);

        if (!$dashboardData) {
            header('Location: ' . TenantContext::getBasePath() . '/');
            exit;
        }

        View::render('achievements/index', [
            'pageTitle' => 'My Achievements',
            'data' => $dashboardData,
            'levelThresholds' => GamificationService::LEVEL_THRESHOLDS,
            'xpValues' => GamificationService::XP_VALUES,
        ]);
    }

    /**
     * API endpoint to get badge progress
     */
    public function progress()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        $progress = GamificationService::getBadgeProgress($_SESSION['user_id']);

        echo json_encode([
            'success' => true,
            'progress' => $progress
        ]);
    }

    /**
     * API endpoint to get full dashboard data
     */
    public function api()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        $data = GamificationService::getDashboardData($_SESSION['user_id']);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * View all badges (earned and available)
     */
    public function badges()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $userBadges = \Nexus\Models\UserBadge::getForUser($userId);
        $earnedKeys = array_column($userBadges, 'badge_key');
        $showcasedBadges = \Nexus\Models\UserBadge::getShowcased($userId);
        $showcasedKeys = array_column($showcasedBadges, 'badge_key');

        // Get rarity stats
        $rarityStats = \Nexus\Models\UserBadge::getBadgeRarityStats();

        // Group all badges by category
        $allBadges = GamificationService::getBadgeDefinitions();
        $badgesByCategory = [];

        $categoryNames = [
            'vol' => 'Volunteering',
            'offer' => 'Offers',
            'request' => 'Requests',
            'earn' => 'Earning Credits',
            'spend' => 'Spending Credits',
            'transaction' => 'Transactions',
            'diversity' => 'Community Impact',
            'connection' => 'Connections',
            'message' => 'Messaging',
            'review_given' => 'Reviews Given',
            '5star' => 'Reviews Received',
            'event_attend' => 'Event Attendance',
            'event_host' => 'Event Hosting',
            'group_join' => 'Groups',
            'group_create' => 'Group Creation',
            'post' => 'Posts',
            'likes_received' => 'Engagement',
            'profile' => 'Profile',
            'membership' => 'Membership',
            'streak' => 'Streaks',
            'level' => 'Levels',
            'special' => 'Special',
            'vol_org' => 'Organizations',
        ];

        foreach ($allBadges as $badge) {
            $type = $badge['type'];
            if (!isset($badgesByCategory[$type])) {
                $badgesByCategory[$type] = [
                    'name' => $categoryNames[$type] ?? ucfirst($type),
                    'badges' => []
                ];
            }
            $badge['earned'] = in_array($badge['key'], $earnedKeys);
            $badge['showcased'] = in_array($badge['key'], $showcasedKeys);
            $badge['rarity'] = $rarityStats[$badge['key']] ?? null;
            $badgesByCategory[$type]['badges'][] = $badge;
        }

        View::render('achievements/badges', [
            'pageTitle' => 'All Badges',
            'badgesByCategory' => $badgesByCategory,
            'totalEarned' => count($userBadges),
            'totalAvailable' => count($allBadges),
            'showcasedBadges' => $showcasedBadges,
        ]);
    }

    /**
     * Update badge showcase selection
     */
    public function updateShowcase()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $badgeKeys = $_POST['badge_keys'] ?? [];
        \Nexus\Models\UserBadge::updateShowcase($_SESSION['user_id'], $badgeKeys);

        $referer = UrlHelper::safeReferer(TenantContext::getBasePath() . '/achievements/badges');
        header('Location: ' . $referer . '?showcase_updated=1');
        exit;
    }

    /**
     * View active challenges
     */
    public function challenges()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        try {
            $challenges = ChallengeService::getChallengesWithProgress($userId);
        } catch (\Throwable $e) {
            $challenges = [];
        }

        // Get user XP info
        $user = \Nexus\Core\Database::query(
            "SELECT xp, level FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        View::render('achievements/challenges', [
            'pageTitle' => 'Challenges',
            'challenges' => $challenges,
            'userXP' => (int)($user['xp'] ?? 0),
            'userLevel' => (int)($user['level'] ?? 1),
        ]);
    }

    /**
     * View badge collections
     */
    public function collections()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        try {
            $collections = BadgeCollectionService::getCollectionsWithProgress($userId);
        } catch (\Throwable $e) {
            $collections = [];
        }

        View::render('achievements/collections', [
            'pageTitle' => 'Badge Collections',
            'collections' => $collections,
        ]);
    }

    /**
     * XP Shop page
     */
    public function shop()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        try {
            $shopData = XPShopService::getItemsWithUserStatus($userId);
        } catch (\Throwable $e) {
            $shopData = ['items' => [], 'user_xp' => 0];
        }

        View::render('achievements/shop', [
            'pageTitle' => 'XP Shop',
            'items' => $shopData['items'],
            'userXP' => $shopData['user_xp'],
        ]);
    }

    /**
     * Leaderboard seasons page
     */
    public function seasons()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        try {
            $seasonData = LeaderboardSeasonService::getSeasonWithUserData($userId);
            $allSeasons = LeaderboardSeasonService::getAllSeasons();
        } catch (\Throwable $e) {
            $seasonData = null;
            $allSeasons = [];
        }

        View::render('achievements/seasons', [
            'pageTitle' => 'Leaderboard Seasons',
            'seasonData' => $seasonData,
            'allSeasons' => $allSeasons,
        ]);
    }
}
