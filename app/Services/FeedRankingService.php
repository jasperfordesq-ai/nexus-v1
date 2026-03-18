<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * FeedRankingService — Laravel DI service for feed ranking (EdgeRank).
 *
 * The ranking algorithm itself (rankPosts, getEdgeRankScore, boostPost)
 * is very complex (800+ lines, 15-signal pipeline) and delegates to legacy.
 * Simpler tracking/config methods are converted to DB facade.
 */
class FeedRankingService
{
    private const VIEW_TRACKING_ENABLED = true;
    private const CLICK_TRACKING_ENABLED = true;

    public function __construct()
    {
    }

    /**
     * Rank feed items — delegates to legacy FeedRankingService.
     * // TODO: Convert to Eloquent (800+ line 15-signal pipeline)
     */
    public function rankPosts(int $tenantId, array $postIds, int $userId): array
    {
        return \Nexus\Services\FeedRankingService::rankPosts($tenantId, $postIds, $userId);
    }

    /**
     * Get EdgeRank score for a single post — delegates to legacy.
     * // TODO: Convert to Eloquent (complex multi-signal scoring)
     */
    public function getEdgeRankScore(int $tenantId, int $postId, int $userId): float
    {
        return \Nexus\Services\FeedRankingService::getEdgeRankScore($tenantId, $postId, $userId);
    }

    /**
     * Boost a post — delegates to legacy.
     * // TODO: Convert to Eloquent (complex multi-signal scoring)
     */
    public function boostPost(int $tenantId, int $postId, float $factor = 1.5): bool
    {
        return \Nexus\Services\FeedRankingService::boostPost($tenantId, $postId, $factor);
    }

    /**
     * Record a feed post impression.
     */
    public function recordImpression(int $postId, int $userId): void
    {
        if (!self::VIEW_TRACKING_ENABLED || $userId === 0 || $postId === 0) {
            return;
        }

        try {
            DB::statement(
                "INSERT INTO feed_impressions (post_id, user_id, tenant_id, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE view_count = view_count + 1, updated_at = NOW()",
                [$postId, $userId, TenantContext::getId()]
            );
        } catch (\Exception $e) {
            // Non-blocking — best effort
        }
    }

    /**
     * Record a feed post click.
     */
    public function recordClick(int $postId, int $userId): void
    {
        if (!self::CLICK_TRACKING_ENABLED || $userId === 0 || $postId === 0) {
            return;
        }

        try {
            DB::statement(
                "INSERT INTO feed_clicks (post_id, user_id, tenant_id, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE click_count = click_count + 1, updated_at = NOW()",
                [$postId, $userId, TenantContext::getId()]
            );
        } catch (\Exception $e) {
            // Non-blocking — best effort
        }
    }

    /**
     * Check if the EdgeRank algorithm is enabled.
     */
    public function isEnabled(): bool
    {
        $config = $this->getConfig();
        return !empty($config['enabled']);
    }

    /**
     * Get the EdgeRank configuration from the tenant's configuration JSON.
     */
    public function getConfig(): array
    {
        $defaults = [
            'enabled' => true,
            'like_weight' => 1,
            'comment_weight' => 5,
            'share_weight' => 8,
        ];

        try {
            $tenantId = TenantContext::getId();
            $row = DB::selectOne(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );

            if ($row && $row->configuration) {
                $configArr = json_decode($row->configuration, true);
                if (is_array($configArr) && isset($configArr['feed_algorithm'])) {
                    return array_merge($defaults, $configArr['feed_algorithm']);
                }
            }
        } catch (\Exception $e) {
            // Fall through to defaults
        }

        return $defaults;
    }

    /**
     * Clear the cached EdgeRank config.
     *
     * Note: The legacy service uses a static cache. In the Laravel version,
     * config is loaded fresh each time from DB, so this is a no-op.
     */
    public function clearCache(): void
    {
        // No-op in the Laravel version — config is loaded fresh from DB each call.
    }
}
