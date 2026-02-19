<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupRecommendationEngine
 *
 * ML-powered group discovery system using multiple recommendation algorithms:
 * 1. Collaborative Filtering - "Users like you joined these groups"
 * 2. Content-Based Matching - Match interests to group descriptions
 * 3. Location-Based - Groups near user's location
 * 4. Activity-Based - Groups in categories user engages with
 *
 * Algorithms are weighted and fused to produce personalized recommendations.
 */
class GroupRecommendationEngine
{
    /**
     * Algorithm weights (sum = 1.0)
     */
    const WEIGHT_COLLABORATIVE = 0.40;  // 40% - strongest signal
    const WEIGHT_CONTENT = 0.25;        // 25% - interests match
    const WEIGHT_LOCATION = 0.20;       // 20% - geographic relevance
    const WEIGHT_ACTIVITY = 0.15;       // 15% - behavioral patterns

    /**
     * Get personalized group recommendations for a user
     *
     * @param int $userId
     * @param int $limit Number of recommendations to return
     * @param array $options Optional filters (exclude_joined, type_id, etc.)
     * @return array Recommended groups with scores
     */
    public static function getRecommendations($userId, $limit = 10, $options = [])
    {
        $tenantId = TenantContext::getId();

        // Get user's current group memberships
        $userGroups = self::getUserGroups($userId);

        // If user has no groups, fall back to popularity-based recommendations
        if (empty($userGroups)) {
            return self::getPopularGroups($tenantId, $limit, $options);
        }

        // Run all recommendation algorithms in parallel
        $collaborativeScores = self::collaborativeFiltering($userId, $userGroups, $tenantId);
        $contentScores = self::contentBasedMatching($userId, $tenantId);
        $locationScores = self::locationBasedRecommendations($userId, $tenantId);
        $activityScores = self::activityBasedRecommendations($userId, $tenantId);

        // Fuse scores with weighted combination
        $fusedScores = self::fuseScores([
            'collaborative' => $collaborativeScores,
            'content' => $contentScores,
            'location' => $locationScores,
            'activity' => $activityScores,
        ], [
            'collaborative' => self::WEIGHT_COLLABORATIVE,
            'content' => self::WEIGHT_CONTENT,
            'location' => self::WEIGHT_LOCATION,
            'activity' => self::WEIGHT_ACTIVITY,
        ]);

        // Exclude groups user already joined
        $excludeIds = array_column($userGroups, 'group_id');
        if (!empty($options['exclude_ids'])) {
            $excludeIds = array_merge($excludeIds, $options['exclude_ids']);
        }

        // Filter and rank
        $recommendations = self::rankAndFilter($fusedScores, $excludeIds, $tenantId, $limit, $options);

        // Hydrate with group details
        return self::hydrateGroupDetails($recommendations, $tenantId);
    }

    /**
     * Collaborative Filtering: Find groups that similar users joined
     *
     * Algorithm: Jaccard similarity on group membership sets
     */
    private static function collaborativeFiltering($userId, $userGroups, $tenantId)
    {
        $userGroupIds = array_column($userGroups, 'group_id');

        if (empty($userGroupIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userGroupIds), '?'));
        $params = array_merge([$tenantId, $userId], $userGroupIds, [$userId]);

        // Find users with overlapping group memberships (similar users)
        $similarUsers = Database::query("
            SELECT
                gm.user_id,
                COUNT(*) as overlap_count,
                (COUNT(*) * 1.0 / (
                    SELECT COUNT(DISTINCT group_id)
                    FROM group_members
                    WHERE user_id IN (?, gm.user_id) AND status = 'active'
                )) as jaccard_similarity
            FROM group_members gm
            WHERE gm.group_id IN ($placeholders)
            AND gm.user_id != ?
            AND gm.status = 'active'
            AND gm.group_id IN (
                SELECT g.id FROM `groups` g WHERE g.tenant_id = ?
            )
            GROUP BY gm.user_id
            HAVING overlap_count >= 2
            ORDER BY jaccard_similarity DESC, overlap_count DESC
            LIMIT 100
        ", array_merge([$userId, $tenantId], $userGroupIds, [$userId, $tenantId]))->fetchAll();

        if (empty($similarUsers)) {
            return [];
        }

        // Get groups these similar users joined (but current user hasn't)
        $similarUserIds = array_column($similarUsers, 'user_id');
        $similarityMap = [];
        foreach ($similarUsers as $user) {
            $similarityMap[$user['user_id']] = $user['jaccard_similarity'];
        }

        $userPlaceholders = implode(',', array_fill(0, count($similarUserIds), '?'));
        $params = array_merge($similarUserIds, $userGroupIds);

        $candidateGroups = Database::query("
            SELECT
                gm.group_id,
                gm.user_id,
                COUNT(*) as recommendation_count
            FROM group_members gm
            WHERE gm.user_id IN ($userPlaceholders)
            AND gm.group_id NOT IN ($placeholders)
            AND gm.status = 'active'
            GROUP BY gm.group_id, gm.user_id
        ", $params)->fetchAll();

        // Calculate weighted scores
        $scores = [];
        foreach ($candidateGroups as $candidate) {
            $groupId = $candidate['group_id'];
            $recommenderId = $candidate['user_id'];
            $similarity = $similarityMap[$recommenderId] ?? 0;

            if (!isset($scores[$groupId])) {
                $scores[$groupId] = 0;
            }

            // Weight by similarity of recommending user
            $scores[$groupId] += $similarity;
        }

        // Normalize scores to 0-1 range
        if (!empty($scores)) {
            $maxScore = max($scores);
            if ($maxScore > 0) {
                foreach ($scores as $groupId => $score) {
                    $scores[$groupId] = $score / $maxScore;
                }
            }
        }

        return $scores;
    }

    /**
     * Content-Based Matching: Match user interests to group metadata
     *
     * Uses TF-IDF-like scoring on user bio + group descriptions
     */
    private static function contentBasedMatching($userId, $tenantId)
    {
        // Get user profile data
        $user = Database::query(
            "SELECT bio, location, interests FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user || empty($user['bio'])) {
            return [];
        }

        // Extract keywords from user bio (simple word frequency)
        $userKeywords = self::extractKeywords($user['bio'] . ' ' . ($user['interests'] ?? ''));

        if (empty($userKeywords)) {
            return [];
        }

        // Get all public groups
        $groups = Database::query(
            "SELECT id, name, description
             FROM `groups`
             WHERE tenant_id = ?
             AND (visibility IS NULL OR visibility = 'public')
             AND description IS NOT NULL",
            [$tenantId]
        )->fetchAll();

        $scores = [];

        foreach ($groups as $group) {
            $groupText = $group['name'] . ' ' . $group['description'];
            $groupKeywords = self::extractKeywords($groupText);

            // Calculate keyword overlap (Jaccard similarity on keyword sets)
            $intersection = count(array_intersect($userKeywords, $groupKeywords));
            $union = count(array_unique(array_merge($userKeywords, $groupKeywords)));

            if ($union > 0) {
                $scores[$group['id']] = $intersection / $union;
            }
        }

        return $scores;
    }

    /**
     * Location-Based Recommendations: Groups near user's location
     */
    private static function locationBasedRecommendations($userId, $tenantId)
    {
        // Get user location
        $user = Database::query(
            "SELECT latitude, longitude FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user || empty($user['latitude']) || empty($user['longitude'])) {
            return [];
        }

        $userLat = $user['latitude'];
        $userLon = $user['longitude'];

        // Haversine distance formula to find nearby groups
        $groups = Database::query("
            SELECT
                id,
                latitude,
                longitude,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            FROM `groups`
            WHERE tenant_id = ?
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL
            AND (visibility IS NULL OR visibility = 'public')
            HAVING distance_km <= 50
            ORDER BY distance_km ASC
            LIMIT 50
        ", [$userLat, $userLon, $userLat, $tenantId])->fetchAll();

        $scores = [];

        foreach ($groups as $group) {
            $distance = $group['distance_km'];

            // Score inversely proportional to distance (closer = higher score)
            // Max score at 0km, min score at 50km
            if ($distance <= 50) {
                $scores[$group['id']] = 1 - ($distance / 50);
            }
        }

        return $scores;
    }

    /**
     * Activity-Based Recommendations: Groups in categories user engages with
     */
    private static function activityBasedRecommendations($userId, $tenantId)
    {
        // Get user's activity patterns (listings, posts in specific categories)
        $userActivity = Database::query("
            SELECT
                c.id as category_id,
                c.name as category_name,
                COUNT(*) as activity_count
            FROM listings l
            JOIN categories c ON l.category_id = c.id
            WHERE l.user_id = ?
            AND l.tenant_id = ?
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY c.id, c.name
            ORDER BY activity_count DESC
            LIMIT 5
        ", [$userId, $tenantId])->fetchAll();

        if (empty($userActivity)) {
            return [];
        }

        // Map activity to group types (if you have a category→type mapping)
        // For now, use keyword matching between category names and group descriptions
        $scores = [];
        $activityKeywords = [];

        foreach ($userActivity as $activity) {
            $keywords = self::extractKeywords($activity['category_name']);
            $activityKeywords = array_merge($activityKeywords, $keywords);
        }

        $activityKeywords = array_unique($activityKeywords);

        // Find groups with matching keywords
        $groups = Database::query(
            "SELECT id, name, description
             FROM `groups`
             WHERE tenant_id = ?
             AND (visibility IS NULL OR visibility = 'public')",
            [$tenantId]
        )->fetchAll();

        foreach ($groups as $group) {
            $groupText = $group['name'] . ' ' . $group['description'];
            $groupKeywords = self::extractKeywords($groupText);

            $overlap = count(array_intersect($activityKeywords, $groupKeywords));

            if ($overlap > 0) {
                $scores[$group['id']] = min(1.0, $overlap / count($activityKeywords));
            }
        }

        return $scores;
    }

    /**
     * Fuse multiple scoring algorithms with weighted combination
     */
    private static function fuseScores($scoreArrays, $weights)
    {
        $fusedScores = [];

        foreach ($scoreArrays as $algorithm => $scores) {
            $weight = $weights[$algorithm] ?? 0;

            foreach ($scores as $groupId => $score) {
                if (!isset($fusedScores[$groupId])) {
                    $fusedScores[$groupId] = 0;
                }

                $fusedScores[$groupId] += ($score * $weight);
            }
        }

        return $fusedScores;
    }

    /**
     * Rank and filter recommendations
     */
    private static function rankAndFilter($scores, $excludeIds, $tenantId, $limit, $options)
    {
        // Remove excluded groups
        foreach ($excludeIds as $excludeId) {
            unset($scores[$excludeId]);
        }

        // Sort by score descending
        arsort($scores);

        // Apply type filter if specified
        if (!empty($options['type_id'])) {
            $validGroupIds = Database::query(
                "SELECT id FROM `groups` WHERE tenant_id = ? AND type_id = ?",
                [$tenantId, $options['type_id']]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $scores = array_intersect_key($scores, array_flip($validGroupIds));
        }

        // Limit results
        return array_slice($scores, 0, $limit, true);
    }

    /**
     * Hydrate group IDs with full group details
     */
    private static function hydrateGroupDetails($scores, $tenantId)
    {
        if (empty($scores)) {
            return [];
        }

        $groupIds = array_keys($scores);
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

        $params = array_merge([$tenantId], $groupIds);

        $groups = Database::query("
            SELECT
                g.*,
                gt.name as type_name,
                COUNT(DISTINCT gm.id) as member_count
            FROM `groups` g
            LEFT JOIN group_types gt ON g.type_id = gt.id
            LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
            WHERE g.tenant_id = ?
            AND g.id IN ($placeholders)
            GROUP BY g.id
        ", $params)->fetchAll();

        // Merge scores with group details
        $result = [];
        foreach ($groups as $group) {
            $group['recommendation_score'] = $scores[$group['id']] ?? 0;
            $group['recommendation_reason'] = self::generateReason($group, $scores[$group['id']]);
            $result[] = $group;
        }

        // Sort by score
        usort($result, function($a, $b) {
            return $b['recommendation_score'] <=> $a['recommendation_score'];
        });

        return $result;
    }

    /**
     * Get popular groups (fallback for new users)
     */
    private static function getPopularGroups($tenantId, $limit, $options)
    {
        $sql = "
            SELECT
                g.*,
                gt.name as type_name,
                COUNT(DISTINCT gm.id) as member_count
            FROM `groups` g
            LEFT JOIN group_types gt ON g.type_id = gt.id
            LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
            WHERE g.tenant_id = ?
            AND (g.visibility IS NULL OR g.visibility = 'public')
        ";

        $params = [$tenantId];

        if (!empty($options['type_id'])) {
            $sql .= " AND g.type_id = ?";
            $params[] = $options['type_id'];
        }

        $sql .= "
            GROUP BY g.id
            ORDER BY member_count DESC, g.is_featured DESC
            LIMIT ?
        ";

        $params[] = $limit;

        $groups = Database::query($sql, $params)->fetchAll();

        foreach ($groups as &$group) {
            $group['recommendation_score'] = 0.5; // Neutral score
            $group['recommendation_reason'] = 'Popular in your community';
        }

        return $groups;
    }

    /**
     * Get user's current group memberships
     */
    private static function getUserGroups($userId)
    {
        return Database::query(
            "SELECT group_id FROM group_members WHERE user_id = ? AND status = 'active'",
            [$userId]
        )->fetchAll();
    }

    /**
     * Extract keywords from text (simple NLP)
     */
    private static function extractKeywords($text)
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation
        $text = preg_replace('/[^\w\s]/', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text);

        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'them', 'their', 'what', 'which', 'who', 'when', 'where', 'why', 'how'];

        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords);
        });

        return array_unique(array_values($keywords));
    }

    /**
     * Generate human-readable recommendation reason
     */
    private static function generateReason($group, $score)
    {
        if ($score >= 0.8) {
            return 'Highly recommended based on your interests';
        } elseif ($score >= 0.6) {
            return 'Members like you also joined this group';
        } elseif ($score >= 0.4) {
            return 'Popular in your area';
        } else {
            return 'Might interest you';
        }
    }

    /**
     * Track user interaction with recommendations (for learning)
     *
     * @param int $userId
     * @param int $groupId
     * @param string $action 'viewed', 'clicked', 'joined', 'dismissed'
     */
    public static function trackInteraction($userId, $groupId, $action)
    {
        try {
            Database::query(
                "INSERT INTO group_recommendation_interactions
                 (user_id, group_id, action, created_at)
                 VALUES (?, ?, ?, NOW())",
                [$userId, $groupId, $action]
            );
        } catch (\Exception $e) {
            // Table may not exist yet - silent fail
        }
    }

    /**
     * Get recommendation performance metrics (for admin analytics)
     */
    public static function getPerformanceMetrics($days = 30)
    {
        try {
            return Database::query("
                SELECT
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM group_recommendation_interactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action
            ", [$days])->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }
}
