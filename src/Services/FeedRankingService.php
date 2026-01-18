<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FeedRankingService - Modified EdgeRank Algorithm
 *
 * Calculates a rank_score for feed posts based on three weighted factors:
 * 1. Engagement Weight: (Likes * 1) + (Comments * 5)
 * 2. Creator Vitality: Multiplier (0.0-1.0) based on poster's recent activity
 * 3. Geospatial Linear Decay: Distance-based score reduction
 *
 * Final Score = Engagement * Vitality * GeoDecay
 *
 * Configuration is loaded from tenant settings (admin/feed-algorithm).
 */
class FeedRankingService
{
    // =========================================================================
    // DEFAULT CONFIGURATION (Fallback if tenant config not set)
    // =========================================================================

    // Engagement weights
    const LIKE_WEIGHT = 1;
    const COMMENT_WEIGHT = 5;
    const SHARE_WEIGHT = 8;                 // Shares are high-intent engagement

    // Creator Vitality thresholds (days since last activity)
    const VITALITY_FULL_THRESHOLD = 7;      // Active within 7 days = 1.0
    const VITALITY_DECAY_THRESHOLD = 30;    // Beyond 30 days = 0.5 (minimum)
    const VITALITY_MINIMUM = 0.5;

    // Geospatial decay parameters (in kilometers)
    const GEO_FULL_SCORE_RADIUS = 10;       // < 10km = 100% score
    const GEO_DECAY_PER_INTERVAL = 0.10;    // 10% reduction per interval
    const GEO_DECAY_INTERVAL = 10;          // Every 10km beyond threshold
    const GEO_MINIMUM_SCORE = 0.1;          // Minimum 10% (never fully zero)

    // Content Freshness Decay parameters (in hours)
    const FRESHNESS_FULL_HOURS = 24;        // Posts < 24 hours = 100% freshness
    const FRESHNESS_HALF_LIFE_HOURS = 72;   // Half-life: 72 hours (3 days)
    const FRESHNESS_MINIMUM = 0.3;          // Minimum 30% (old posts still show)

    // Social Graph parameters
    const SOCIAL_GRAPH_ENABLED = true;      // Enable social graph boosting
    const SOCIAL_GRAPH_MAX_BOOST = 2.0;     // Max 2x boost for close connections
    const SOCIAL_GRAPH_INTERACTION_DAYS = 90; // Look back 90 days for interactions
    const SOCIAL_GRAPH_FOLLOWER_BOOST = 1.5; // Boost for users you follow

    // Negative Signals parameters
    const NEGATIVE_SIGNALS_ENABLED = true;  // Enable negative signal downranking
    const HIDE_PENALTY = 0.0;               // Hidden posts = 0 (completely hidden)
    const MUTE_PENALTY = 0.1;               // Muted users = 10% visibility
    const BLOCK_PENALTY = 0.0;              // Blocked users = 0 (completely hidden)
    const REPORT_PENALTY_PER = 0.15;        // 15% reduction per report

    // Content Quality parameters
    const QUALITY_ENABLED = true;           // Enable content quality scoring
    const QUALITY_IMAGE_BOOST = 1.3;        // 30% boost for posts with images
    const QUALITY_LINK_BOOST = 1.1;         // 10% boost for posts with links
    const QUALITY_LENGTH_MIN = 50;          // Minimum chars for length bonus
    const QUALITY_LENGTH_BONUS = 1.2;       // 20% boost for substantial posts
    const QUALITY_VIDEO_BOOST = 1.4;        // 40% boost for video URLs
    const QUALITY_HASHTAG_BOOST = 1.1;      // 10% boost for posts with hashtags
    const QUALITY_MENTION_BOOST = 1.15;     // 15% boost for @mentions

    // Content Diversity parameters
    const DIVERSITY_ENABLED = true;         // Enable content diversity
    const DIVERSITY_MAX_CONSECUTIVE = 2;    // Max posts from same user in sequence
    const DIVERSITY_PENALTY = 0.5;          // 50% penalty for exceeding limit
    const DIVERSITY_TYPE_ENABLED = true;    // Enable content-type diversity
    const DIVERSITY_TYPE_MAX_CONSECUTIVE = 3; // Max same content-type in sequence

    // Default score when calculations can't be performed
    const DEFAULT_SCORE = 1.0;

    // Cached configuration
    private static ?array $config = null;

    /**
     * Get configuration from tenant settings, with fallback to constants
     */
    private static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Default values from constants
        $defaults = [
            'enabled' => true,
            'like_weight' => self::LIKE_WEIGHT,
            'comment_weight' => self::COMMENT_WEIGHT,
            'share_weight' => self::SHARE_WEIGHT,
            'vitality_full_days' => self::VITALITY_FULL_THRESHOLD,
            'vitality_decay_days' => self::VITALITY_DECAY_THRESHOLD,
            'vitality_minimum' => self::VITALITY_MINIMUM,
            'geo_full_radius' => self::GEO_FULL_SCORE_RADIUS,
            'geo_decay_interval' => self::GEO_DECAY_INTERVAL,
            'geo_decay_rate' => self::GEO_DECAY_PER_INTERVAL,
            'geo_minimum' => self::GEO_MINIMUM_SCORE,
            // Content Freshness Decay
            'freshness_enabled' => true,
            'freshness_full_hours' => self::FRESHNESS_FULL_HOURS,
            'freshness_half_life' => self::FRESHNESS_HALF_LIFE_HOURS,
            'freshness_minimum' => self::FRESHNESS_MINIMUM,
            // Social Graph
            'social_graph_enabled' => self::SOCIAL_GRAPH_ENABLED,
            'social_graph_max_boost' => self::SOCIAL_GRAPH_MAX_BOOST,
            'social_graph_lookback_days' => self::SOCIAL_GRAPH_INTERACTION_DAYS,
            'social_graph_follower_boost' => self::SOCIAL_GRAPH_FOLLOWER_BOOST,
            // Negative Signals
            'negative_signals_enabled' => self::NEGATIVE_SIGNALS_ENABLED,
            'hide_penalty' => self::HIDE_PENALTY,
            'mute_penalty' => self::MUTE_PENALTY,
            'block_penalty' => self::BLOCK_PENALTY,
            'report_penalty_per' => self::REPORT_PENALTY_PER,
            // Content Quality
            'quality_enabled' => self::QUALITY_ENABLED,
            'quality_image_boost' => self::QUALITY_IMAGE_BOOST,
            'quality_link_boost' => self::QUALITY_LINK_BOOST,
            'quality_length_min' => self::QUALITY_LENGTH_MIN,
            'quality_length_bonus' => self::QUALITY_LENGTH_BONUS,
            'quality_video_boost' => self::QUALITY_VIDEO_BOOST,
            'quality_hashtag_boost' => self::QUALITY_HASHTAG_BOOST,
            'quality_mention_boost' => self::QUALITY_MENTION_BOOST,
            // Content Diversity
            'diversity_enabled' => self::DIVERSITY_ENABLED,
            'diversity_max_consecutive' => self::DIVERSITY_MAX_CONSECUTIVE,
            'diversity_penalty' => self::DIVERSITY_PENALTY,
            'diversity_type_enabled' => self::DIVERSITY_TYPE_ENABLED,
            'diversity_type_max_consecutive' => self::DIVERSITY_TYPE_MAX_CONSECUTIVE,
        ];

        try {
            // Try to load tenant configuration
            $tenantId = TenantContext::getId();
            $configJson = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['feed_algorithm'])) {
                    self::$config = array_merge($defaults, $configArr['feed_algorithm']);
                    return self::$config;
                }
            }
        } catch (\Exception $e) {
            // Silently fall back to defaults
        }

        self::$config = $defaults;
        return self::$config;
    }

    /**
     * Check if the algorithm is enabled
     */
    public static function isEnabled(): bool
    {
        $config = self::getConfig();
        return !empty($config['enabled']);
    }

    /**
     * Clear cached config (useful after saving new settings)
     */
    public static function clearCache(): void
    {
        self::$config = null;
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Build a ranked feed SQL query that calculates Total_Score
     * Returns the SQL and parameters to be used with existing WHERE clauses
     *
     * @param int $viewerId The user viewing the feed
     * @param float|null $viewerLat Viewer's latitude (null if unknown)
     * @param float|null $viewerLon Viewer's longitude (null if unknown)
     * @param array $existingWhereConditions Additional WHERE conditions as strings
     * @param array $existingParams Parameters for existing WHERE conditions
     * @return array ['sql' => string, 'params' => array]
     */
    public static function buildRankedFeedQuery(
        int $viewerId,
        ?float $viewerLat = null,
        ?float $viewerLon = null,
        array $existingWhereConditions = [],
        array $existingParams = []
    ): array {
        $tenantId = TenantContext::getId();
        $config = self::getConfig();

        // Build the score calculation SQL components
        $engagementSql = self::getEngagementScoreSql();
        $vitalitySql = self::getVitalityScoreSql();
        $geoDecaySql = self::getGeoDecayScoreSql($viewerLat, $viewerLon);
        $freshnessSql = self::getFreshnessScoreSql();
        $socialGraphSql = self::getSocialGraphScoreSql($viewerId);
        $negativeSignalsSql = self::getNegativeSignalsScoreSql($viewerId);
        $qualitySql = self::getContentQualityScoreSql();

        // Combine into Total_Score calculation
        // Formula: Engagement × Vitality × GeoDecay × Freshness × SocialGraph × NegativeSignals × Quality
        $totalScoreSql = "({$engagementSql}) * ({$vitalitySql}) * ({$geoDecaySql}) * ({$freshnessSql}) * ({$socialGraphSql}) * ({$negativeSignalsSql}) * ({$qualitySql})";

        // Check if feed_posts has group_id column (for backwards compatibility with older databases)
        $hasGroupIdColumn = false;
        try {
            $columnCheck = Database::query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
            $hasGroupIdColumn = !empty($columnCheck);
        } catch (\Exception $e) {
            $hasGroupIdColumn = false;
        }

        // Build group context SQL based on column availability
        $groupSelectCols = $hasGroupIdColumn
            ? "g.id as group_id, g.name as group_name, g.image_url as group_image, g.location as group_location,"
            : "NULL as group_id, NULL as group_name, NULL as group_image, NULL as group_location,";
        $groupJoin = $hasGroupIdColumn ? "LEFT JOIN `groups` g ON p.group_id = g.id" : "";

        // Build the complete query
        $sql = "
            SELECT
                p.*,
                u.name as author_name,
                u.avatar_url as author_avatar,
                u.latitude as author_lat,
                u.longitude as author_lon,
                u.location as author_location,

                -- Group context (for posts made in groups)
                {$groupSelectCols}

                -- Engagement Score Component
                ({$engagementSql}) as engagement_score,

                -- Creator Vitality Component
                ({$vitalitySql}) as vitality_score,

                -- Geospatial Decay Component
                ({$geoDecaySql}) as geo_score,

                -- Content Freshness Component
                ({$freshnessSql}) as freshness_score,

                -- Social Graph Component
                ({$socialGraphSql}) as social_score,

                -- Negative Signals Component
                ({$negativeSignalsSql}) as negative_signals_score,

                -- Content Quality Component
                ({$qualitySql}) as quality_score,

                -- Final Calculated Score
                ({$totalScoreSql}) as rank_score,

                -- Supporting data
                (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
                (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count

            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            {$groupJoin}
            WHERE p.tenant_id = ?
        ";

        $params = [$viewerId, $tenantId];

        // Add any existing WHERE conditions
        if (!empty($existingWhereConditions)) {
            foreach ($existingWhereConditions as $condition) {
                $sql .= " AND ({$condition})";
            }
            $params = array_merge($params, $existingParams);
        }

        // Order by rank_score descending
        $sql .= " ORDER BY rank_score DESC, p.created_at DESC";

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * Calculate rank score for a single post (useful for real-time updates)
     *
     * @param array $post Post data with user_id, likes_count, comments_count
     * @param int $viewerId The user viewing the feed
     * @param float|null $viewerLat Viewer's latitude
     * @param float|null $viewerLon Viewer's longitude
     * @return float Calculated rank score
     */
    public static function calculatePostScore(
        array $post,
        int $viewerId,
        ?float $viewerLat = null,
        ?float $viewerLon = null
    ): float {
        // Get engagement score
        $likesCount = (int)($post['likes_count'] ?? 0);
        $commentsCount = (int)($post['comments_count'] ?? 0);
        $engagementScore = self::calculateEngagementScore($likesCount, $commentsCount);

        // Get vitality score
        $posterId = (int)($post['user_id'] ?? 0);
        $vitalityScore = self::calculateVitalityScore($posterId);

        // Get geo decay score
        $posterLat = isset($post['author_lat']) ? (float)$post['author_lat'] : null;
        $posterLon = isset($post['author_lon']) ? (float)$post['author_lon'] : null;
        $geoScore = self::calculateGeoDecayScore($viewerLat, $viewerLon, $posterLat, $posterLon);

        // Calculate final score
        return $engagementScore * $vitalityScore * $geoScore;
    }

    /**
     * Get a simplified SQL snippet for ranking that can be added to existing queries
     * Use this when you need to add ranking to an existing complex query
     *
     * @param float|null $viewerLat
     * @param float|null $viewerLon
     * @return string SQL snippet for ORDER BY clause
     */
    public static function getRankingOrderBySql(?float $viewerLat = null, ?float $viewerLon = null): string
    {
        $engagement = self::getEngagementScoreSql();
        $vitality = self::getVitalityScoreSql();
        $geoDecay = self::getGeoDecayScoreSql($viewerLat, $viewerLon);

        return "({$engagement}) * ({$vitality}) * ({$geoDecay}) DESC, p.created_at DESC";
    }

    // =========================================================================
    // ENGAGEMENT WEIGHT CALCULATION
    // =========================================================================

    /**
     * Calculate engagement score using configurable weights
     * Returns minimum of 1.0 to avoid zero scores
     */
    public static function calculateEngagementScore(int $likes, int $comments): float
    {
        $config = self::getConfig();
        $likeWeight = $config['like_weight'];
        $commentWeight = $config['comment_weight'];

        $score = ($likes * $likeWeight) + ($comments * $commentWeight);
        return max(1.0, $score); // Minimum score of 1.0
    }

    /**
     * SQL snippet for engagement score calculation
     * Uses only guaranteed tables (likes, comments) - shares calculated separately if table exists
     */
    private static function getEngagementScoreSql(): string
    {
        $config = self::getConfig();
        $likeWeight = $config['like_weight'];
        $commentWeight = $config['comment_weight'];

        // Safe engagement calculation - only uses guaranteed tables
        return "
            GREATEST(1.0,
                (COALESCE((SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id), 0) * {$likeWeight}) +
                (COALESCE((SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id), 0) * {$commentWeight})
            )
        ";
    }

    // =========================================================================
    // CREATOR VITALITY CALCULATION
    // =========================================================================

    /**
     * Calculate vitality multiplier based on poster's last activity
     * Uses configurable thresholds from tenant settings
     */
    public static function calculateVitalityScore(int $userId): float
    {
        $config = self::getConfig();
        $lastActivity = self::getLastActivityDate($userId);

        if (!$lastActivity) {
            return $config['vitality_minimum'];
        }

        $daysSinceActivity = self::getDaysSinceDate($lastActivity);

        return self::computeVitalityFromDays($daysSinceActivity);
    }

    /**
     * Compute vitality score from days since last activity
     */
    public static function computeVitalityFromDays(int $days): float
    {
        $config = self::getConfig();
        $fullThreshold = $config['vitality_full_days'];
        $decayThreshold = $config['vitality_decay_days'];
        $minimum = $config['vitality_minimum'];

        // Active within threshold = full score
        if ($days <= $fullThreshold) {
            return 1.0;
        }

        // Beyond decay threshold = minimum
        if ($days >= $decayThreshold) {
            return $minimum;
        }

        // Linear decay between thresholds
        $decayRange = $decayThreshold - $fullThreshold;
        $daysIntoDecay = $days - $fullThreshold;
        $decayPercent = $daysIntoDecay / $decayRange;

        $scoreRange = 1.0 - $minimum;
        return 1.0 - ($decayPercent * $scoreRange);
    }

    /**
     * Get the last activity date for a user
     * Checks activity_log table first, falls back to created_at
     */
    private static function getLastActivityDate(int $userId): ?string
    {
        try {
            // First try activity_log table (if login events are logged)
            $sql = "SELECT MAX(created_at) as last_activity
                    FROM activity_log
                    WHERE user_id = ? AND action IN ('login', 'post_created', 'comment_added', 'like_added')";
            $result = Database::query($sql, [$userId])->fetch();

            if ($result && $result['last_activity']) {
                return $result['last_activity'];
            }

            // Fallback: Check for recent posts
            $sql = "SELECT MAX(created_at) as last_activity FROM feed_posts WHERE user_id = ?";
            $result = Database::query($sql, [$userId])->fetch();

            if ($result && $result['last_activity']) {
                return $result['last_activity'];
            }

            // Ultimate fallback: user registration date
            $sql = "SELECT created_at FROM users WHERE id = ?";
            $result = Database::query($sql, [$userId])->fetch();

            return $result['created_at'] ?? null;

        } catch (\Exception $e) {
            error_log("FeedRankingService::getLastActivityDate error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * SQL snippet for vitality score calculation
     * Uses COALESCE with multiple fallback sources for last activity
     */
    private static function getVitalityScoreSql(): string
    {
        $config = self::getConfig();
        $fullThreshold = $config['vitality_full_days'];
        $decayThreshold = $config['vitality_decay_days'];
        $minimum = $config['vitality_minimum'];
        $decayRange = $decayThreshold - $fullThreshold;
        $scoreRange = 1.0 - $minimum;

        // Calculate days since last activity using multiple sources
        return "
            CASE
                -- Get days since last activity
                WHEN DATEDIFF(NOW(), COALESCE(
                    (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND action IN ('login', 'post_created')),
                    (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id),
                    u.created_at
                )) <= {$fullThreshold} THEN 1.0

                WHEN DATEDIFF(NOW(), COALESCE(
                    (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND action IN ('login', 'post_created')),
                    (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id),
                    u.created_at
                )) >= {$decayThreshold} THEN {$minimum}

                ELSE 1.0 - (
                    (DATEDIFF(NOW(), COALESCE(
                        (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND action IN ('login', 'post_created')),
                        (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id),
                        u.created_at
                    )) - {$fullThreshold}) / {$decayRange} * {$scoreRange}
                )
            END
        ";
    }

    // =========================================================================
    // GEOSPATIAL LINEAR DECAY CALCULATION
    // =========================================================================

    /**
     * Calculate geo decay score based on distance
     * Uses configurable radius and decay parameters
     */
    public static function calculateGeoDecayScore(
        ?float $viewerLat,
        ?float $viewerLon,
        ?float $posterLat,
        ?float $posterLon
    ): float {
        // If coordinates unavailable, return default (no penalty)
        if ($viewerLat === null || $viewerLon === null ||
            $posterLat === null || $posterLon === null) {
            return self::DEFAULT_SCORE;
        }

        // Calculate distance using Haversine formula
        $distanceKm = self::calculateHaversineDistance($viewerLat, $viewerLon, $posterLat, $posterLon);

        return self::computeGeoDecayFromDistance($distanceKm);
    }

    /**
     * Compute geo decay score from distance in km
     */
    public static function computeGeoDecayFromDistance(float $distanceKm): float
    {
        $config = self::getConfig();
        $fullRadius = $config['geo_full_radius'];
        $decayInterval = $config['geo_decay_interval'];
        $decayRate = $config['geo_decay_rate'];
        $minScore = $config['geo_minimum'];

        // Within full score radius = 100%
        if ($distanceKm <= $fullRadius) {
            return 1.0;
        }

        // Calculate decay
        $distanceBeyondThreshold = $distanceKm - $fullRadius;
        $decayIntervals = floor($distanceBeyondThreshold / $decayInterval);
        $totalDecay = $decayIntervals * $decayRate;

        $score = 1.0 - $totalDecay;

        return max($minScore, $score);
    }

    /**
     * Haversine formula for calculating distance between two coordinates
     *
     * @return float Distance in kilometers
     */
    public static function calculateHaversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadiusKm = 6371;

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * SQL snippet for geospatial decay calculation
     * Uses Haversine formula in SQL
     */
    private static function getGeoDecayScoreSql(?float $viewerLat, ?float $viewerLon): string
    {
        $config = self::getConfig();

        // If viewer location unknown, return default score (no geo penalty)
        if ($viewerLat === null || $viewerLon === null) {
            return (string)self::DEFAULT_SCORE;
        }

        $fullRadius = $config['geo_full_radius'];
        $decayInterval = $config['geo_decay_interval'];
        $decayRate = $config['geo_decay_rate'];
        $minScore = $config['geo_minimum'];

        // Haversine distance calculation in SQL
        $distanceSql = "
            (6371 * ACOS(
                LEAST(1.0, GREATEST(-1.0,
                    COS(RADIANS({$viewerLat})) * COS(RADIANS(u.latitude)) *
                    COS(RADIANS(u.longitude) - RADIANS({$viewerLon})) +
                    SIN(RADIANS({$viewerLat})) * SIN(RADIANS(u.latitude))
                ))
            ))
        ";

        return "
            CASE
                -- No coordinates available = default score (no penalty)
                WHEN u.latitude IS NULL OR u.longitude IS NULL THEN " . self::DEFAULT_SCORE . "

                -- Within full score radius = 100%
                WHEN {$distanceSql} <= {$fullRadius} THEN 1.0

                -- Apply linear decay
                ELSE GREATEST(
                    {$minScore},
                    1.0 - (FLOOR(({$distanceSql} - {$fullRadius}) / {$decayInterval}) * {$decayRate})
                )
            END
        ";
    }

    // =========================================================================
    // CONTENT FRESHNESS DECAY CALCULATION
    // =========================================================================

    /**
     * Calculate freshness score based on post age
     * Uses exponential decay with configurable half-life
     *
     * @param string $postCreatedAt Post creation timestamp
     * @return float Freshness score (0.3 to 1.0)
     */
    public static function calculateFreshnessScore(string $postCreatedAt): float
    {
        $config = self::getConfig();

        if (empty($config['freshness_enabled'])) {
            return self::DEFAULT_SCORE;
        }

        $fullHours = $config['freshness_full_hours'];
        $halfLife = $config['freshness_half_life'];
        $minimum = $config['freshness_minimum'];

        $hoursSincePost = self::getHoursSinceDate($postCreatedAt);

        // Posts within full_hours get 100%
        if ($hoursSincePost <= $fullHours) {
            return 1.0;
        }

        // Exponential decay after full_hours
        // Score = e^(-ln(2) * (hours - full_hours) / half_life)
        $decayHours = $hoursSincePost - $fullHours;
        $decayFactor = exp(-0.693 * $decayHours / $halfLife); // 0.693 = ln(2)

        return max($minimum, $decayFactor);
    }

    /**
     * SQL snippet for content freshness decay calculation
     */
    private static function getFreshnessScoreSql(): string
    {
        $config = self::getConfig();

        if (empty($config['freshness_enabled'])) {
            return (string)self::DEFAULT_SCORE;
        }

        $fullHours = (float)$config['freshness_full_hours'];
        $halfLife = (float)$config['freshness_half_life'];
        $minimum = (float)$config['freshness_minimum'];

        // Calculate hours since post creation
        // Using TIMESTAMPDIFF for MySQL
        return "
            CASE
                -- Posts within full freshness period = 100%
                WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) <= {$fullHours} THEN 1.0

                -- Exponential decay using approximation: e^(-0.693 * x / half_life)
                -- We use: GREATEST(minimum, EXP(-0.693 * (hours - full_hours) / half_life))
                ELSE GREATEST(
                    {$minimum},
                    EXP(-0.693 * (TIMESTAMPDIFF(HOUR, p.created_at, NOW()) - {$fullHours}) / {$halfLife})
                )
            END
        ";
    }

    // =========================================================================
    // SOCIAL GRAPH CALCULATION
    // =========================================================================

    /**
     * Calculate social graph score based on viewer's interaction history with poster
     * Boosts content from users the viewer frequently interacts with
     *
     * @param int $viewerId The viewing user
     * @param int $posterId The post author
     * @return float Social graph multiplier (1.0 to max_boost)
     */
    public static function calculateSocialGraphScore(int $viewerId, int $posterId): float
    {
        $config = self::getConfig();

        if (empty($config['social_graph_enabled']) || $viewerId === 0) {
            return self::DEFAULT_SCORE;
        }

        $maxBoost = $config['social_graph_max_boost'];
        $lookbackDays = $config['social_graph_lookback_days'];

        try {
            // Count interactions: likes given to poster's content + comments on poster's content
            $sql = "
                SELECT
                    (
                        -- Likes given to this poster's content
                        SELECT COUNT(*) FROM likes l
                        JOIN feed_posts p ON l.target_type = 'post' AND l.target_id = p.id
                        WHERE l.user_id = ? AND p.user_id = ?
                        AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) +
                    (
                        -- Comments on this poster's content
                        SELECT COUNT(*) FROM comments c
                        JOIN feed_posts p ON c.target_type = 'post' AND c.target_id = p.id
                        WHERE c.user_id = ? AND p.user_id = ?
                        AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) +
                    (
                        -- Direct messages or follows would go here if available
                        0
                    ) as interaction_count
            ";

            $result = Database::query($sql, [
                $viewerId, $posterId, $lookbackDays,
                $viewerId, $posterId, $lookbackDays
            ])->fetch();

            $interactions = (int)($result['interaction_count'] ?? 0);

            if ($interactions === 0) {
                return self::DEFAULT_SCORE;
            }

            // Logarithmic scale: score = 1 + log2(interactions + 1) * boost_factor
            // Capped at max_boost
            // 1 interaction = ~1.3x, 3 interactions = ~1.6x, 7 interactions = ~1.9x, 15+ = max
            $boostFactor = ($maxBoost - 1) / 4; // Spread the boost over ~4 log steps
            $score = 1.0 + (log($interactions + 1, 2) * $boostFactor);

            return min($maxBoost, $score);

        } catch (\Exception $e) {
            error_log("FeedRankingService::calculateSocialGraphScore error: " . $e->getMessage());
            return self::DEFAULT_SCORE;
        }
    }

    /**
     * SQL snippet for social graph calculation
     * Note: This is simplified for SQL - uses subquery to count interactions
     * Uses only guaranteed tables (likes, comments) - follower boost disabled until user_follows exists
     */
    private static function getSocialGraphScoreSql(int $viewerId): string
    {
        $config = self::getConfig();

        if (empty($config['social_graph_enabled']) || $viewerId === 0) {
            return (string)self::DEFAULT_SCORE;
        }

        $maxBoost = (float)$config['social_graph_max_boost'];
        $lookbackDays = (int)$config['social_graph_lookback_days'];
        $boostFactor = ($maxBoost - 1) / 4;

        // Safe SQL - only uses guaranteed tables (likes, comments)
        // Follower boost disabled until user_follows table is implemented
        return "
            CASE
                WHEN {$viewerId} = 0 THEN 1.0
                ELSE
                    LEAST(
                        {$maxBoost},
                        1.0 + (
                            LOG2(1 + COALESCE((
                                SELECT COUNT(*) FROM likes l2
                                WHERE l2.user_id = {$viewerId}
                                AND l2.target_type = 'post'
                                AND l2.target_id IN (SELECT id FROM feed_posts WHERE user_id = p.user_id)
                                AND l2.created_at >= DATE_SUB(NOW(), INTERVAL {$lookbackDays} DAY)
                            ), 0) + COALESCE((
                                SELECT COUNT(*) FROM comments c2
                                WHERE c2.user_id = {$viewerId}
                                AND c2.target_type = 'post'
                                AND c2.target_id IN (SELECT id FROM feed_posts WHERE user_id = p.user_id)
                                AND c2.created_at >= DATE_SUB(NOW(), INTERVAL {$lookbackDays} DAY)
                            ), 0)) * {$boostFactor}
                        )
                    )
            END
        ";
    }

    // =========================================================================
    // NEGATIVE SIGNALS CALCULATION
    // =========================================================================

    /**
     * Calculate negative signals score based on user actions
     * Considers: hidden posts, muted users, reports
     *
     * @param int $viewerId The viewing user
     * @return float Multiplier (0.0 to 1.0, lower = more penalized)
     */
    public static function calculateNegativeSignalsScore(int $viewerId, int $postId, int $posterId): float
    {
        $config = self::getConfig();

        if (empty($config['negative_signals_enabled']) || $viewerId === 0) {
            return self::DEFAULT_SCORE;
        }

        try {
            // Check if viewer has hidden this specific post
            $hiddenPost = Database::query(
                "SELECT 1 FROM user_hidden_posts WHERE user_id = ? AND post_id = ? LIMIT 1",
                [$viewerId, $postId]
            )->fetch();

            if ($hiddenPost) {
                return $config['hide_penalty']; // Usually 0 = completely hidden
            }

            // Check if viewer has muted this user
            $mutedUser = Database::query(
                "SELECT 1 FROM user_muted_users WHERE user_id = ? AND muted_user_id = ? LIMIT 1",
                [$viewerId, $posterId]
            )->fetch();

            if ($mutedUser) {
                return $config['mute_penalty']; // Usually 0.1 = barely visible
            }

            // Check report count for the post
            $reportCount = Database::query(
                "SELECT COUNT(*) as cnt FROM reports WHERE target_type = 'post' AND target_id = ?",
                [$postId]
            )->fetchColumn();

            if ($reportCount > 0) {
                $penalty = $reportCount * $config['report_penalty_per'];
                return max(0.1, 1.0 - $penalty); // Don't go below 10%
            }

            return self::DEFAULT_SCORE;

        } catch (\Exception $e) {
            // Tables might not exist yet - silently return default
            return self::DEFAULT_SCORE;
        }
    }

    /**
     * SQL snippet for negative signals calculation
     *
     * Returns 1.0 (no penalty) as default - the tables need to exist for this to work.
     * The actual filtering happens in PHP via calculateNegativeSignalsScore() for safety.
     */
    private static function getNegativeSignalsScoreSql(int $viewerId): string
    {
        // Return safe default - negative signals filtering is done in PHP
        // to avoid SQL errors when tables don't exist
        return (string)self::DEFAULT_SCORE;
    }

    // =========================================================================
    // CONTENT QUALITY CALCULATION
    // =========================================================================

    /**
     * Calculate content quality score based on post attributes
     * Considers: has image, has links, content length
     *
     * @param array $post Post data with content, image_url
     * @return float Multiplier (1.0 to ~1.5)
     */
    public static function calculateContentQualityScore(array $post): float
    {
        $config = self::getConfig();

        if (empty($config['quality_enabled'])) {
            return self::DEFAULT_SCORE;
        }

        $score = 1.0;
        $content = $post['content'] ?? '';
        $imageUrl = $post['image_url'] ?? null;

        // Boost for posts with images
        if (!empty($imageUrl)) {
            $score *= $config['quality_image_boost'];
        }

        // Boost for posts with links
        if (preg_match('/https?:\/\/[^\s]+/', $content)) {
            $score *= $config['quality_link_boost'];
        }

        // Boost for substantial content length
        $contentLength = mb_strlen(strip_tags($content));
        if ($contentLength >= $config['quality_length_min']) {
            $score *= $config['quality_length_bonus'];
        }

        return $score;
    }

    /**
     * SQL snippet for content quality calculation
     * Uses only safe operations that work across all MySQL versions
     */
    private static function getContentQualityScoreSql(): string
    {
        $config = self::getConfig();

        if (empty($config['quality_enabled'])) {
            return (string)self::DEFAULT_SCORE;
        }

        $imageBoost = (float)$config['quality_image_boost'];
        $linkBoost = (float)$config['quality_link_boost'];
        $lengthMin = (int)$config['quality_length_min'];
        $lengthBonus = (float)$config['quality_length_bonus'];
        $videoBoost = (float)($config['quality_video_boost'] ?? 1.4);
        $hashtagBoost = (float)($config['quality_hashtag_boost'] ?? 1.1);
        $mentionBoost = (float)($config['quality_mention_boost'] ?? 1.15);

        // SQL to calculate quality multiplier using safe LIKE patterns (no REGEXP)
        // Video URLs: youtube.com, youtu.be, vimeo.com, tiktok.com
        return "
            (
                -- Base score
                1.0
                -- Image boost (use COALESCE for safety if column might not exist)
                * CASE WHEN COALESCE(p.image_url, '') != '' THEN {$imageBoost} ELSE 1.0 END
                -- Video URL boost (YouTube, Vimeo, TikTok)
                * CASE
                    WHEN p.content LIKE '%youtube.com%'
                      OR p.content LIKE '%youtu.be%'
                      OR p.content LIKE '%vimeo.com%'
                      OR p.content LIKE '%tiktok.com%'
                      OR p.content LIKE '%dailymotion.com%'
                    THEN {$videoBoost}
                    ELSE 1.0
                END
                -- Link boost (check for http in content, but not already counted as video)
                * CASE
                    WHEN (p.content LIKE '%http://%' OR p.content LIKE '%https://%')
                      AND p.content NOT LIKE '%youtube.com%'
                      AND p.content NOT LIKE '%youtu.be%'
                      AND p.content NOT LIKE '%vimeo.com%'
                      AND p.content NOT LIKE '%tiktok.com%'
                    THEN {$linkBoost}
                    ELSE 1.0
                END
                -- Hashtag boost (use LIKE pattern instead of REGEXP for compatibility)
                * CASE WHEN p.content LIKE '%#%' THEN {$hashtagBoost} ELSE 1.0 END
                -- Mention boost (use LIKE pattern instead of REGEXP for compatibility)
                * CASE WHEN p.content LIKE '%@%' THEN {$mentionBoost} ELSE 1.0 END
                -- Length boost
                * CASE WHEN CHAR_LENGTH(COALESCE(p.content, '')) >= {$lengthMin} THEN {$lengthBonus} ELSE 1.0 END
            )
        ";
    }

    // =========================================================================
    // CONTENT DIVERSITY (Post-Query Processing)
    // =========================================================================

    /**
     * Apply content diversity to a list of feed items
     * Prevents too many consecutive posts from the same user
     *
     * This is applied AFTER the main query to reorder/penalize items
     *
     * @param array $feedItems Array of feed items with user_id
     * @return array Reordered feed items with diversity applied
     */
    public static function applyContentDiversity(array $feedItems): array
    {
        $config = self::getConfig();

        if (empty($config['diversity_enabled']) || empty($feedItems)) {
            return $feedItems;
        }

        $maxConsecutive = $config['diversity_max_consecutive'];
        $penalty = $config['diversity_penalty'];

        $result = [];
        $userConsecutiveCounts = [];
        $deferred = [];

        foreach ($feedItems as $item) {
            $userId = $item['user_id'] ?? 0;

            // Count consecutive posts from this user in result
            $consecutiveCount = 0;
            for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxConsecutive; $i--) {
                if (($result[$i]['user_id'] ?? 0) === $userId) {
                    $consecutiveCount++;
                } else {
                    break;
                }
            }

            // If we've hit the limit, defer this item
            if ($consecutiveCount >= $maxConsecutive) {
                $item['_diversity_deferred'] = true;
                $item['rank_score'] = ($item['rank_score'] ?? 1) * $penalty;
                $deferred[] = $item;
            } else {
                $result[] = $item;
            }
        }

        // Interleave deferred items back into the feed
        foreach ($deferred as $deferredItem) {
            // Find the best position to insert (after some non-same-user posts)
            $inserted = false;
            for ($i = 0; $i < count($result); $i++) {
                $canInsert = true;
                // Check if inserting here would create consecutive issues
                for ($j = max(0, $i - $maxConsecutive + 1); $j < min(count($result), $i + $maxConsecutive); $j++) {
                    if (($result[$j]['user_id'] ?? 0) === ($deferredItem['user_id'] ?? 0)) {
                        $canInsert = false;
                        break;
                    }
                }
                if ($canInsert && ($deferredItem['rank_score'] ?? 0) >= ($result[$i]['rank_score'] ?? 0) * $penalty) {
                    array_splice($result, $i, 0, [$deferredItem]);
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                $result[] = $deferredItem; // Add at end if no good position
            }
        }

        return $result;
    }

    /**
     * Get diversity configuration for client-side use
     */
    public static function getDiversityConfig(): array
    {
        $config = self::getConfig();
        return [
            'enabled' => !empty($config['diversity_enabled']),
            'max_consecutive' => $config['diversity_max_consecutive'] ?? 2,
            'penalty' => $config['diversity_penalty'] ?? 0.5,
            'type_enabled' => !empty($config['diversity_type_enabled']),
            'type_max_consecutive' => $config['diversity_type_max_consecutive'] ?? 3,
        ];
    }

    /**
     * Apply content-TYPE diversity to a list of feed items
     * Prevents too many consecutive items of the same content type (post/event/listing)
     *
     * This is applied AFTER user diversity to ensure type mixing
     *
     * @param array $feedItems Array of feed items with 'type' or 'content_type' key
     * @return array Reordered feed items with type diversity applied
     */
    public static function applyContentTypeDiversity(array $feedItems): array
    {
        $config = self::getConfig();

        if (empty($config['diversity_type_enabled']) || empty($feedItems)) {
            return $feedItems;
        }

        $maxConsecutive = $config['diversity_type_max_consecutive'] ?? 3;

        $result = [];
        $deferred = [];

        foreach ($feedItems as $item) {
            // Determine content type - could be 'type', 'content_type', or inferred
            $contentType = $item['type'] ?? $item['content_type'] ?? 'post';

            // Count consecutive items of same type in result
            $consecutiveCount = 0;
            for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxConsecutive; $i--) {
                $prevType = $result[$i]['type'] ?? $result[$i]['content_type'] ?? 'post';
                if ($prevType === $contentType) {
                    $consecutiveCount++;
                } else {
                    break;
                }
            }

            // If we've hit the limit, defer this item
            if ($consecutiveCount >= $maxConsecutive) {
                $item['_type_diversity_deferred'] = true;
                $deferred[] = $item;
            } else {
                $result[] = $item;
            }
        }

        // Interleave deferred items back into the feed
        foreach ($deferred as $deferredItem) {
            $deferredType = $deferredItem['type'] ?? $deferredItem['content_type'] ?? 'post';
            $inserted = false;

            for ($i = 0; $i < count($result); $i++) {
                $canInsert = true;
                // Check if inserting here would create consecutive issues
                for ($j = max(0, $i - $maxConsecutive + 1); $j < min(count($result), $i + $maxConsecutive); $j++) {
                    $checkType = $result[$j]['type'] ?? $result[$j]['content_type'] ?? 'post';
                    if ($checkType === $deferredType) {
                        $canInsert = false;
                        break;
                    }
                }
                if ($canInsert) {
                    array_splice($result, $i, 0, [$deferredItem]);
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                $result[] = $deferredItem; // Add at end if no good position
            }
        }

        return $result;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Calculate days between a date and now
     */
    private static function getDaysSinceDate(string $dateString): int
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->diff($date);
            return $diff->days;
        } catch (\Exception $e) {
            return 999; // Return high number to indicate very old/invalid
        }
    }

    /**
     * Get viewer's coordinates from session or database
     */
    public static function getViewerCoordinates(int $viewerId): array
    {
        try {
            $sql = "SELECT latitude, longitude FROM users WHERE id = ?";
            $result = Database::query($sql, [$viewerId])->fetch();

            return [
                'lat' => $result['latitude'] ?? null,
                'lon' => $result['longitude'] ?? null
            ];
        } catch (\Exception $e) {
            return ['lat' => null, 'lon' => null];
        }
    }

    // =========================================================================
    // RECOMMENDATION CONTEXT BADGES
    // =========================================================================

    /**
     * Get recommendation context flags for a feed item
     * Returns badges/flags to explain WHY a post is shown
     *
     * @param array $item Feed item with user_id, created_at, vitality_score, etc.
     * @param int $viewerId The viewing user's ID
     * @return array Context flags: is_new_member, is_inactive_creator, is_recent_post
     */
    public static function getRecommendationContext(array $item, int $viewerId): array
    {
        $context = [
            'is_new_member' => false,      // User account < 14 days old
            'is_inactive_creator' => false, // Poster has low vitality (inactive user)
            'is_recent_post' => false,      // Post created within last 24 hours
            'badges' => []                  // Human-readable badge labels
        ];

        $creatorId = (int)($item['user_id'] ?? 0);
        if ($creatorId === 0) {
            return $context;
        }

        try {
            // 1. Check if creator is a new member (account < 14 days old)
            $userCreatedAt = self::getUserCreatedAt($creatorId);
            if ($userCreatedAt) {
                $daysSinceJoin = self::getDaysSinceDate($userCreatedAt);
                if ($daysSinceJoin <= 14) {
                    $context['is_new_member'] = true;
                    $context['badges'][] = [
                        'type' => 'new_member',
                        'label' => 'New Member',
                        'icon' => 'fa-seedling',
                        'color' => '#10b981', // green
                        'description' => 'Joined within the last 2 weeks'
                    ];
                }
            }

            // 2. Check creator vitality (is_inactive_creator)
            // Use pre-calculated vitality_score if available, otherwise calculate
            $vitalityScore = $item['vitality_score'] ?? null;
            if ($vitalityScore === null) {
                $vitalityScore = self::calculateVitalityScore($creatorId);
            }

            $config = self::getConfig();
            // Creator is considered "inactive" if vitality is at or below 60% of full
            // This indicates they haven't been active recently
            if ($vitalityScore <= 0.6) {
                $context['is_inactive_creator'] = true;
                $context['badges'][] = [
                    'type' => 'inactive_creator',
                    'label' => 'Needs Support',
                    'icon' => 'fa-hand-holding-heart',
                    'color' => '#f59e0b', // amber/orange
                    'description' => 'This member hasn\'t been active recently - show them some love!',
                    'admin_only' => true // Only show to admins
                ];
            }

            // 3. Check if post is recent (within 24 hours)
            $postCreatedAt = $item['created_at'] ?? null;
            if ($postCreatedAt) {
                $hoursSincePost = self::getHoursSinceDate($postCreatedAt);
                if ($hoursSincePost <= 24) {
                    $context['is_recent_post'] = true;
                    $context['badges'][] = [
                        'type' => 'recent_post',
                        'label' => 'Fresh',
                        'icon' => 'fa-clock',
                        'color' => '#3b82f6', // blue
                        'description' => 'Posted within the last 24 hours'
                    ];
                }
            }

        } catch (\Exception $e) {
            error_log("FeedRankingService::getRecommendationContext error: " . $e->getMessage());
        }

        return $context;
    }

    /**
     * Get user's account creation date
     */
    private static function getUserCreatedAt(int $userId): ?string
    {
        try {
            $sql = "SELECT created_at FROM users WHERE id = ?";
            $result = Database::query($sql, [$userId])->fetch();
            return $result['created_at'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate hours between a date and now
     */
    private static function getHoursSinceDate(string $dateString): float
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $date->getTimestamp();
            return $diff / 3600; // Convert seconds to hours
        } catch (\Exception $e) {
            return 9999; // Return high number to indicate very old/invalid
        }
    }

    /**
     * Filter badges for display based on user role
     *
     * @param array $badges Array of badge data
     * @param bool $isAdmin Whether the viewing user is an admin
     * @return array Filtered badges
     */
    public static function filterBadgesForUser(array $badges, bool $isAdmin = false): array
    {
        return array_filter($badges, function($badge) use ($isAdmin) {
            // If badge is admin-only, only show to admins
            if (!empty($badge['admin_only']) && !$isAdmin) {
                return false;
            }
            return true;
        });
    }

    /**
     * Debug method: Get breakdown of score components for a post
     */
    public static function debugScoreBreakdown(
        array $post,
        int $viewerId,
        ?float $viewerLat = null,
        ?float $viewerLon = null
    ): array {
        $likesCount = (int)($post['likes_count'] ?? 0);
        $commentsCount = (int)($post['comments_count'] ?? 0);
        $engagementScore = self::calculateEngagementScore($likesCount, $commentsCount);

        $posterId = (int)($post['user_id'] ?? 0);
        $vitalityScore = self::calculateVitalityScore($posterId);

        $posterLat = isset($post['author_lat']) ? (float)$post['author_lat'] : null;
        $posterLon = isset($post['author_lon']) ? (float)$post['author_lon'] : null;

        $distance = null;
        if ($viewerLat && $viewerLon && $posterLat && $posterLon) {
            $distance = self::calculateHaversineDistance($viewerLat, $viewerLon, $posterLat, $posterLon);
        }

        $geoScore = self::calculateGeoDecayScore($viewerLat, $viewerLon, $posterLat, $posterLon);

        return [
            'post_id' => $post['id'] ?? null,
            'engagement' => [
                'likes' => $likesCount,
                'comments' => $commentsCount,
                'formula' => "({$likesCount} * " . self::LIKE_WEIGHT . ") + ({$commentsCount} * " . self::COMMENT_WEIGHT . ")",
                'score' => $engagementScore
            ],
            'vitality' => [
                'user_id' => $posterId,
                'score' => $vitalityScore
            ],
            'geospatial' => [
                'viewer_coords' => ['lat' => $viewerLat, 'lon' => $viewerLon],
                'poster_coords' => ['lat' => $posterLat, 'lon' => $posterLon],
                'distance_km' => $distance,
                'score' => $geoScore
            ],
            'total_score' => $engagementScore * $vitalityScore * $geoScore
        ];
    }
}
