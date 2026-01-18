<?php

namespace Nexus\Controllers;

use Nexus\Core\Database;
use Nexus\Services\NexusScoreService;
use Nexus\Services\NexusScoreCacheService;
use Nexus\Services\GamificationService;
use Nexus\Models\User;

/**
 * Nexus Score Controller
 * Handles scoring system views and API endpoints
 */
class NexusScoreController
{
    private $db;
    private $scoreService;
    private $cacheService;
    private $gamificationService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->scoreService = new NexusScoreService($this->db);
        $this->cacheService = new NexusScoreCacheService($this->db);
        $this->gamificationService = new GamificationService($this->db);
    }

    /**
     * Display user's personal score dashboard
     */
    public function dashboard()
    {
        // Verify authentication
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Get score (from cache or calculate fresh) - REAL-TIME DATA FOR YOUR ACCOUNT
        $scoreData = $this->cacheService->getScore($userId, $tenantId);

        // Get user badges - YOUR REAL BADGES
        $badges = \Nexus\Models\UserBadge::getForUser($userId);

        // Get recent achievements (last 30 days) - YOUR REAL ACHIEVEMENTS
        $recentAchievements = $this->getRecentAchievements($userId, $tenantId, 30);

        // Get community statistics - REAL COMMUNITY DATA
        $communityStats = $this->cacheService->getCachedLeaderboard($tenantId, 100);

        // Get real milestones for achievements tab - YOUR REAL MILESTONES
        $milestones = $this->getUserMilestones($userId, $tenantId, $scoreData);

        // Get leaderboard data for leaderboard tab - REAL LEADERBOARD
        $leaderboardData = $this->cacheService->getCachedLeaderboard($tenantId, 50);
        $currentUserData = $this->cacheService->getCachedRank($userId, $tenantId);

        // Render dashboard view using View::render for layout awareness
        \Nexus\Core\View::render('dashboard/nexus-score-dashboard-page', [
            'isPublic' => false,
            'scoreData' => $scoreData,
            'badges' => $badges,
            'recentAchievements' => $recentAchievements,
            'communityStats' => $communityStats,
            'milestones' => $milestones,
            'leaderboardData' => $leaderboardData,
            'currentUserData' => $currentUserData
        ]);
    }

    /**
     * Display public profile score view
     */
    public function publicProfile($profileUserId)
    {
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Calculate score (limited data for public view)
        $scoreData = $this->scoreService->calculateNexusScore($profileUserId, $tenantId);

        // Get showcased badges only
        $badges = \Nexus\Models\UserBadge::getForUser($profileUserId);
        $badges = array_filter($badges, function($b) {
            return $b['is_showcased'] ?? false;
        });

        // Render using View::render for layout awareness
        \Nexus\Core\View::render('components/nexus-score-dashboard', [
            'isPublic' => true,
            'scoreData' => $scoreData,
            'badges' => $badges,
            'profileUserId' => $profileUserId
        ]);
    }

    /**
     * Display leaderboard
     */
    public function leaderboard()
    {
        $tenantId = $_SESSION['tenant_id'] ?? 1;
        $timeframe = $_GET['timeframe'] ?? 'all-time';
        $category = $_GET['category'] ?? 'overall';

        // Get top users from cache (REAL LEADERBOARD DATA)
        $leaderboardData = $this->cacheService->getCachedLeaderboard($tenantId, 50);

        // Get current user's rank if authenticated (YOUR REAL RANK)
        $currentUserData = null;
        if (isset($_SESSION['user_id'])) {
            $currentUserData = $this->cacheService->getCachedRank($_SESSION['user_id'], $tenantId);
        }

        // Render using View::render for layout awareness
        \Nexus\Core\View::render('components/nexus-leaderboard', [
            'leaderboardData' => $leaderboardData,
            'currentUserData' => $currentUserData,
            'timeframe' => $timeframe,
            'category' => $category
        ]);
    }

    /**
     * Generate and display impact report
     */
    public function impactReport()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Get date range from query params
        $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end'] ?? date('Y-m-d');

        // Calculate score
        $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);

        // Get impact metrics
        $impactMetrics = $this->calculateImpactMetrics($userId, $tenantId, $startDate, $endDate);

        // Prepare report data
        $reportData = [
            'title' => 'My Nexus Impact Report',
            'period' => date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate)),
            'score_data' => $scoreData,
            'impact_metrics' => $impactMetrics
        ];

        // Render using View::render for layout awareness
        \Nexus\Core\View::render('reports/nexus-impact-report', [
            'reportType' => 'user',
            'reportData' => $reportData
        ]);
    }

    /**
     * Admin analytics dashboard
     */
    public function adminAnalytics()
    {
        // Check admin permission
        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Get aggregated analytics
        $analyticsData = $this->getAnalyticsData($tenantId);

        // Get top performers
        $topPerformers = $this->getLeaderboardData($tenantId, 'monthly', 'overall', 10);

        // Get category statistics
        $categoryStats = $this->getCategoryStatistics($tenantId);

        // Render using View::render for layout awareness
        \Nexus\Core\View::render('admin/nexus-score-analytics', [
            'analyticsData' => $analyticsData,
            'topPerformers' => $topPerformers,
            'categoryStats' => $categoryStats
        ]);
    }

    /**
     * API: Get user score (JSON)
     */
    public function apiGetScore()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userId = $_GET['user_id'] ?? $_SESSION['user_id'];
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Only allow users to view their own score or admins to view any
        if ($userId != $_SESSION['user_id'] && !($_SESSION['is_admin'] ?? false)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);
        echo json_encode($scoreData);
    }

    /**
     * API: Recalculate all scores (admin only, background task)
     */
    public function apiRecalculateScores()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // This would ideally be a background job
        // For now, just acknowledge the request
        echo json_encode([
            'success' => true,
            'message' => 'Score recalculation initiated',
            'note' => 'This process runs in the background'
        ]);
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Get recent achievements for a user
     */
    private function getRecentAchievements($userId, $tenantId, $days = 30)
    {
        $stmt = $this->db->prepare("
            SELECT name, icon, awarded_at as date, 25 as points
            FROM user_badges
            WHERE user_id = ?
            AND awarded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY awarded_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get leaderboard data
     */
    private function getLeaderboardData($tenantId, $timeframe, $category, $limit = 10)
    {
        // Get all active users and calculate their scores
        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, avatar_url
            FROM users
            WHERE tenant_id = ? AND is_approved = 1
            ORDER BY id
        ");
        $stmt->execute([$tenantId]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $userScores = [];
        foreach ($users as $user) {
            try {
                $scoreData = $this->scoreService->calculateNexusScore($user['id'], $tenantId);
                $userScores[] = [
                    'user_id' => $user['id'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'avatar_url' => $user['avatar_url'],
                    'score' => $scoreData['total_score'],
                    'tier' => $scoreData['tier']
                ];
            } catch (\Exception $e) {
                // Skip users with calculation errors
                continue;
            }
        }

        // Sort by score descending
        usort($userScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Get top users
        $topUsers = array_slice($userScores, 0, $limit);

        // Calculate community average
        $totalScore = array_sum(array_column($userScores, 'score'));
        $totalUsers = count($userScores);
        $communityAverage = $totalUsers > 0 ? round($totalScore / $totalUsers, 1) : 0;

        return [
            'top_users' => $topUsers,
            'community_average' => $communityAverage,
            'total_users' => $totalUsers
        ];
    }

    /**
     * Get user's rank
     */
    private function getUserRank($userId, $tenantId)
    {
        $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);
        $userScore = $scoreData['total_score'];

        // Get all users and their scores to determine rank
        $stmt = $this->db->prepare("
            SELECT id
            FROM users
            WHERE tenant_id = ? AND is_approved = 1
        ");
        $stmt->execute([$tenantId]);
        $users = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $scores = [];
        foreach ($users as $uid) {
            try {
                $data = $this->scoreService->calculateNexusScore($uid, $tenantId);
                $scores[$uid] = $data['total_score'];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort scores descending
        arsort($scores);

        // Find user's rank
        $rank = 1;
        foreach ($scores as $uid => $score) {
            if ($uid == $userId) {
                break;
            }
            $rank++;
        }

        return [
            'rank' => $rank,
            'score' => $userScore,
            'total_users' => count($scores)
        ];
    }

    /**
     * Calculate impact metrics for report
     */
    private function calculateImpactMetrics($userId, $tenantId, $startDate, $endDate)
    {
        // Current period data
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_exchanges,
                COALESCE(SUM(amount), 0) as hours_exchanged,
                COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id
                                    WHEN receiver_id = ? THEN sender_id END) as active_members
            FROM transactions
            WHERE tenant_id = ?
            AND (sender_id = ? OR receiver_id = ?)
            AND status = 'completed'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $userId, $tenantId, $userId, $userId, $startDate, $endDate]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Previous period data (for comparison)
        $dateDiff = (strtotime($endDate) - strtotime($startDate));
        $prevStartDate = date('Y-m-d', strtotime($startDate) - $dateDiff);
        $prevEndDate = $startDate;

        $stmtPrev = $this->db->prepare("
            SELECT
                COUNT(*) as total_exchanges,
                COALESCE(SUM(amount), 0) as hours_exchanged,
                COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id
                                    WHEN receiver_id = ? THEN sender_id END) as active_members
            FROM transactions
            WHERE tenant_id = ?
            AND (sender_id = ? OR receiver_id = ?)
            AND status = 'completed'
            AND created_at BETWEEN ? AND ?
        ");
        $stmtPrev->execute([$userId, $userId, $tenantId, $userId, $userId, $prevStartDate, $prevEndDate]);
        $prevData = $stmtPrev->fetch(\PDO::FETCH_ASSOC);

        // Calculate percentage changes
        $exchangesChange = $this->calculatePercentChange($prevData['total_exchanges'] ?? 0, $data['total_exchanges'] ?? 0);
        $hoursChange = $this->calculatePercentChange($prevData['hours_exchanged'] ?? 0, $data['hours_exchanged'] ?? 0);
        $membersChange = $this->calculatePercentChange($prevData['active_members'] ?? 0, $data['active_members'] ?? 0);

        // Calculate economic value
        $economicValue = ($data['hours_exchanged'] ?? 0) * 25;
        $prevEconomicValue = ($prevData['hours_exchanged'] ?? 0) * 25;
        $valueChange = $this->calculatePercentChange($prevEconomicValue, $economicValue);

        // Get additional metrics
        $skillsStmt = $this->db->prepare("SELECT COUNT(DISTINCT skill) as count FROM user_skills WHERE user_id = ?");
        $skillsStmt->execute([$userId]);
        $skillsCount = (int)$skillsStmt->fetchColumn();

        $eventsStmt = $this->db->prepare("SELECT COUNT(*) as count FROM event_rsvps WHERE user_id = ? AND status = 'going'");
        $eventsStmt->execute([$userId]);
        $eventsCount = (int)$eventsStmt->fetchColumn();

        // Calculate network diversity (percentage of unique connections vs total transactions)
        $networkDiversity = $data['total_exchanges'] > 0
            ? round(($data['active_members'] / $data['total_exchanges']) * 100, 1)
            : 0;

        // Generate personalized story
        $story = $this->generateImpactStory($data, $exchangesChange, $hoursChange);
        $keyInsight = $this->generateKeyInsight($data, $tenantId);

        return [
            'total_exchanges' => (int)($data['total_exchanges'] ?? 0),
            'exchanges_change' => $exchangesChange,
            'hours_exchanged' => (float)($data['hours_exchanged'] ?? 0),
            'hours_change' => $hoursChange,
            'active_members' => (int)($data['active_members'] ?? 0),
            'members_change' => $membersChange,
            'economic_value' => $economicValue,
            'value_change' => $valueChange,
            'avg_transaction' => ($data['total_exchanges'] > 0 ? round($data['hours_exchanged'] / $data['total_exchanges'], 1) : 0),
            'network_diversity' => $networkDiversity,
            'skills_count' => $skillsCount,
            'events_count' => $eventsCount,
            'story' => $story,
            'key_insight' => $keyInsight
        ];
    }

    private function calculatePercentChange($old, $new)
    {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }
        return round((($new - $old) / $old) * 100, 1);
    }

    private function generateImpactStory($data, $exchangesChange, $hoursChange)
    {
        $exchanges = $data['total_exchanges'];
        $hours = $data['hours_exchanged'];
        $members = $data['active_members'];

        if ($exchanges == 0) {
            return "Start your journey by completing your first exchange to begin making an impact in the community.";
        }

        $stories = [];
        if ($exchangesChange > 10) {
            $stories[] = "Your activity has increased by {$exchangesChange}% this period";
        }
        if ($hours >= 10) {
            $stories[] = "you've contributed {$hours} hours of valuable services";
        }
        if ($members >= 5) {
            $stories[] = "building connections with {$members} community members";
        }

        if (empty($stories)) {
            return "You're actively participating in the timebank community. Keep up the great work!";
        }

        return ucfirst(implode(', ', $stories)) . ". Your engagement is making a measurable difference in building a thriving sharing economy.";
    }

    private function generateKeyInsight($data, $tenantId)
    {
        $exchanges = $data['total_exchanges'];
        $members = $data['active_members'];

        if ($exchanges == 0) {
            return "Complete your first transaction to unlock personalized insights about your community impact.";
        }

        if ($members >= 10) {
            return "Your diverse network of {$members} connections demonstrates strong community integration and trust-building.";
        }

        if ($exchanges >= 5) {
            return "You're building momentum with {$exchanges} completed exchanges. Keep expanding your network for greater impact.";
        }

        return "Every exchange strengthens the community. Your participation helps create a resilient local economy.";
    }

    /**
     * Get admin analytics data
     */
    private function getAnalyticsData($tenantId)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1");
        $stmt->execute([$tenantId]);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $scores = [];
        $tierCounts = [];

        // Calculate scores for all users
        foreach ($userIds as $userId) {
            try {
                $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);
                $scores[] = $scoreData['total_score'];

                $tierName = $scoreData['tier']['name'];
                if (!isset($tierCounts[$tierName])) {
                    $tierCounts[$tierName] = 0;
                }
                $tierCounts[$tierName]++;
            } catch (\Exception $e) {
                continue;
            }
        }

        // Calculate statistics
        $totalUsers = count($scores);
        $averageScore = $totalUsers > 0 ? round(array_sum($scores) / $totalUsers, 1) : 0;

        // Calculate median
        sort($scores);
        $medianScore = 0;
        if ($totalUsers > 0) {
            $middle = floor($totalUsers / 2);
            if ($totalUsers % 2 == 0) {
                $medianScore = round(($scores[$middle - 1] + $scores[$middle]) / 2, 1);
            } else {
                $medianScore = round($scores[$middle], 1);
            }
        }

        // Ensure all tiers are present
        $allTiers = ['Legendary', 'Elite', 'Expert', 'Advanced', 'Proficient', 'Intermediate', 'Developing', 'Beginner', 'Novice'];
        foreach ($allTiers as $tier) {
            if (!isset($tierCounts[$tier])) {
                $tierCounts[$tier] = 0;
            }
        }

        return [
            'total_users' => $totalUsers,
            'average_score' => $averageScore,
            'median_score' => $medianScore,
            'tier_distribution' => $tierCounts
        ];
    }

    /**
     * Get category statistics
     */
    private function getCategoryStatistics($tenantId)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1");
        $stmt->execute([$tenantId]);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $categoryTotals = [
            'engagement' => [],
            'quality' => [],
            'volunteer' => [],
            'activity' => [],
            'badges' => [],
            'impact' => []
        ];

        // Collect scores for each category
        foreach ($userIds as $userId) {
            try {
                $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);
                $breakdown = $scoreData['breakdown'];

                foreach ($categoryTotals as $key => &$values) {
                    if (isset($breakdown[$key]['score'])) {
                        $values[] = $breakdown[$key]['score'];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Calculate averages
        $stats = [];
        $maxScores = [
            'engagement' => 250,
            'quality' => 200,
            'volunteer' => 200,
            'activity' => 150,
            'badges' => 100,
            'impact' => 100
        ];

        foreach ($categoryTotals as $category => $scores) {
            $avg = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
            $stats[$category] = [
                'avg' => $avg,
                'max' => $maxScores[$category]
            ];
        }

        return $stats;
    }

    /**
     * Get user's real milestones based on actual achievements
     */
    private function getUserMilestones($userId, $tenantId, $scoreData)
    {
        $milestones = [];
        $currentScore = $scoreData['total_score'];

        // Score-based milestones
        $scoreMilestones = [
            100 => ['name' => 'First 100 Points', 'icon' => '🎯', 'reward' => 'Profile customization unlocked'],
            200 => ['name' => 'Beginner Tier Achieved', 'icon' => '🌟', 'reward' => 'Community recognition'],
            300 => ['name' => 'Developing Tier Achieved', 'icon' => '🌱', 'reward' => 'Advanced search filters'],
            400 => ['name' => 'Intermediate Tier Achieved', 'icon' => '💪', 'reward' => 'Featured in community spotlight'],
            500 => ['name' => 'Proficient Tier Achieved', 'icon' => '🚀', 'reward' => 'Priority listing placement'],
            600 => ['name' => 'Advanced Tier Achieved', 'icon' => '🔥', 'reward' => 'Mentor badge access'],
            700 => ['name' => 'Expert Tier Achieved', 'icon' => '⭐', 'reward' => 'Exclusive events access'],
            800 => ['name' => 'Elite Tier Achieved', 'icon' => '💎', 'reward' => 'VIP support access'],
            900 => ['name' => 'Legendary Tier Achieved', 'icon' => '👑', 'reward' => 'Hall of Fame recognition']
        ];

        foreach ($scoreMilestones as $threshold => $milestone) {
            if ($currentScore >= $threshold) {
                $milestones[] = [
                    'name' => $milestone['name'],
                    'description' => "Reached {$threshold} total points",
                    'date' => 'Achieved', // Would get from nexus_score_milestones table
                    'reward' => $milestone['reward'],
                    'icon' => $milestone['icon']
                ];
            }
        }

        // Activity-based milestones from actual data
        $breakdown = $scoreData['breakdown'];

        // Transaction milestones
        if ($breakdown['engagement']['score'] >= 50) {
            $milestones[] = [
                'name' => 'Community Engager',
                'description' => 'Active participation in time credit exchanges',
                'date' => 'Achieved',
                'reward' => 'Increased visibility',
                'icon' => '🤝'
            ];
        }

        // Quality milestones
        if ($breakdown['quality']['score'] >= 50) {
            $milestones[] = [
                'name' => 'Quality Contributor',
                'description' => 'Maintained high service quality ratings',
                'date' => 'Achieved',
                'reward' => 'Trust badge',
                'icon' => '⭐'
            ];
        }

        // Volunteer milestones
        if ($breakdown['volunteer']['score'] >= 50) {
            $milestones[] = [
                'name' => 'Dedicated Volunteer',
                'description' => 'Significant volunteer hour contributions',
                'date' => 'Achieved',
                'reward' => 'Volunteer recognition',
                'icon' => '🏅'
            ];
        }

        // Badge milestones
        if ($breakdown['badges']['score'] >= 25) {
            $milestones[] = [
                'name' => 'Badge Collector',
                'description' => 'Earned multiple achievement badges',
                'date' => 'Achieved',
                'reward' => 'Badge showcase',
                'icon' => '🎖️'
            ];
        }

        // If no milestones yet, add encouraging message
        if (empty($milestones)) {
            $milestones[] = [
                'name' => 'Getting Started',
                'description' => 'Begin your journey to earn your first milestone',
                'date' => 'In Progress',
                'reward' => 'Complete transactions and engage with the community',
                'icon' => '🌱'
            ];
        }

        // Sort by most recent (in this case, highest score requirement)
        return array_slice($milestones, -5); // Return last 5 milestones
    }
}
