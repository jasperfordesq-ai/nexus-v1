<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use Illuminate\Support\Facades\DB;

/**
 * GroupRecommendationService — Laravel DI-based service for group recommendations.
 *
 * Provides personalized group discovery using popularity and activity signals.
 */
class GroupRecommendationService
{
    /**
     * Get personalized group recommendations for a user.
     */
    public function getRecommendations(int $userId, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        if (!DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->exists()) {
            return [];
        }

        $joinedIds = DB::table('group_members')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('group_id')
            ->all();

        $query = DB::table('groups as g')
            ->leftJoin('group_members as gm', function ($join) use ($tenantId) {
                $join->on('g.id', '=', 'gm.group_id')
                    ->where('gm.tenant_id', $tenantId)
                    ->where('gm.status', 'active');
            })
            ->where('g.tenant_id', $tenantId)
            ->where('g.status', GroupStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('g.visibility')->orWhere('g.visibility', 'public');
            })
            ->select('g.*', DB::raw('COUNT(gm.id) as member_count'));

        if (! empty($joinedIds)) {
            $query->whereNotIn('g.id', $joinedIds);
        }

        $query->groupBy('g.id')->orderByDesc('member_count')->orderByDesc('g.created_at');

        return $query
            ->limit($limit)
            ->get()
            ->map(fn ($g) => (array) $g)
            ->all();
    }

    /**
     * Track a recommendation interaction (view, click, join).
     */
    public function track(int $userId, int $groupId, string $action = 'view'): bool
    {
        $tenantId = (int) TenantContext::getId();
        $normalizedAction = match ($action) {
            'view', 'viewed' => 'viewed',
            'click', 'clicked' => 'clicked',
            'join', 'joined' => 'joined',
            'dismiss', 'dismissed' => 'dismissed',
            default => null,
        };
        if ($normalizedAction === null) {
            return false;
        }

        $validUser = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->exists();
        $validGroup = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', GroupStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->exists();
        if (!$validUser || !$validGroup) {
            return false;
        }

        return DB::table('group_recommendation_interactions')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'group_id'   => $groupId,
            'action'     => $normalizedAction,
            'created_at' => now(),
        ]);
    }

    /**
     * Get groups similar to a given group (shared members / category).
     */
    public function similar(int $groupId, int $limit = 5): array
    {
        $tenantId = TenantContext::getId();
        $group = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->where('id', $groupId)
            ->where('status', GroupStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->first();
        if (! $group) {
            return [];
        }

        $memberIds = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('user_id');

        if ($memberIds->isEmpty()) {
            return DB::table('groups')
                ->where('tenant_id', $tenantId)
                ->where('status', GroupStatus::Active->value)
                ->where(function ($query) {
                    $query->whereNull('visibility')->orWhere('visibility', 'public');
                })
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
            ->where('g.status', GroupStatus::Active->value)
            ->where('gm.tenant_id', $tenantId)
            ->where('gm.status', 'active')
            ->where(function ($query) {
                $query->whereNull('g.visibility')->orWhere('g.visibility', 'public');
            })
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
