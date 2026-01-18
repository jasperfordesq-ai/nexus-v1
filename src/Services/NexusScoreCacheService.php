<?php

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * Nexus Score Cache Service
 * Manages cached score calculations for performance
 */
class NexusScoreCacheService
{
    private $db;
    private $scoreService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->scoreService = new NexusScoreService($db);
    }

    /**
     * Get score for a user (from cache or calculate fresh)
     * @param int $userId
     * @param int $tenantId
     * @param bool $forceRecalculate Force fresh calculation
     * @return array Score data
     */
    public function getScore(int $userId, int $tenantId, bool $forceRecalculate = false): array
    {
        // Try to get from cache first (if not forcing recalculation)
        if (!$forceRecalculate) {
            $cached = $this->getCachedScore($userId, $tenantId);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Calculate fresh score
        $scoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);

        // Cache it
        $this->cacheScore($userId, $tenantId, $scoreData);

        return $scoreData;
    }

    /**
     * Get cached score if it exists and is recent (within 1 hour)
     */
    private function getCachedScore(int $userId, int $tenantId): ?array
    {
        try {
            // Check if cache table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'nexus_score_cache'")->fetch();
            if (!$tableCheck) {
                return null; // Cache table doesn't exist yet
            }

            $stmt = $this->db->prepare("
                SELECT *
                FROM nexus_score_cache
                WHERE user_id = ? AND tenant_id = ?
                AND calculated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$userId, $tenantId]);
            $cached = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$cached) {
                return null;
            }

            // Reconstruct score data from cache
            $engagementScore = (float)$cached['engagement_score'];
            $qualityScore = (float)$cached['quality_score'];
            $volunteerScore = (float)$cached['volunteer_score'];
            $activityScore = (float)$cached['activity_score'];
            $badgeScore = (float)$cached['badge_score'];
            $impactScore = (float)$cached['impact_score'];

            return [
                'total_score' => (float)$cached['total_score'],
                'max_score' => 1000,
                'percentage' => round(($cached['total_score'] / 1000) * 100, 1),
                'percentile' => (int)$cached['percentile'],
                'tier' => $this->scoreService->calculateTier($cached['total_score']),
                'breakdown' => [
                    'engagement' => [
                        'score' => $engagementScore,
                        'max' => 250,
                        'percentage' => round(($engagementScore / 250) * 100, 1),
                        'details' => []
                    ],
                    'quality' => [
                        'score' => $qualityScore,
                        'max' => 200,
                        'percentage' => round(($qualityScore / 200) * 100, 1),
                        'details' => []
                    ],
                    'volunteer' => [
                        'score' => $volunteerScore,
                        'max' => 200,
                        'percentage' => round(($volunteerScore / 200) * 100, 1),
                        'details' => []
                    ],
                    'activity' => [
                        'score' => $activityScore,
                        'max' => 150,
                        'percentage' => round(($activityScore / 150) * 100, 1),
                        'details' => []
                    ],
                    'badges' => [
                        'score' => $badgeScore,
                        'max' => 100,
                        'percentage' => round(($badgeScore / 100) * 100, 1),
                        'details' => []
                    ],
                    'impact' => [
                        'score' => $impactScore,
                        'max' => 100,
                        'percentage' => round(($impactScore / 100) * 100, 1),
                        'details' => []
                    ]
                ],
                'cached' => true,
                'cached_at' => $cached['calculated_at']
            ];
        } catch (\Exception $e) {
            // Cache table doesn't exist or error, return null to trigger fresh calculation
            return null;
        }
    }

    /**
     * Cache the calculated score
     */
    private function cacheScore(int $userId, int $tenantId, array $scoreData): void
    {
        try {
            // Check if cache table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'nexus_score_cache'")->fetch();
            if (!$tableCheck) {
                return; // Cache table doesn't exist, skip caching
            }

            $breakdown = $scoreData['breakdown'];

            $stmt = $this->db->prepare("
                INSERT INTO nexus_score_cache (
                    tenant_id, user_id, total_score,
                    engagement_score, quality_score, volunteer_score,
                    activity_score, badge_score, impact_score,
                    percentile, tier, calculated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    total_score = VALUES(total_score),
                    engagement_score = VALUES(engagement_score),
                    quality_score = VALUES(quality_score),
                    volunteer_score = VALUES(volunteer_score),
                    activity_score = VALUES(activity_score),
                    badge_score = VALUES(badge_score),
                    impact_score = VALUES(impact_score),
                    percentile = VALUES(percentile),
                    tier = VALUES(tier),
                    calculated_at = NOW()
            ");

            $stmt->execute([
                $tenantId,
                $userId,
                $scoreData['total_score'],
                $breakdown['engagement']['score'],
                $breakdown['quality']['score'],
                $breakdown['volunteer']['score'],
                $breakdown['activity']['score'],
                $breakdown['badges']['score'],
                $breakdown['impact']['score'],
                $scoreData['percentile'],
                $scoreData['tier']['name']
            ]);
        } catch (\Exception $e) {
            // Silently fail if cache table doesn't exist
            error_log("Score cache error: " . $e->getMessage());
        }
    }

    /**
     * Invalidate cache for a user (force recalculation on next request)
     */
    public function invalidateCache(int $userId, int $tenantId): void
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM nexus_score_cache
                WHERE user_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$userId, $tenantId]);
        } catch (\Exception $e) {
            // Silently fail if cache table doesn't exist
        }
    }

    /**
     * Get leaderboard from cache (much faster)
     * Falls back to live calculation if cache doesn't exist
     */
    public function getCachedLeaderboard(int $tenantId, int $limit = 10): array
    {
        try {
            // Check if cache table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'nexus_score_cache'")->fetch();
            if (!$tableCheck) {
                // Cache table doesn't exist - calculate live
                return $this->calculateLiveLeaderboard($tenantId, $limit);
            }

            // Get top users from cache
            $stmt = $this->db->prepare("
                SELECT
                    c.user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.avatar_url,
                    c.total_score as score,
                    c.tier
                FROM nexus_score_cache c
                JOIN users u ON c.user_id = u.id
                WHERE c.tenant_id = ?
                ORDER BY c.total_score DESC
                LIMIT ?
            ");
            $stmt->execute([$tenantId, $limit]);
            $topUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get community stats
            $statsStmt = $this->db->prepare("
                SELECT
                    AVG(total_score) as avg_score,
                    COUNT(*) as total_users
                FROM nexus_score_cache
                WHERE tenant_id = ?
            ");
            $statsStmt->execute([$tenantId]);
            $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'top_users' => $topUsers,
                'community_average' => round($stats['avg_score'] ?? 0, 1),
                'total_users' => (int)($stats['total_users'] ?? 0)
            ];
        } catch (\Exception $e) {
            return ['top_users' => [], 'community_average' => 0, 'total_users' => 0];
        }
    }

    /**
     * Calculate live leaderboard (fallback when cache doesn't exist)
     * Calculates scores for all approved users in tenant and returns top N
     */
    private function calculateLiveLeaderboard(int $tenantId, int $limit = 10): array
    {
        try {
            // Get all approved users for this tenant
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, avatar_url
                FROM users
                WHERE tenant_id = ? AND is_approved = 1
                ORDER BY id ASC
                LIMIT 100
            ");
            $stmt->execute([$tenantId]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($users)) {
                return [
                    'top_users' => [],
                    'community_average' => 0,
                    'total_users' => 0
                ];
            }

            // Calculate score for each user
            $userScores = [];
            $totalScore = 0;

            foreach ($users as $user) {
                try {
                    $scoreData = $this->scoreService->calculateNexusScore($user['id'], $tenantId);
                    $userScores[] = [
                        'user_id' => $user['id'],
                        'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                        'avatar_url' => $user['avatar_url'],
                        'score' => $scoreData['total_score'],
                        'tier' => $scoreData['tier']['name']
                    ];
                    $totalScore += $scoreData['total_score'];
                } catch (\Exception $e) {
                    // Skip users with calculation errors
                    continue;
                }
            }

            // Sort by score descending
            usort($userScores, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Get top N users
            $topUsers = array_slice($userScores, 0, $limit);

            // Calculate community average
            $totalUsers = count($userScores);
            $communityAverage = $totalUsers > 0 ? round($totalScore / $totalUsers, 1) : 0;

            return [
                'top_users' => $topUsers,
                'community_average' => $communityAverage,
                'total_users' => $totalUsers
            ];
        } catch (\Exception $e) {
            error_log("Live leaderboard calculation error: " . $e->getMessage());
            return [
                'top_users' => [],
                'community_average' => 0,
                'total_users' => 0
            ];
        }
    }

    /**
     * Get user rank from cache
     */
    public function getCachedRank(int $userId, int $tenantId): array
    {
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'nexus_score_cache'")->fetch();
            if (!$tableCheck) {
                // Calculate rank live if cache doesn't exist
                return $this->calculateLiveRank($userId, $tenantId);
            }

            // Get user's score
            $scoreStmt = $this->db->prepare("
                SELECT total_score
                FROM nexus_score_cache
                WHERE user_id = ? AND tenant_id = ?
            ");
            $scoreStmt->execute([$userId, $tenantId]);
            $userScore = $scoreStmt->fetchColumn();

            if ($userScore === false) {
                return ['rank' => 0, 'score' => 0, 'total_users' => 0];
            }

            // Count users with higher scores
            $rankStmt = $this->db->prepare("
                SELECT COUNT(*) + 1 as rank
                FROM nexus_score_cache
                WHERE tenant_id = ? AND total_score > ?
            ");
            $rankStmt->execute([$tenantId, $userScore]);
            $rank = $rankStmt->fetchColumn();

            // Get total users
            $totalStmt = $this->db->prepare("
                SELECT COUNT(*) FROM nexus_score_cache WHERE tenant_id = ?
            ");
            $totalStmt->execute([$tenantId]);
            $totalUsers = $totalStmt->fetchColumn();

            return [
                'rank' => (int)$rank,
                'score' => (float)$userScore,
                'total_users' => (int)$totalUsers
            ];
        } catch (\Exception $e) {
            return ['rank' => 0, 'score' => 0, 'total_users' => 0];
        }
    }

    /**
     * Calculate live rank (fallback when cache doesn't exist)
     */
    private function calculateLiveRank(int $userId, int $tenantId): array
    {
        try {
            // Calculate user's score
            $userScoreData = $this->scoreService->calculateNexusScore($userId, $tenantId);
            $userScore = $userScoreData['total_score'];

            // Get all approved users for this tenant (limit to reasonable number)
            $stmt = $this->db->prepare("
                SELECT id
                FROM users
                WHERE tenant_id = ? AND is_approved = 1
                LIMIT 100
            ");
            $stmt->execute([$tenantId]);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($users)) {
                return [
                    'rank' => 1,
                    'score' => $userScore,
                    'total_users' => 1
                ];
            }

            // Count how many users have higher scores
            $higherScoreCount = 0;
            foreach ($users as $user) {
                if ($user['id'] == $userId) continue;

                try {
                    $otherScoreData = $this->scoreService->calculateNexusScore($user['id'], $tenantId);
                    if ($otherScoreData['total_score'] > $userScore) {
                        $higherScoreCount++;
                    }
                } catch (\Exception $e) {
                    // Skip users with calculation errors
                    continue;
                }
            }

            return [
                'rank' => $higherScoreCount + 1,
                'score' => $userScore,
                'total_users' => count($users)
            ];
        } catch (\Exception $e) {
            error_log("Live rank calculation error: " . $e->getMessage());
            return ['rank' => 0, 'score' => 0, 'total_users' => 0];
        }
    }
}
