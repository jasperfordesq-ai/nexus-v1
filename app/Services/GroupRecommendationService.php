<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GroupRecommendationService — Laravel DI-based service for group recommendations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\GroupRecommendationEngine.
 * Provides personalized group discovery using popularity and activity signals.
 */
class GroupRecommendationService
{
    /**
     * Get personalized group recommendations for a user.
     */
    public function getRecommendations(int $userId, int $limit = 10): array
    {
        $joinedIds = DB::table('group_members')
            ->where('user_id', $userId)
            ->pluck('group_id')
            ->all();

        $tenantId = TenantContext::getId();

        $query = DB::table('groups as g')
            ->leftJoin(DB::raw('(SELECT group_id, COUNT(*) as member_count FROM group_members GROUP BY group_id) as gm'), 'g.id', '=', 'gm.group_id')
            ->where('g.tenant_id', $tenantId)
            ->where('g.status', 'active')
            ->select('g.*', DB::raw('COALESCE(gm.member_count, 0) as member_count'));

        if (! empty($joinedIds)) {
            $query->whereNotIn('g.id', $joinedIds);
        }

        $query->orderByDesc('member_count')->orderByDesc('g.created_at');

        return $query
            ->limit($limit)
            ->get()
            ->map(fn ($g) => (array) $g)
            ->all();
    }

    /**
     * Track a recommendation interaction (view, click, join).
     */
    public function track(int $userId, int $groupId, string $action = 'view'): void
    {
        DB::table('group_recommendation_events')->insert([
            'user_id'    => $userId,
            'group_id'   => $groupId,
            'action'     => $action,
            'created_at' => now(),
        ]);
    }

    /**
     * Get groups similar to a given group (shared members / category).
     */
    public function similar(int $groupId, int $limit = 5): array
    {
        $tenantId = TenantContext::getId();
        $group = DB::table('groups')->where('tenant_id', $tenantId)->where('id', $groupId)->first();
        if (! $group) {
            return [];
        }

        $memberIds = DB::table('group_members')
            ->where('group_id', $groupId)
            ->pluck('user_id');

        if ($memberIds->isEmpty()) {
            return DB::table('groups')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('id', '!=', $groupId)
                ->limit($limit)
                ->get()
                ->map(fn ($g) => (array) $g)
                ->all();
        }

        return DB::table('groups as g')
            ->join('group_members as gm', 'g.id', '=', 'gm.group_id')
            ->where('g.tenant_id', $tenantId)
            ->where('g.id', '!=', $groupId)
            ->where('g.status', 'active')
            ->whereIn('gm.user_id', $memberIds)
            ->select('g.*', DB::raw('COUNT(DISTINCT gm.user_id) as shared_members'))
            ->groupBy('g.id')
            ->orderByDesc('shared_members')
            ->limit($limit)
            ->get()
            ->map(fn ($g) => (array) $g)
            ->all();
    }
}
