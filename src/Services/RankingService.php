<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * RankingService - Unified Algorithm Framework
 *
 * Base class providing shared utilities for all ranking algorithms:
 * - EdgeRank (Feed) - Social engagement & freshness
 * - MatchRank (Listings) - Relevance & quality matching
 * - CommunityRank (Members) - Activity & contribution scoring
 *
 * All algorithms share:
 * - Configurable weights via tenant settings
 * - Geospatial calculations
 * - Freshness decay functions
 * - Activity scoring
 *
 * Configuration stored in tenants.configuration JSON:
 * {
 *   "algorithms": {
 *     "feed": { ... EdgeRank settings ... },
 *     "listings": { ... MatchRank settings ... },
 *     "members": { ... CommunityRank settings ... },
 *     "shared": { ... common settings ... }
 *   }
 * }
 */
class RankingService
{
    // =========================================================================
    // SHARED CONSTANTS
    // =========================================================================

    // Default score when calculations can't be performed
    const DEFAULT_SCORE = 1.0;

    // Earth's radius in kilometers (for Haversine)
    const EARTH_RADIUS_KM = 6371;

    // =========================================================================
    // SHARED CONFIGURATION
    // =========================================================================

    protected static ?array $sharedConfig = null;

    /**
     * Get shared algorithm configuration
     */
    public static function getSharedConfig(): array
    {
        if (self::$sharedConfig !== null) {
            return self::$sharedConfig;
        }

        $defaults = [
            // Geospatial defaults
            'geo_enabled' => true,
            'geo_full_radius_km' => 25,
            'geo_decay_per_km' => 0.005,    // 0.5% decay per km beyond threshold
            'geo_minimum_score' => 0.1,

            // Freshness decay defaults
            'freshness_enabled' => true,
            'freshness_full_hours' => 48,
            'freshness_half_life_hours' => 168, // 7 days
            'freshness_minimum' => 0.2,

            // Activity scoring defaults
            'activity_lookback_days' => 30,
            'activity_weight_login' => 1,
            'activity_weight_post' => 3,
            'activity_weight_comment' => 2,
            'activity_weight_transaction' => 5,

            // Quality scoring defaults
            'quality_enabled' => true,
            'quality_complete_profile_boost' => 1.3,
            'quality_verified_boost' => 1.5,
            'quality_has_image_boost' => 1.2,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['shared'])) {
                    self::$sharedConfig = array_merge($defaults, $configArr['algorithms']['shared']);
                    return self::$sharedConfig;
                }
            }
        } catch (\Exception $e) {
            // Silently fall back to defaults
        }

        self::$sharedConfig = $defaults;
        return self::$sharedConfig;
    }

    /**
     * Clear all cached configs (useful after saving settings)
     */
    public static function clearAllCaches(): void
    {
        self::$sharedConfig = null;
        ListingRankingService::clearCache();
        MemberRankingService::clearCache();
        FeedRankingService::clearCache();
    }

    /**
     * Get all algorithm configurations for admin display
     */
    public static function getAllConfigurations(): array
    {
        return [
            'shared' => self::getSharedConfig(),
            'feed' => FeedRankingService::isEnabled() ? 'EdgeRank (Active)' : 'Disabled',
            'listings' => ListingRankingService::getConfig(),
            'members' => MemberRankingService::getConfig(),
        ];
    }

    // =========================================================================
    // GEOSPATIAL UTILITIES (Shared)
    // =========================================================================

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1 First latitude
     * @param float $lon1 First longitude
     * @param float $lat2 Second latitude
     * @param float $lon2 Second longitude
     * @return float Distance in kilometers
     */
    public static function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Calculate geo decay score based on distance
     *
     * @param float|null $viewerLat Viewer's latitude
     * @param float|null $viewerLon Viewer's longitude
     * @param float|null $targetLat Target's latitude
     * @param float|null $targetLon Target's longitude
     * @return float Score from geo_minimum to 1.0
     */
    public static function calculateGeoScore(
        ?float $viewerLat,
        ?float $viewerLon,
        ?float $targetLat,
        ?float $targetLon
    ): float {
        $config = self::getSharedConfig();

        if (!$config['geo_enabled']) {
            return self::DEFAULT_SCORE;
        }

        // If coordinates unavailable, return default (no penalty)
        if ($viewerLat === null || $viewerLon === null ||
            $targetLat === null || $targetLon === null) {
            return self::DEFAULT_SCORE;
        }

        $distance = self::calculateDistance($viewerLat, $viewerLon, $targetLat, $targetLon);

        // Within full score radius = 100%
        if ($distance <= $config['geo_full_radius_km']) {
            return 1.0;
        }

        // Calculate linear decay beyond threshold
        $distanceBeyond = $distance - $config['geo_full_radius_km'];
        $decay = $distanceBeyond * $config['geo_decay_per_km'];

        return max($config['geo_minimum_score'], 1.0 - $decay);
    }

    /**
     * SQL snippet for geospatial distance calculation
     * Returns distance in km between viewer and target coordinates
     */
    public static function getDistanceSql(
        float $viewerLat,
        float $viewerLon,
        string $latColumn = 'latitude',
        string $lonColumn = 'longitude',
        string $tableAlias = ''
    ): string {
        $prefix = $tableAlias ? "{$tableAlias}." : '';

        return "
            (6371 * ACOS(
                LEAST(1.0, GREATEST(-1.0,
                    COS(RADIANS({$viewerLat})) * COS(RADIANS({$prefix}{$latColumn})) *
                    COS(RADIANS({$prefix}{$lonColumn}) - RADIANS({$viewerLon})) +
                    SIN(RADIANS({$viewerLat})) * SIN(RADIANS({$prefix}{$latColumn}))
                ))
            ))
        ";
    }

    /**
     * SQL snippet for geo decay score calculation
     */
    public static function getGeoScoreSql(
        ?float $viewerLat,
        ?float $viewerLon,
        string $latColumn = 'latitude',
        string $lonColumn = 'longitude',
        string $tableAlias = ''
    ): string {
        $config = self::getSharedConfig();

        if (!$config['geo_enabled'] || $viewerLat === null || $viewerLon === null) {
            return (string)self::DEFAULT_SCORE;
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';
        $fullRadius = $config['geo_full_radius_km'];
        $decayRate = $config['geo_decay_per_km'];
        $minScore = $config['geo_minimum_score'];

        $distanceSql = self::getDistanceSql($viewerLat, $viewerLon, $latColumn, $lonColumn, $tableAlias);

        return "
            CASE
                WHEN {$prefix}{$latColumn} IS NULL OR {$prefix}{$lonColumn} IS NULL THEN " . self::DEFAULT_SCORE . "
                WHEN {$distanceSql} <= {$fullRadius} THEN 1.0
                ELSE GREATEST(
                    {$minScore},
                    1.0 - (({$distanceSql} - {$fullRadius}) * {$decayRate})
                )
            END
        ";
    }

    // =========================================================================
    // FRESHNESS UTILITIES (Shared)
    // =========================================================================

    /**
     * Calculate freshness score based on age
     * Uses exponential decay with configurable half-life
     *
     * @param string $createdAt Creation timestamp
     * @return float Freshness score from minimum to 1.0
     */
    public static function calculateFreshnessScore(string $createdAt): float
    {
        $config = self::getSharedConfig();

        if (!$config['freshness_enabled']) {
            return self::DEFAULT_SCORE;
        }

        $hoursSince = self::getHoursSince($createdAt);

        // Within full freshness period = 100%
        if ($hoursSince <= $config['freshness_full_hours']) {
            return 1.0;
        }

        // Exponential decay: e^(-ln(2) * hours / half_life)
        $decayHours = $hoursSince - $config['freshness_full_hours'];
        $decayFactor = exp(-0.693 * $decayHours / $config['freshness_half_life_hours']);

        return max($config['freshness_minimum'], $decayFactor);
    }

    /**
     * SQL snippet for freshness decay calculation
     */
    public static function getFreshnessScoreSql(string $dateColumn = 'created_at', string $tableAlias = ''): string
    {
        $config = self::getSharedConfig();

        if (!$config['freshness_enabled']) {
            return (string)self::DEFAULT_SCORE;
        }

        $prefix = $tableAlias ? "{$tableAlias}." : '';
        $fullHours = (float)$config['freshness_full_hours'];
        $halfLife = (float)$config['freshness_half_life_hours'];
        $minimum = (float)$config['freshness_minimum'];

        return "
            CASE
                WHEN TIMESTAMPDIFF(HOUR, {$prefix}{$dateColumn}, NOW()) <= {$fullHours} THEN 1.0
                ELSE GREATEST(
                    {$minimum},
                    EXP(-0.693 * (TIMESTAMPDIFF(HOUR, {$prefix}{$dateColumn}, NOW()) - {$fullHours}) / {$halfLife})
                )
            END
        ";
    }

    // =========================================================================
    // ACTIVITY SCORING UTILITIES (Shared)
    // =========================================================================

    /**
     * Calculate activity score for a user based on recent actions
     *
     * @param int $userId User ID to score
     * @return float Activity score (0.0 to 1.0+)
     */
    public static function calculateActivityScore(int $userId): float
    {
        $config = self::getSharedConfig();
        $lookbackDays = $config['activity_lookback_days'];

        try {
            // Count different activity types
            $activities = Database::query(
                "SELECT
                    COALESCE(SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END), 0) as logins,
                    COALESCE(SUM(CASE WHEN action IN ('post_created', 'listing_created') THEN 1 ELSE 0 END), 0) as posts,
                    COALESCE(SUM(CASE WHEN action IN ('comment_added', 'reply_added') THEN 1 ELSE 0 END), 0) as comments,
                    COALESCE(SUM(CASE WHEN action IN ('transaction_completed', 'exchange_completed') THEN 1 ELSE 0 END), 0) as transactions
                 FROM activity_log
                 WHERE user_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$userId, $lookbackDays]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$activities) {
                return 0.0;
            }

            // Calculate weighted score
            $score =
                ($activities['logins'] * $config['activity_weight_login']) +
                ($activities['posts'] * $config['activity_weight_post']) +
                ($activities['comments'] * $config['activity_weight_comment']) +
                ($activities['transactions'] * $config['activity_weight_transaction']);

            // Normalize to 0-1 range (with 20 points being "fully active")
            return min(1.0, $score / 20);

        } catch (\Exception $e) {
            // Fallback: Check last_login_at
            try {
                $user = Database::query(
                    "SELECT last_login_at FROM users WHERE id = ?",
                    [$userId]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($user && $user['last_login_at']) {
                    $daysSince = self::getDaysSince($user['last_login_at']);
                    if ($daysSince <= 7) return 0.8;
                    if ($daysSince <= 30) return 0.5;
                    return 0.2;
                }
            } catch (\Exception $e2) {}

            return 0.3; // Default fallback
        }
    }

    // =========================================================================
    // QUALITY SCORING UTILITIES (Shared)
    // =========================================================================

    /**
     * Calculate profile completeness score
     *
     * @param array $user User data array
     * @return float Score from 0.5 to 1.0
     */
    public static function calculateProfileCompletenessScore(array $user): float
    {
        $score = 0.5; // Base score
        $maxBonus = 0.5;
        $fields = 0;
        $filledFields = 0;

        // Check key profile fields
        $checkFields = [
            'first_name', 'last_name', 'bio', 'location',
            'avatar_url', 'skills', 'interests'
        ];

        foreach ($checkFields as $field) {
            $fields++;
            if (!empty($user[$field])) {
                $filledFields++;
            }
        }

        if ($fields > 0) {
            $completionRate = $filledFields / $fields;
            $score += ($completionRate * $maxBonus);
        }

        return $score;
    }

    /**
     * Calculate user reputation score based on transactions and ratings
     *
     * @param int $userId User ID
     * @return float Reputation score (0.0 to 1.0)
     */
    public static function calculateReputationScore(int $userId): float
    {
        try {
            // Get transaction stats
            $stats = Database::query(
                "SELECT
                    COUNT(*) as total_transactions,
                    COALESCE(AVG(rating), 0) as avg_rating,
                    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_ratings
                 FROM transactions
                 WHERE (sender_id = ? OR receiver_id = ?)
                 AND status = 'completed'",
                [$userId, $userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$stats || $stats['total_transactions'] == 0) {
                return 0.5; // Neutral for new users
            }

            // Combine transaction count and rating
            // More transactions + higher rating = better score
            $transactionScore = min(1.0, $stats['total_transactions'] / 20); // Cap at 20 transactions
            $ratingScore = $stats['avg_rating'] / 5; // Normalize to 0-1

            // Weighted average: 40% transactions, 60% rating
            return (0.4 * $transactionScore) + (0.6 * $ratingScore);

        } catch (\Exception $e) {
            return 0.5;
        }
    }

    // =========================================================================
    // TIME UTILITIES
    // =========================================================================

    /**
     * Get hours since a given timestamp
     */
    public static function getHoursSince(string $dateString): float
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $date->getTimestamp();
            return max(0, $diff / 3600);
        } catch (\Exception $e) {
            return 9999;
        }
    }

    /**
     * Get days since a given timestamp
     */
    public static function getDaysSince(string $dateString): int
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->diff($date);
            return $diff->days;
        } catch (\Exception $e) {
            return 999;
        }
    }

    // =========================================================================
    // USER COORDINATE HELPERS
    // =========================================================================

    /**
     * Get coordinates for a user
     */
    public static function getUserCoordinates(int $userId): array
    {
        try {
            $result = Database::query(
                "SELECT latitude, longitude FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            return [
                'lat' => $result['latitude'] ?? null,
                'lon' => $result['longitude'] ?? null
            ];
        } catch (\Exception $e) {
            return ['lat' => null, 'lon' => null];
        }
    }

    /**
     * Get coordinates from session or user record
     */
    public static function getViewerCoordinates(?int $userId = null): array
    {
        // Check session first
        if (isset($_SESSION['user_latitude']) && isset($_SESSION['user_longitude'])) {
            return [
                'lat' => (float)$_SESSION['user_latitude'],
                'lon' => (float)$_SESSION['user_longitude']
            ];
        }

        // Fall back to user record
        if ($userId) {
            return self::getUserCoordinates($userId);
        }

        return ['lat' => null, 'lon' => null];
    }
}
