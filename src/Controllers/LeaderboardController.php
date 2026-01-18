<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Services\LeaderboardService;
use Nexus\Services\StreakService;
use Nexus\Services\GamificationService;

class LeaderboardController
{
    /**
     * Display the main leaderboards page
     */
    public function index()
    {
        $type = $_GET['type'] ?? 'xp';
        $period = $_GET['period'] ?? 'all_time';

        // Validate type and period
        if (!array_key_exists($type, LeaderboardService::LEADERBOARD_TYPES)) {
            $type = 'xp';
        }
        if (!in_array($period, LeaderboardService::PERIODS)) {
            $period = 'all_time';
        }

        // Get main leaderboard
        $leaderboard = LeaderboardService::getLeaderboard($type, $period, 20);

        // Get user's personal stats if logged in
        $userStats = null;
        $userStreaks = null;
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $userStats = $this->getUserStats($userId);
            $userStreaks = StreakService::getAllStreaks($userId);
        }

        View::render('leaderboard/index', [
            'pageTitle' => 'Leaderboards',
            'leaderboard' => $leaderboard,
            'currentType' => $type,
            'currentPeriod' => $period,
            'types' => LeaderboardService::LEADERBOARD_TYPES,
            'periods' => [
                'all_time' => 'All Time',
                'monthly' => 'This Month',
                'weekly' => 'This Week'
            ],
            'userStats' => $userStats,
            'userStreaks' => $userStreaks
        ]);
    }

    /**
     * API endpoint to get leaderboard data
     */
    public function api()
    {
        header('Content-Type: application/json');

        $type = $_GET['type'] ?? 'xp';
        $period = $_GET['period'] ?? 'all_time';
        $limit = min((int)($_GET['limit'] ?? 10), 50);

        if (!array_key_exists($type, LeaderboardService::LEADERBOARD_TYPES)) {
            echo json_encode(['error' => 'Invalid leaderboard type']);
            return;
        }

        $leaderboard = LeaderboardService::getLeaderboard($type, $period, $limit);

        // Format scores for display
        foreach ($leaderboard as &$entry) {
            $entry['formatted_score'] = LeaderboardService::formatScore($entry['score'], $type);
            $entry['medal'] = LeaderboardService::getMedalIcon($entry['rank']);
        }

        echo json_encode([
            'success' => true,
            'type' => $type,
            'period' => $period,
            'title' => LeaderboardService::LEADERBOARD_TYPES[$type],
            'data' => $leaderboard
        ]);
    }

    /**
     * Get user's personal gamification stats
     */
    private function getUserStats($userId)
    {
        $user = \Nexus\Models\User::findById($userId);
        if (!$user) return null;

        $xp = (int)($user['xp'] ?? 0);
        $level = (int)($user['level'] ?? 1);

        return [
            'xp' => $xp,
            'level' => $level,
            'level_progress' => GamificationService::getLevelProgress($xp, $level),
            'xp_for_next' => GamificationService::getXPForNextLevel($level),
            'badges_count' => count(\Nexus\Models\UserBadge::getForUser($userId)),
            'level_thresholds' => GamificationService::LEVEL_THRESHOLDS
        ];
    }

    /**
     * Get user's streak information (API)
     */
    public function streaks()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        $streaks = StreakService::getAllStreaks($_SESSION['user_id']);

        // Add display info
        foreach ($streaks as $type => &$streak) {
            $streak['icon'] = StreakService::getStreakIcon($streak['current']);
            $streak['message'] = StreakService::getStreakMessage($streak);
        }

        echo json_encode([
            'success' => true,
            'streaks' => $streaks
        ]);
    }

    /**
     * Summary widget data for dashboard
     */
    public function widget()
    {
        header('Content-Type: application/json');

        // Get top 3 for multiple categories
        $summary = [
            'xp' => LeaderboardService::getLeaderboard('xp', 'all_time', 3, false),
            'vol_hours' => LeaderboardService::getLeaderboard('vol_hours', 'all_time', 3, false),
            'credits_earned' => LeaderboardService::getLeaderboard('credits_earned', 'all_time', 3, false),
        ];

        // Add medals and format
        foreach ($summary as $type => &$leaders) {
            foreach ($leaders as &$entry) {
                $entry['medal'] = LeaderboardService::getMedalIcon($entry['rank']);
                $entry['formatted_score'] = LeaderboardService::formatScore($entry['score'], $type);
            }
        }

        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
    }
}
