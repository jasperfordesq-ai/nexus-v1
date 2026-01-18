<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Listing;

/**
 * MatchingService - Facade for the Smart Matching Engine
 *
 * This service provides the public API for matching functionality.
 * It delegates to SmartMatchingEngine for the actual algorithm.
 *
 * Features:
 * - Distance-aware matching (Haversine formula)
 * - Skill/keyword matching
 * - Reciprocity detection (mutual exchange opportunities)
 * - Quality scoring (profile completeness, ratings)
 * - Configurable weights and thresholds
 */
class MatchingService
{
    /**
     * Find matches for a specific user based on their listings and preferences.
     *
     * @param int $userId User to find matches for
     * @param int $limit Maximum number of matches to return
     * @param array $options Additional options (max_distance, min_score, categories)
     * @return array Array of matches with scores, reasons, and distances
     */
    public static function getSuggestionsForUser($userId, $limit = 5, array $options = [])
    {
        $options['limit'] = $limit;

        try {
            // Use the new SmartMatchingEngine
            return SmartMatchingEngine::findMatchesForUser($userId, $options);
        } catch (\Exception $e) {
            // Fallback to legacy matching if engine fails
            error_log("SmartMatchingEngine error: " . $e->getMessage());
            return self::getLegacyMatches($userId, $limit);
        }
    }

    /**
     * Get "hot" matches - high score AND close proximity
     *
     * @param int $userId User ID
     * @param int $limit Maximum results
     * @return array Hot matches (score > 80%, distance < 15km)
     */
    public static function getHotMatches($userId, $limit = 5)
    {
        try {
            return SmartMatchingEngine::getHotMatches($userId, $limit);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get mutual matches - both parties can help each other
     *
     * @param int $userId User ID
     * @param int $limit Maximum results
     * @return array Mutual match opportunities
     */
    public static function getMutualMatches($userId, $limit = 10)
    {
        try {
            return SmartMatchingEngine::getMutualMatches($userId, $limit);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get matches organized by type
     *
     * @param int $userId User ID
     * @return array ['hot' => [...], 'good' => [...], 'all' => [...]]
     */
    public static function getMatchesByType($userId)
    {
        $all = self::getSuggestionsForUser($userId, 30);

        return [
            'hot' => array_filter($all, fn($m) => ($m['match_score'] ?? 0) >= 80 && ($m['distance_km'] ?? 999) <= 15),
            'good' => array_filter($all, fn($m) => ($m['match_score'] ?? 0) >= 60),
            'mutual' => array_filter($all, fn($m) => ($m['match_type'] ?? '') === 'mutual'),
            'all' => $all,
        ];
    }

    /**
     * Save user's match preferences
     *
     * @param int $userId User ID
     * @param array $preferences Preference data
     * @return bool Success
     */
    public static function savePreferences($userId, array $preferences)
    {
        $tenantId = TenantContext::getId();

        $maxDistance = (int)($preferences['max_distance_km'] ?? 25);
        $minScore = (int)($preferences['min_match_score'] ?? 50);
        $frequency = $preferences['notification_frequency'] ?? 'daily';
        $notifyHot = isset($preferences['notify_hot_matches']) ? 1 : 0;
        $notifyMutual = isset($preferences['notify_mutual_matches']) ? 1 : 0;
        $categories = !empty($preferences['categories']) ? json_encode($preferences['categories']) : null;

        try {
            Database::query(
                "INSERT INTO match_preferences
                 (user_id, tenant_id, max_distance_km, min_match_score, notification_frequency,
                  notify_hot_matches, notify_mutual_matches, categories)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 max_distance_km = VALUES(max_distance_km),
                 min_match_score = VALUES(min_match_score),
                 notification_frequency = VALUES(notification_frequency),
                 notify_hot_matches = VALUES(notify_hot_matches),
                 notify_mutual_matches = VALUES(notify_mutual_matches),
                 categories = VALUES(categories),
                 updated_at = NOW()",
                [$userId, $tenantId, $maxDistance, $minScore, $frequency, $notifyHot, $notifyMutual, $categories]
            );
            return true;
        } catch (\Exception $e) {
            error_log("Failed to save match preferences: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's match preferences
     *
     * @param int $userId User ID
     * @return array Preferences with defaults
     */
    public static function getPreferences($userId)
    {
        $tenantId = TenantContext::getId();

        $defaults = [
            'max_distance_km' => 25,
            'min_match_score' => 50,
            'notification_frequency' => 'daily',
            'notify_hot_matches' => true,
            'notify_mutual_matches' => true,
            'categories' => [],
        ];

        try {
            $prefs = Database::query(
                "SELECT * FROM match_preferences WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($prefs) {
                return [
                    'max_distance_km' => (int)$prefs['max_distance_km'],
                    'min_match_score' => (int)$prefs['min_match_score'],
                    'notification_frequency' => $prefs['notification_frequency'],
                    'notify_hot_matches' => (bool)$prefs['notify_hot_matches'],
                    'notify_mutual_matches' => (bool)$prefs['notify_mutual_matches'],
                    'categories' => $prefs['categories'] ? json_decode($prefs['categories'], true) : [],
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return $defaults;
    }

    /**
     * Record a match interaction (for learning/analytics)
     *
     * @param int $userId User ID
     * @param int $listingId Listing ID
     * @param string $action Action taken (viewed, contacted, saved, dismissed)
     * @param float|null $matchScore Score at time of action
     * @param float|null $distance Distance at time of action
     * @return bool Success
     */
    public static function recordInteraction($userId, $listingId, $action, $matchScore = null, $distance = null)
    {
        $tenantId = TenantContext::getId();

        try {
            // Update cache status
            Database::query(
                "UPDATE match_cache SET status = ? WHERE user_id = ? AND listing_id = ? AND tenant_id = ?",
                [$action, $userId, $listingId, $tenantId]
            );

            // Record in history
            Database::query(
                "INSERT INTO match_history (user_id, listing_id, tenant_id, match_score, distance_km, action)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $listingId, $tenantId, $matchScore, $distance, $action]
            );

            // Get listing category for ML learning
            $listing = Database::query(
                "SELECT category_id FROM listings WHERE id = ?",
                [$listingId]
            )->fetch();

            // Update ML learning data
            if ($listing) {
                MatchLearningService::recordInteraction($userId, $listingId, $action, [
                    'category_id' => $listing['category_id'],
                    'distance_km' => $distance,
                    'match_score' => $matchScore,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            // Tables might not exist
            return false;
        }
    }

    /**
     * Get match statistics for a user
     *
     * @param int $userId User ID
     * @return array Stats (total_matches, hot_matches, mutual_matches, avg_score)
     */
    public static function getStats($userId)
    {
        $matches = self::getMatchesByType($userId);

        return [
            'total_matches' => count($matches['all']),
            'hot_matches' => count($matches['hot']),
            'mutual_matches' => count($matches['mutual']),
            'avg_score' => $matches['all']
                ? round(array_sum(array_column($matches['all'], 'match_score')) / count($matches['all']), 1)
                : 0,
            'avg_distance' => $matches['all']
                ? round(array_sum(array_filter(array_column($matches['all'], 'distance_km'))) / max(1, count(array_filter(array_column($matches['all'], 'distance_km')))), 1)
                : null,
        ];
    }

    /**
     * Notify user of new matches (called by cron job)
     *
     * @param int $userId User ID
     * @return int Number of notifications sent
     */
    public static function notifyNewMatches($userId)
    {
        try {
            return SmartMatchingEngine::notifyNewMatches($userId);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mark a match as converted to a transaction
     *
     * @param int $userId User ID who completed the transaction
     * @param int $listingId Listing ID involved
     * @param int $transactionId Transaction ID created
     * @return bool Success
     */
    public static function markConversion($userId, $listingId, $transactionId)
    {
        $tenantId = TenantContext::getId();

        try {
            // Find the match history record
            $match = Database::query(
                "SELECT id FROM match_history
                 WHERE user_id = ? AND listing_id = ? AND tenant_id = ?
                 AND action IN ('contacted', 'saved', 'viewed')
                 ORDER BY created_at DESC LIMIT 1",
                [$userId, $listingId, $tenantId]
            )->fetch();

            if ($match) {
                // Update match history
                Database::query(
                    "UPDATE match_history
                     SET resulted_in_transaction = 1,
                         transaction_id = ?,
                         conversion_time = NOW(),
                         action = 'completed'
                     WHERE id = ?",
                    [$transactionId, $match['id']]
                );

                // Update transaction with match attribution
                Database::query(
                    "UPDATE transactions SET source_match_id = ? WHERE id = ?",
                    [$match['id'], $transactionId]
                );

                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log("Failed to mark conversion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get conversion rate for matches
     *
     * @return float Percentage of matches that converted to transactions
     */
    public static function getConversionRate()
    {
        $tenantId = TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT
                     COUNT(DISTINCT CASE WHEN resulted_in_transaction = 1 THEN id END) as conversions,
                     COUNT(DISTINCT id) as total
                 FROM match_history
                 WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            if ($result && $result['total'] > 0) {
                return round(($result['conversions'] / $result['total']) * 100, 2);
            }
        } catch (\Exception $e) {
            // Table might not have the column yet
        }

        return 0;
    }

    /**
     * Get conversion funnel metrics
     *
     * @return array Funnel data (matched -> viewed -> contacted -> completed)
     */
    public static function getConversionFunnel()
    {
        $tenantId = TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT
                     COUNT(DISTINCT user_id) as total_users,
                     COUNT(DISTINCT CASE WHEN action = 'viewed' THEN CONCAT(user_id, '-', listing_id) END) as viewed,
                     COUNT(DISTINCT CASE WHEN action = 'saved' THEN CONCAT(user_id, '-', listing_id) END) as saved,
                     COUNT(DISTINCT CASE WHEN action = 'contacted' THEN CONCAT(user_id, '-', listing_id) END) as contacted,
                     COUNT(DISTINCT CASE WHEN action = 'completed' OR resulted_in_transaction = 1 THEN CONCAT(user_id, '-', listing_id) END) as completed
                 FROM match_history
                 WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            // Get total matches from cache
            $matchedResult = Database::query(
                "SELECT COUNT(DISTINCT CONCAT(user_id, '-', listing_id)) as matched
                 FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            return [
                'matched' => (int)($matchedResult['matched'] ?? 0),
                'viewed' => (int)($result['viewed'] ?? 0),
                'saved' => (int)($result['saved'] ?? 0),
                'contacted' => (int)($result['contacted'] ?? 0),
                'completed' => (int)($result['completed'] ?? 0),
                'conversion_rate' => self::getConversionRate(),
            ];
        } catch (\Exception $e) {
            return [
                'matched' => 0,
                'viewed' => 0,
                'saved' => 0,
                'contacted' => 0,
                'completed' => 0,
                'conversion_rate' => 0,
            ];
        }
    }

    /**
     * Get average time from match to conversion
     *
     * @return float Average days to conversion
     */
    public static function getAverageTimeToConversion()
    {
        $tenantId = TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT AVG(DATEDIFF(conversion_time, created_at)) as avg_days
                 FROM match_history
                 WHERE tenant_id = ?
                 AND resulted_in_transaction = 1
                 AND conversion_time IS NOT NULL",
                [$tenantId]
            )->fetch();

            return round((float)($result['avg_days'] ?? 0), 1);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get top converting categories
     *
     * @param int $limit Number of categories to return
     * @return array Categories with conversion stats
     */
    public static function getTopConvertingCategories($limit = 10)
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT
                     c.id,
                     c.name,
                     c.color,
                     COUNT(DISTINCT mh.id) as total_matches,
                     COUNT(DISTINCT CASE WHEN mh.resulted_in_transaction = 1 THEN mh.id END) as conversions,
                     ROUND(AVG(mh.match_score), 1) as avg_score,
                     ROUND(
                         COUNT(DISTINCT CASE WHEN mh.resulted_in_transaction = 1 THEN mh.id END) * 100.0 /
                         NULLIF(COUNT(DISTINCT mh.id), 0),
                     1) as conversion_rate
                 FROM match_history mh
                 JOIN listings l ON mh.listing_id = l.id
                 JOIN categories c ON l.category_id = c.id
                 WHERE mh.tenant_id = ?
                 GROUP BY c.id, c.name, c.color
                 ORDER BY conversions DESC, total_matches DESC
                 LIMIT ?",
                [$tenantId, $limit]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Legacy matching fallback (category-only, no distance)
     * Used if SmartMatchingEngine fails
     */
    private static function getLegacyMatches($userId, $limit)
    {
        $tenantId = TenantContext::getId();

        $myListings = Database::query(
            "SELECT * FROM listings WHERE user_id = ? AND status = 'active' AND tenant_id = ? ORDER BY created_at DESC LIMIT 5",
            [$userId, $tenantId]
        )->fetchAll();

        if (empty($myListings)) {
            return [];
        }

        $matches = [];
        $seenIds = [];

        foreach ($myListings as $me) {
            $targetType = ($me['type'] === 'offer') ? 'request' : 'offer';

            $sql = "SELECT l.*, u.first_name, u.last_name, u.avatar_url, u.location as author_location,
                           c.name as category_name, c.color as category_color
                    FROM listings l
                    JOIN users u ON l.user_id = u.id
                    LEFT JOIN categories c ON l.category_id = c.id
                    WHERE l.tenant_id = ?
                    AND l.type = ?
                    AND l.status = 'active'
                    AND l.user_id != ?
                    AND l.category_id = ?
                    LIMIT 3";

            $results = Database::query($sql, [$tenantId, $targetType, $userId, $me['category_id']])->fetchAll();

            foreach ($results as $r) {
                if (in_array($r['id'], $seenIds)) continue;

                $r['match_score'] = 60; // Legacy fixed score
                $r['match_reasons'] = ["Matches your listing: '{$me['title']}'"];
                $r['match_type'] = 'legacy';
                $r['distance_km'] = null;
                $matches[] = $r;
                $seenIds[] = $r['id'];

                if (count($matches) >= $limit) break 2;
            }
        }

        return $matches;
    }
}
