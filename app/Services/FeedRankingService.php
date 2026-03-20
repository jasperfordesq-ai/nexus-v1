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
    // Signal constants
    private const VIEW_TRACKING_ENABLED = true;
    private const CLICK_TRACKING_ENABLED = true;
    private const VELOCITY_ENABLED = true;
    private const VELOCITY_WINDOW_HOURS = 2;
    private const VELOCITY_THRESHOLD = 3;
    private const VELOCITY_MAX_BOOST = 1.8;
    private const VELOCITY_DECAY_HOURS = 6;
    private const CONVERSATION_DEPTH_ENABLED = true;
    private const CONVERSATION_DEPTH_MAX_BOOST = 1.5;
    private const CONVERSATION_DEPTH_THRESHOLD = 3;
    private const REACTION_WEIGHTS = [
        'love' => 2.0, 'celebrate' => 1.8, 'insightful' => 1.5,
        'like' => 1.0, 'curious' => 0.8, 'sad' => 0.6, 'angry' => 0.5,
    ];

    private ?array $config = null;
    /** @var array<int, int> */
    private array $mutedUserSet = [];

    public function __construct()
    {
    }

    // =========================================================================
    // CONFIG
    // =========================================================================

    public function getConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $defaults = [
            'enabled' => true,
            'like_weight' => 1, 'comment_weight' => 5, 'share_weight' => 8,
            'vitality_full_days' => 7, 'vitality_decay_days' => 30, 'vitality_minimum' => 0.5,
            'geo_full_radius' => 50, 'geo_decay_interval' => 100,
            'geo_decay_rate' => 0.03, 'geo_minimum' => 0.15,
            'freshness_enabled' => true, 'freshness_full_hours' => 24,
            'freshness_half_life' => 72, 'freshness_minimum' => 0.3, 'freshness_gravity' => 1.0,
            'social_graph_enabled' => true, 'social_graph_max_boost' => 2.0,
            'social_graph_lookback_days' => 90, 'social_graph_follower_boost' => 1.5,
            'negative_signals_enabled' => true,
            'hide_penalty' => 0.0, 'mute_penalty' => 0.1, 'block_penalty' => 0.0,
            'report_penalty_per' => 0.15,
            'quality_enabled' => true,
            'quality_image_boost' => 1.3, 'quality_link_boost' => 1.1,
            'quality_length_min' => 50, 'quality_length_bonus' => 1.2,
            'quality_video_boost' => 1.4, 'quality_hashtag_boost' => 1.1,
            'quality_mention_boost' => 1.15,
            'diversity_enabled' => true, 'diversity_max_consecutive' => 2,
            'diversity_penalty' => 0.5,
            'diversity_type_enabled' => true, 'diversity_type_max_consecutive' => 3,
            'velocity_enabled' => true, 'velocity_window_hours' => 2,
            'velocity_threshold' => 3, 'velocity_max_boost' => 1.8, 'velocity_decay_hours' => 6,
            'conversation_depth_enabled' => true,
            'conversation_depth_max_boost' => 1.5, 'conversation_depth_threshold' => 3,
            'ctr_enabled' => true, 'ctr_max_boost' => 1.5, 'ctr_min_impressions' => 5,
            'user_type_prefs_enabled' => true, 'user_type_prefs_max_boost' => 1.4,
            'user_type_prefs_lookback_days' => 30,
            'save_signal_enabled' => true, 'save_signal_max_boost' => 1.35, 'save_signal_min_saves' => 2,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = DB::table('tenants')->where('id', $tenantId)->value('configuration');
            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['feed_algorithm'])) {
                    $this->config = array_merge($defaults, $configArr['feed_algorithm']);
                    $this->validateConfig();
                    return $this->config;
                }
            }
        } catch (\Exception $e) {
            // Fall through to defaults
        }

        $this->config = $defaults;
        $this->validateConfig();
        return $this->config;
    }

    private function validateConfig(): void
    {
        $c = &$this->config;
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
        return !empty($this->getConfig()['enabled']);
    }

    public function clearCache(): void
    {
        $this->config = null;
        $this->mutedUserSet = [];
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

            return $this->calculatePostScore($post, $userId, $viewerCoords[0], $viewerCoords[1]);
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
            // Store boost in a post_boosts table or feed_posts metadata
            DB::statement(
                "INSERT INTO post_boosts (post_id, tenant_id, boost_factor, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE boost_factor = ?, updated_at = NOW()",
                [$postId, $tenantId, $factor, $factor]
            );
            return true;
        } catch (\Exception $e) {
            // post_boosts table may not exist — try updating feed_posts directly
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
    public function rankFeedItems(array $items, ?int $viewerId = null, ?string $viewerTimezone = null): array
    {
        if (!$this->isEnabled() || count($items) < 2) {
            return $items;
        }

        $config = $this->getConfig();
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
        $userTypePrefs = (!empty($config['user_type_prefs_enabled']) && $viewerId) ? $this->getUserTypePreferences($viewerId) : [];
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
                $score *= $this->hackerNewsDecay($hoursAgo);
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
                $score *= $this->calculateGeoDecayScore($viewerLat, $viewerLon, $aLat, $aLon);
            }

            // 8. Quality
            if ($config['quality_enabled']) {
                $ic = (string) ($item['content'] ?? '');
                if (!empty($item['image_url'])) $score *= (float) $config['quality_image_boost'];
                if (preg_match('/(?:youtube\.com|youtu\.be|vimeo\.com|tiktok\.com|dailymotion\.com)/i', $ic)) $score *= (float) $config['quality_video_boost'];
                if (strpos($ic, '#') !== false) $score *= (float) $config['quality_hashtag_boost'];
                if (strpos($ic, '@') !== false) $score *= (float) $config['quality_mention_boost'];
                if (strlen($ic) >= $config['quality_length_min']) $score *= (float) $config['quality_length_bonus'];
            }

            // 9. Context Timing
            $score *= $this->contextualBoost($sourceType, $viewerTimezone);

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
        } catch (\Exception $e) { return []; }
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
            $config = $this->getConfig();
            foreach ($rows as $row) {
                $daysSince = $this->getDaysSinceDate($row->last_active);
                $r[(int) $row->user_id] = $this->computeVitalityFromDays($daysSince);
            }
            foreach ($userIds as $uid) {
                if (!isset($r[$uid])) { $r[$uid] = (float) $config['vitality_minimum']; }
            }
            return $r;
        } catch (\Exception $e) { return []; }
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
        } catch (\Exception $e) { return []; }
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
        } catch (\Exception $e) { return []; }
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
        } catch (\Exception $e) { return []; }
    }

    private function getBatchNegativeSignals(int $viewerId, array $postIds, array $authorIds): array
    {
        $config = $this->getConfig();
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
        } catch (\Exception $e) {}
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
        } catch (\Exception $e) { return []; }
    }

    private function getUserTypePreferences(int $viewerId): array
    {
        if ($viewerId === 0) return [];
        $config = $this->getConfig();
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
        } catch (\Exception $e) { return []; }
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
        } catch (\Exception $e) { return []; }
    }

    // =========================================================================
    // SCORING HELPERS
    // =========================================================================

    private function hackerNewsDecay(int $hoursAgo): float
    {
        $config = $this->getConfig();
        $halfLife = max(1.0, (float) $config['freshness_half_life']);
        $gravity = (float) ($config['freshness_gravity'] ?? 1.0);
        $decay = 1.0 / pow(1.0 + $hoursAgo / $halfLife, $gravity);
        return max((float) $config['freshness_minimum'], $decay);
    }

    private function calculateGeoDecayScore(?float $viewerLat, ?float $viewerLon, ?float $posterLat, ?float $posterLon): float
    {
        if ($viewerLat === null || $viewerLon === null || $posterLat === null || $posterLon === null) return 1.0;

        $distanceKm = $this->calculateHaversineDistance($viewerLat, $viewerLon, $posterLat, $posterLon);
        $config = $this->getConfig();
        $fullRadius = $config['geo_full_radius'];

        if ($distanceKm <= $fullRadius) return 1.0;

        $distanceBeyond = $distanceKm - $fullRadius;
        $decayIntervals = floor($distanceBeyond / $config['geo_decay_interval']);
        $totalDecay = $decayIntervals * $config['geo_decay_rate'];

        return max($config['geo_minimum'], 1.0 - $totalDecay);
    }

    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
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

    private function computeVitalityFromDays(int $days): float
    {
        $config = $this->getConfig();
        $fullThreshold = $config['vitality_full_days'];
        $decayThreshold = $config['vitality_decay_days'];
        $minimum = $config['vitality_minimum'];

        if ($days <= $fullThreshold) return 1.0;
        if ($days >= $decayThreshold) return $minimum;

        $decayRange = $decayThreshold - $fullThreshold;
        $daysIntoDecay = $days - $fullThreshold;
        $scoreRange = 1.0 - $minimum;
        return 1.0 - ($daysIntoDecay / $decayRange * $scoreRange);
    }

    private function contextualBoost(string $sourceType, ?string $viewerTimezone = null): float
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

    private function calculatePostScore(array $post, int $viewerId, ?float $viewerLat = null, ?float $viewerLon = null): float
    {
        $config = $this->getConfig();
        $postId = (int) ($post['id'] ?? $post['post_id'] ?? 0);
        $score = 1.0;

        // Time Decay
        $createdAt = $post['created_at'] ?? null;
        if ($createdAt) {
            $hoursAgo = max(0, (int) round((time() - strtotime($createdAt)) / 3600));
            $score *= $this->hackerNewsDecay($hoursAgo);
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
        $score *= $this->calculateGeoDecayScore($viewerLat, $viewerLon, $posterLat, $posterLon);

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
        } catch (\Exception $e) {}
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
        } catch (\Exception $e) { return []; }
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
        } catch (\Exception $e) { return []; }
    }

    private function isAuthorMuted(int $authorId): bool
    {
        return isset($this->mutedUserSet[$authorId]);
    }

    // =========================================================================
    // DIVERSITY (Post-Sort Processing)
    // =========================================================================

    private function applyDiversityInPlace(array $items): array
    {
        $config = $this->getConfig();
        $userDiv = !empty($config['diversity_enabled']);
        $typeDiv = !empty($config['diversity_type_enabled']);
        if (!$userDiv && !$typeDiv) return $items;

        $maxUser = $config['diversity_max_consecutive'] ?? 2;
        $maxType = $config['diversity_type_max_consecutive'] ?? 3;
        $result = [];
        $deferred = [];

        foreach ($items as $item) {
            $userId = (int) ($item['user_id'] ?? 0);
            $cType = $item['type'] ?? $item['content_type'] ?? 'post';
            $shouldDefer = false;

            if ($userDiv && $userId > 0) {
                $c = 0;
                for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxUser; $i--) {
                    if ((int) ($result[$i]['user_id'] ?? 0) === $userId) $c++; else break;
                }
                if ($c >= $maxUser) $shouldDefer = true;
            }
            if (!$shouldDefer && $typeDiv) {
                $c = 0;
                for ($i = count($result) - 1; $i >= 0 && $i >= count($result) - $maxType; $i--) {
                    if (($result[$i]['type'] ?? $result[$i]['content_type'] ?? 'post') === $cType) $c++; else break;
                }
                if ($c >= $maxType) $shouldDefer = true;
            }

            if ($shouldDefer) $deferred[] = $item; else $result[] = $item;
        }

        foreach ($deferred as $di) {
            $dUid = (int) ($di['user_id'] ?? 0);
            $dType = $di['type'] ?? $di['content_type'] ?? 'post';
            $ins = false;
            for ($i = 0; $i < count($result); $i++) {
                $ok = true;
                if ($userDiv && $dUid > 0) {
                    for ($j = max(0, $i - $maxUser + 1); $j < min(count($result), $i + $maxUser); $j++) {
                        if ((int) ($result[$j]['user_id'] ?? 0) === $dUid) { $ok = false; break; }
                    }
                }
                if ($ok && $typeDiv) {
                    for ($j = max(0, $i - $maxType + 1); $j < min(count($result), $i + $maxType); $j++) {
                        if (($result[$j]['type'] ?? $result[$j]['content_type'] ?? 'post') === $dType) { $ok = false; break; }
                    }
                }
                if ($ok) { array_splice($result, $i, 0, [$di]); $ins = true; break; }
            }
            if (!$ins) $result[] = $di;
        }

        return $result;
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    private function getDaysSinceDate(string $dateString): int
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
