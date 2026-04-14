<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FeedRankingService — Full EdgeRank Algorithm (15-Signal Pipeline)
 *
 * Implements a complete EdgeRank-style feed ranking with 15 weighted signals:
 *  1. Time Decay        — Hacker News-style freshness decay (72h half-life)
 *  2. Engagement        — Log-scaled likes + comments
 *  3. Engagement Velocity — Trending detection (rapid engagement in 2h window)
 *  4. Content Type      — Community actions rank above passive content
 *  5. Social Affinity   — Interaction-based relationship scoring
 *  6. Creator Vitality  — Poster's recent activity level
 *  7. Geo Decay         — Haversine distance-based decay
 *  8. Content Quality   — Images, video, hashtags, @mentions, length
 *  9. Context Timing    — Time-of-day / day-of-week boosts
 * 10. Conversation Depth — Threaded discussion depth boost
 * 11. Reaction Weighting — Emoji reaction type weights
 * 12. Negative Signals  — Hidden posts, muted users, reports
 * 13. CTR Feedback      — Click-through rate with confidence gating
 * 14. User Type Prefs   — Per-user content type personalization
 * 15. Save/Bookmark     — Interest graph from saved content
 *
 * Post-sort: User diversity + Content-type diversity reordering.
 *
 * Uses raw DB:: queries for performance on the complex 15-signal pipeline.
 */
class FeedRankingService
{
    // Public signal constants (used by tests)
    public const LIKE_WEIGHT = 1;
    public const COMMENT_WEIGHT = 5;
    public const SHARE_WEIGHT = 8;

    public const FRESHNESS_FULL_HOURS = 24;
    public const FRESHNESS_HALF_LIFE_HOURS = 72;
    public const FRESHNESS_MINIMUM = 0.3;

    public const GEO_FULL_SCORE_RADIUS = 50;
    public const GEO_DECAY_INTERVAL = 100;
    public const GEO_DECAY_PER_INTERVAL = 0.03;
    public const GEO_MINIMUM_SCORE = 0.15;

    public const VITALITY_FULL_THRESHOLD = 7;
    public const VITALITY_DECAY_THRESHOLD = 30;
    public const VITALITY_MINIMUM = 0.5;

    public const SOCIAL_GRAPH_ENABLED = true;
    public const SOCIAL_GRAPH_MAX_BOOST = 2.0;
    public const SOCIAL_GRAPH_INTERACTION_DAYS = 90;
    public const SOCIAL_GRAPH_FOLLOWER_BOOST = 1.5;

    public const NEGATIVE_SIGNALS_ENABLED = true;
    public const HIDE_PENALTY = 0.0;
    public const MUTE_PENALTY = 0.1;
    public const BLOCK_PENALTY = 0.0;
    public const REPORT_PENALTY_PER = 0.15;

    public const VELOCITY_ENABLED = true;
    public const VELOCITY_WINDOW_HOURS = 2;
    public const VELOCITY_THRESHOLD = 3;
    public const VELOCITY_MAX_BOOST = 1.8;
    public const VELOCITY_DECAY_HOURS = 6;

    public const CONVERSATION_DEPTH_ENABLED = true;
    public const CONVERSATION_DEPTH_MAX_BOOST = 1.5;
    public const CONVERSATION_DEPTH_THRESHOLD = 3;

    public const DIVERSITY_ENABLED = true;
    public const DIVERSITY_MAX_CONSECUTIVE = 2;
    public const DIVERSITY_PENALTY = 0.5;
    public const DIVERSITY_TYPE_ENABLED = true;
    public const DIVERSITY_TYPE_MAX_CONSECUTIVE = 3;

    public const QUALITY_ENABLED = true;
    public const QUALITY_IMAGE_BOOST = 1.3;
    public const QUALITY_LINK_BOOST = 1.1;
    public const QUALITY_LENGTH_MIN = 50;
    public const QUALITY_LENGTH_BONUS = 1.2;
    public const QUALITY_VIDEO_BOOST = 1.4;
    public const QUALITY_HASHTAG_BOOST = 1.1;
    public const QUALITY_MENTION_BOOST = 1.15;

    public const CTR_ENABLED = true;
    public const CTR_MAX_BOOST = 1.5;
    public const CTR_MIN_IMPRESSIONS = 5;

    public const USER_TYPE_PREFS_ENABLED = true;
    public const USER_TYPE_PREFS_MAX_BOOST = 1.4;
    public const USER_TYPE_PREFS_LOOKBACK_DAYS = 30;

    public const SAVE_SIGNAL_ENABLED = true;
    public const SAVE_SIGNAL_MAX_BOOST = 1.35;
    public const SAVE_SIGNAL_MIN_SAVES = 2;

    public const REACTION_WEIGHTS = [
        'love' => 2.0, 'celebrate' => 1.8, 'insightful' => 1.5,
        'like' => 1.0, 'curious' => 0.8, 'sad' => 0.6, 'angry' => 0.5,
    ];

    // View/click tracking
    private const VIEW_TRACKING_ENABLED = true;
    private const CLICK_TRACKING_ENABLED = true;

    private ?array $config = null;
    /** @var array<int, int> */
    private array $mutedUserSet = [];

    /**
     * Per-request, per-tenant config cache. getConfig() is called from inside
     * tight scoring loops — without this, every post scored re-queries the
     * tenants table.
     *
     * @var array<int, array>
     */
    private static array $configCacheByTenant = [];

    public function __construct()
    {
    }

    // =========================================================================
    // CONFIG
    // =========================================================================

    public static function getConfig(): array
    {
        // Per-request memoization keyed by tenant — avoids repeated DB hits
        // from inside scoring loops (called once per post).
        try {
            $tenantIdForCache = TenantContext::getId();
            if ($tenantIdForCache !== null && isset(self::$configCacheByTenant[$tenantIdForCache])) {
                return self::$configCacheByTenant[$tenantIdForCache];
            }
        } catch (\Throwable $e) {
            $tenantIdForCache = null;
        }

        $defaults = [
            'enabled' => true,
            'like_weight' => self::LIKE_WEIGHT, 'comment_weight' => self::COMMENT_WEIGHT, 'share_weight' => self::SHARE_WEIGHT,
            'vitality_full_days' => self::VITALITY_FULL_THRESHOLD, 'vitality_decay_days' => self::VITALITY_DECAY_THRESHOLD, 'vitality_minimum' => self::VITALITY_MINIMUM,
            'geo_full_radius' => self::GEO_FULL_SCORE_RADIUS, 'geo_decay_interval' => self::GEO_DECAY_INTERVAL,
            'geo_decay_rate' => self::GEO_DECAY_PER_INTERVAL, 'geo_minimum' => self::GEO_MINIMUM_SCORE,
            'freshness_enabled' => true, 'freshness_full_hours' => self::FRESHNESS_FULL_HOURS,
            'freshness_half_life' => self::FRESHNESS_HALF_LIFE_HOURS, 'freshness_minimum' => self::FRESHNESS_MINIMUM, 'freshness_gravity' => 1.0,
            'social_graph_enabled' => self::SOCIAL_GRAPH_ENABLED, 'social_graph_max_boost' => self::SOCIAL_GRAPH_MAX_BOOST,
            'social_graph_lookback_days' => self::SOCIAL_GRAPH_INTERACTION_DAYS, 'social_graph_follower_boost' => self::SOCIAL_GRAPH_FOLLOWER_BOOST,
            'negative_signals_enabled' => self::NEGATIVE_SIGNALS_ENABLED,
            'hide_penalty' => self::HIDE_PENALTY, 'mute_penalty' => self::MUTE_PENALTY, 'block_penalty' => self::BLOCK_PENALTY,
            'report_penalty_per' => self::REPORT_PENALTY_PER,
            'quality_enabled' => self::QUALITY_ENABLED,
            'quality_image_boost' => self::QUALITY_IMAGE_BOOST, 'quality_link_boost' => self::QUALITY_LINK_BOOST,
            'quality_length_min' => self::QUALITY_LENGTH_MIN, 'quality_length_bonus' => self::QUALITY_LENGTH_BONUS,
            'quality_video_boost' => self::QUALITY_VIDEO_BOOST, 'quality_hashtag_boost' => self::QUALITY_HASHTAG_BOOST,
            'quality_mention_boost' => self::QUALITY_MENTION_BOOST,
            'diversity_enabled' => self::DIVERSITY_ENABLED, 'diversity_max_consecutive' => self::DIVERSITY_MAX_CONSECUTIVE,
            'diversity_penalty' => self::DIVERSITY_PENALTY,
            'diversity_type_enabled' => self::DIVERSITY_TYPE_ENABLED, 'diversity_type_max_consecutive' => self::DIVERSITY_TYPE_MAX_CONSECUTIVE,
            'velocity_enabled' => self::VELOCITY_ENABLED, 'velocity_window_hours' => self::VELOCITY_WINDOW_HOURS,
            'velocity_threshold' => self::VELOCITY_THRESHOLD, 'velocity_max_boost' => self::VELOCITY_MAX_BOOST, 'velocity_decay_hours' => self::VELOCITY_DECAY_HOURS,
            'conversation_depth_enabled' => self::CONVERSATION_DEPTH_ENABLED,
            'conversation_depth_max_boost' => self::CONVERSATION_DEPTH_MAX_BOOST, 'conversation_depth_threshold' => self::CONVERSATION_DEPTH_THRESHOLD,
            'ctr_enabled' => self::CTR_ENABLED, 'ctr_max_boost' => self::CTR_MAX_BOOST, 'ctr_min_impressions' => self::CTR_MIN_IMPRESSIONS,
            'user_type_prefs_enabled' => self::USER_TYPE_PREFS_ENABLED, 'user_type_prefs_max_boost' => self::USER_TYPE_PREFS_MAX_BOOST,
            'user_type_prefs_lookback_days' => self::USER_TYPE_PREFS_LOOKBACK_DAYS,
            'save_signal_enabled' => self::SAVE_SIGNAL_ENABLED, 'save_signal_max_boost' => self::SAVE_SIGNAL_MAX_BOOST, 'save_signal_min_saves' => self::SAVE_SIGNAL_MIN_SAVES,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = DB::table('tenants')->where('id', $tenantId)->value('configuration');
            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['feed_algorithm'])) {
                    $config = array_merge($defaults, $configArr['feed_algorithm']);
                    self::validateConfigArray($config);
                    if ($tenantIdForCache !== null) {
                        self::$configCacheByTenant[$tenantIdForCache] = $config;
                    }
                    return $config;
                }
            }
        } catch (\Exception $e) {
            // Fall through to defaults
        }

        self::validateConfigArray($defaults);
        if ($tenantIdForCache !== null) {
            self::$configCacheByTenant[$tenantIdForCache] = $defaults;
        }
        return $defaults;
    }

    /**
     * Clear the per-request static config cache. Required for tests and for
     * Octane-style persistent workers between requests.
     */
    public static function clearStaticCache(): void
    {
        self::$configCacheByTenant = [];
    }

    private static function validateConfigArray(array &$c): void
    {
        $c['like_weight'] = max(0, (float) $c['like_weight']);
        $c['comment_weight'] = max(0, (float) $c['comment_weight']);
        $c['share_weight'] = max(0, (float) $c['share_weight']);
        $c['vitality_full_days'] = max(1, (int) $c['vitality_full_days']);
        $c['vitality_decay_days'] = max($c['vitality_full_days'] + 1, (int) $c['vitality_decay_days']);
        $c['vitality_minimum'] = max(0.0, min(1.0, (float) $c['vitality_minimum']));
        $c['geo_full_radius'] = max(0, (float) $c['geo_full_radius']);
        $c['geo_decay_interval'] = max(1, (float) $c['geo_decay_interval']);
        $c['geo_decay_rate'] = max(0.0, min(1.0, (float) $c['geo_decay_rate']));
        $c['geo_minimum'] = max(0.0, min(1.0, (float) $c['geo_minimum']));
        $c['freshness_half_life'] = max(1, (float) $c['freshness_half_life']);
        $c['freshness_minimum'] = max(0.0, min(1.0, (float) $c['freshness_minimum']));
        $c['freshness_gravity'] = max(0.1, min(3.0, (float) ($c['freshness_gravity'] ?? 1.0)));
        $c['social_graph_max_boost'] = max(1.0, min(5.0, (float) $c['social_graph_max_boost']));
        $c['social_graph_lookback_days'] = max(1, (int) $c['social_graph_lookback_days']);
        foreach (['quality_image_boost','quality_link_boost','quality_length_bonus','quality_video_boost','quality_hashtag_boost','quality_mention_boost'] as $key) {
            $c[$key] = max(1.0, min(3.0, (float) $c[$key]));
        }
    }

    public function isEnabled(): bool
    {
        return !empty(self::getConfig()['enabled']);
    }

    public function clearCache(): void
    {
        $this->config = null;
        $this->mutedUserSet = [];
    }

    // =========================================================================
    // PUBLIC STATIC SCORING METHODS (used by tests)
    // =========================================================================

    /**
     * Calculate Haversine distance between two coordinate pairs.
     */
    public static function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
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
     * Compute geo decay score from a pre-calculated distance in km.
     */
    public static function computeGeoDecayFromDistance(float $distanceKm): float
    {
        if ($distanceKm <= self::GEO_FULL_SCORE_RADIUS) {
            return 1.0;
        }

        $distanceBeyond = $distanceKm - self::GEO_FULL_SCORE_RADIUS;
        $decayIntervals = floor($distanceBeyond / self::GEO_DECAY_INTERVAL);
        $totalDecay = $decayIntervals * self::GEO_DECAY_PER_INTERVAL;

        return max(self::GEO_MINIMUM_SCORE, 1.0 - $totalDecay);
    }

    /**
     * Calculate geo decay score from viewer/poster coordinates.
     */
    public static function calculateGeoDecayScore(?float $viewerLat, ?float $viewerLon, ?float $posterLat, ?float $posterLon): float
    {
        if ($viewerLat === null || $viewerLon === null || $posterLat === null || $posterLon === null) {
            return 1.0;
        }

        $distanceKm = self::calculateHaversineDistance($viewerLat, $viewerLon, $posterLat, $posterLon);
        return self::computeGeoDecayFromDistance($distanceKm);
    }

    /**
     * Compute vitality score from days since last activity.
     */
    public static function computeVitalityFromDays(int $days): float
    {
        if ($days <= self::VITALITY_FULL_THRESHOLD) {
            return 1.0;
        }
        if ($days >= self::VITALITY_DECAY_THRESHOLD) {
            return self::VITALITY_MINIMUM;
        }

        $decayRange = self::VITALITY_DECAY_THRESHOLD - self::VITALITY_FULL_THRESHOLD;
        $daysIntoDecay = $days - self::VITALITY_FULL_THRESHOLD;
        $scoreRange = 1.0 - self::VITALITY_MINIMUM;
        return 1.0 - ($daysIntoDecay / $decayRange * $scoreRange);
    }

    /**
     * Calculate content quality score for a post.
     */
    public static function calculateContentQualityScore(array $post): float
    {
        $score = 1.0;
        $content = (string) ($post['content'] ?? '');

        if (!empty($post['image_url'])) {
            $score *= self::QUALITY_IMAGE_BOOST;
        }
        if (preg_match('/https?:\/\//', $content)) {
            $score *= self::QUALITY_LINK_BOOST;
        }
        if (preg_match('/(?:youtube\.com|youtu\.be|vimeo\.com|tiktok\.com|dailymotion\.com)/i', $content)) {
            $score *= self::QUALITY_VIDEO_BOOST;
        }
        if (strpos($content, '#') !== false) {
            $score *= self::QUALITY_HASHTAG_BOOST;
        }
        if (strpos($content, '@') !== false) {
            $score *= self::QUALITY_MENTION_BOOST;
        }
        if (strlen($content) >= self::QUALITY_LENGTH_MIN) {
            $score *= self::QUALITY_LENGTH_BONUS;
        }

        return $score;
    }

    /**
     * Calculate engagement score from likes and comments.
     */
    public static function calculateEngagementScore(int $likes, int $comments): float
    {
        $points = ($likes * self::LIKE_WEIGHT) + ($comments * self::COMMENT_WEIGHT);
        if ($points <= 0) {
            return 1.0;
        }
        return 1.0 + min(log(1.0 + $points) * 0.3, 2.0);
    }

    /**
     * Calculate vitality score for a user.
     */
    public static function calculateVitalityScore(int $userId): float
    {
        try {
            $tenantId = TenantContext::getId();
            $rows = DB::select(
                "SELECT MAX(created_at) AS last_active FROM (
                    SELECT MAX(created_at) AS created_at FROM activity_log WHERE user_id = ? AND tenant_id = ? AND action IN ('login','post_created','comment_added','like_added')
                    UNION ALL
                    SELECT MAX(created_at) AS created_at FROM feed_posts WHERE user_id = ? AND tenant_id = ?
                ) AS combined",
                [$userId, $tenantId, $userId, $tenantId]
            );
            if (!empty($rows) && $rows[0]->last_active) {
                $days = self::getDaysSinceDateStatic($rows[0]->last_active);
                return self::computeVitalityFromDays($days);
            }
        } catch (\Exception $e) {
        }
        return self::VITALITY_MINIMUM;
    }

    /**
     * Calculate freshness score from a datetime string.
     */
    public static function calculateFreshnessScore(string $createdAt): float
    {
        $hoursAgo = max(0, (time() - strtotime($createdAt)) / 3600);
        if ($hoursAgo <= self::FRESHNESS_FULL_HOURS) {
            return 1.0;
        }
        return self::hackerNewsDecay((int) round($hoursAgo));
    }

    /**
     * Calculate social graph score for viewer-author pair.
     */
    public static function calculateSocialGraphScore(int $viewerId, int $authorId): float
    {
        if ($viewerId === 0 || $authorId === 0) {
            return 1.0;
        }
        try {
            $tenantId = TenantContext::getId();
            $sql = "SELECT SUM(CASE
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 3
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 2
                    ELSE 1
                END) AS weighted_interactions FROM (
                SELECT created_at FROM likes WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
                UNION ALL
                SELECT created_at FROM comments WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
            ) AS va";
            $params = [$viewerId, $tenantId, self::SOCIAL_GRAPH_INTERACTION_DAYS, $viewerId, $tenantId, self::SOCIAL_GRAPH_INTERACTION_DAYS];
            $rows = DB::select($sql, $params);
            if (!empty($rows) && $rows[0]->weighted_interactions > 0) {
                $bf = (self::SOCIAL_GRAPH_MAX_BOOST - 1) / 4;
                return min(self::SOCIAL_GRAPH_MAX_BOOST, 1.0 + (log((float) $rows[0]->weighted_interactions + 1, 2) * $bf));
            }
        } catch (\Exception $e) {
        }
        return 1.0;
    }

    /**
     * Calculate negative signals score for a post.
     */
    public static function calculateNegativeSignalsScore(int $viewerId, int $postId): float
    {
        if ($viewerId === 0) {
            return 1.0;
        }
        try {
            $tenantId = TenantContext::getId();
            $hidden = DB::table('feed_hidden')
                ->where('user_id', $viewerId)
                ->where('tenant_id', $tenantId)
                ->where('post_id', $postId)
                ->exists();
            if ($hidden) {
                return self::HIDE_PENALTY;
            }

            $reportCount = DB::table('reports')
                ->where('target_type', 'post')
                ->where('target_id', $postId)
                ->where('tenant_id', $tenantId)
                ->count();
            if ($reportCount > 0) {
                return max(0.1, 1.0 - $reportCount * self::REPORT_PENALTY_PER);
            }
        } catch (\Exception $e) {
        }
        return 1.0;
    }

    /**
     * Hacker News-style time decay.
     */
    public static function hackerNewsDecay(int $hoursAgo): float
    {
        $halfLife = max(1.0, (float) self::FRESHNESS_HALF_LIFE_HOURS);
        $gravity = 1.0;
        $decay = 1.0 / pow(1.0 + $hoursAgo / $halfLife, $gravity);
        return max(self::FRESHNESS_MINIMUM, $decay);
    }

    /**
     * Contextual boost based on content type and time of day.
     */
    public static function contextualBoost(string $sourceType, ?string $viewerTimezone = null): float
    {
        try {
            $tz = $viewerTimezone ? new \DateTimeZone($viewerTimezone) : new \DateTimeZone('UTC');
            $now = new \DateTime('now', $tz);
            $hour = (int) $now->format('G');
            $dow = (int) $now->format('N');
        } catch (\Exception $e) {
            $hour = (int) date('G');
            $dow = (int) date('N');
        }

        $isWeekend = $dow >= 6;
        $isWeekday = !$isWeekend;
        $isMorning = $hour >= 7 && $hour < 12;
        $isEvening = $hour >= 19 && $hour < 22;

        return match ($sourceType) {
            'event' => ($dow === 1 && $isMorning) ? 1.20 : (($dow === 5 && $hour >= 14) ? 1.15 : 1.0),
            'volunteer' => $isWeekend ? 1.18 : 1.0,
            'job' => ($isWeekday && $isMorning && $dow <= 3) ? 1.15 : 1.0,
            'post', 'poll' => $isEvening ? 1.12 : (($hour >= 2 && $hour < 6) ? 0.90 : 1.0),
            'listing' => ($isWeekend && $isMorning) ? 1.10 : 1.0,
            default => 1.0,
        };
    }

    // =========================================================================
    // DIVERSITY (Public Static)
    // =========================================================================

    /**
     * Apply user-author diversity to feed items.
     */
    public static function applyContentDiversity(array $items): array
    {
        $maxUser = self::DIVERSITY_MAX_CONSECUTIVE;
        $result = [];
        $deferred = [];

        foreach ($items as $item) {
            $userId = (int) ($item['user_id'] ?? 0);
            $shouldDefer = false;

            if ($userId > 0) {
                $c = 0;
                for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxUser; $i--) {
                    if ((int) ($result[$i]['user_id'] ?? 0) === $userId) {
                        $c++;
                    } else {
                        break;
                    }
                }
                if ($c >= $maxUser) {
                    $shouldDefer = true;
                }
            }

            if ($shouldDefer) {
                $deferred[] = $item;
            } else {
                $result[] = $item;
            }
        }

        foreach ($deferred as $di) {
            $dUid = (int) ($di['user_id'] ?? 0);
            $ins = false;
            for ($i = 0; $i < count($result); $i++) {
                $ok = true;
                if ($dUid > 0) {
                    for ($j = max(0, $i - $maxUser + 1); $j < min(count($result), $i + $maxUser); $j++) {
                        if ((int) ($result[$j]['user_id'] ?? 0) === $dUid) {
                            $ok = false;
                            break;
                        }
                    }
                }
                if ($ok) {
                    array_splice($result, $i, 0, [$di]);
                    $ins = true;
                    break;
                }
            }
            if (!$ins) {
                $result[] = $di;
            }
        }

        return $result;
    }

    /**
     * Apply content-type diversity to feed items.
     */
    public static function applyContentTypeDiversity(array $items): array
    {
        $maxType = self::DIVERSITY_TYPE_MAX_CONSECUTIVE;
        $result = [];
        $deferred = [];

        foreach ($items as $item) {
            $cType = $item['type'] ?? $item['content_type'] ?? 'post';
            $shouldDefer = false;

            $c = 0;
            for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxType; $i--) {
                if (($result[$i]['type'] ?? $result[$i]['content_type'] ?? 'post') === $cType) {
                    $c++;
                } else {
                    break;
                }
            }
            if ($c >= $maxType) {
                $shouldDefer = true;
            }

            if ($shouldDefer) {
                $deferred[] = $item;
            } else {
                $result[] = $item;
            }
        }

        foreach ($deferred as $di) {
            $dType = $di['type'] ?? $di['content_type'] ?? 'post';
            $ins = false;
            for ($i = 0; $i < count($result); $i++) {
                $ok = true;
                for ($j = max(0, $i - $maxType + 1); $j < min(count($result), $i + $maxType); $j++) {
                    if (($result[$j]['type'] ?? $result[$j]['content_type'] ?? 'post') === $dType) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    array_splice($result, $i, 0, [$di]);
                    $ins = true;
                    break;
                }
            }
            if (!$ins) {
                $result[] = $di;
            }
        }

        return $result;
    }

    // =========================================================================
    // MAIN RANKING: rankPosts / getEdgeRankScore / boostPost
    // =========================================================================

    /**
     * Rank feed_activity items in-memory using 15-signal EdgeRank.
     *
     * @param int $tenantId Tenant scope
     * @param array $postIds Feed item IDs to rank
     * @param int $userId Authenticated user ID
     * @return array Re-ranked post IDs
     */
    public function rankPosts(int $tenantId, array $postIds, int $userId): array
    {
        if (empty($postIds)) {
            return [];
        }

        // Load posts as items
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        try {
            $items = array_map(
                fn ($row) => (array) $row,
                DB::select(
                    "SELECT p.*, u.latitude as author_lat, u.longitude as author_lon
                     FROM feed_posts p
                     LEFT JOIN users u ON p.user_id = u.id
                     WHERE p.id IN ({$placeholders}) AND p.tenant_id = ?",
                    array_merge($postIds, [$tenantId])
                )
            );
        } catch (\Exception $e) {
            return $postIds; // Return unranked on error
        }

        if (empty($items)) {
            return $postIds;
        }

        // Use rankFeedItems for full 15-signal ranking
        $ranked = $this->rankFeedItems($items, $userId);

        return array_map(fn ($item) => (int) ($item['id'] ?? $item['post_id'] ?? 0), $ranked);
    }

    /**
     * Get EdgeRank score for a single post.
     */
    public function getEdgeRankScore(int $tenantId, int $postId, int $userId): float
    {
        try {
            $rows = DB::select(
                "SELECT p.*, u.latitude as author_lat, u.longitude as author_lon
                 FROM feed_posts p
                 LEFT JOIN users u ON p.user_id = u.id
                 WHERE p.id = ? AND p.tenant_id = ?",
                [$postId, $tenantId]
            );

            if (empty($rows)) {
                return 0.0;
            }

            $post = (array) $rows[0];
            $viewerCoords = $this->getUserCoordinates($userId);

            return self::calculatePostScore($post, $userId, $viewerCoords[0], $viewerCoords[1]);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Boost a post's visibility.
     */
    public function boostPost(int $tenantId, int $postId, float $factor = 1.5): bool
    {
        try {
            DB::statement(
                "INSERT INTO post_boosts (post_id, tenant_id, boost_factor, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE boost_factor = ?, updated_at = NOW()",
                [$postId, $tenantId, $factor, $factor]
            );
            return true;
        } catch (\Exception $e) {
            try {
                DB::table('feed_posts')
                    ->where('id', $postId)
                    ->where('tenant_id', $tenantId)
                    ->update(['boost_factor' => $factor]);
                return true;
            } catch (\Exception $e2) {
                return false;
            }
        }
    }

    // =========================================================================
    // FULL 15-SIGNAL IN-MEMORY RANKING
    // =========================================================================

    /**
     * Rank feed items in-memory using 15-signal EdgeRank.
     */
    /**
     * @param bool $includeReasons If true, attaches '_ranking_reasons' with top signals per item
     */
    public function rankFeedItems(array $items, ?int $viewerId = null, ?string $viewerTimezone = null, bool $includeReasons = false): array
    {
        if (!$this->isEnabled() || count($items) < 2) {
            return $items;
        }

        $config = self::getConfig();
        $typeWeights = [
            'event' => 1.4, 'challenge' => 1.3, 'poll' => 1.25,
            'volunteer' => 1.2, 'goal' => 1.1, 'post' => 1.0,
            'listing' => 0.9, 'job' => 0.9, 'review' => 0.8,
        ];

        $authorIds = array_unique(array_filter(array_map(fn ($i) => (int) ($i['user_id'] ?? 0), $items)));
        $postIds = array_unique(array_filter(array_map(fn ($i) => (int) ($i['id'] ?? $i['post_id'] ?? 0), $items)));

        $connectedSet = [];
        $socialScores = [];
        if ($viewerId) {
            $connectedIds = $this->getViewerConnectedUserIds($viewerId);
            $connectedSet = array_flip($connectedIds);
            if (!empty($authorIds)) {
                $socialScores = $this->getBatchSocialGraphScores($viewerId, $authorIds);
            }
        }

        $viewerLat = null;
        $viewerLon = null;
        if ($viewerId) {
            [$viewerLat, $viewerLon] = $this->getUserCoordinates($viewerId);
        }

        $authorCoords = !empty($authorIds) ? $this->getBatchUserCoordinates($authorIds) : [];
        $vitalityScores = !empty($authorIds) ? $this->getBatchVitalityScores($authorIds) : [];
        $velocityScores = (self::VELOCITY_ENABLED && !empty($postIds)) ? $this->getBatchEngagementVelocity($postIds) : [];
        $conversationDepths = (self::CONVERSATION_DEPTH_ENABLED && !empty($postIds)) ? $this->getBatchConversationDepth($postIds) : [];
        $reactionScores = !empty($postIds) ? $this->getBatchReactionScores($postIds) : [];
        $negativeScores = ($viewerId && !empty($postIds)) ? $this->getBatchNegativeSignals($viewerId, $postIds, $authorIds) : [];
        $ctrScores = (!empty($config['ctr_enabled']) && !empty($postIds)) ? $this->getBatchClickThroughRates($postIds) : [];
        $userTypePrefs = (!empty($config['user_type_prefs_enabled']) && $viewerId) ? $this->getInstanceUserTypePreferences($viewerId) : [];
        $saveScores = (!empty($config['save_signal_enabled']) && !empty($postIds)) ? $this->getBatchSaveScores($postIds) : [];

        foreach ($items as &$item) {
            $postId = (int) ($item['id'] ?? $item['post_id'] ?? 0);
            $authorId = (int) ($item['user_id'] ?? 0);
            $score = 1.0;

            if ($authorId && $this->isAuthorMuted($authorId)) {
                $item['_edge_rank'] = 0.0;
                continue;
            }

            // 1. Time Decay
            $createdAt = $item['created_at'] ?? null;
            if ($createdAt) {
                $hoursAgo = max(0, (int) round((time() - strtotime($createdAt)) / 3600));
                $score *= self::hackerNewsDecay($hoursAgo);
            }

            // 2. Engagement
            $likes = (int) ($item['likes_count'] ?? 0);
            $comments = (int) ($item['comments_count'] ?? 0);
            $points = ($likes * $config['like_weight']) + ($comments * $config['comment_weight']);
            $score *= $points > 0 ? 1.0 + min(log(1.0 + $points) * 0.3, 2.0) : 1.05;

            // 3. Velocity
            if (self::VELOCITY_ENABLED && isset($velocityScores[$postId])) {
                $v = $velocityScores[$postId];
                if ($v >= self::VELOCITY_THRESHOLD) {
                    $rawBoost = min(self::VELOCITY_MAX_BOOST, 1.0 + (($v - self::VELOCITY_THRESHOLD) / self::VELOCITY_THRESHOLD) * 0.4);
                    $postAgeHours = $createdAt ? max(0, (time() - strtotime($createdAt)) / 3600) : 0;
                    $velocityDecay = max(0.0, 1.0 - ($postAgeHours / (self::VELOCITY_DECAY_HOURS * 2)));
                    $score *= 1.0 + ($rawBoost - 1.0) * $velocityDecay;
                }
            }

            // 4. Type weight
            $sourceType = $item['type'] ?? $item['source_type'] ?? 'post';
            $score *= $typeWeights[$sourceType] ?? 1.0;

            // 5. Social Affinity
            if ($authorId && $viewerId) {
                if (isset($socialScores[$authorId]) && $socialScores[$authorId] > 0) {
                    $bf = ($config['social_graph_max_boost'] - 1) / 4;
                    $score *= min($config['social_graph_max_boost'], 1.0 + (log($socialScores[$authorId] + 1, 2) * $bf));
                } elseif (isset($connectedSet[$authorId])) {
                    $score *= (float) $config['social_graph_follower_boost'];
                }
            }

            // 6. Vitality
            if ($authorId && isset($vitalityScores[$authorId])) {
                $score *= $vitalityScores[$authorId];
            }

            // 7. Geo Decay
            if ($viewerLat !== null && $authorId && isset($authorCoords[$authorId])) {
                [$aLat, $aLon] = $authorCoords[$authorId];
                $score *= self::calculateGeoDecayScore($viewerLat, $viewerLon, $aLat, $aLon);
            }

            // 8. Quality
            if ($config['quality_enabled']) {
                $score *= self::calculateContentQualityScore($item);
            }

            // 9. Context Timing
            $score *= self::contextualBoost($sourceType, $viewerTimezone);

            // 10. Conversation Depth
            if (self::CONVERSATION_DEPTH_ENABLED && isset($conversationDepths[$postId])) {
                $d = $conversationDepths[$postId];
                if ($d >= self::CONVERSATION_DEPTH_THRESHOLD) {
                    $score *= min(self::CONVERSATION_DEPTH_MAX_BOOST, 1.0 + ($d / (self::CONVERSATION_DEPTH_THRESHOLD * 3)) * 0.5);
                }
            }

            // 11. Reaction Weighting
            if (isset($reactionScores[$postId]) && $reactionScores[$postId] > 0) {
                $score *= 1.0 + min($reactionScores[$postId] * 0.1, 1.0);
            }

            // 12. Negative Signals
            if ($viewerId && isset($negativeScores[$postId])) {
                $score *= $negativeScores[$postId];
            }

            // 13. CTR
            if (!empty($config['ctr_enabled']) && isset($ctrScores[$postId])) {
                $ctr = $ctrScores[$postId];
                $impressions = $ctrScores['_impressions'][$postId] ?? 0;
                if ($impressions >= ($config['ctr_min_impressions'] ?? 5)) {
                    $ctrMultiplier = 1.0 + ($ctr - 0.1) * (($config['ctr_max_boost'] ?? 1.5) - 1.0) / 0.9;
                    $score *= max(0.8, min((float) ($config['ctr_max_boost'] ?? 1.5), $ctrMultiplier));
                }
            }

            // 14. User Type Preferences
            if (!empty($config['user_type_prefs_enabled']) && !empty($userTypePrefs)) {
                $itemType = $item['type'] ?? $item['source_type'] ?? 'post';
                if (isset($userTypePrefs[$itemType])) {
                    $score *= $userTypePrefs[$itemType];
                }
            }

            // 15. Save/Bookmark
            if (!empty($config['save_signal_enabled']) && isset($saveScores[$postId])) {
                $saves = $saveScores[$postId];
                if ($saves >= ($config['save_signal_min_saves'] ?? 2)) {
                    $saveBoost = min(
                        (float) ($config['save_signal_max_boost'] ?? 1.35),
                        1.0 + log($saves, 10) * 0.2
                    );
                    $score *= $saveBoost;
                }
            }

            $item['_edge_rank'] = $score;
        }
        unset($item);

        usort($items, fn ($a, $b) => ($b['_edge_rank'] ?? 0) <=> ($a['_edge_rank'] ?? 0));

        $items = $this->applyDiversityInPlace($items);

        foreach ($items as &$item) {
            unset($item['_edge_rank']);
        }
        unset($item);

        // Second pass: compute ranking reasons when requested
        // Done after sort so we don't waste time on items that get filtered
        if ($includeReasons) {
            $items = $this->attachRankingReasons($items, $viewerId, $connectedSet, $socialScores, $velocityScores, $typeWeights);
        }

        return $items;
    }

    /**
     * Attach human-readable ranking reasons to each item.
     * Returns the top 3 contributing signals as i18n-ready keys.
     */
    private function attachRankingReasons(
        array $items,
        ?int $viewerId,
        array $connectedSet,
        array $socialScores,
        array $velocityScores,
        array $typeWeights,
    ): array {
        static $reasonLabels = [
            'social_affinity' => 'why_shown.from_connection',
            'engagement'      => 'why_shown.popular',
            'velocity'        => 'why_shown.trending',
            'type_boost'      => 'why_shown.community_content',
            'quality'         => 'why_shown.quality_content',
            'conversation'    => 'why_shown.active_discussion',
            'fresh'           => 'why_shown.fresh',
            'saved'           => 'why_shown.frequently_saved',
        ];

        foreach ($items as &$item) {
            $signals = [];
            $authorId = (int) ($item['user_id'] ?? 0);
            $postId = (int) ($item['id'] ?? $item['post_id'] ?? 0);

            // Social affinity
            if ($viewerId && $authorId && (isset($connectedSet[$authorId]) || (isset($socialScores[$authorId]) && $socialScores[$authorId] > 0))) {
                $signals['social_affinity'] = 2.0;
            }

            // Engagement
            $likes = (int) ($item['likes_count'] ?? 0);
            $comments = (int) ($item['comments_count'] ?? 0);
            if (($likes + $comments) >= 3) {
                $signals['engagement'] = 1.5 + min($likes + $comments, 20) * 0.05;
            }

            // Velocity
            if (isset($velocityScores[$postId]) && $velocityScores[$postId] >= self::VELOCITY_THRESHOLD) {
                $signals['velocity'] = 1.8;
            }

            // Type boost
            $sourceType = $item['type'] ?? $item['source_type'] ?? 'post';
            if (($typeWeights[$sourceType] ?? 1.0) > 1.1) {
                $signals['type_boost'] = $typeWeights[$sourceType] ?? 1.0;
            }

            // Quality (images, video)
            if (!empty($item['image_url']) || !empty($item['media'])) {
                $signals['quality'] = 1.3;
            }

            // Freshness (< 4h)
            $createdAt = $item['created_at'] ?? null;
            if ($createdAt) {
                $hoursAgo = max(0, (time() - strtotime($createdAt)) / 3600);
                if ($hoursAgo < 4) {
                    $signals['fresh'] = 1.5;
                }
            }

            // Sort by weight descending, take top 3
            arsort($signals);
            $topReasons = [];
            foreach (array_slice($signals, 0, 3, true) as $key => $weight) {
                $topReasons[] = $reasonLabels[$key] ?? $key;
            }

            $item['ranking_reasons'] = $topReasons;
        }
        unset($item);

        return $items;
    }

    // =========================================================================
    // TRACKING
    // =========================================================================

    public function recordImpression(int $postId, int $userId): void
    {
        if (!self::VIEW_TRACKING_ENABLED || $userId === 0 || $postId === 0) return;
        try {
            DB::statement(
                "INSERT INTO feed_impressions (post_id, user_id, tenant_id, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE view_count = view_count + 1, updated_at = NOW()",
                [$postId, $userId, TenantContext::getId()]
            );
        } catch (\Exception $e) {
            // Non-blocking
        }
    }

    public function recordClick(int $postId, int $userId): void
    {
        if (!self::CLICK_TRACKING_ENABLED || $userId === 0 || $postId === 0) return;
        try {
            DB::statement(
                "INSERT INTO feed_clicks (post_id, user_id, tenant_id, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE click_count = click_count + 1, updated_at = NOW()",
                [$postId, $userId, TenantContext::getId()]
            );
        } catch (\Exception $e) {
            // Non-blocking
        }
    }

    // =========================================================================
    // BATCH DATA LOADING (Private, Tenant-Scoped)
    // =========================================================================

    private function getBatchSocialGraphScores(int $viewerId, array $authorIds): array
    {
        if (empty($authorIds) || $viewerId === 0) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($authorIds), '?'));
            $days = 90;
            $sql = "SELECT p.user_id AS author_id, SUM(CASE
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 3
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 2
                    ELSE 1
                END) AS weighted_interactions FROM (
                SELECT target_id, created_at FROM likes WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
                UNION ALL
                SELECT target_id, created_at FROM comments WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
            ) AS va JOIN feed_posts p ON p.id=va.target_id AND p.tenant_id=? WHERE p.user_id IN ($ph) GROUP BY p.user_id";
            $params = array_merge([$viewerId, $tenantId, $days, $viewerId, $tenantId, $days, $tenantId], $authorIds);
            $rows = DB::select($sql, $params);
            $r = [];
            foreach ($rows as $row) { $r[(int) $row->author_id] = (float) $row->weighted_interactions; }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchVitalityScores(array $userIds): array
    {
        if (empty($userIds)) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT user_id, MAX(created_at) AS last_active FROM (
                SELECT user_id, MAX(created_at) AS created_at FROM activity_log WHERE user_id IN ($ph) AND tenant_id=? AND action IN ('login','post_created','comment_added','like_added') GROUP BY user_id
                UNION ALL
                SELECT user_id, MAX(created_at) AS created_at FROM feed_posts WHERE user_id IN ($ph) AND tenant_id=? GROUP BY user_id
            ) AS combined GROUP BY user_id";
            $params = array_merge($userIds, [$tenantId], $userIds, [$tenantId]);
            $rows = DB::select($sql, $params);
            $r = [];
            foreach ($rows as $row) {
                $daysSince = self::getDaysSinceDateStatic($row->last_active);
                $r[(int) $row->user_id] = self::computeVitalityFromDays($daysSince);
            }
            $config = self::getConfig();
            foreach ($userIds as $uid) {
                if (!isset($r[$uid])) { $r[$uid] = (float) $config['vitality_minimum']; }
            }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchEngagementVelocity(array $postIds): array
    {
        if (empty($postIds)) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $hrs = self::VELOCITY_WINDOW_HOURS;
            $sql = "SELECT target_id AS post_id, COUNT(*) AS velocity FROM (
                SELECT target_id FROM likes WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? HOUR)
                UNION ALL
                SELECT target_id FROM comments WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? HOUR)
            ) AS recent GROUP BY target_id";
            $params = array_merge($postIds, [$tenantId, $hrs], $postIds, [$tenantId, $hrs]);
            $rows = DB::select($sql, $params);
            $r = [];
            foreach ($rows as $row) { $r[(int) $row->post_id] = (int) $row->velocity; }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchConversationDepth(array $postIds): array
    {
        if (empty($postIds)) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $rows = DB::select(
                "SELECT target_id AS post_id, COUNT(*) AS depth FROM comments WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND parent_id IS NOT NULL GROUP BY target_id",
                array_merge($postIds, [$tenantId])
            );
            $r = [];
            foreach ($rows as $row) { $r[(int) $row->post_id] = (int) $row->depth; }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchReactionScores(array $postIds): array
    {
        if (empty($postIds)) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $rows = DB::select(
                "SELECT target_id AS post_id, reaction_type, COUNT(*) AS cnt FROM reactions WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? GROUP BY target_id, reaction_type",
                array_merge($postIds, [$tenantId])
            );
            $r = [];
            foreach ($rows as $row) {
                $pid = (int) $row->post_id;
                $type = strtolower($row->reaction_type ?? 'like');
                $weight = self::REACTION_WEIGHTS[$type] ?? 1.0;
                $r[$pid] = ($r[$pid] ?? 0) + ($weight * (int) $row->cnt);
            }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchNegativeSignals(int $viewerId, array $postIds, array $authorIds): array
    {
        $config = self::getConfig();
        if (empty($config['negative_signals_enabled']) || $viewerId === 0) return [];
        $result = [];
        $tenantId = TenantContext::getId();
        $this->mutedUserSet = [];
        try {
            if (!empty($postIds)) {
                $ph = implode(',', array_fill(0, count($postIds), '?'));
                $rows = DB::select("SELECT post_id FROM feed_hidden WHERE user_id=? AND tenant_id=? AND post_id IN ($ph)", array_merge([$viewerId, $tenantId], $postIds));
                foreach ($rows as $row) { $result[(int) $row->post_id] = (float) $config['hide_penalty']; }
            }
            if (!empty($authorIds)) {
                $ph = implode(',', array_fill(0, count($authorIds), '?'));
                $rows = DB::select("SELECT muted_user_id FROM feed_muted_users WHERE user_id=? AND tenant_id=? AND muted_user_id IN ($ph)", array_merge([$viewerId, $tenantId], $authorIds));
                foreach ($rows as $row) { $this->mutedUserSet[(int) $row->muted_user_id] = 1; }
            }
            if (!empty($postIds)) {
                $ph = implode(',', array_fill(0, count($postIds), '?'));
                $rows = DB::select("SELECT target_id, COUNT(*) AS report_count FROM reports WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? GROUP BY target_id", array_merge($postIds, [$tenantId]));
                foreach ($rows as $row) {
                    $pid = (int) $row->target_id;
                    if (!isset($result[$pid])) {
                        $result[$pid] = max(0.1, 1.0 - (int) $row->report_count * (float) $config['report_penalty_per']);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('FeedRankingService: failed to fetch batch report penalties', ['error' => $e->getMessage()]);
        }
        return $result;
    }

    private function getBatchClickThroughRates(array $postIds): array
    {
        if (empty($postIds) || !self::CLICK_TRACKING_ENABLED) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $rows = DB::select(
                "SELECT fi.post_id, SUM(fi.view_count) AS impressions, COALESCE(SUM(fc.click_count),0)/GREATEST(SUM(fi.view_count),1) AS ctr
                 FROM feed_impressions fi LEFT JOIN feed_clicks fc ON fc.post_id=fi.post_id AND fc.tenant_id=fi.tenant_id
                 WHERE fi.post_id IN ($ph) AND fi.tenant_id=? GROUP BY fi.post_id",
                array_merge($postIds, [$tenantId])
            );
            $r = ['_impressions' => []];
            foreach ($rows as $row) {
                $pid = (int) $row->post_id;
                $r[$pid] = min(1.0, (float) $row->ctr);
                $r['_impressions'][$pid] = (int) $row->impressions;
            }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    /**
     * Instance-level user type preferences (used internally by rankFeedItems).
     */
    private function getInstanceUserTypePreferences(int $viewerId): array
    {
        return self::getUserTypePreferences($viewerId);
    }

    /**
     * Get user content type preferences.
     */
    public static function getUserTypePreferences(int $viewerId): array
    {
        if ($viewerId === 0) return [];
        $config = self::getConfig();
        $maxBoost = (float) ($config['user_type_prefs_max_boost'] ?? 1.4);
        $lookbackDays = (int) ($config['user_type_prefs_lookback_days'] ?? 30);
        try {
            $tenantId = TenantContext::getId();
            $sql = "SELECT fa.source_type, COUNT(*) AS engagements FROM (
                SELECT target_id FROM likes WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
                UNION ALL
                SELECT target_id FROM comments WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
            ) AS eng
            JOIN feed_activity fa ON fa.source_type IN ('post','listing','event','poll','goal','review','job','challenge','volunteer')
                AND fa.source_id = eng.target_id AND fa.tenant_id=?
            GROUP BY fa.source_type";
            $params = [$viewerId, $tenantId, $lookbackDays, $viewerId, $tenantId, $lookbackDays, $tenantId];
            $rows = DB::select($sql, $params);
            if (empty($rows)) return [];

            $maxEng = 0;
            $typeCounts = [];
            foreach ($rows as $row) {
                $typeCounts[$row->source_type] = (int) $row->engagements;
                $maxEng = max($maxEng, (int) $row->engagements);
            }
            if ($maxEng === 0) return [];

            $result = [];
            foreach ($typeCounts as $type => $count) {
                $normalized = $count / $maxEng;
                $result[$type] = 1.0 + ($normalized * ($maxBoost - 1.0));
            }
            return $result;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getBatchSaveScores(array $postIds): array
    {
        if (empty($postIds)) return [];
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $sql = "SELECT fa.source_id AS post_id, (
                        COALESCE((SELECT COUNT(*) FROM user_saved_listings usl WHERE usl.listing_id = fa.source_id AND usl.tenant_id = ?), 0) +
                        COALESCE((SELECT COUNT(*) FROM listing_favorites lf WHERE lf.listing_id = fa.source_id AND lf.tenant_id = ?), 0)
                    ) AS save_count
                    FROM feed_activity fa
                    WHERE fa.source_id IN ($ph) AND fa.tenant_id = ?
                    AND fa.source_type IN ('listing', 'post')
                    GROUP BY fa.source_id HAVING save_count > 0";
            $params = array_merge([$tenantId, $tenantId], $postIds, [$tenantId]);
            $rows = DB::select($sql, $params);
            $r = [];
            foreach ($rows as $row) { $r[(int) $row->post_id] = (int) $row->save_count; }
            return $r;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    // =========================================================================
    // SCORING HELPERS (static)
    // =========================================================================

    /**
     * Calculate the full post score (used by getEdgeRankScore).
     */
    public static function calculatePostScore(array $post, int $viewerId, ?float $viewerLat = null, ?float $viewerLon = null): float
    {
        $config = self::getConfig();
        $score = 1.0;

        // Time Decay
        $createdAt = $post['created_at'] ?? null;
        if ($createdAt) {
            $hoursAgo = max(0, (int) round((time() - strtotime($createdAt)) / 3600));
            $score *= self::hackerNewsDecay($hoursAgo);
        }

        // Engagement
        $likes = (int) ($post['likes_count'] ?? 0);
        $comments = (int) ($post['comments_count'] ?? 0);
        $points = ($likes * $config['like_weight']) + ($comments * $config['comment_weight']);
        $score *= max(1.0, $points);

        // Type weight
        $typeWeights = [
            'event' => 1.4, 'challenge' => 1.3, 'poll' => 1.25,
            'volunteer' => 1.2, 'goal' => 1.1, 'post' => 1.0,
            'listing' => 0.9, 'job' => 0.9, 'review' => 0.8,
        ];
        $sourceType = $post['type'] ?? $post['source_type'] ?? 'post';
        $score *= $typeWeights[$sourceType] ?? 1.0;

        // Geo Decay
        $posterLat = isset($post['author_lat']) ? (float) $post['author_lat'] : null;
        $posterLon = isset($post['author_lon']) ? (float) $post['author_lon'] : null;
        $score *= self::calculateGeoDecayScore($viewerLat, $viewerLon, $posterLat, $posterLon);

        return $score;
    }

    // =========================================================================
    // COORDINATE HELPERS
    // =========================================================================

    private function getUserCoordinates(int $userId): array
    {
        try {
            $row = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', TenantContext::getId())
                ->select('latitude', 'longitude')
                ->first();

            if ($row && $row->latitude !== null && $row->longitude !== null) {
                return [(float) $row->latitude, (float) $row->longitude];
            }
        } catch (\Exception $e) {
            \Log::warning('FeedRankingService: failed to fetch user coordinates', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
        return [null, null];
    }

    private function getBatchUserCoordinates(array $userIds): array
    {
        if (empty($userIds)) return [];
        try {
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $rows = DB::select(
                "SELECT id, latitude, longitude FROM users WHERE id IN ($ph) AND latitude IS NOT NULL AND longitude IS NOT NULL",
                $userIds
            );
            $result = [];
            foreach ($rows as $row) {
                $result[(int) $row->id] = [(float) $row->latitude, (float) $row->longitude];
            }
            return $result;
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function getViewerConnectedUserIds(int $viewerId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $rows = DB::select(
                "SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as connected_id
                 FROM transactions
                 WHERE (sender_id = ? OR receiver_id = ?) AND tenant_id = ? AND status = 'completed'",
                [$viewerId, $viewerId, $viewerId, $tenantId]
            );
            return array_map(fn ($row) => (int) $row->connected_id, $rows);
        } catch (\Exception $e) { Log::warning('FeedRankingService batch query failed', ['error' => $e->getMessage()]); return []; }
    }

    private function isAuthorMuted(int $authorId): bool
    {
        return isset($this->mutedUserSet[$authorId]);
    }

    // =========================================================================
    // DIVERSITY (Instance-level, used by rankFeedItems)
    // =========================================================================

    private function applyDiversityInPlace(array $items): array
    {
        $config = self::getConfig();
        $userDiv = !empty($config['diversity_enabled']);
        $typeDiv = !empty($config['diversity_type_enabled']);
        if (!$userDiv && !$typeDiv) return $items;

        // Use the static methods for the actual diversity logic
        if ($userDiv) {
            $items = self::applyContentDiversity($items);
        }
        if ($typeDiv) {
            $items = self::applyContentTypeDiversity($items);
        }

        return $items;
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    private static function getDaysSinceDateStatic(string $dateString): int
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            return $now->diff($date)->days;
        } catch (\Exception $e) {
            return 999;
        }
    }
}
