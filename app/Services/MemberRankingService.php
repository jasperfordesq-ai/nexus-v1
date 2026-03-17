<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * MemberRankingService — Laravel DI-based service for the CommunityRank algorithm.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\MemberRankingService.
 * Computes weighted scores (activity, contribution, reputation) to rank members.
 */
class MemberRankingService
{
    private const ACTIVITY_LOOKBACK_DAYS = 30;
    private const WEIGHT_ACTIVITY = 0.35;
    private const WEIGHT_CONTRIBUTION = 0.35;
    private const WEIGHT_REPUTATION = 0.30;

    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Rank members within a tenant using the CommunityRank algorithm.
     *
     * @return array Sorted list of ranked members with scores
     */
    public function rankMembers(int $tenantId, int $limit = 50): array
    {
        $cutoff = now()->subDays(self::ACTIVITY_LOOKBACK_DAYS)->toDateTimeString();

        $users = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select(['id', 'first_name', 'last_name', 'avatar_url', 'points',
                       'is_verified', 'created_at'])
            ->get();

        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        // Activity: transactions in lookback window
        $txnCounts = DB::select(
            "SELECT sender_id as user_id, COUNT(*) as cnt FROM transactions
             WHERE sender_id IN ({$placeholders}) AND created_at >= ? GROUP BY sender_id",
            array_merge($userIds, [$cutoff])
        );
        $txnMap = collect($txnCounts)->pluck('cnt', 'user_id')->all();

        // Activity: posts in lookback window
        $postCounts = DB::select(
            "SELECT user_id, COUNT(*) as cnt FROM feed_posts
             WHERE user_id IN ({$placeholders}) AND created_at >= ? GROUP BY user_id",
            array_merge($userIds, [$cutoff])
        );
        $postMap = collect($postCounts)->pluck('cnt', 'user_id')->all();

        // Contribution: listings created
        $listingCounts = DB::select(
            "SELECT user_id, COUNT(*) as cnt FROM listings
             WHERE user_id IN ({$placeholders}) GROUP BY user_id",
            $userIds
        );
        $listingMap = collect($listingCounts)->pluck('cnt', 'user_id')->all();

        // Reputation: average review rating
        $avgRatings = DB::select(
            "SELECT reviewed_user_id as user_id, AVG(rating) as avg_rating
             FROM reviews WHERE reviewed_user_id IN ({$placeholders}) GROUP BY reviewed_user_id",
            $userIds
        );
        $ratingMap = collect($avgRatings)->pluck('avg_rating', 'user_id')->all();

        // Normalize and score
        $maxActivity = max(1, max(array_merge([0], array_values($txnMap), array_values($postMap))));
        $maxContrib = max(1, max(array_merge([0], array_values($listingMap))));

        $ranked = $users->map(function ($user) use ($txnMap, $postMap, $listingMap, $ratingMap, $maxActivity, $maxContrib) {
            $activity = (($txnMap[$user->id] ?? 0) + ($postMap[$user->id] ?? 0)) / $maxActivity;
            $contribution = ($listingMap[$user->id] ?? 0) / $maxContrib;
            $reputation = min(1.0, ($ratingMap[$user->id] ?? 3.0) / 5.0);

            $score = (self::WEIGHT_ACTIVITY * $activity)
                   + (self::WEIGHT_CONTRIBUTION * $contribution)
                   + (self::WEIGHT_REPUTATION * $reputation);

            if ($user->is_verified) {
                $score *= 1.1;
            }

            return [
                'user_id'      => $user->id,
                'name'         => trim($user->first_name . ' ' . $user->last_name),
                'avatar_url'   => $user->avatar_url,
                'score'        => round(min(1.0, $score), 4),
                'activity'     => round($activity, 4),
                'contribution' => round($contribution, 4),
                'reputation'   => round($reputation, 4),
            ];
        })
        ->sortByDesc('score')
        ->take($limit)
        ->values()
        ->all();

        return $ranked;
    }

    /**
     * Check if the CommunityRank algorithm is enabled.
     */
    public function isEnabled(): bool
    {
        return \Nexus\Services\MemberRankingService::isEnabled();
    }

    /**
     * Get the current CommunityRank configuration.
     */
    public function getConfig(): array
    {
        return \Nexus\Services\MemberRankingService::getConfig();
    }

    /**
     * Clear the CommunityRank cache.
     */
    public function clearCache(): void
    {
        \Nexus\Services\MemberRankingService::clearCache();
    }
}
