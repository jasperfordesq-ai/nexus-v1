<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * MemberRankingService — CommunityRank algorithm for ranking members.
 *
 * Computes weighted scores (activity, contribution, reputation) to rank members
 * within a tenant. Uses Eloquent/DB query builder instead of legacy Database class.
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

        // Activity: transactions in lookback window
        $txnMap = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->whereIn('sender_id', $userIds)
            ->where('created_at', '>=', $cutoff)
            ->select('sender_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('sender_id')
            ->pluck('cnt', 'user_id')
            ->all();

        // Activity: posts in lookback window
        $postMap = DB::table('feed_posts')
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', $cutoff)
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->all();

        // Contribution: listings created
        $listingMap = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->all();

        // Reputation: average review rating
        $ratingMap = DB::table('reviews')
            ->where('tenant_id', $tenantId)
            ->whereIn('reviewed_user_id', $userIds)
            ->select('reviewed_user_id as user_id', DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('reviewed_user_id')
            ->pluck('avg_rating', 'user_id')
            ->all();

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
        $config = $this->getConfig();
        return !empty($config['enabled']);
    }

    /**
     * Get the current CommunityRank configuration.
     */
    public function getConfig(): array
    {
        $defaults = [
            'enabled' => true,
            'activity_lookback_days' => self::ACTIVITY_LOOKBACK_DAYS,
            'weight_activity' => self::WEIGHT_ACTIVITY,
            'weight_contribution' => self::WEIGHT_CONTRIBUTION,
            'weight_reputation' => self::WEIGHT_REPUTATION,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = DB::table('tenants')
                ->where('id', $tenantId)
                ->value('configuration');

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['members'])) {
                    return array_merge($defaults, $configArr['algorithms']['members']);
                }
            }
        } catch (\Exception $e) {
            // Fall back to defaults
        }

        return $defaults;
    }

    /**
     * Clear the CommunityRank cache.
     */
    public function clearCache(): void
    {
        $tenantId = TenantContext::getId();
        Cache::forget("community_rank:{$tenantId}");
    }
}
