<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchLearningService - Machine Learning Feedback Loop for Smart Matching
 *
 * This service learns from user behavior to improve match quality over time.
 * It tracks:
 * - Category affinities (which categories users engage with most)
 * - Distance preferences (actual vs stated distance tolerance)
 * - Interaction patterns (what leads to successful transactions)
 *
 * The learned data is used to boost future match scores.
 */
class MatchLearningService
{
    /**
     * Interaction weights for learning
     */
    private const INTERACTION_WEIGHTS = [
        'viewed' => 0.1,
        'saved' => 0.3,
        'contacted' => 0.5,
        'completed' => 1.0,
        'dismissed' => -0.5,
        'reported' => -1.0,
    ];

    /**
     * Distance buckets in km
     */
    private const DISTANCE_BUCKETS = [
        '0-2' => ['min' => 0, 'max' => 2, 'column' => 'interactions_0_2km'],
        '2-5' => ['min' => 2, 'max' => 5, 'column' => 'interactions_2_5km'],
        '5-15' => ['min' => 5, 'max' => 15, 'column' => 'interactions_5_15km'],
        '15-50' => ['min' => 15, 'max' => 50, 'column' => 'interactions_15_50km'],
        '50+' => ['min' => 50, 'max' => 9999, 'column' => 'interactions_50plus_km'],
    ];

    /**
     * Calculate a learned boost for a specific match based on user's history
     *
     * @param int $userId User to get boost for
     * @param array $candidateListing The listing being scored
     * @return float Boost value (-10 to +10)
     */
    public static function getHistoricalBoost($userId, $candidateListing): float
    {
        $boost = 0;

        // Get category affinity boost
        $categoryId = $candidateListing['category_id'] ?? null;
        if ($categoryId) {
            $boost += self::getCategoryBoost($userId, $categoryId);
        }

        // Get distance preference boost
        $distance = $candidateListing['distance_km'] ?? null;
        if ($distance !== null) {
            $boost += self::getDistanceBoost($userId, $distance);
        }

        // Clamp to reasonable range
        return max(-10, min(10, $boost));
    }

    /**
     * Get boost based on category affinity
     *
     * @param int $userId User ID
     * @param int $categoryId Category ID
     * @return float Boost value (-5 to +5)
     */
    private static function getCategoryBoost($userId, $categoryId): float
    {
        $tenantId = TenantContext::getId();

        try {
            $affinity = Database::query(
                "SELECT affinity_score FROM user_category_affinity
                 WHERE user_id = ? AND tenant_id = ? AND category_id = ?",
                [$userId, $tenantId, $categoryId]
            )->fetch();

            if ($affinity) {
                // Score is 0-100, baseline is 50
                // Convert to boost: 0 = -5, 50 = 0, 100 = +5
                return ($affinity['affinity_score'] - 50) / 10;
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        return 0;
    }

    /**
     * Get boost based on distance preference learning
     *
     * @param int $userId User ID
     * @param float $distance Distance in km
     * @return float Boost value (-3 to +3)
     */
    private static function getDistanceBoost($userId, $distance): float
    {
        $tenantId = TenantContext::getId();

        try {
            $pref = Database::query(
                "SELECT learned_max_distance_km, stated_max_distance_km
                 FROM user_distance_preference
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($pref && $pref['learned_max_distance_km']) {
                $learnedMax = (float)$pref['learned_max_distance_km'];

                // Boost closer matches more if user typically engages with closer ones
                if ($distance <= $learnedMax * 0.5) {
                    return 3; // Very close, strong boost
                } elseif ($distance <= $learnedMax) {
                    return 1; // Within learned preference
                } elseif ($distance <= $learnedMax * 1.5) {
                    return -1; // Slightly beyond preference
                } else {
                    return -3; // Far beyond preference
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        return 0;
    }

    /**
     * Record an interaction and update learning data
     *
     * @param int $userId User ID
     * @param int $listingId Listing ID
     * @param string $action Action type (viewed, saved, contacted, completed, dismissed)
     * @param array $metadata Additional data (match_score, distance_km, category_id)
     * @return bool Success
     */
    public static function recordInteraction($userId, $listingId, $action, array $metadata = []): bool
    {
        $tenantId = TenantContext::getId();
        $categoryId = $metadata['category_id'] ?? null;
        $distance = $metadata['distance_km'] ?? null;

        try {
            // Update category affinity
            if ($categoryId) {
                self::updateCategoryAffinity($userId, $categoryId, $action);
            }

            // Update distance preference
            if ($distance !== null) {
                self::updateDistancePreference($userId, $distance, $action);
            }

            return true;
        } catch (\Exception $e) {
            error_log("MatchLearningService error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update category affinity based on interaction
     *
     * @param int $userId User ID
     * @param int $categoryId Category ID
     * @param string $action Action type
     */
    private static function updateCategoryAffinity($userId, $categoryId, $action): void
    {
        $tenantId = TenantContext::getId();
        $weight = self::INTERACTION_WEIGHTS[$action] ?? 0;

        // Determine which count column to increment
        $countColumn = match ($action) {
            'viewed' => 'view_count',
            'saved' => 'save_count',
            'contacted' => 'contact_count',
            'completed' => 'transaction_count',
            'dismissed' => 'dismiss_count',
            default => null
        };

        if (!$countColumn) return;

        try {
            // Insert or update affinity record
            Database::query(
                "INSERT INTO user_category_affinity
                 (user_id, tenant_id, category_id, {$countColumn}, affinity_score)
                 VALUES (?, ?, ?, 1, 50 + ?)
                 ON DUPLICATE KEY UPDATE
                 {$countColumn} = {$countColumn} + 1,
                 affinity_score = LEAST(100, GREATEST(0, affinity_score + ?)),
                 last_interaction = NOW()",
                [$userId, $tenantId, $categoryId, $weight * 10, $weight * 2]
            );
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    /**
     * Update distance preference based on interaction
     *
     * @param int $userId User ID
     * @param float $distance Distance in km
     * @param string $action Action type
     */
    private static function updateDistancePreference($userId, $distance, $action): void
    {
        $tenantId = TenantContext::getId();

        // Determine which bucket
        $bucketColumn = null;
        foreach (self::DISTANCE_BUCKETS as $bucket) {
            if ($distance >= $bucket['min'] && $distance < $bucket['max']) {
                $bucketColumn = $bucket['column'];
                break;
            }
        }

        if (!$bucketColumn) return;

        // Only count positive interactions for distance learning
        if (!in_array($action, ['viewed', 'saved', 'contacted', 'completed'])) {
            return;
        }

        try {
            Database::query(
                "INSERT INTO user_distance_preference
                 (user_id, tenant_id, {$bucketColumn})
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                 {$bucketColumn} = {$bucketColumn} + 1,
                 last_updated = NOW()",
                [$userId, $tenantId]
            );

            // Recalculate learned distance after update
            self::recalculateLearnedDistance($userId);
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    /**
     * Recalculate learned max distance based on interaction history
     *
     * @param int $userId User ID
     */
    private static function recalculateLearnedDistance($userId): void
    {
        $tenantId = TenantContext::getId();

        try {
            $pref = Database::query(
                "SELECT * FROM user_distance_preference
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if (!$pref) return;

            // Calculate weighted average distance
            $totalInteractions = 0;
            $weightedDistance = 0;

            $bucketMidpoints = [
                'interactions_0_2km' => 1,      // midpoint: 1km
                'interactions_2_5km' => 3.5,    // midpoint: 3.5km
                'interactions_5_15km' => 10,    // midpoint: 10km
                'interactions_15_50km' => 32.5, // midpoint: 32.5km
                'interactions_50plus_km' => 75, // estimate: 75km
            ];

            foreach ($bucketMidpoints as $column => $midpoint) {
                $count = (int)($pref[$column] ?? 0);
                $totalInteractions += $count;
                $weightedDistance += $count * $midpoint;
            }

            if ($totalInteractions >= 5) { // Need minimum interactions to learn
                // Calculate weighted average and add buffer
                $avgDistance = $weightedDistance / $totalInteractions;
                $learnedMax = $avgDistance * 1.5; // Add 50% buffer

                Database::query(
                    "UPDATE user_distance_preference
                     SET learned_max_distance_km = ?
                     WHERE user_id = ? AND tenant_id = ?",
                    [$learnedMax, $userId, $tenantId]
                );
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
    }

    /**
     * Get user's learned category preferences (affinities)
     *
     * @param int $userId User ID
     * @return array Categories with affinity scores
     */
    public static function getCategoryAffinities($userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT uca.*, c.name as category_name, c.color as category_color
                 FROM user_category_affinity uca
                 JOIN categories c ON uca.category_id = c.id
                 WHERE uca.user_id = ? AND uca.tenant_id = ?
                 ORDER BY uca.affinity_score DESC",
                [$userId, $tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user's actual learned distance preference vs stated preference
     *
     * @param int $userId User ID
     * @return array Distance preference data
     */
    public static function getLearnedDistancePreference($userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $pref = Database::query(
                "SELECT * FROM user_distance_preference
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($pref) {
                return [
                    'stated_max' => (float)($pref['stated_max_distance_km'] ?? 25),
                    'learned_max' => (float)($pref['learned_max_distance_km'] ?? null),
                    'distribution' => [
                        '0-2km' => (int)$pref['interactions_0_2km'],
                        '2-5km' => (int)$pref['interactions_2_5km'],
                        '5-15km' => (int)$pref['interactions_5_15km'],
                        '15-50km' => (int)$pref['interactions_15_50km'],
                        '50+km' => (int)$pref['interactions_50plus_km'],
                    ],
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            'stated_max' => 25,
            'learned_max' => null,
            'distribution' => [],
        ];
    }

    /**
     * Get learning statistics for admin dashboard
     *
     * @return array Learning stats
     */
    public static function getLearningStats(): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Users with category affinities
            $categoryStats = Database::query(
                "SELECT COUNT(DISTINCT user_id) as users_with_affinities,
                        AVG(affinity_score) as avg_affinity
                 FROM user_category_affinity
                 WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            // Users with distance learning
            $distanceStats = Database::query(
                "SELECT COUNT(DISTINCT user_id) as users_with_distance_learning,
                        AVG(learned_max_distance_km) as avg_learned_distance
                 FROM user_distance_preference
                 WHERE tenant_id = ? AND learned_max_distance_km IS NOT NULL",
                [$tenantId]
            )->fetch();

            // Top categories by affinity
            $topCategories = Database::query(
                "SELECT c.name, c.color, AVG(uca.affinity_score) as avg_affinity,
                        SUM(uca.transaction_count) as conversions
                 FROM user_category_affinity uca
                 JOIN categories c ON uca.category_id = c.id
                 WHERE uca.tenant_id = ?
                 GROUP BY c.id, c.name, c.color
                 ORDER BY avg_affinity DESC
                 LIMIT 5",
                [$tenantId]
            )->fetchAll();

            return [
                'users_with_affinities' => (int)($categoryStats['users_with_affinities'] ?? 0),
                'avg_affinity_score' => round((float)($categoryStats['avg_affinity'] ?? 50), 1),
                'users_with_distance_learning' => (int)($distanceStats['users_with_distance_learning'] ?? 0),
                'avg_learned_distance' => round((float)($distanceStats['avg_learned_distance'] ?? 0), 1),
                'top_affinity_categories' => $topCategories,
            ];
        } catch (\Exception $e) {
            return [
                'users_with_affinities' => 0,
                'avg_affinity_score' => 50,
                'users_with_distance_learning' => 0,
                'avg_learned_distance' => 0,
                'top_affinity_categories' => [],
            ];
        }
    }

    /**
     * Reset learning data for a user (for testing or user request)
     *
     * @param int $userId User ID
     * @return bool Success
     */
    public static function resetUserLearning($userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM user_category_affinity WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
            Database::query(
                "DELETE FROM user_distance_preference WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
