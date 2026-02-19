<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Services\GamificationService;
use Nexus\Models\User;
use Nexus\Models\Transaction;
use Nexus\Models\Review;
use PDO;

/**
 * Nexus Score Service - Calculate comprehensive 1000-point scoring system
 *
 * Score Breakdown:
 * - Community Engagement: 250 points
 * - Contribution Quality: 200 points
 * - Volunteer Hours: 200 points
 * - Platform Activity: 150 points
 * - Badges & Achievements: 100 points
 * - Social Impact: 100 points
 *
 * Total: 1000 points maximum
 */
class NexusScoreService
{
    private $db;
    private $gamificationService;

    // Maximum points per category
    const MAX_ENGAGEMENT = 250;
    const MAX_QUALITY = 200;
    const MAX_VOLUNTEER = 200;
    const MAX_ACTIVITY = 150;
    const MAX_BADGES = 100;
    const MAX_IMPACT = 100;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->gamificationService = new GamificationService($db);
    }

    /**
     * Calculate comprehensive Nexus Score for a user
     *
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @return array Score details with breakdown
     */
    public function calculateNexusScore(int $userId, int $tenantId): array
    {
        $engagement = $this->calculateEngagementScore($userId, $tenantId);
        $quality = $this->calculateQualityScore($userId, $tenantId);
        $volunteer = $this->calculateVolunteerScore($userId, $tenantId);
        $activity = $this->calculateActivityScore($userId, $tenantId);
        $badges = $this->calculateBadgeScore($userId, $tenantId);
        $impact = $this->calculateImpactScore($userId, $tenantId);

        $totalScore = $engagement['score'] + $quality['score'] + $volunteer['score']
                    + $activity['score'] + $badges['score'] + $impact['score'];

        // Calculate percentile rank
        $percentile = $this->calculatePercentile($userId, $tenantId, $totalScore);

        // Determine tier
        $tier = $this->calculateTier($totalScore);

        return [
            'total_score' => $totalScore,
            'max_score' => 1000,
            'percentage' => round(($totalScore / 1000) * 100, 1),
            'percentile' => $percentile,
            'tier' => $tier,
            'breakdown' => [
                'engagement' => $engagement,
                'quality' => $quality,
                'volunteer' => $volunteer,
                'activity' => $activity,
                'badges' => $badges,
                'impact' => $impact
            ],
            'insights' => $this->generateInsights($engagement, $quality, $volunteer, $activity, $badges, $impact),
            'next_milestone' => $this->getNextMilestone($totalScore)
        ];
    }

    /**
     * Community Engagement Score (250 points max)
     * - Time credits sent: up to 80 points
     * - Time credits received: up to 70 points
     * - Unique connections: up to 50 points
     * - Diversity of interactions: up to 50 points
     */
    private function calculateEngagementScore(int $userId, int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END), 0) as credits_sent,
                COALESCE(SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END), 0) as credits_received,
                COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id
                                    WHEN receiver_id = ? THEN sender_id END) as unique_connections,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM transactions
            WHERE tenant_id = ? AND status = 'completed'
            AND (sender_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $tenantId, $userId, $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate sub-scores
        $creditsSentScore = min(80, ($data['credits_sent'] / 100) * 80); // Max at 100 credits
        $creditsReceivedScore = min(70, ($data['credits_received'] / 100) * 70); // Max at 100 credits
        $connectionsScore = min(50, ($data['unique_connections'] / 25) * 50); // Max at 25 connections
        $diversityScore = min(50, ($data['active_days'] / 100) * 50); // Max at 100 active days

        $totalScore = $creditsSentScore + $creditsReceivedScore + $connectionsScore + $diversityScore;

        return [
            'score' => round($totalScore, 1),
            'max' => self::MAX_ENGAGEMENT,
            'percentage' => round(($totalScore / self::MAX_ENGAGEMENT) * 100, 1),
            'details' => [
                'credits_sent' => (int)$data['credits_sent'],
                'credits_sent_score' => round($creditsSentScore, 1),
                'credits_received' => (int)$data['credits_received'],
                'credits_received_score' => round($creditsReceivedScore, 1),
                'unique_connections' => (int)$data['unique_connections'],
                'connections_score' => round($connectionsScore, 1),
                'active_days' => (int)$data['active_days'],
                'diversity_score' => round($diversityScore, 1)
            ]
        ];
    }

    /**
     * Contribution Quality Score (200 points max)
     * - Average rating: up to 100 points
     * - Number of positive reviews: up to 50 points
     * - Transaction success rate: up to 50 points
     */
    private function calculateQualityScore(int $userId, int $tenantId): array
    {
        // Get average rating
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(AVG(rating), 0) as avg_rating,
                COUNT(*) as review_count,
                SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_reviews
            FROM reviews
            WHERE receiver_id = ?
        ");
        $stmt->execute([$userId]);
        $reviewData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get transaction success rate
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions
            FROM transactions
            WHERE tenant_id = ? AND (sender_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$tenantId, $userId, $userId]);
        $transactionData = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgRating = (float)$reviewData['avg_rating'];
        $positiveReviews = (int)$reviewData['positive_reviews'];
        $successRate = $transactionData['total_transactions'] > 0
            ? ($transactionData['completed_transactions'] / $transactionData['total_transactions'])
            : 0;

        // Calculate sub-scores
        $ratingScore = ($avgRating / 5.0) * 100; // 5-star scale to 100 points
        $reviewScore = min(50, ($positiveReviews / 20) * 50); // Max at 20 positive reviews
        $successScore = $successRate * 50; // 100% success rate = 50 points

        $totalScore = $ratingScore + $reviewScore + $successScore;

        return [
            'score' => round($totalScore, 1),
            'max' => self::MAX_QUALITY,
            'percentage' => round(($totalScore / self::MAX_QUALITY) * 100, 1),
            'details' => [
                'avg_rating' => round($avgRating, 2),
                'rating_score' => round($ratingScore, 1),
                'review_count' => (int)$reviewData['review_count'],
                'positive_reviews' => $positiveReviews,
                'review_score' => round($reviewScore, 1),
                'success_rate' => round($successRate * 100, 1),
                'success_score' => round($successScore, 1)
            ]
        ];
    }

    /**
     * Volunteer Hours Score (200 points max)
     * - Total verified hours: up to 150 points
     * - Consistency (regularity): up to 50 points
     */
    private function calculateVolunteerScore(int $userId, int $tenantId): array
    {
        // Get volunteer hours from transactions (using all completed transactions as proxy)
        // In the future, you can filter by category or add a transaction_type column
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(amount), 0) as total_hours,
                COUNT(DISTINCT DATE(created_at)) as volunteer_days,
                DATEDIFF(MAX(created_at), MIN(created_at)) + 1 as days_span
            FROM transactions
            WHERE tenant_id = ?
            AND sender_id = ?
            AND status = 'completed'
        ");
        $stmt->execute([$tenantId, $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalHours = (float)$data['total_hours'];
        $volunteerDays = (int)$data['volunteer_days'];
        $daysSpan = (int)$data['days_span'];

        // Calculate sub-scores
        $hoursScore = min(150, ($totalHours / 250) * 150); // Max at 250 hours

        // Consistency: volunteer days / total days (how regular)
        $consistency = $daysSpan > 0 ? ($volunteerDays / $daysSpan) : 0;
        $consistencyScore = min(50, $consistency * 50 * 10); // Boost for regular volunteering

        $totalScore = $hoursScore + $consistencyScore;

        return [
            'score' => round($totalScore, 1),
            'max' => self::MAX_VOLUNTEER,
            'percentage' => round(($totalScore / self::MAX_VOLUNTEER) * 100, 1),
            'details' => [
                'total_hours' => round($totalHours, 1),
                'hours_score' => round($hoursScore, 1),
                'volunteer_days' => $volunteerDays,
                'consistency_rate' => round($consistency * 100, 1),
                'consistency_score' => round($consistencyScore, 1)
            ]
        ];
    }

    /**
     * Platform Activity Score (150 points max)
     * - Listings created: up to 40 points
     * - Events attended/hosted: up to 40 points
     * - Group participation: up to 35 points
     * - Login streak: up to 35 points
     */
    private function calculateActivityScore(int $userId, int $tenantId): array
    {
        // Count listings
        $stmt = $this->db->prepare("SELECT COUNT(*) as listing_count FROM listings WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $listingCount = (int)$stmt->fetchColumn();

        // Count event participation (if events table exists)
        $eventCount = 0;
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM event_participants WHERE user_id = ?");
            $stmt->execute([$userId]);
            $eventCount = (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            // Events table might not exist
        }

        // Count group memberships
        $stmt = $this->db->prepare("SELECT COUNT(*) as group_count FROM group_members WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $groupCount = (int)$stmt->fetchColumn();

        // Get login streak from database directly
        $loginStreak = $this->getLoginStreak($userId);

        // Calculate sub-scores
        $listingScore = min(40, ($listingCount / 20) * 40); // Max at 20 listings
        $eventScore = min(40, ($eventCount / 30) * 40); // Max at 30 events
        $groupScore = min(35, ($groupCount / 10) * 35); // Max at 10 groups
        $streakScore = min(35, ($loginStreak / 100) * 35); // Max at 100 day streak

        $totalScore = $listingScore + $eventScore + $groupScore + $streakScore;

        return [
            'score' => round($totalScore, 1),
            'max' => self::MAX_ACTIVITY,
            'percentage' => round(($totalScore / self::MAX_ACTIVITY) * 100, 1),
            'details' => [
                'listing_count' => $listingCount,
                'listing_score' => round($listingScore, 1),
                'event_count' => $eventCount,
                'event_score' => round($eventScore, 1),
                'group_count' => $groupCount,
                'group_score' => round($groupScore, 1),
                'login_streak' => $loginStreak,
                'streak_score' => round($streakScore, 1)
            ]
        ];
    }

    /**
     * Badge & Achievement Score (100 points max)
     * Based on badge rarity and quantity
     */
    private function calculateBadgeScore(int $userId, int $tenantId): array
    {
        $badges = \Nexus\Models\UserBadge::getForUser($userId);

        // Badge rarity weights
        $rarityWeights = [
            'vol_500h' => 10,      // Volunteer Champion
            'vol_250h' => 8,       // Volunteer Hero
            'transaction_50' => 7, // Exchange Master
            'diversity_25' => 7,   // Community Pillar
            'membership_annual' => 6,
            'level_10' => 6,       // Community Champion
            'vol_100h' => 5,
            'level_5' => 4,
            'default' => 2         // Standard badges
        ];

        $totalBadgeScore = 0;
        $badgeDetails = [];

        foreach ($badges as $badge) {
            $badgeKey = $badge['badge_key'] ?? '';
            $weight = $rarityWeights[$badgeKey] ?? $rarityWeights['default'];
            $totalBadgeScore += $weight;

            $badgeDetails[] = [
                'name' => $badge['name'],
                'key' => $badgeKey,
                'weight' => $weight
            ];
        }

        // Cap at 100 points
        $score = min(100, $totalBadgeScore);

        return [
            'score' => round($score, 1),
            'max' => self::MAX_BADGES,
            'percentage' => round(($score / self::MAX_BADGES) * 100, 1),
            'details' => [
                'badge_count' => count($badges),
                'rare_badges' => count(array_filter($badges, function($b) use ($rarityWeights) {
                    $key = $b['badge_key'] ?? '';
                    return ($rarityWeights[$key] ?? 2) >= 6;
                })),
                'badges' => $badgeDetails
            ]
        ];
    }

    /**
     * Social Impact Score (100 points max)
     * - Content creation: up to 50 points
     * - Engagement received: up to 50 points
     */
    private function calculateImpactScore(int $userId, int $tenantId): array
    {
        // Count posts
        $postCount = 0;
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM feed_posts WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            $postCount = (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            // Feed posts table might not exist
        }

        // Count likes received
        $likesReceived = 0;
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM post_likes pl
                JOIN feed_posts fp ON pl.post_id = fp.id
                WHERE fp.user_id = ? AND pl.tenant_id = ?
            ");
            $stmt->execute([$userId, $tenantId]);
            $likesReceived = (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            // Likes table might not exist
        }

        // Calculate sub-scores
        $contentScore = min(50, ($postCount / 50) * 50); // Max at 50 posts
        $engagementScore = min(50, ($likesReceived / 100) * 50); // Max at 100 likes

        $totalScore = $contentScore + $engagementScore;

        return [
            'score' => round($totalScore, 1),
            'max' => self::MAX_IMPACT,
            'percentage' => round(($totalScore / self::MAX_IMPACT) * 100, 1),
            'details' => [
                'post_count' => $postCount,
                'content_score' => round($contentScore, 1),
                'likes_received' => $likesReceived,
                'engagement_score' => round($engagementScore, 1)
            ]
        ];
    }

    /**
     * Calculate user's percentile rank in community
     */
    private function calculatePercentile(int $userId, int $tenantId, float $userScore): int
    {
        // This would require caching scores or calculating on-demand
        // For now, return estimated percentile based on score
        if ($userScore >= 900) return 99;
        if ($userScore >= 800) return 95;
        if ($userScore >= 700) return 90;
        if ($userScore >= 600) return 80;
        if ($userScore >= 500) return 70;
        if ($userScore >= 400) return 60;
        if ($userScore >= 300) return 50;
        if ($userScore >= 200) return 40;
        if ($userScore >= 100) return 30;
        return 20;
    }

    /**
     * Calculate user tier based on total score
     */
    public function calculateTier(float $score): array
    {
        if ($score >= 900) {
            return ['name' => 'Legendary', 'color' => '#ffd700', 'icon' => '👑'];
        } elseif ($score >= 800) {
            return ['name' => 'Elite', 'color' => '#c0c0c0', 'icon' => '💎'];
        } elseif ($score >= 700) {
            return ['name' => 'Expert', 'color' => '#cd7f32', 'icon' => '⭐'];
        } elseif ($score >= 600) {
            return ['name' => 'Advanced', 'color' => '#6366f1', 'icon' => '🔥'];
        } elseif ($score >= 500) {
            return ['name' => 'Proficient', 'color' => '#8b5cf6', 'icon' => '🚀'];
        } elseif ($score >= 400) {
            return ['name' => 'Intermediate', 'color' => '#06b6d4', 'icon' => '💪'];
        } elseif ($score >= 300) {
            return ['name' => 'Developing', 'color' => '#10b981', 'icon' => '🌱'];
        } elseif ($score >= 200) {
            return ['name' => 'Beginner', 'color' => '#f59e0b', 'icon' => '🌟'];
        } else {
            return ['name' => 'Novice', 'color' => '#94a3b8', 'icon' => '🎯'];
        }
    }

    /**
     * Generate personalized insights and improvement tips
     */
    private function generateInsights(array $engagement, array $quality, array $volunteer, array $activity, array $badges, array $impact): array
    {
        $insights = [];
        $categories = [
            ['name' => 'Engagement', 'data' => $engagement, 'key' => 'engagement'],
            ['name' => 'Quality', 'data' => $quality, 'key' => 'quality'],
            ['name' => 'Volunteer', 'data' => $volunteer, 'key' => 'volunteer'],
            ['name' => 'Activity', 'data' => $activity, 'key' => 'activity'],
            ['name' => 'Badges', 'data' => $badges, 'key' => 'badges'],
            ['name' => 'Impact', 'data' => $impact, 'key' => 'impact']
        ];

        // Sort by percentage to find strengths and weaknesses
        usort($categories, function($a, $b) {
            return $b['data']['percentage'] <=> $a['data']['percentage'];
        });

        // Strongest area
        $strongest = $categories[0];
        $insights[] = [
            'type' => 'strength',
            'title' => "{$strongest['name']} is your strongest area!",
            'message' => "You're in the top tier with {$strongest['data']['percentage']}% in {$strongest['name']}.",
            'icon' => '🏆'
        ];

        // Weakest area (improvement opportunity)
        $weakest = $categories[count($categories) - 1];
        if ($weakest['data']['percentage'] < 50) {
            $insights[] = [
                'type' => 'improvement',
                'title' => "Grow your {$weakest['name']} score",
                'message' => $this->getImprovementTip($weakest['key']),
                'icon' => '💡'
            ];
        }

        // Specific suggestions
        if ($engagement['details']['unique_connections'] < 10) {
            $insights[] = [
                'type' => 'suggestion',
                'title' => 'Connect with more community members',
                'message' => 'Exchange credits with new people to increase your network diversity.',
                'icon' => '🤝'
            ];
        }

        if ($quality['details']['review_count'] < 5) {
            $insights[] = [
                'type' => 'suggestion',
                'title' => 'Complete more exchanges',
                'message' => 'Build your reputation by completing transactions and earning reviews.',
                'icon' => '⭐'
            ];
        }

        return $insights;
    }

    /**
     * Get improvement tip for category
     */
    private function getImprovementTip(string $category): string
    {
        $tips = [
            'engagement' => 'Try sending and receiving time credits with different community members.',
            'quality' => 'Focus on completing transactions successfully and earning positive reviews.',
            'volunteer' => 'Log your volunteer hours regularly and maintain consistency.',
            'activity' => 'Create listings, join groups, and maintain a daily login streak.',
            'badges' => 'Complete challenges to earn more badges and achievements.',
            'impact' => 'Share posts and engage with the community feed.'
        ];

        return $tips[$category] ?? 'Keep participating in the community!';
    }

    /**
     * Get next milestone
     */
    private function getNextMilestone(float $currentScore): array
    {
        $milestones = [
            ['score' => 200, 'name' => 'Beginner', 'reward' => 'Unlock profile customization'],
            ['score' => 300, 'name' => 'Developing', 'reward' => 'Unlock advanced search filters'],
            ['score' => 400, 'name' => 'Intermediate', 'reward' => 'Featured in community spotlight'],
            ['score' => 500, 'name' => 'Proficient', 'reward' => 'Unlock priority listing placement'],
            ['score' => 600, 'name' => 'Advanced', 'reward' => 'Mentor badge and mentoring access'],
            ['score' => 700, 'name' => 'Expert', 'reward' => 'Exclusive community events access'],
            ['score' => 800, 'name' => 'Elite', 'reward' => 'VIP support and early feature access'],
            ['score' => 900, 'name' => 'Legendary', 'reward' => 'Hall of Fame recognition'],
        ];

        foreach ($milestones as $milestone) {
            if ($currentScore < $milestone['score']) {
                $remaining = $milestone['score'] - $currentScore;
                return [
                    'target_score' => $milestone['score'],
                    'name' => $milestone['name'],
                    'reward' => $milestone['reward'],
                    'points_remaining' => round($remaining, 1),
                    'progress_percentage' => round(($currentScore / $milestone['score']) * 100, 1)
                ];
            }
        }

        // Already at max
        return [
            'target_score' => 1000,
            'name' => 'Maximum Level',
            'reward' => 'You\'ve achieved everything!',
            'points_remaining' => 0,
            'progress_percentage' => 100
        ];
    }

    /**
     * Get community-wide statistics
     */
    public function getCommunityStats(int $tenantId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1");
        $stmt->execute([$tenantId]);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $scores = [];
        $tierCounts = [];

        // Calculate scores for all users
        foreach ($userIds as $userId) {
            try {
                $scoreData = $this->calculateNexusScore($userId, $tenantId);
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

        // Calculate average
        $totalUsers = count($scores);
        $averageScore = $totalUsers > 0 ? round(array_sum($scores) / $totalUsers, 1) : 0;

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
            'tier_distribution' => $tierCounts
        ];
    }

    /**
     * Get user's current login streak
     */
    private function getLoginStreak(int $userId): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT current_streak
                FROM user_streaks
                WHERE user_id = ? AND streak_type = 'login'
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)($result['current_streak'] ?? 0);
        } catch (\Exception $e) {
            // user_streaks table might not exist, return 0
            return 0;
        }
    }
}
