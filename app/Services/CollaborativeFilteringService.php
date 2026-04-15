<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CollaborativeFilteringService — Item-based Collaborative Filtering
 *
 * Implements item-item collaborative filtering using cosine similarity on
 * implicit feedback (saves, transactions).
 *
 * Algorithm reference:
 *   Sarwar et al. (2001) "Item-based Collaborative Filtering Recommendation Algorithms"
 *   WWW '01. https://dl.acm.org/doi/10.1145/371920.372071
 *
 * Key methods:
 * - getSimilarListings()          — "users who saved X also saved Y"
 * - getSuggestedMembers()         — "users who exchanged with X also exchanged with Y"
 * - getSuggestedListingsForUser() — user-user CF: listings liked by similar users
 */
class CollaborativeFilteringService
{
    private const CACHE_TTL_SECONDS = 3600;  // 1 hour
    private const MIN_COMMON_USERS = 2;
    private const MAX_TRAINING_ROWS = 5000;

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Find listings similar to the given listing based on shared saves.
     *
     * @return int[] Similar listing IDs, ranked by similarity
     */
    public static function getSimilarListings(int $listingId, int $tenantId, int $limit = 5): array
    {
        $cacheKey = "cf_listings_{$tenantId}_{$listingId}_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Check pre-trained KNN cache
        $knnKey = "recs_listings_{$tenantId}_{$listingId}";
        $knnCached = Cache::get($knnKey);
        if ($knnCached !== null && !empty($knnCached)) {
            $knnFiltered = array_values(array_filter($knnCached, fn (int $id) => $id !== $listingId));
            return array_slice($knnFiltered, 0, $limit);
        }

        $interactions = self::loadListingInteractions($tenantId);

        if (empty($interactions)) {
            $fallback = self::getPopularListingsFallback($tenantId, $limit + 1);
            $fallback = array_values(array_filter($fallback, fn (int $id) => $id !== $listingId));
            return array_slice($fallback, 0, $limit);
        }

        $similar = self::itemBasedRecommendations($listingId, $interactions, $limit + 1);
        $similar = array_values(array_filter($similar, fn (int $id) => $id !== $listingId));
        $similar = array_slice($similar, 0, $limit);

        Cache::put($cacheKey, $similar, self::CACHE_TTL_SECONDS);

        return $similar;
    }

    /**
     * Find members similar to the given user based on shared exchange partners.
     *
     * @return int[] Similar user IDs, ranked by similarity
     */
    public static function getSuggestedMembers(int $userId, int $tenantId, int $limit = 5): array
    {
        $cacheKey = "cf_members_{$tenantId}_{$userId}_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $knnKey = "recs_members_{$tenantId}_{$userId}";
        $knnCached = Cache::get($knnKey);
        if ($knnCached !== null && !empty($knnCached)) {
            return array_slice($knnCached, 0, $limit);
        }

        $interactions = self::loadMemberInteractions($tenantId);

        if (empty($interactions)) {
            return self::getPopularMembersFallback($tenantId, $limit);
        }

        $similar = self::itemBasedRecommendations($userId, $interactions, $limit);
        Cache::put($cacheKey, $similar, self::CACHE_TTL_SECONDS);

        return $similar;
    }

    /**
     * User-user CF: recommend listings liked by similar users.
     *
     * Reference: Resnick et al. (1994) "GroupLens: an open architecture for
     * collaborative filtering of netnews." CSCW '94.
     *
     * @return int[] Recommended listing IDs, ranked by aggregated similarity
     */
    public static function getSuggestedListingsForUser(int $userId, int $tenantId, int $limit = 10): array
    {
        $cacheKey = "cf_uu_listings_{$tenantId}_{$userId}_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1 — Find similar users via transaction graph
        $memberInteractions = self::loadMemberInteractions($tenantId);
        if (empty($memberInteractions) || !isset($memberInteractions[$userId])) {
            return self::getPopularListingsFallbackExcludingSaved($userId, $tenantId, $limit);
        }

        $sourceVector = $memberInteractions[$userId];
        $similarities = [];
        foreach ($memberInteractions as $candidateId => $candidateVector) {
            if ($candidateId === $userId) {
                continue;
            }
            $commonPartners = array_intersect_key($sourceVector, $candidateVector);
            if (count($commonPartners) < self::MIN_COMMON_USERS) {
                continue;
            }
            $sim = self::cosineSimilarity($sourceVector, $candidateVector);
            if ($sim > 0.0) {
                $similarities[$candidateId] = $sim;
            }
        }

        if (empty($similarities)) {
            return self::getPopularListingsFallbackExcludingSaved($userId, $tenantId, $limit);
        }

        arsort($similarities);
        $topUserIds = array_keys(array_slice($similarities, 0, 20, true));

        // Step 2 — Load listing saves for those similar users
        $listingInteractions = self::loadListingInteractions($tenantId);
        if (empty($listingInteractions)) {
            return self::getPopularListingsFallbackExcludingSaved($userId, $tenantId, $limit);
        }

        // Aggregate: listing score = sum(user_similarity * save_weight)
        $listingScores = [];
        foreach ($topUserIds as $similarUserId) {
            $simWeight = $similarities[$similarUserId];
            $savedListings = $listingInteractions[$similarUserId] ?? [];
            foreach ($savedListings as $listingId => $saveWeight) {
                $listingScores[$listingId] = ($listingScores[$listingId] ?? 0.0) + $simWeight * $saveWeight;
            }
        }

        // Remove listings the source user already saved
        $userSaved = $listingInteractions[$userId] ?? [];
        foreach (array_keys($userSaved) as $alreadySeen) {
            unset($listingScores[$alreadySeen]);
        }

        // Also exclude from listing_favorites
        try {
            $savedRows = DB::table('listing_favorites as lf')
                ->join('listings as l', 'lf.listing_id', '=', 'l.id')
                ->where('lf.user_id', $userId)
                ->where('l.tenant_id', $tenantId)
                ->pluck('lf.listing_id')
                ->all();
            foreach ($savedRows as $savedId) {
                unset($listingScores[(int) $savedId]);
            }
        } catch (\Throwable $e) {
            // listing_favorites table may not exist
        }

        arsort($listingScores);
        $result = array_map('intval', array_keys(array_slice($listingScores, 0, $limit, true)));

        Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    // =========================================================================
    // CORE ALGORITHM
    // =========================================================================

    /**
     * Item-based collaborative filtering using cosine similarity.
     *
     * @param int   $sourceItemId  The item to find neighbours for
     * @param array $interactions  [userId => [itemId => rating, ...], ...]
     * @param int   $limit         Max results
     * @return int[]               Similar item IDs (most similar first)
     */
    private static function itemBasedRecommendations(int $sourceItemId, array $interactions, int $limit): array
    {
        // Build item->user index
        $itemUsers = [];
        foreach ($interactions as $userId => $items) {
            foreach ($items as $itemId => $rating) {
                $itemUsers[$itemId][$userId] = (float) $rating;
            }
        }

        if (!isset($itemUsers[$sourceItemId])) {
            return [];
        }

        $sourceVector = $itemUsers[$sourceItemId];
        $similarities = [];

        foreach ($itemUsers as $candidateId => $candidateVector) {
            if ($candidateId === $sourceItemId) {
                continue;
            }

            $commonUsers = array_intersect_key($sourceVector, $candidateVector);
            if (count($commonUsers) < self::MIN_COMMON_USERS) {
                continue;
            }

            $sim = self::cosineSimilarity($sourceVector, $candidateVector);
            if ($sim > 0.0) {
                $similarities[$candidateId] = $sim;
            }
        }

        arsort($similarities);

        return array_keys(array_slice($similarities, 0, $limit, true));
    }

    /**
     * Cosine similarity between two sparse vectors.
     *
     * @param float[] $a Vector A [key => value]
     * @param float[] $b Vector B [key => value]
     * @return float    Similarity in [0, 1]
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $key => $val) {
            $normA += $val * $val;
            $dotProduct += $val * ($b[$key] ?? 0.0);
        }

        foreach ($b as $val) {
            $normB += $val * $val;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0.0 ? $dotProduct / $denominator : 0.0;
    }

    // =========================================================================
    // COLD-START FALLBACKS
    // =========================================================================

    private static function getPopularListingsFallback(int $tenantId, int $limit): array
    {
        try {
            return DB::table('listings')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            Log::debug('[CollaborativeFiltering] getPopularListingsFallback failed: ' . $e->getMessage());
            return [];
        }
    }

    private static function getPopularListingsFallbackExcludingSaved(int $userId, int $tenantId, int $limit): array
    {
        $fallback = self::getPopularListingsFallback($tenantId, $limit + 20);

        try {
            $savedRows = DB::table('listing_favorites as lf')
                ->join('listings as l', 'lf.listing_id', '=', 'l.id')
                ->where('lf.user_id', $userId)
                ->where('l.tenant_id', $tenantId)
                ->pluck('lf.listing_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $fallback = array_values(array_filter($fallback, fn (int $id) => !in_array($id, $savedRows, true)));
        } catch (\Throwable $e) {
            Log::debug('[CollaborativeFiltering] Could not exclude saved listings (table may not exist): ' . $e->getMessage());
        }

        return array_slice($fallback, 0, $limit);
    }

    private static function getPopularMembersFallback(int $tenantId, int $limit): array
    {
        try {
            return DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            Log::debug('[CollaborativeFiltering] getPopularMembersFallback failed: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    /**
     * Load listing save interactions: [userId => [listingId => 1.0, ...], ...]
     */
    private static function loadListingInteractions(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT lf.user_id, lf.listing_id
                 FROM listing_favorites lf
                 JOIN listings l ON lf.listing_id = l.id
                 WHERE l.tenant_id = ? AND l.status = 'active'
                 LIMIT " . self::MAX_TRAINING_ROWS,
                [$tenantId]
            );
        } catch (\Throwable $e) {
            Log::debug('[CollaborativeFiltering] loadListingInteractions failed: ' . $e->getMessage());
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[(int) $row->user_id][(int) $row->listing_id] = 1.0;
        }

        return $matrix;
    }

    /**
     * Load member interaction graph: [userId => [partnerId => count, ...], ...]
     */
    private static function loadMemberInteractions(int $tenantId): array
    {
        try {
            $rows = DB::select(
                "SELECT sender_id, receiver_id, COUNT(*) as interaction_count
                 FROM transactions
                 WHERE tenant_id = ? AND status = 'completed'
                 GROUP BY sender_id, receiver_id
                 LIMIT " . self::MAX_TRAINING_ROWS,
                [$tenantId]
            );
        } catch (\Throwable $e) {
            Log::debug('[CollaborativeFiltering] loadMemberInteractions failed: ' . $e->getMessage());
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $matrix = [];
        foreach ($rows as $row) {
            $senderId = (int) $row->sender_id;
            $receiverId = (int) $row->receiver_id;
            $count = (float) $row->interaction_count;

            // Bidirectional
            $matrix[$senderId][$receiverId] = $count;
            $matrix[$receiverId][$senderId] = $count;
        }

        return $matrix;
    }
}
