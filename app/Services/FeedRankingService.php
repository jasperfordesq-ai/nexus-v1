<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FeedRankingService � Laravel DI wrapper for legacy \Nexus\Services\FeedRankingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FeedRankingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FeedRankingService::rankPosts().
     */
    public function rankPosts(int $tenantId, array $postIds, int $userId): array
    {
        return \Nexus\Services\FeedRankingService::rankPosts($tenantId, $postIds, $userId);
    }

    /**
     * Delegates to legacy FeedRankingService::getEdgeRankScore().
     */
    public function getEdgeRankScore(int $tenantId, int $postId, int $userId): float
    {
        return \Nexus\Services\FeedRankingService::getEdgeRankScore($tenantId, $postId, $userId);
    }

    /**
     * Delegates to legacy FeedRankingService::boostPost().
     */
    public function boostPost(int $tenantId, int $postId, float $factor = 1.5): bool
    {
        return \Nexus\Services\FeedRankingService::boostPost($tenantId, $postId, $factor);
    }

    /**
     * Record a feed post impression — delegates to legacy FeedRankingService.
     */
    public function recordImpression(int $postId, int $userId): void
    {
        \Nexus\Services\FeedRankingService::recordImpression($postId, $userId);
    }

    /**
     * Record a feed post click — delegates to legacy FeedRankingService.
     */
    public function recordClick(int $postId, int $userId): void
    {
        \Nexus\Services\FeedRankingService::recordClick($postId, $userId);
    }

    /**
     * Check if the EdgeRank algorithm is enabled — delegates to legacy FeedRankingService.
     */
    public function isEnabled(): bool
    {
        return \Nexus\Services\FeedRankingService::isEnabled();
    }

    /**
     * Get the EdgeRank configuration — delegates to legacy FeedRankingService.
     */
    public function getConfig(): array
    {
        return \Nexus\Services\FeedRankingService::getConfig();
    }

    /**
     * Clear the cached EdgeRank config — delegates to legacy FeedRankingService.
     */
    public function clearCache(): void
    {
        \Nexus\Services\FeedRankingService::clearCache();
    }
}
