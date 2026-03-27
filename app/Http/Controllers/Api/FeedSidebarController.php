<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * FeedSidebarController — Feed sidebar widgets (stats, suggestions, combined sidebar).
 *
 * Native Eloquent implementation — no legacy delegation.
 */
class FeedSidebarController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/community/stats
     *
     * Tenant-scoped community statistics: member count, listing count, events, groups.
     */
    public function communityStats(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        try {
            $cacheKey = "community_stats:{$tenantId}";

            $stats = Cache::remember($cacheKey, 120, function () use ($tenantId) {
                $members = (int) DB::table('users')->where('tenant_id', $tenantId)->count();
                $listings = (int) DB::table('listings')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count();

                $events = 0;
                try {
                    $events = (int) DB::table('events')->where('tenant_id', $tenantId)->count();
                } catch (\Exception $e) { /* table may not exist */ }

                $groups = 0;
                try {
                    $groups = (int) DB::table('groups')->where('tenant_id', $tenantId)->count();
                } catch (\Exception $e) { /* table may not exist */ }

                return [
                    'members'  => $members,
                    'listings' => $listings,
                    'events'   => $events,
                    'groups'   => $groups,
                ];
            });

            return $this->respondWithData($stats);
        } catch (\Throwable $e) {
            report($e);
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to load community stats', null, 500);
        }
    }

    /**
     * GET /api/v2/members/suggested
     *
     * Suggested members to connect with, excluding already-connected users.
     */
    public function suggestedMembers(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $limit = $this->queryInt('limit', 5, 1, 20);

        try {
            // Get IDs of users already connected
            $connectedIds = [$userId]; // exclude self
            try {
                $connections = DB::table('connections')
                    ->where('status', 'accepted')
                    ->where(function ($q) use ($userId) {
                        $q->where('requester_id', $userId)
                          ->orWhere('receiver_id', $userId);
                    })
                    ->selectRaw("CASE WHEN requester_id = ? THEN receiver_id ELSE requester_id END as connected_id", [$userId])
                    ->pluck('connected_id')
                    ->all();
                $connectedIds = array_merge($connectedIds, array_map('intval', $connections));
            } catch (\Exception $e) { /* connections table may not exist */ }

            $members = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereNotIn('id', $connectedIds)
                ->orderByDesc('last_active_at')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->select('id', 'first_name', 'last_name', 'organization_name', 'profile_type', 'avatar_url', 'location', 'last_active_at')
                ->get();

            $now = now();
            $filtered = $members->map(function ($m) use ($now) {
                $lastActive = $m->last_active_at ? \Carbon\Carbon::parse($m->last_active_at) : null;
                return [
                    'id'                => (int) $m->id,
                    'first_name'        => $m->first_name ?? '',
                    'last_name'         => $m->last_name ?? '',
                    'organization_name' => $m->organization_name,
                    'profile_type'      => $m->profile_type ?? 'individual',
                    'avatar_url'        => $m->avatar_url,
                    'location'          => $m->location,
                    'is_online'         => $lastActive && $lastActive->gt($now->copy()->subMinutes(5)),
                    'is_recent'         => $lastActive && $lastActive->gt($now->copy()->subDay()),
                ];
            })->values()->all();

            return $this->respondWithData($filtered);
        } catch (\Throwable $e) {
            report($e);
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to load suggestions', null, 500);
        }
    }

    /**
     * GET /api/v2/feed/sidebar
     *
     * Aggregated sidebar data for the feed page (stats, categories, events, groups,
     * listings, friends, profile stats, suggested members).
     */
    public function sidebar(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        $data = [];

        // 1. Community stats (cached — these rarely change and run 4 COUNT queries)
        try {
            $cacheKey = "sidebar_community_stats:{$tenantId}";

            $data['community_stats'] = Cache::remember($cacheKey, 120, function () use ($tenantId) {
                $stats = [
                    'members'  => (int) DB::table('users')->where('tenant_id', $tenantId)->count(),
                    'listings' => (int) DB::table('listings')->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                ];
                try { $stats['events'] = (int) DB::table('events')->where('tenant_id', $tenantId)->count(); } catch (\Exception $e) { $stats['events'] = 0; }
                try { $stats['groups'] = (int) DB::table('groups')->where('tenant_id', $tenantId)->count(); } catch (\Exception $e) { $stats['groups'] = 0; }
                return $stats;
            });
        } catch (\Throwable $e) {
            $data['community_stats'] = ['members' => 0, 'listings' => 0, 'events' => 0, 'groups' => 0];
        }

        // 2. Top categories
        try {
            $data['top_categories'] = DB::table('categories as c')
                ->join('listings as l', function ($join) use ($tenantId) {
                    $join->on('l.category_id', '=', 'c.id')
                         ->where('l.tenant_id', $tenantId)
                         ->where('l.status', 'active');
                })
                ->where('c.tenant_id', $tenantId)
                ->where('c.type', 'listing')
                ->select('c.id', 'c.name', 'c.slug', 'c.color', DB::raw('COUNT(l.id) as listing_count'))
                ->groupBy('c.id', 'c.name', 'c.slug', 'c.color')
                ->having('listing_count', '>', 0)
                ->orderByDesc('listing_count')
                ->limit(8)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            $data['top_categories'] = [];
        }

        // 3. Upcoming events
        try {
            $data['upcoming_events'] = DB::table('events')
                ->where('tenant_id', $tenantId)
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->limit(3)
                ->select('id', 'title', 'start_time', 'location')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            $data['upcoming_events'] = [];
        }

        // 4. Popular groups
        try {
            $data['popular_groups'] = DB::table('groups as g')
                ->leftJoin('group_members as gm', 'g.id', '=', 'gm.group_id')
                ->where('g.tenant_id', $tenantId)
                ->where('g.is_active', 1)
                ->select('g.id', 'g.name', 'g.description', 'g.image_url', DB::raw('COUNT(gm.id) as member_count'))
                ->groupBy('g.id', 'g.name', 'g.description', 'g.image_url')
                ->orderByDesc('member_count')
                ->orderByDesc('g.created_at')
                ->limit(3)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            $data['popular_groups'] = [];
        }

        // Authenticated-only sidebar sections
        if ($userId) {
            $now = now();

            // 5. Suggested listings
            try {
                $data['suggested_listings'] = DB::table('listings as l')
                    ->join('users as u', 'l.user_id', '=', 'u.id')
                    ->where('l.tenant_id', $tenantId)
                    ->where('l.user_id', '!=', $userId)
                    ->where('l.status', 'active')
                    ->orderByDesc('l.created_at')
                    ->limit(4)
                    ->select(
                        'l.id', 'l.title', 'l.type', 'l.image_url',
                        DB::raw("COALESCE(NULLIF(u.name, ''), CONCAT(u.first_name, ' ', u.last_name)) as owner_name")
                    )
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            } catch (\Throwable $e) {
                $data['suggested_listings'] = [];
            }

            // 6. Friends (connections)
            try {
                $data['friends'] = DB::table('connections as c')
                    ->join('users as u', function ($join) use ($userId) {
                        $join->whereRaw("u.id = CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END", [$userId]);
                    })
                    ->where(function ($q) use ($userId) {
                        $q->where('c.requester_id', $userId)->orWhere('c.receiver_id', $userId);
                    })
                    ->where('c.status', 'accepted')
                    ->orderByDesc('u.last_active_at')
                    ->limit(8)
                    ->select('u.id', 'u.first_name', 'u.last_name', 'u.organization_name', 'u.profile_type', 'u.avatar_url', 'u.location', 'u.last_active_at')
                    ->get()
                    ->map(function ($f) use ($now) {
                        $arr = (array) $f;
                        $lastActive = $f->last_active_at ? \Carbon\Carbon::parse($f->last_active_at) : null;
                        $arr['is_online'] = $lastActive && $lastActive->gt($now->copy()->subMinutes(5));
                        $arr['is_recent'] = $lastActive && $lastActive->gt($now->copy()->subDay());
                        return $arr;
                    })
                    ->all();
            } catch (\Throwable $e) {
                $data['friends'] = [];
            }

            // 7. Profile stats
            try {
                $data['profile_stats'] = [
                    'total_listings' => (int) DB::table('listings')->where('user_id', $userId)->where('tenant_id', $tenantId)->count(),
                    'offers'         => (int) DB::table('listings')->where('user_id', $userId)->where('tenant_id', $tenantId)->where('type', 'offer')->count(),
                    'requests'       => (int) DB::table('listings')->where('user_id', $userId)->where('tenant_id', $tenantId)->where('type', 'request')->count(),
                    'hours_given'    => (float) DB::table('transactions')->where('sender_id', $userId)->where('tenant_id', $tenantId)->sum('amount'),
                    'hours_received' => (float) DB::table('transactions')->where('receiver_id', $userId)->where('tenant_id', $tenantId)->sum('amount'),
                ];
            } catch (\Throwable $e) {
                $data['profile_stats'] = null;
            }

            // 8. Suggested members (People You May Know)
            try {
                $connectedIds = [$userId];
                try {
                    $cids = DB::table('connections')
                        ->where('status', 'accepted')
                        ->where(function ($q) use ($userId) {
                            $q->where('requester_id', $userId)->orWhere('receiver_id', $userId);
                        })
                        ->selectRaw("CASE WHEN requester_id = ? THEN receiver_id ELSE requester_id END as cid", [$userId])
                        ->pluck('cid')
                        ->all();
                    $connectedIds = array_merge($connectedIds, array_map('intval', $cids));
                } catch (\Exception $e) {}

                $data['suggested_members'] = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->whereNotIn('id', $connectedIds)
                    ->orderByDesc('last_active_at')
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->select('id', 'first_name', 'last_name', 'organization_name', 'profile_type', 'avatar_url', 'location', 'last_active_at')
                    ->get()
                    ->map(function ($m) use ($now) {
                        $arr = (array) $m;
                        $lastActive = $m->last_active_at ? \Carbon\Carbon::parse($m->last_active_at) : null;
                        $arr['is_online'] = $lastActive && $lastActive->gt($now->copy()->subMinutes(5));
                        $arr['is_recent'] = $lastActive && $lastActive->gt($now->copy()->subDay());
                        return $arr;
                    })
                    ->all();
            } catch (\Throwable $e) {
                $data['suggested_members'] = [];
            }
        }

        return $this->respondWithData($data);
    }
}
