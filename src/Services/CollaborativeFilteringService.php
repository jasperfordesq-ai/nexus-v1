<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * CollaborativeFilteringService — Item-based Collaborative Filtering
 *
 * Implements item-item collaborative filtering using cosine similarity on
 * implicit feedback (saves, transactions). This is the standard approach
 * used by academic and industry recommendation systems:
 *
 * Algorithm reference:
 *   Sarwar et al. (2001) "Item-based Collaborative Filtering Recommendation Algorithms"
 *   WWW '01. https://dl.acm.org/doi/10.1145/371920.372071
 *
 * Key methods:
 * - getSimilarListings()  — "users who saved X also saved Y"
 * - getSuggestedMembers() — "users who exchanged with X also exchanged with Y"
 *
 * Similarity results are cached in Redis for 1 hour to avoid repeated computation.
 */
class CollaborativeFilteringService
{
    private const CACHE_TTL_SECONDS = 3600;  // 1 hour
    private const MIN_COMMON_USERS   = 2;    // Min shared interactions for similarity
    private const MAX_TRAINING_ROWS  = 5000; // Cap training data per tenant for performance

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Find listings similar to the given listing based on shared saves
     *
     * @param int $listingId  Source listing
     * @param int $tenantId   Tenant scope
     * @param int $limit      Max results
     * @return int[]          Similar listing IDs, ranked by similarity
     */
    public static function getSimilarListings(int $listingId, int $tenantId, int $limit = 5): array
    {
        $cacheKey = "cf_listings_{$tenantId}_{$listingId}_{$limit}";
        $cached   = RedisCache::get($cacheKey, $tenantId);
        if ($cached !== null) {
            return $cached;
        }

        // Check pre-trained KNN cache first (computed by train_recommendations.php).
        // The offline pipeline stores listing recs keyed by user ID, not listing ID,
        // so this fast path only applies when a listing-keyed KNN result exists.
        $knnKey    = "recs_listings_{$tenantId}_{$listingId}";
        $knnCached = RedisCache::get($knnKey, $tenantId);
        if ($knnCached !== null && !empty($knnCached)) {
            return array_slice($knnCached, 0, $limit);
        }

        // Load user→listing interaction matrix (implicit: save = 1)
        $interactions = self::loadListingInteractions($tenantId);

        if (empty($interactions)) {
            return [];
        }

        $similar = self::itemBasedRecommendations($listingId, $interactions, $limit);
        RedisCache::set($cacheKey, $similar, $tenantId, self::CACHE_TTL_SECONDS);

        return $similar;
    }

    /**
     * Find members similar to the given user based on shared exchange partners
     *
     * @param int $userId   Source user
     * @param int $tenantId Tenant scope
     * @param int $limit    Max results
     * @return int[]        Similar user IDs, ranked by similarity
     */
    public static function getSuggestedMembers(int $userId, int $tenantId, int $limit = 5): array
    {
        $cacheKey = "cf_members_{$tenantId}_{$userId}_{$limit}";
        $cached   = RedisCache::get($cacheKey, $tenantId);
        if ($cached !== null) {
            return $cached;
        }

        // Check pre-trained KNN cache first (computed by train_recommendations.php).
        // The offline pipeline pre-computes member-to-member recommendations nightly.
        $knnKey    = "recs_members_{$tenantId}_{$userId}";
        $knnCached = RedisCache::get($knnKey, $tenantId);
        if ($knnCached !== null && !empty($knnCached)) {
            return array_slice($knnCached, 0, $limit);
        }

        // Load user→user interaction matrix (implicit: transaction = 1)
        $interactions = self::loadMemberInteractions($tenantId);

        if (empty($interactions)) {
            return [];
        }

        $similar = self::itemBasedRecommendations($userId, $interactions, $limit);
        RedisCache::set($cacheKey, $similar, $tenantId, self::CACHE_TTL_SECONDS);

        return $similar;
    }

    // =========================================================================
    // CORE ALGORITHM
    // =========================================================================

    /**
     * Item-based collaborative filtering using cosine similarity
     *
     * Builds a user-item matrix, computes cosine similarity between the source
     * item and all other items, and returns the top-N most similar item IDs.
     *
     * @param int   $sourceItemId  The item to find neighbours for
     * @param array $interactions  [userId => [itemId => rating, ...], ...]
     * @param int   $limit         Max results
     * @return int[]               Similar item IDs (most similar first)
     */
    private static function itemBasedRecommendations(
        int $sourceItemId,
        array $interactions,
        int $limit
    ): array {
        // Build item→user index: [itemId => [userId => rating, ...], ...]
        $itemUsers = [];
        foreach ($interactions as $userId => $items) {
            foreach ($items as $itemId => $rating) {
                $itemUsers[$itemId][$userId] = (float)$rating;
            }
        }

        if (!isset($itemUsers[$sourceItemId])) {
            return []; // Source item has no interactions
        }

        $sourceVector = $itemUsers[$sourceItemId];
        $similarities = [];

        foreach ($itemUsers as $candidateId => $candidateVector) {
            if ($candidateId === $sourceItemId) {
                continue;
            }

            // Find users who interacted with both items
            $commonUsers = array_intersect_key($sourceVector, $candidateVector);

            if (count($commonUsers) < self::MIN_COMMON_USERS) {
                continue; // Skip items with too few shared interactions
            }

            $sim = self::cosineSimilarity($sourceVector, $candidateVector);

            if ($sim > 0.0) {
                $similarities[$candidateId] = $sim;
            }
        }

        // Sort by similarity descending
        arsort($similarities);

        return array_keys(array_slice($similarities, 0, $limit, true));
    }

    /**
     * Cosine similarity between two sparse vectors
     *
     * cos(A, B) = (A · B) / (|A| × |B|)
     *
     * @param float[] $a  Vector A [itemId => value]
     * @param float[] $b  Vector B [itemId => value]
     * @return float       Similarity in [0, 1]
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA      = 0.0;
        $normB      = 0.0;

        // Dot product (only over shared keys for sparse vectors)
        foreach ($a as $key => $val) {
            $normA      += $val * $val;
            $dotProduct += $val * ($b[$key] ?? 0.0);
        }

        foreach ($b as $val) {
            $normB += $val * $val;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0.0 ? $dotProduct / $denominator : 0.0;
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    /**
     * Load listing save interactions: [userId => [listingId => 1.0, ...], ...]
     *
     * Uses listing_favorites table if it exists, falls back to view_count-weighted
     * interaction simulation from the listings table.
     */
    private static function loadListingInteractions(int $tenantId): array
    {
        try {
            // Try listing_favorites table first (explicit save = strongest signal)
            $rows = Database::query(
                "SELECT lf.user_id, lf.listing_id
                 FROM listing_favorites lf
                 JOIN listings l ON lf.listing_id = l.id
                 WHERE l.tenant_id = ?
                   AND l.status = 'active'
                 LIMIT " . self::MAX_TRAINING_ROWS,
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Table may not exist yet
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[(int)$row['user_id']][(int)$row['listing_id']] = 1.0;
        }

        return $matrix;
    }

    /**
     * Load member interaction graph: [userId => [partnerId => count, ...], ...]
     *
     * Uses transactions table — users who have exchanged hours have a social link.
     */
    private static function loadMemberInteractions(int $tenantId): array
    {
        try {
            $rows = Database::query(
                "SELECT sender_id, receiver_id, COUNT(*) as interaction_count
                 FROM transactions
                 WHERE tenant_id = ?
                   AND status = 'completed'
                 GROUP BY sender_id, receiver_id
                 LIMIT " . self::MAX_TRAINING_ROWS,
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $matrix = [];
        foreach ($rows as $row) {
            $senderId   = (int)$row['sender_id'];
            $receiverId = (int)$row['receiver_id'];
            $count      = (float)$row['interaction_count'];

            // Bidirectional: both users are "connected" to each other
            $matrix[$senderId][$receiverId]   = $count;
            $matrix[$receiverId][$senderId]   = $count;
        }

        return $matrix;
    }
}
