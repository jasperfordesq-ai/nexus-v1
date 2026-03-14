<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FeedRankingService - Full EdgeRank Algorithm (15-Signal Pipeline)
 *
 * Implements a complete EdgeRank-style feed ranking with 15 weighted signals:
 *  1. Time Decay        — Hacker News-style freshness decay (72h half-life)
 *  2. Engagement        — Log-scaled likes + comments (prevents viral runaway)
 *  3. Engagement Velocity — Trending detection (rapid engagement in 2h window)
 *  4. Content Type      — Community actions rank above passive content
 *  5. Social Affinity   — Interaction-based relationship scoring
 *  6. Creator Vitality  — Poster's recent activity level
 *  7. Geo Decay         — Haversine distance-based decay
 *  8. Content Quality   — Images, video, hashtags, @mentions, length
 *  9. Context Timing    — Time-of-day / day-of-week boosts (viewer timezone)
 * 10. Conversation Depth — Threaded discussion depth boost
 * 11. Reaction Weighting — Emoji reaction type weights (love=2x, angry=0.5x)
 * 12. Negative Signals  — Hidden posts, muted users, reports
 * 13. CTR Feedback     — Click-through rate with confidence gating
 * 14. User Type Prefs  — Per-user content type personalization
 * 15. Save/Bookmark    — Interest graph from saved content
 *
 * Post-sort: User diversity + Content-type diversity reordering.
 */
class FeedRankingService
{
    const LIKE_WEIGHT = 1;
    const COMMENT_WEIGHT = 5;
    const SHARE_WEIGHT = 8;
    const VITALITY_FULL_THRESHOLD = 7;
    const VITALITY_DECAY_THRESHOLD = 30;
    const VITALITY_MINIMUM = 0.5;
    const GEO_FULL_SCORE_RADIUS = 50;
    const GEO_DECAY_INTERVAL = 100;
    const GEO_DECAY_PER_INTERVAL = 0.03;
    const GEO_MINIMUM_SCORE = 0.15;
    const FRESHNESS_FULL_HOURS = 24;
    const FRESHNESS_HALF_LIFE_HOURS = 72;
    const FRESHNESS_MINIMUM = 0.3;
    const SOCIAL_GRAPH_ENABLED = true;
    const SOCIAL_GRAPH_MAX_BOOST = 2.0;
    const SOCIAL_GRAPH_INTERACTION_DAYS = 90;
    const SOCIAL_GRAPH_FOLLOWER_BOOST = 1.5;
    const NEGATIVE_SIGNALS_ENABLED = true;
    const HIDE_PENALTY = 0.0;
    const MUTE_PENALTY = 0.1;
    const BLOCK_PENALTY = 0.0;
    const REPORT_PENALTY_PER = 0.15;
    const QUALITY_ENABLED = true;
    const QUALITY_IMAGE_BOOST = 1.3;
    const QUALITY_LINK_BOOST = 1.1;
    const QUALITY_LENGTH_MIN = 50;
    const QUALITY_LENGTH_BONUS = 1.2;
    const QUALITY_VIDEO_BOOST = 1.4;
    const QUALITY_HASHTAG_BOOST = 1.1;
    const QUALITY_MENTION_BOOST = 1.15;
    const DIVERSITY_ENABLED = true;
    const DIVERSITY_MAX_CONSECUTIVE = 2;
    const DIVERSITY_PENALTY = 0.5;
    const DIVERSITY_TYPE_ENABLED = true;
    const DIVERSITY_TYPE_MAX_CONSECUTIVE = 3;
    const VELOCITY_ENABLED = true;
    const VELOCITY_WINDOW_HOURS = 2;
    const VELOCITY_THRESHOLD = 3;
    const VELOCITY_MAX_BOOST = 1.8;
    const VELOCITY_DECAY_HOURS = 6;
    const CONVERSATION_DEPTH_ENABLED = true;
    const CONVERSATION_DEPTH_MAX_BOOST = 1.5;
    const CONVERSATION_DEPTH_THRESHOLD = 3;
    const REACTION_WEIGHTS = [
        'love' => 2.0, 'celebrate' => 1.8, 'insightful' => 1.5,
        'like' => 1.0, 'curious' => 0.8, 'sad' => 0.6, 'angry' => 0.5,
    ];
    const VIEW_TRACKING_ENABLED = true;
    const CLICK_TRACKING_ENABLED = true;
    const CTR_ENABLED = true;
    const CTR_MAX_BOOST = 1.5;
    const CTR_MIN_IMPRESSIONS = 5;
    const USER_TYPE_PREFS_ENABLED = true;
    const USER_TYPE_PREFS_MAX_BOOST = 1.4;
    const USER_TYPE_PREFS_LOOKBACK_DAYS = 30;
    const SAVE_SIGNAL_ENABLED = true;
    const SAVE_SIGNAL_MAX_BOOST = 1.35;
    const SAVE_SIGNAL_MIN_SAVES = 2;
    const DEFAULT_SCORE = 1.0;

    private static ?array $config = null;
    /** @var array<int, int> */
    private static array $mutedUserSet = [];



    public static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = [
            'enabled' => true,
            'like_weight' => self::LIKE_WEIGHT, 'comment_weight' => self::COMMENT_WEIGHT,
            'share_weight' => self::SHARE_WEIGHT,
            'vitality_full_days' => self::VITALITY_FULL_THRESHOLD,
            'vitality_decay_days' => self::VITALITY_DECAY_THRESHOLD,
            'vitality_minimum' => self::VITALITY_MINIMUM,
            'geo_full_radius' => self::GEO_FULL_SCORE_RADIUS,
            'geo_decay_interval' => self::GEO_DECAY_INTERVAL,
            'geo_decay_rate' => self::GEO_DECAY_PER_INTERVAL,
            'geo_minimum' => self::GEO_MINIMUM_SCORE,
            'freshness_enabled' => true,
            'freshness_full_hours' => self::FRESHNESS_FULL_HOURS,
            'freshness_half_life' => self::FRESHNESS_HALF_LIFE_HOURS,
            'freshness_minimum' => self::FRESHNESS_MINIMUM,
            'freshness_gravity' => 1.0,
            'social_graph_enabled' => self::SOCIAL_GRAPH_ENABLED,
            'social_graph_max_boost' => self::SOCIAL_GRAPH_MAX_BOOST,
            'social_graph_lookback_days' => self::SOCIAL_GRAPH_INTERACTION_DAYS,
            'social_graph_follower_boost' => self::SOCIAL_GRAPH_FOLLOWER_BOOST,
            'negative_signals_enabled' => self::NEGATIVE_SIGNALS_ENABLED,
            'hide_penalty' => self::HIDE_PENALTY, 'mute_penalty' => self::MUTE_PENALTY,
            'block_penalty' => self::BLOCK_PENALTY, 'report_penalty_per' => self::REPORT_PENALTY_PER,
            'quality_enabled' => self::QUALITY_ENABLED,
            'quality_image_boost' => self::QUALITY_IMAGE_BOOST,
            'quality_link_boost' => self::QUALITY_LINK_BOOST,
            'quality_length_min' => self::QUALITY_LENGTH_MIN,
            'quality_length_bonus' => self::QUALITY_LENGTH_BONUS,
            'quality_video_boost' => self::QUALITY_VIDEO_BOOST,
            'quality_hashtag_boost' => self::QUALITY_HASHTAG_BOOST,
            'quality_mention_boost' => self::QUALITY_MENTION_BOOST,
            'diversity_enabled' => self::DIVERSITY_ENABLED,
            'diversity_max_consecutive' => self::DIVERSITY_MAX_CONSECUTIVE,
            'diversity_penalty' => self::DIVERSITY_PENALTY,
            'diversity_type_enabled' => self::DIVERSITY_TYPE_ENABLED,
            'diversity_type_max_consecutive' => self::DIVERSITY_TYPE_MAX_CONSECUTIVE,
            'velocity_enabled' => self::VELOCITY_ENABLED,
            'velocity_window_hours' => self::VELOCITY_WINDOW_HOURS,
            'velocity_threshold' => self::VELOCITY_THRESHOLD,
            'velocity_max_boost' => self::VELOCITY_MAX_BOOST,
            'velocity_decay_hours' => self::VELOCITY_DECAY_HOURS,
            'conversation_depth_enabled' => self::CONVERSATION_DEPTH_ENABLED,
            'conversation_depth_max_boost' => self::CONVERSATION_DEPTH_MAX_BOOST,
            'conversation_depth_threshold' => self::CONVERSATION_DEPTH_THRESHOLD,
            'ctr_enabled' => self::CTR_ENABLED,
            'ctr_max_boost' => self::CTR_MAX_BOOST,
            'ctr_min_impressions' => self::CTR_MIN_IMPRESSIONS,
            'user_type_prefs_enabled' => self::USER_TYPE_PREFS_ENABLED,
            'user_type_prefs_max_boost' => self::USER_TYPE_PREFS_MAX_BOOST,
            'user_type_prefs_lookback_days' => self::USER_TYPE_PREFS_LOOKBACK_DAYS,
            'save_signal_enabled' => self::SAVE_SIGNAL_ENABLED,
            'save_signal_max_boost' => self::SAVE_SIGNAL_MAX_BOOST,
            'save_signal_min_saves' => self::SAVE_SIGNAL_MIN_SAVES,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetchColumn();
            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['feed_algorithm'])) {
                    self::$config = array_merge($defaults, $configArr['feed_algorithm']);
                    self::validateConfig();
                    return self::$config;
                }
            }
        } catch (\Exception $e) {}

        self::$config = $defaults;
        self::validateConfig();
        return self::$config;
    }

    /**
     * Validate config values are within reasonable bounds
     */
    private static function validateConfig(): void
    {
        $c = &self::$config;

        // Weights must be non-negative
        $c['like_weight'] = max(0, (float)$c['like_weight']);
        $c['comment_weight'] = max(0, (float)$c['comment_weight']);
        $c['share_weight'] = max(0, (float)$c['share_weight']);

        // Vitality bounds
        $c['vitality_full_days'] = max(1, (int)$c['vitality_full_days']);
        $c['vitality_decay_days'] = max($c['vitality_full_days'] + 1, (int)$c['vitality_decay_days']);
        $c['vitality_minimum'] = max(0.0, min(1.0, (float)$c['vitality_minimum']));

        // Geo bounds
        $c['geo_full_radius'] = max(0, (float)$c['geo_full_radius']);
        $c['geo_decay_interval'] = max(1, (float)$c['geo_decay_interval']);
        $c['geo_decay_rate'] = max(0.0, min(1.0, (float)$c['geo_decay_rate']));
        $c['geo_minimum'] = max(0.0, min(1.0, (float)$c['geo_minimum']));

        // Freshness bounds
        $c['freshness_half_life'] = max(1, (float)$c['freshness_half_life']);
        $c['freshness_minimum'] = max(0.0, min(1.0, (float)$c['freshness_minimum']));
        $c['freshness_gravity'] = max(0.1, min(3.0, (float)($c['freshness_gravity'] ?? 1.0)));

        // Social graph bounds
        $c['social_graph_max_boost'] = max(1.0, min(5.0, (float)$c['social_graph_max_boost']));
        $c['social_graph_lookback_days'] = max(1, (int)$c['social_graph_lookback_days']);

        // Quality boost bounds (>= 1.0, max 3.0)
        foreach (['quality_image_boost', 'quality_link_boost', 'quality_length_bonus', 'quality_video_boost', 'quality_hashtag_boost', 'quality_mention_boost'] as $key) {
            $c[$key] = max(1.0, min(3.0, (float)$c[$key]));
        }
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
        self::$mutedUserSet = [];
    }


    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Rank feed_activity items in-memory using 15-signal EdgeRank
     *
     * @param array       $items          Feed items from FeedService::getFeed()['items']
     * @param int|null    $viewerId       Authenticated user ID (null for anonymous)
     * @param string|null $viewerTimezone IANA timezone, null = UTC
     * @return array Re-ranked items
     */
    public static function rankFeedItems(array $items, ?int $viewerId = null, ?string $viewerTimezone = null): array
    {
        if (!self::isEnabled() || count($items) < 2) {
            return $items;
        }

        $config = self::getConfig();
        $typeWeights = [
            'event' => 1.4, 'challenge' => 1.3, 'poll' => 1.25,
            'volunteer' => 1.2, 'goal' => 1.1, 'post' => 1.0,
            'listing' => 0.9, 'job' => 0.9, 'review' => 0.8,
        ];

        $authorIds = array_unique(array_filter(array_map(static fn($i) => (int)($i['user_id'] ?? 0), $items)));
        $postIds = array_unique(array_filter(array_map(static fn($i) => (int)($i['id'] ?? $i['post_id'] ?? 0), $items)));

        $connectedSet = [];
        $socialScores = [];
        if ($viewerId) {
            $connectedIds = self::getViewerConnectedUserIds($viewerId);
            $connectedSet = array_flip($connectedIds);
            if (!empty($authorIds)) { $socialScores = self::getBatchSocialGraphScores($viewerId, $authorIds); }
        }

        $viewerLat = null; $viewerLon = null;
        if ($viewerId) { [$viewerLat, $viewerLon] = self::getUserCoordinates($viewerId); }

        $authorCoords = !empty($authorIds) ? self::getBatchUserCoordinates($authorIds) : [];
        $vitalityScores = !empty($authorIds) ? self::getBatchVitalityScores($authorIds) : [];
        $velocityScores = (self::VELOCITY_ENABLED && !empty($postIds)) ? self::getBatchEngagementVelocity($postIds) : [];
        $conversationDepths = (self::CONVERSATION_DEPTH_ENABLED && !empty($postIds)) ? self::getBatchConversationDepth($postIds) : [];
        $reactionScores = !empty($postIds) ? self::getBatchReactionScores($postIds) : [];
        $negativeScores = ($viewerId && !empty($postIds)) ? self::getBatchNegativeSignals($viewerId, $postIds, $authorIds) : [];
        $ctrScores = (!empty($config['ctr_enabled']) && !empty($postIds)) ? self::getBatchClickThroughRates($postIds) : [];
        $userTypePrefs = (!empty($config['user_type_prefs_enabled']) && $viewerId) ? self::getUserTypePreferences($viewerId) : [];
        $saveScores = (!empty($config['save_signal_enabled']) && !empty($postIds)) ? self::getBatchSaveScores($postIds) : [];

        foreach ($items as &$item) {
            $postId = (int)($item['id'] ?? $item['post_id'] ?? 0);
            $authorId = (int)($item['user_id'] ?? 0);
            $score = 1.0;

            if ($authorId && self::isAuthorMuted($authorId)) { $item['_edge_rank'] = 0.0; continue; }

            // 1. Time Decay
            $createdAt = $item['created_at'] ?? null;
            if ($createdAt) {
                $hoursAgo = max(0, (int)round((time() - strtotime($createdAt)) / 3600));
                $score *= self::hackerNewsDecay($hoursAgo);
            }

            // 2. Engagement
            $likes = (int)($item['likes_count'] ?? 0);
            $comments = (int)($item['comments_count'] ?? 0);
            $points = ($likes * $config['like_weight']) + ($comments * $config['comment_weight']);
            if ($points > 0) { $score *= 1.0 + min(log(1.0 + $points) * 0.3, 2.0); }
            else { $score *= 1.05; }

            // 3. Velocity (with temporal decay via VELOCITY_DECAY_HOURS)
            if (self::VELOCITY_ENABLED && isset($velocityScores[$postId])) {
                $v = $velocityScores[$postId];
                if ($v >= self::VELOCITY_THRESHOLD) {
                    $rawBoost = min(self::VELOCITY_MAX_BOOST, 1.0 + (($v - self::VELOCITY_THRESHOLD) / self::VELOCITY_THRESHOLD) * 0.4);
                    // Temporal decay: velocity boost fades as post ages past VELOCITY_DECAY_HOURS
                    $postAgeHours = $createdAt ? max(0, (time() - strtotime($createdAt)) / 3600) : 0;
                    $velocityDecay = max(0.0, 1.0 - ($postAgeHours / (self::VELOCITY_DECAY_HOURS * 2)));
                    $score *= 1.0 + ($rawBoost - 1.0) * $velocityDecay;
                }
            }

            // 4. Type weight
            $sourceType = $item['type'] ?? $item['source_type'] ?? 'post';
            $score *= $typeWeights[$sourceType] ?? 1.0;

            // 5. Social Affinity (recency-weighted)
            if ($authorId && $viewerId) {
                if (isset($socialScores[$authorId]) && $socialScores[$authorId] > 0) {
                    $bf = ($config['social_graph_max_boost'] - 1) / 4;
                    $score *= min($config['social_graph_max_boost'], 1.0 + (log($socialScores[$authorId] + 1, 2) * $bf));
                } elseif (isset($connectedSet[$authorId])) {
                    $score *= (float)$config['social_graph_follower_boost'];
                }
            }

            // 6. Vitality
            if ($authorId && isset($vitalityScores[$authorId])) { $score *= $vitalityScores[$authorId]; }

            // 7. Geo Decay
            if ($viewerLat !== null && $authorId && isset($authorCoords[$authorId])) {
                [$aLat, $aLon] = $authorCoords[$authorId];
                $score *= self::calculateGeoDecayScore($viewerLat, $viewerLon, $aLat, $aLon);
            }

            // 8. Quality
            if ($config['quality_enabled']) {
                $ic = (string)($item['content'] ?? '');
                if (!empty($item['image_url'])) { $score *= (float)$config['quality_image_boost']; }
                if (preg_match('/(?:youtube\.com|youtu\.be|vimeo\.com|tiktok\.com|dailymotion\.com)/i', $ic)) { $score *= (float)$config['quality_video_boost']; }
                if (strpos($ic, '#') !== false) { $score *= (float)$config['quality_hashtag_boost']; }
                if (strpos($ic, '@') !== false) { $score *= (float)$config['quality_mention_boost']; }
                if (strlen($ic) >= $config['quality_length_min']) { $score *= (float)$config['quality_length_bonus']; }
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
            if ($viewerId && isset($negativeScores[$postId])) { $score *= $negativeScores[$postId]; }

            // 13. Click-Through Rate feedback loop
            if (!empty($config['ctr_enabled']) && isset($ctrScores[$postId])) {
                $ctr = $ctrScores[$postId];
                $impressions = $ctrScores['_impressions'][$postId] ?? 0;
                if ($impressions >= ($config['ctr_min_impressions'] ?? 5)) {
                    $ctrMultiplier = 1.0 + ($ctr - 0.1) * (($config['ctr_max_boost'] ?? 1.5) - 1.0) / 0.9;
                    $score *= max(0.8, min((float)($config['ctr_max_boost'] ?? 1.5), $ctrMultiplier));
                }
            }

            // 14. Per-user content type preferences
            if (!empty($config['user_type_prefs_enabled']) && !empty($userTypePrefs)) {
                $itemType = $item['type'] ?? $item['source_type'] ?? 'post';
                if (isset($userTypePrefs[$itemType])) {
                    $score *= $userTypePrefs[$itemType];
                }
            }

            // 15. Save/Bookmark interest signal
            if (!empty($config['save_signal_enabled']) && isset($saveScores[$postId])) {
                $saves = $saveScores[$postId];
                if ($saves >= ($config['save_signal_min_saves'] ?? 2)) {
                    $saveBoost = min(
                        (float)($config['save_signal_max_boost'] ?? 1.35),
                        1.0 + log($saves, 10) * 0.2
                    );
                    $score *= $saveBoost;
                }
            }

            $item['_edge_rank'] = $score;
        }
        unset($item);

        usort($items, static function (array $a, array $b): int {
            return ($b['_edge_rank'] ?? 0) <=> ($a['_edge_rank'] ?? 0);
        });

        $items = self::applyDiversityInPlace($items, self::getDiversityConfig());

        foreach ($items as &$item) { unset($item['_edge_rank']); }
        unset($item);
        return $items;
    }


    // =========================================================================
    // BATCH DATA LOADING METHODS (Private, Tenant-Scoped)
    // =========================================================================

    /**
     * Batch social graph with recency weighting (7d:3x, 30d:2x, 90d:1x).
     * @return array<int, float> authorId => weighted interaction score
     */
    private static function getBatchSocialGraphScores(int $viewerId, array $authorIds): array
    {
        if (empty($authorIds) || $viewerId === 0) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($authorIds), '?'));
            $days = self::SOCIAL_GRAPH_INTERACTION_DAYS;
            $sql = "SELECT p.user_id AS author_id, SUM(CASE
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 3
                    WHEN va.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 2
                    ELSE 1
                END) AS weighted_interactions FROM (
                SELECT target_id, created_at FROM likes WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
                UNION ALL
                SELECT target_id, created_at FROM comments WHERE user_id=? AND target_type='post' AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
            ) AS va JOIN feed_posts p ON p.id=va.target_id AND p.tenant_id=? WHERE p.user_id IN ($ph) GROUP BY p.user_id";
            $params = array_merge([$viewerId,$tenantId,$days,$viewerId,$tenantId,$days,$tenantId], $authorIds);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) { $r[(int)$row['author_id']] = (float)$row['weighted_interactions']; }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    /** @return array<int, float> userId => vitality (0.5-1.0) */
    private static function getBatchVitalityScores(array $userIds): array
    {
        if (empty($userIds)) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($userIds), '?'));
            $sql = "SELECT user_id, MAX(created_at) AS last_active FROM (
                SELECT user_id, MAX(created_at) AS created_at FROM activity_log WHERE user_id IN ($ph) AND tenant_id=? AND action IN ('login','post_created','comment_added','like_added') GROUP BY user_id
                UNION ALL
                SELECT user_id, MAX(created_at) AS created_at FROM feed_posts WHERE user_id IN ($ph) AND tenant_id=? GROUP BY user_id
            ) AS combined GROUP BY user_id";
            $params = array_merge($userIds, [$tenantId], $userIds, [$tenantId]);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) { $r[(int)$row['user_id']] = self::computeVitalityFromDays(self::getDaysSinceDate($row['last_active'])); }
            $config = self::getConfig();
            foreach ($userIds as $uid) { if (!isset($r[$uid])) { $r[$uid] = (float)$config['vitality_minimum']; } }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    /** @return array<int, int> postId => recent interaction count */
    private static function getBatchEngagementVelocity(array $postIds): array
    {
        if (empty($postIds)) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $hrs = self::VELOCITY_WINDOW_HOURS;
            $sql = "SELECT target_id AS post_id, COUNT(*) AS velocity FROM (
                SELECT target_id FROM likes WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? HOUR)
                UNION ALL
                SELECT target_id FROM comments WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? HOUR)
            ) AS recent GROUP BY target_id";
            $params = array_merge($postIds, [$tenantId,$hrs], $postIds, [$tenantId,$hrs]);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) { $r[(int)$row['post_id']] = (int)$row['velocity']; }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    /** @return array<int, int> postId => reply count */
    private static function getBatchConversationDepth(array $postIds): array
    {
        if (empty($postIds)) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $sql = "SELECT target_id AS post_id, COUNT(*) AS depth FROM comments WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? AND parent_id IS NOT NULL GROUP BY target_id";
            $params = array_merge($postIds, [$tenantId]);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) { $r[(int)$row['post_id']] = (int)$row['depth']; }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    /** @return array<int, float> postId => weighted reaction score */
    private static function getBatchReactionScores(array $postIds): array
    {
        if (empty($postIds)) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $sql = "SELECT target_id AS post_id, reaction_type, COUNT(*) AS cnt FROM reactions WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? GROUP BY target_id, reaction_type";
            $params = array_merge($postIds, [$tenantId]);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) {
                $pid = (int)$row['post_id'];
                $type = strtolower($row['reaction_type'] ?? 'like');
                $weight = self::REACTION_WEIGHTS[$type] ?? 1.0;
                $r[$pid] = ($r[$pid] ?? 0) + ($weight * (int)$row['cnt']);
            }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    /**
     * Batch negative signals. Also populates self::$mutedUserSet.
     * @return array<int, float> postId => multiplier (0.0-1.0)
     */
    private static function getBatchNegativeSignals(int $viewerId, array $postIds, array $authorIds): array
    {
        $config = self::getConfig();
        if (empty($config['negative_signals_enabled']) || $viewerId === 0) { return []; }
        $result = [];
        $tenantId = TenantContext::getId();
        self::$mutedUserSet = [];
        try {
            if (!empty($postIds)) {
                $ph = implode(',', array_fill(0, count($postIds), '?'));
                $rows = Database::query("SELECT post_id FROM feed_hidden WHERE user_id=? AND tenant_id=? AND post_id IN ($ph)", array_merge([$viewerId,$tenantId], $postIds))->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($rows as $hid) { $result[(int)$hid] = (float)$config['hide_penalty']; }
            }
            if (!empty($authorIds)) {
                $ph = implode(',', array_fill(0, count($authorIds), '?'));
                $rows = Database::query("SELECT muted_user_id FROM feed_muted_users WHERE user_id=? AND tenant_id=? AND muted_user_id IN ($ph)", array_merge([$viewerId,$tenantId], $authorIds))->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($rows as $mid) { self::$mutedUserSet[(int)$mid] = 1; }
            }
            if (!empty($postIds)) {
                $ph = implode(',', array_fill(0, count($postIds), '?'));
                $rows = Database::query("SELECT target_id, COUNT(*) AS report_count FROM reports WHERE target_type='post' AND target_id IN ($ph) AND tenant_id=? GROUP BY target_id", array_merge($postIds, [$tenantId]))->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $pid = (int)$row['target_id'];
                    if (!isset($result[$pid])) { $result[$pid] = max(0.1, 1.0 - (int)$row['report_count'] * (float)$config['report_penalty_per']); }
                }
            }
        } catch (\Exception $e) {}
        return $result;
    }

    private static function isAuthorMuted(int $authorId): bool
    {
        return isset(self::$mutedUserSet[$authorId]);
    }


    // =========================================================================
    // USER TYPE PREFERENCES (Signal 14)
    // =========================================================================

    /**
     * Per-user content type preferences from engagement history.
     * @return array<string, float> sourceType => multiplier (1.0 to max_boost)
     */
    private static function getUserTypePreferences(int $viewerId): array
    {
        if ($viewerId === 0) { return []; }
        $config = self::getConfig();
        $maxBoost = (float)($config['user_type_prefs_max_boost'] ?? 1.4);
        $lookbackDays = (int)($config['user_type_prefs_lookback_days'] ?? 30);

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
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) { return []; }

            $maxEng = 0;
            $typeCounts = [];
            foreach ($rows as $row) {
                $typeCounts[$row['source_type']] = (int)$row['engagements'];
                $maxEng = max($maxEng, (int)$row['engagements']);
            }
            if ($maxEng === 0) { return []; }

            $result = [];
            foreach ($typeCounts as $type => $count) {
                $normalized = $count / $maxEng;
                $result[$type] = 1.0 + ($normalized * ($maxBoost - 1.0));
            }
            return $result;
        } catch (\Exception $e) { return []; }
    }

    // =========================================================================
    // SAVE/BOOKMARK INTEREST GRAPH (Signal 15)
    // =========================================================================

    /**
     * Batch save/bookmark counts across listing_favorites and user_saved_listings.
     * @return array<int, int> postId => total save count
     */
    private static function getBatchSaveScores(array $postIds): array
    {
        if (empty($postIds)) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));

            // Count saves from user_saved_listings (for listing-type feed items)
            // and listing_favorites (for CF-style saves)
            $sql = "SELECT fa.source_id AS post_id, (
                        COALESCE((SELECT COUNT(*) FROM user_saved_listings usl
                            WHERE usl.listing_id = fa.source_id AND usl.tenant_id = ?), 0) +
                        COALESCE((SELECT COUNT(*) FROM listing_favorites lf
                            WHERE lf.listing_id = fa.source_id AND lf.tenant_id = ?), 0)
                    ) AS save_count
                    FROM feed_activity fa
                    WHERE fa.source_id IN ($ph) AND fa.tenant_id = ?
                    AND fa.source_type IN ('listing', 'post')
                    GROUP BY fa.source_id
                    HAVING save_count > 0";
            $params = array_merge([$tenantId, $tenantId], $postIds, [$tenantId]);
            $rows = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $r = [];
            foreach ($rows as $row) { $r[(int)$row['post_id']] = (int)$row['save_count']; }
            return $r;
        } catch (\Exception $e) { return []; }
    }

    // =========================================================================
    // DIVERSITY (Post-Sort Processing)
    // =========================================================================

    private static function applyDiversityInPlace(array $items, array $config): array
    {
        if (empty($items)) { return $items; }
        $userDiv = !empty($config['enabled']);
        $typeDiv = !empty($config['type_enabled']);
        if (!$userDiv && !$typeDiv) { return $items; }
        $maxUser = $config['max_consecutive'] ?? 2;
        $maxType = $config['type_max_consecutive'] ?? 3;
        $result = []; $deferred = [];

        foreach ($items as $item) {
            $userId = (int)($item['user_id'] ?? 0);
            $cType = $item['type'] ?? $item['content_type'] ?? 'post';
            $shouldDefer = false;
            if ($userDiv && $userId > 0) {
                $c = 0;
                for ($i = count($result)-1; $i >= 0 && $i >= count($result)-$maxUser; $i--) {
                    if ((int)($result[$i]['user_id'] ?? 0) === $userId) { $c++; } else { break; }
                }
                if ($c >= $maxUser) { $shouldDefer = true; }
            }
            if (!$shouldDefer && $typeDiv) {
                $c = 0;
                for ($i = count($result)-1; $i >= 0 && $i >= count($result)-$maxType; $i--) {
                    if (($result[$i]['type'] ?? $result[$i]['content_type'] ?? 'post') === $cType) { $c++; } else { break; }
                }
                if ($c >= $maxType) { $shouldDefer = true; }
            }
            if ($shouldDefer) { $deferred[] = $item; } else { $result[] = $item; }
        }

        foreach ($deferred as $di) {
            $dUid = (int)($di['user_id'] ?? 0);
            $dType = $di['type'] ?? $di['content_type'] ?? 'post';
            $ins = false;
            for ($i = 0; $i < count($result); $i++) {
                $ok = true;
                if ($userDiv && $dUid > 0) {
                    for ($j = max(0,$i-$maxUser+1); $j < min(count($result),$i+$maxUser); $j++) {
                        if ((int)($result[$j]['user_id'] ?? 0) === $dUid) { $ok = false; break; }
                    }
                }
                if ($ok && $typeDiv) {
                    for ($j = max(0,$i-$maxType+1); $j < min(count($result),$i+$maxType); $j++) {
                        if (($result[$j]['type'] ?? $result[$j]['content_type'] ?? 'post') === $dType) { $ok = false; break; }
                    }
                }
                if ($ok) { array_splice($result, $i, 0, [$di]); $ins = true; break; }
            }
            if (!$ins) { $result[] = $di; }
        }
        return $result;
    }


    // =========================================================================
    // CONTEXT TIMING
    // =========================================================================

    private static function contextualBoost(string $sourceType, ?string $viewerTimezone = null): float
    {
        try {
            $tz = $viewerTimezone ? new \DateTimeZone($viewerTimezone) : new \DateTimeZone('UTC');
            $now = new \DateTime('now', $tz);
            $hour = (int)$now->format('G');
            $dow  = (int)$now->format('N');
        } catch (\Exception $e) {
            $hour = (int)date('G');
            $dow  = (int)date('N');
        }
        $isWeekend = $dow >= 6; $isWeekday = !$isWeekend;
        $isMorning = $hour >= 7 && $hour < 12; $isEvening = $hour >= 19 && $hour < 22;
        switch ($sourceType) {
            case 'event':
                if ($dow === 1 && $isMorning) return 1.20;
                if ($dow === 5 && $hour >= 14) return 1.15;
                return 1.0;
            case 'volunteer': return $isWeekend ? 1.18 : 1.0;
            case 'job': return ($isWeekday && $isMorning && $dow <= 3) ? 1.15 : 1.0;
            case 'post': case 'poll':
                if ($isEvening) return 1.12;
                if ($hour >= 2 && $hour < 6) return 0.90;
                return 1.0;
            case 'listing': return ($isWeekend && $isMorning) ? 1.10 : 1.0;
            default: return 1.0;
        }
    }


    // =========================================================================
    // TRACKING METHODS
    // =========================================================================

    public static function recordImpression(int $postId, int $userId): void
    {
        if (!self::VIEW_TRACKING_ENABLED || $userId === 0 || $postId === 0) { return; }
        try {
            Database::query("INSERT INTO feed_impressions (post_id,user_id,tenant_id,created_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE view_count=view_count+1,updated_at=NOW()", [$postId, $userId, TenantContext::getId()]);
        } catch (\Exception $e) {}
    }

    public static function recordClick(int $postId, int $userId): void
    {
        if (!self::CLICK_TRACKING_ENABLED || $userId === 0 || $postId === 0) { return; }
        try {
            Database::query("INSERT INTO feed_clicks (post_id,user_id,tenant_id,created_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE click_count=click_count+1,updated_at=NOW()", [$postId, $userId, TenantContext::getId()]);
        } catch (\Exception $e) {}
    }

    /**
     * Batch CTR with impression counts for confidence gating.
     * @return array postId => CTR, plus '_impressions' => [postId => count]
     */
    public static function getBatchClickThroughRates(array $postIds): array
    {
        if (empty($postIds) || !self::CLICK_TRACKING_ENABLED) { return []; }
        try {
            $tenantId = TenantContext::getId();
            $ph = implode(',', array_fill(0, count($postIds), '?'));
            $rows = Database::query("SELECT fi.post_id, SUM(fi.view_count) AS impressions, COALESCE(SUM(fc.click_count),0)/GREATEST(SUM(fi.view_count),1) AS ctr FROM feed_impressions fi LEFT JOIN feed_clicks fc ON fc.post_id=fi.post_id AND fc.tenant_id=fi.tenant_id WHERE fi.post_id IN ($ph) AND fi.tenant_id=? GROUP BY fi.post_id", array_merge($postIds, [$tenantId]))->fetchAll(\PDO::FETCH_ASSOC);
            $r = ['_impressions' => []];
            foreach ($rows as $row) {
                $pid = (int)$row['post_id'];
                $r[$pid] = min(1.0, (float)$row['ctr']);
                $r['_impressions'][$pid] = (int)$row['impressions'];
            }
            return $r;
        } catch (\Exception $e) { return []; }
    }


    // =========================================================================
    // HACKER NEWS DECAY
    // =========================================================================

    private static function hackerNewsDecay(int $hoursAgo): float
    {
        $config = self::getConfig();
        $halfLife = max(1.0, (float)$config['freshness_half_life']);
        $gravity = (float)($config['freshness_gravity'] ?? 1.0);
        $decay = 1.0 / pow(1.0 + $hoursAgo / $halfLife, $gravity);
        return max((float)$config['freshness_minimum'], $decay);
    }


    // =========================================================================
    // COORDINATE HELPERS
    // =========================================================================

    /**
     * Get lat/lon for a single user. Returns [lat, lon] or [null, null].
     *
     * @return array{0: float|null, 1: float|null}
     */
    private static function getUserCoordinates(int $userId): array
    {
        try {
            $row = Database::query(
                "SELECT latitude, longitude FROM users WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$userId, TenantContext::getId()]
            )->fetch(\PDO::FETCH_ASSOC);

            if ($row && $row['latitude'] !== null && $row['longitude'] !== null) {
                return [(float)$row['latitude'], (float)$row['longitude']];
            }
        } catch (\Exception $e) {
            // Ignore — geo is best-effort
        }

        return [null, null];
    }

    /**
     * Batch-load lat/lon for a list of user IDs.
     * Returns an array keyed by user_id => [lat, lon].
     *
     * @param  int[]  $userIds
     * @return array<int, array{0: float, 1: float}>
     */
    private static function getBatchUserCoordinates(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $rows = Database::query(
                "SELECT id, latitude, longitude FROM users
                  WHERE id IN ($placeholders)
                    AND latitude IS NOT NULL AND longitude IS NOT NULL",
                $userIds
            )->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $result[(int)$row['id']] = [(float)$row['latitude'], (float)$row['longitude']];
            }

            return $result;
        } catch (\Exception $e) {
            error_log("FeedRankingService::getBatchUserCoordinates error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get IDs of users the viewer has exchanged hours with (social graph proxy)
     * Used for affinity boosting in EdgeRank.
     */
    private static function getViewerConnectedUserIds(int $viewerId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $rows = Database::query(
                "SELECT DISTINCT
                    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as connected_id
                 FROM transactions
                 WHERE (sender_id = ? OR receiver_id = ?)
                   AND tenant_id = ?
                   AND status = 'completed'",
                [$viewerId, $viewerId, $viewerId, $tenantId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            return array_map('intval', $rows);
        } catch (\Exception $e) {
            error_log("FeedRankingService::getViewerConnectedUserIds error: " . $e->getMessage());
            return [];
        }
    }


    public static function getViewerCoordinates(int $viewerId): array
    {
        try {
            $tenantId = TenantContext::getId();
            $sql = "SELECT latitude, longitude FROM users WHERE id = ? AND tenant_id = ?";
            $result = Database::query($sql, [$viewerId, $tenantId])->fetch();

            return [
                'lat' => $result['latitude'] ?? null,
                'lon' => $result['longitude'] ?? null
            ];
        } catch (\Exception $e) {
            return ['lat' => null, 'lon' => null];
        }
    }



    // =========================================================================
    // SINGLE-POST SCORING
    // =========================================================================

    /**
     * Calculate rank score for a single post using all 15 signals.
     * Fully aligned with rankFeedItems() for consistent scoring.
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
        $config = self::getConfig();
        $postId = (int)($post['id'] ?? $post['post_id'] ?? 0);
        $posterId = (int)($post['user_id'] ?? 0);
        $sourceType = $post['type'] ?? $post['source_type'] ?? 'post';

        // 1. Time Decay
        $createdAt = $post['created_at'] ?? null;
        $timeDecay = 1.0;
        if ($createdAt) {
            $hoursAgo = max(0, (int)round((time() - strtotime($createdAt)) / 3600));
            $timeDecay = self::hackerNewsDecay($hoursAgo);
        }

        // 2. Engagement
        $likesCount = (int)($post['likes_count'] ?? 0);
        $commentsCount = (int)($post['comments_count'] ?? 0);
        $engagementScore = self::calculateEngagementScore($likesCount, $commentsCount) * $timeDecay;

        // 3. Velocity (single-post: query directly)
        $velocityBoost = 1.0;
        if (self::VELOCITY_ENABLED && $postId) {
            $velocityData = self::getBatchEngagementVelocity([$postId]);
            if (isset($velocityData[$postId]) && $velocityData[$postId] >= self::VELOCITY_THRESHOLD) {
                $v = $velocityData[$postId];
                $rawBoost = min(self::VELOCITY_MAX_BOOST, 1.0 + (($v - self::VELOCITY_THRESHOLD) / self::VELOCITY_THRESHOLD) * 0.4);
                $postAgeHours = $createdAt ? max(0, (time() - strtotime($createdAt)) / 3600) : 0;
                $velocityDecay = max(0.0, 1.0 - ($postAgeHours / (self::VELOCITY_DECAY_HOURS * 2)));
                $velocityBoost = 1.0 + ($rawBoost - 1.0) * $velocityDecay;
            }
        }

        // 4. Type weight
        $typeWeights = [
            'event' => 1.4, 'challenge' => 1.3, 'poll' => 1.25,
            'volunteer' => 1.2, 'goal' => 1.1, 'post' => 1.0,
            'listing' => 0.9, 'job' => 0.9, 'review' => 0.8,
        ];
        $typeWeight = $typeWeights[$sourceType] ?? 1.0;

        // 5. Social Affinity
        $socialBoost = 1.0;
        if ($viewerId && $posterId) {
            $socialBoost = self::calculateSocialGraphScore($viewerId, $posterId);
        }

        // 6. Vitality
        $vitalityScore = self::calculateVitalityScore($posterId);

        // 7. Geo Decay
        $posterLat = isset($post['author_lat']) ? (float)$post['author_lat'] : null;
        $posterLon = isset($post['author_lon']) ? (float)$post['author_lon'] : null;
        $geoScore = self::calculateGeoDecayScore($viewerLat, $viewerLon, $posterLat, $posterLon);

        // 8. Content Quality
        $qualityScore = self::calculateContentQualityScore($post);

        // 9. Context Timing
        $contextBoost = self::contextualBoost($sourceType);

        // 10. Conversation Depth
        $depthBoost = 1.0;
        if (self::CONVERSATION_DEPTH_ENABLED && $postId) {
            $depths = self::getBatchConversationDepth([$postId]);
            if (isset($depths[$postId]) && $depths[$postId] >= self::CONVERSATION_DEPTH_THRESHOLD) {
                $d = $depths[$postId];
                $depthBoost = min(self::CONVERSATION_DEPTH_MAX_BOOST, 1.0 + ($d / (self::CONVERSATION_DEPTH_THRESHOLD * 3)) * 0.5);
            }
        }

        // 11. Reaction Weighting
        $reactionBoost = 1.0;
        if ($postId) {
            $reactions = self::getBatchReactionScores([$postId]);
            if (isset($reactions[$postId]) && $reactions[$postId] > 0) {
                $reactionBoost = 1.0 + min($reactions[$postId] * 0.1, 1.0);
            }
        }

        // 12. Negative Signals
        $negativeScore = 1.0;
        if ($viewerId && $postId) {
            $negativeScore = self::calculateNegativeSignalsScore($viewerId, $postId, $posterId);
        }

        // 13. CTR Feedback
        $ctrBoost = 1.0;
        if (!empty($config['ctr_enabled']) && $postId) {
            $ctrData = self::getBatchClickThroughRates([$postId]);
            if (isset($ctrData[$postId])) {
                $ctr = $ctrData[$postId];
                $impressions = $ctrData['_impressions'][$postId] ?? 0;
                if ($impressions >= ($config['ctr_min_impressions'] ?? 5)) {
                    $ctrMultiplier = 1.0 + ($ctr - 0.1) * (($config['ctr_max_boost'] ?? 1.5) - 1.0) / 0.9;
                    $ctrBoost = max(0.8, min((float)($config['ctr_max_boost'] ?? 1.5), $ctrMultiplier));
                }
            }
        }

        // 14. User Type Preferences
        $typePrefBoost = 1.0;
        if (!empty($config['user_type_prefs_enabled']) && $viewerId) {
            $prefs = self::getUserTypePreferences($viewerId);
            if (isset($prefs[$sourceType])) {
                $typePrefBoost = $prefs[$sourceType];
            }
        }

        // 15. Save/Bookmark signal
        $saveBoost = 1.0;
        if (!empty($config['save_signal_enabled']) && $postId) {
            $saves = self::getBatchSaveScores([$postId]);
            if (isset($saves[$postId]) && $saves[$postId] >= ($config['save_signal_min_saves'] ?? 2)) {
                $saveBoost = min(
                    (float)($config['save_signal_max_boost'] ?? 1.35),
                    1.0 + log($saves[$postId], 10) * 0.2
                );
            }
        }

        return $engagementScore * $velocityBoost * $typeWeight * $socialBoost
             * $vitalityScore * $geoScore * $qualityScore * $contextBoost
             * $depthBoost * $reactionBoost * $negativeScore
             * $ctrBoost * $typePrefBoost * $saveBoost;
    }


    public static function getRankingOrderBySql(?float $viewerLat = null, ?float $viewerLon = null): string
    {
        $engagement = self::getEngagementScoreSql();
        $vitality = self::getVitalityScoreSql();
        $geoDecay = self::getGeoDecayScoreSql($viewerLat, $viewerLon);
        $freshness = self::getFreshnessScoreSql();

        return "({$engagement}) * ({$vitality}) * ({$geoDecay}) * ({$freshness}) DESC, p.created_at DESC";
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
                    WHERE user_id = ? AND tenant_id = ? AND action IN ('login', 'post_created', 'comment_added', 'like_added')";
            $result = Database::query($sql, [$userId, TenantContext::getId()])->fetch();

            if ($result && $result['last_activity']) {
                return $result['last_activity'];
            }

            // Fallback: Check for recent posts
            $sql = "SELECT MAX(created_at) as last_activity FROM feed_posts WHERE user_id = ? AND tenant_id = ?";
            $result = Database::query($sql, [$userId, TenantContext::getId()])->fetch();

            if ($result && $result['last_activity']) {
                return $result['last_activity'];
            }

            // Ultimate fallback: user registration date
            $tenantId = TenantContext::getId();
            $sql = "SELECT created_at FROM users WHERE id = ? AND tenant_id = ?";
            $result = Database::query($sql, [$userId, $tenantId])->fetch();

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
                    (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND tenant_id = p.tenant_id AND action IN ('login', 'post_created')),
                    (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id AND tenant_id = p.tenant_id),
                    u.created_at
                )) <= {$fullThreshold} THEN 1.0

                WHEN DATEDIFF(NOW(), COALESCE(
                    (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND tenant_id = p.tenant_id AND action IN ('login', 'post_created')),
                    (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id AND tenant_id = p.tenant_id),
                    u.created_at
                )) >= {$decayThreshold} THEN {$minimum}

                ELSE 1.0 - (
                    (DATEDIFF(NOW(), COALESCE(
                        (SELECT MAX(created_at) FROM activity_log WHERE user_id = p.user_id AND tenant_id = p.tenant_id AND action IN ('login', 'post_created')),
                        (SELECT MAX(created_at) FROM feed_posts WHERE user_id = p.user_id AND tenant_id = p.tenant_id),
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
                        AND l.tenant_id = ? AND p.tenant_id = ?
                        AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) +
                    (
                        -- Comments on this poster's content
                        SELECT COUNT(*) FROM comments c
                        JOIN feed_posts p ON c.target_type = 'post' AND c.target_id = p.id
                        WHERE c.user_id = ? AND p.user_id = ?
                        AND c.tenant_id = ? AND p.tenant_id = ?
                        AND c.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) +
                    (
                        -- Direct messages or follows would go here if available
                        0
                    ) as interaction_count
            ";

            $tenantId = TenantContext::getId();
            $result = Database::query($sql, [
                $viewerId, $posterId, $tenantId, $tenantId, $lookbackDays,
                $viewerId, $posterId, $tenantId, $tenantId, $lookbackDays
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
     * Uses only guaranteed tables (likes, comments) - follower boost via user_follows table
     */
    private static function getSocialGraphScoreSql(int $viewerId): string
    {
        $config = self::getConfig();

        if (empty($config['social_graph_enabled']) || $viewerId === 0) {
            return (string)self::DEFAULT_SCORE;
        }

        $maxBoost = (float)$config['social_graph_max_boost'];
        $lookbackDays = (int)$config['social_graph_lookback_days'];
        $tenantId = TenantContext::getId();
        $boostFactor = ($maxBoost - 1) / 4;

        // Safe SQL - only uses guaranteed tables (likes, comments)
        // Follower boost via user_follows table (active)
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
                                AND l2.tenant_id = {$tenantId}
                                AND l2.target_id IN (SELECT id FROM feed_posts WHERE user_id = p.user_id AND tenant_id = {$tenantId})
                                AND l2.created_at >= DATE_SUB(NOW(), INTERVAL {$lookbackDays} DAY)
                            ), 0) + COALESCE((
                                SELECT COUNT(*) FROM comments c2
                                WHERE c2.user_id = {$viewerId}
                                AND c2.target_type = 'post'
                                AND c2.tenant_id = {$tenantId}
                                AND c2.target_id IN (SELECT id FROM feed_posts WHERE user_id = p.user_id AND tenant_id = {$tenantId})
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

    public static function calculateNegativeSignalsScore(int $viewerId, int $postId, int $posterId): float
    {
        $config = self::getConfig();
        if (empty($config['negative_signals_enabled']) || $viewerId === 0) { return self::DEFAULT_SCORE; }
        try {
            $tenantId = TenantContext::getId();
            $h = Database::query("SELECT 1 FROM feed_hidden WHERE user_id=? AND post_id=? AND tenant_id=? LIMIT 1", [$viewerId,$postId,$tenantId])->fetch();
            if ($h) { return $config['hide_penalty']; }
            $m = Database::query("SELECT 1 FROM feed_muted_users WHERE user_id=? AND muted_user_id=? AND tenant_id=? LIMIT 1", [$viewerId,$posterId,$tenantId])->fetch();
            if ($m) { return $config['mute_penalty']; }
            $rc = Database::query("SELECT COUNT(*) as cnt FROM reports WHERE target_type='post' AND target_id=? AND tenant_id=?", [$postId,$tenantId])->fetchColumn();
            if ($rc > 0) { return max(0.1, 1.0 - $rc * $config['report_penalty_per']); }
            return self::DEFAULT_SCORE;
        } catch (\Exception $e) { return self::DEFAULT_SCORE; }
    }

    private static function getNegativeSignalsScoreSql(int $viewerId): string
    {
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

        // Boost for posts with video URLs
        if (preg_match('/(?:youtube\.com|youtu\.be|vimeo\.com|tiktok\.com|dailymotion\.com)/i', $content)) {
            $score *= $config['quality_video_boost'];
        }

        // Boost for posts with hashtags
        if (strpos($content, '#') !== false) {
            $score *= $config['quality_hashtag_boost'];
        }

        // Boost for posts with @mentions
        if (strpos($content, '@') !== false) {
            $score *= $config['quality_mention_boost'];
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
    // CONTENT DIVERSITY (Legacy Public Methods)
    // =========================================================================

    public static function applyContentDiversity(array $feedItems): array
    {
        return self::applyDiversityInPlace($feedItems, self::getDiversityConfig());
    }

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

    public static function applyContentTypeDiversity(array $feedItems): array
    {
        $config = self::getDiversityConfig();
        $config['enabled'] = false;
        return self::applyDiversityInPlace($feedItems, $config);
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
            $tenantId = TenantContext::getId();
            $sql = "SELECT created_at FROM users WHERE id = ? AND tenant_id = ?";
            $result = Database::query($sql, [$userId, $tenantId])->fetch();
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
     * Filter badges for display.
     *
     * All badges are now visible to all users — the admin_only restriction
     * has been removed so every user sees algorithm context labels.
     *
     * @param array $badges Array of badge data
     * @param bool $isAdmin Deprecated — kept for backwards compatibility, ignored
     * @return array Filtered badges (currently returns all)
     */
    public static function filterBadgesForUser(array $badges, bool $isAdmin = false): array
    {
        return array_values($badges);
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
