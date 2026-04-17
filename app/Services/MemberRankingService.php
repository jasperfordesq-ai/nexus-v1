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
    private const WEIGHT_ACTIVITY = 0.30;
    private const WEIGHT_CONTRIBUTION = 0.25;
    private const WEIGHT_REPUTATION = 0.25;
    private const WEIGHT_CONNECTIONS = 0.20;

    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Rank members within a tenant using the CommunityRank algorithm.
     *
     * @return array{items: array<int, array<string, float|int|string|null>>, total: int}
     */
    public function rankMembers(
        int $tenantId,
        int $limit = 50,
        int $offset = 0,
        string $search = '',
        ?int $viewerId = null
    ): array
    {
        $config = $this->getConfig();
        $cutoff = now()->subDays((int) ($config['activity_lookback_days'] ?? self::ACTIVITY_LOOKBACK_DAYS))->toDateTimeString();

        $usersQuery = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('privacy_search', 1)
                    ->orWhereNull('privacy_search');
            })
            ->select(['id', 'first_name', 'last_name', 'avatar_url', 'points',
                       'is_verified', 'created_at', 'organization_name', 'profile_type']);

        if ($viewerId) {
            $usersQuery->where('id', '!=', $viewerId);
        }

        if ($search !== '') {
            $memberIds = SearchService::searchUsersStatic($search, $tenantId);
            if ($memberIds !== false && !empty($memberIds)) {
                $usersQuery->whereIn('id', array_map('intval', $memberIds));
            } elseif ($memberIds !== false) {
                $usersQuery->whereRaw('1 = 0');
            } else {
                $like = '%' . $search . '%';
                $usersQuery->where(function ($query) use ($search, $like) {
                    $query->whereRaw(
                        "MATCH(first_name, last_name, bio, skills) AGAINST(? IN BOOLEAN MODE)",
                        [$search]
                    )
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", [$like])
                    ->orWhere('organization_name', 'LIKE', $like)
                    ->orWhere('location', 'LIKE', $like);
                });
            }
        }

        OnboardingConfigService::applyVisibilityScope($usersQuery);

        $users = $usersQuery->get();

        if ($users->isEmpty()) {
            return ['items' => [], 'total' => 0];
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

        $requesterConnectionMap = DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereIn('requester_id', $userIds)
            ->select('requester_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('requester_id')
            ->pluck('cnt', 'user_id')
            ->all();

        $receiverConnectionMap = DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereIn('receiver_id', $userIds)
            ->select('receiver_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('receiver_id')
            ->pluck('cnt', 'user_id')
            ->all();

        // Reputation: average review rating across BOTH local reviews (tenant_id scope)
        // AND federated reviews received from other tenants (reputation portability).
        // A single AVG(rating) merges both sets so members carry reputation globally.
        $ratingMap = DB::table('reviews')
            ->whereIn('receiver_id', $userIds)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhere(function ($q2) use ($tenantId) {
                      $q2->where('receiver_tenant_id', $tenantId)
                         ->where('review_type', 'federated')
                         ->where('show_cross_tenant', 1);
                  });
            })
            ->select('receiver_id as user_id', DB::raw('AVG(rating) as avg_rating'))
            ->groupBy('receiver_id')
            ->pluck('avg_rating', 'user_id')
            ->all();

        // Normalize and score
        $maxActivity = max(1, max(array_merge([0], array_values($txnMap), array_values($postMap))));
        $maxContrib = max(1, max(array_merge([0], array_values($listingMap))));
        $connectionMap = [];
        foreach ($userIds as $userId) {
            $connectionMap[$userId] = (int) ($requesterConnectionMap[$userId] ?? 0) + (int) ($receiverConnectionMap[$userId] ?? 0);
        }
        $maxConnections = max(1, max(array_merge([0], array_values($connectionMap))));

        $weights = [
            'activity' => max(0.0, (float) ($config['weight_activity'] ?? self::WEIGHT_ACTIVITY)),
            'contribution' => max(0.0, (float) ($config['weight_contribution'] ?? self::WEIGHT_CONTRIBUTION)),
            'reputation' => max(0.0, (float) ($config['weight_reputation'] ?? self::WEIGHT_REPUTATION)),
            'connections' => max(0.0, (float) ($config['weight_connections'] ?? self::WEIGHT_CONNECTIONS)),
        ];
        $weightTotal = array_sum($weights) ?: 1.0;

        $ranked = $users->map(function ($user) use ($txnMap, $postMap, $listingMap, $ratingMap, $connectionMap, $maxActivity, $maxContrib, $maxConnections, $weights, $weightTotal) {
            $activity = (($txnMap[$user->id] ?? 0) + ($postMap[$user->id] ?? 0)) / $maxActivity;
            $contribution = ($listingMap[$user->id] ?? 0) / $maxContrib;
            $reputation = min(1.0, ($ratingMap[$user->id] ?? 3.0) / 5.0);
            $connections = ($connectionMap[$user->id] ?? 0) / $maxConnections;

            $score = (
                ($weights['activity'] * $activity)
                + ($weights['contribution'] * $contribution)
                + ($weights['reputation'] * $reputation)
                + ($weights['connections'] * $connections)
            ) / $weightTotal;

            if ($user->is_verified) {
                $score *= 1.1;
            }

            return [
                'user_id'      => $user->id,
                'name'         => ($user->profile_type === 'organisation' && !empty($user->organization_name))
                    ? $user->organization_name
                    : trim($user->first_name . ' ' . $user->last_name),
                'avatar_url'   => $user->avatar_url,
                'score'        => round(min(1.0, $score), 4),
                'activity'     => round($activity, 4),
                'contribution' => round($contribution, 4),
                'reputation'   => round($reputation, 4),
                'connections'  => round($connections, 4),
            ];
        })
        ->sortByDesc('score')
        ->values()
        ->all();

        return [
            'items' => array_slice($ranked, $offset, $limit),
            'total' => count($ranked),
        ];
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
            'weight_connections' => self::WEIGHT_CONNECTIONS,
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
