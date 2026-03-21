<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Event;
use App\Models\Group;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * SearchService — Laravel DI-based unified search service.
 *
 * Provides a single search() method that queries across users, listings,
 * events, and groups using basic LIKE matching. Can be extended with
 * Meilisearch/Scout integration later.
 *
 * Uses Eloquent LIKE-based matching; can be extended with Meilisearch/Scout later.
 */
class SearchService
{
    public function __construct(
        private readonly User $user,
        private readonly Listing $listing,
        private readonly Event $event,
        private readonly Group $group,
    ) {}

    /**
     * Unified search across multiple content types.
     *
     * @param string      $term  Search query.
     * @param string|null $type  Filter: 'users', 'listings', 'events', 'groups', or null for all.
     * @param int         $limit Max results per type.
     * @return array{users?: array, listings?: array, events?: array, groups?: array}
     */
    public function search(string $term, ?string $type = null, int $limit = 10): array
    {
        $limit = min($limit, 50);
        $like = '%' . $term . '%';
        $results = [];

        if ($type === null || $type === 'users') {
            $results['users'] = $this->user->newQuery()
                ->where(function (Builder $q) use ($like) {
                    $q->where('first_name', 'LIKE', $like)
                      ->orWhere('last_name', 'LIKE', $like)
                      ->orWhere('organization_name', 'LIKE', $like)
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
                })
                ->where('status', '!=', 'banned')
                ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio')
                ->limit($limit)
                ->get()
                ->map(fn (User $u) => [
                    ...$u->toArray(),
                    'result_type' => 'user',
                    'name' => ($u->profile_type === 'organisation' && $u->organization_name)
                        ? $u->organization_name
                        : trim($u->first_name . ' ' . $u->last_name),
                    'avatar' => $u->avatar_url,
                    'tagline' => $u->bio,
                ])
                ->all();
        }

        if ($type === null || $type === 'listings') {
            $results['listings'] = $this->listing->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url', 'category:id,name,color'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->where(function (Builder $q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Listing $l) => [
                    ...$l->toArray(),
                    'result_type' => 'listing',
                    'category_name' => $l->category?->name,
                ])
                ->all();
        }

        if ($type === null || $type === 'events') {
            $results['events'] = $this->event->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhere('location', 'LIKE', $like);
                })
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->limit($limit)
                ->get()
                ->map(fn (Event $e) => [...$e->toArray(), 'result_type' => 'event'])
                ->all();
        }

        if ($type === null || $type === 'groups') {
            $results['groups'] = $this->group->newQuery()
                ->withCount('activeMembers')
                ->where(function (Builder $q) use ($like) {
                    $q->where('name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Group $g) => [
                    ...$g->toArray(),
                    'result_type' => 'group',
                    'members_count' => $g->active_members_count,
                ])
                ->all();
        }

        return $results;
    }

    /**
     * Unified search with cursor pagination and flattened results.
     *
     * Used by the SearchController to produce a single paginated list
     * matching the legacy UnifiedSearchService contract.
     *
     * @param string   $term    Search query (min 2 chars)
     * @param int|null $userId  Optional authenticated user
     * @param array    $filters type, cursor, limit, category_id, sort, skills
     * @return array{items: array, cursor: string|null, has_more: bool, total: int, query: string}
     */
    public function unifiedSearch(string $term, ?int $userId = null, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 50);
        $type = $filters['type'] ?? 'all';
        $cursor = $filters['cursor'] ?? null;
        $sort = $filters['sort'] ?? 'relevance';

        $like = '%' . $term . '%';
        $allItems = [];

        // Listings
        if ($type === 'all' || $type === 'listings') {
            $lq = $this->listing->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url', 'category:id,name,color'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                })
                ->where(function (Builder $q) {
                    $q->whereNull('status')->orWhere('status', 'active');
                });

            if (! empty($filters['category_id'])) {
                $lq->where('category_id', (int) $filters['category_id']);
            }

            if (! empty($filters['skills'])) {
                $skills = is_array($filters['skills'])
                    ? $filters['skills']
                    : explode(',', $filters['skills']);
                $skills = array_map(fn ($s) => strtolower(trim($s)), array_filter($skills));
                if (! empty($skills)) {
                    $lq->whereHas('skillTags', function (Builder $q) use ($skills) {
                        $q->whereIn('tag', $skills);
                    });
                }
            }

            $this->applySortOrder($lq, $sort);
            $listings = $lq->limit($limit)->get();
            foreach ($listings as $l) {
                $allItems[] = [
                    ...$l->toArray(),
                    'result_type' => 'listing',
                    'category_name' => $l->category?->name,
                ];
            }
        }

        // Users
        if ($type === 'all' || $type === 'users') {
            $uq = $this->user->newQuery()
                ->where(function (Builder $q) use ($like) {
                    $q->where('first_name', 'LIKE', $like)
                      ->orWhere('last_name', 'LIKE', $like)
                      ->orWhere('organization_name', 'LIKE', $like)
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
                })
                ->where('status', '!=', 'banned')
                ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type', 'bio', 'created_at');

            $this->applySortOrder($uq, $sort);
            $users = $uq->limit($limit)->get();
            foreach ($users as $u) {
                $name = ($u->profile_type === 'organisation' && $u->organization_name)
                    ? $u->organization_name
                    : trim($u->first_name . ' ' . $u->last_name);
                $allItems[] = [
                    ...$u->toArray(),
                    'result_type' => 'user',
                    'name' => $name,
                    'avatar' => $u->avatar_url,
                    'tagline' => $u->bio,
                ];
            }
        }

        // Events
        if ($type === 'all' || $type === 'events') {
            $eq = $this->event->newQuery()
                ->with(['user:id,first_name,last_name,avatar_url'])
                ->where(function (Builder $q) use ($like) {
                    $q->where('title', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhere('location', 'LIKE', $like);
                })
                ->where('start_time', '>=', now());

            $this->applySortOrder($eq, $sort, 'start_time');
            $events = $eq->limit($limit)->get();
            foreach ($events as $e) {
                $allItems[] = [...$e->toArray(), 'result_type' => 'event'];
            }
        }

        // Groups
        if ($type === 'all' || $type === 'groups') {
            $gq = $this->group->newQuery()
                ->withCount('activeMembers')
                ->where(function (Builder $q) use ($like) {
                    $q->where('name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like);
                });

            $this->applySortOrder($gq, $sort);
            $groups = $gq->limit($limit)->get();
            foreach ($groups as $g) {
                $allItems[] = [
                    ...$g->toArray(),
                    'result_type' => 'group',
                    'members_count' => $g->active_members_count,
                ];
            }
        }

        $total = count($allItems);
        $hasMore = $total > $limit;
        if ($hasMore) {
            $allItems = array_slice($allItems, 0, $limit);
        }

        return [
            'items'    => $allItems,
            'cursor'   => null,
            'has_more' => $hasMore,
            'total'    => $total,
            'query'    => $term,
        ];
    }

    /**
     * Get autocomplete suggestions for a partial query.
     *
     * Returns a few results from each type for quick display.
     *
     * @return array{listings: array, users: array, events: array, groups: array}
     */
    public function suggestions(string $term, int $limit = 5): array
    {
        if (strlen($term) < 2) {
            return ['listings' => [], 'users' => [], 'events' => [], 'groups' => []];
        }

        $like = '%' . $term . '%';

        $listings = $this->listing->newQuery()
            ->where(function (Builder $q) use ($like) {
                $q->where('title', 'LIKE', $like);
            })
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->select('id', 'title', 'type')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        $users = $this->user->newQuery()
            ->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like);
            })
            ->where('status', '!=', 'banned')
            ->select('id', 'first_name', 'last_name', 'avatar_url', 'organization_name', 'profile_type')
            ->limit($limit)
            ->get()
            ->map(function (User $u) {
                $name = ($u->profile_type === 'organisation' && $u->organization_name)
                    ? $u->organization_name
                    : trim($u->first_name . ' ' . $u->last_name);
                return [...$u->toArray(), 'name' => $name];
            })
            ->all();

        $events = $this->event->newQuery()
            ->where('title', 'LIKE', $like)
            ->where('start_time', '>=', now())
            ->select('id', 'title', 'start_time')
            ->orderBy('start_time')
            ->limit($limit)
            ->get()
            ->toArray();

        $groups = $this->group->newQuery()
            ->where('name', 'LIKE', $like)
            ->select('id', 'name')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        return compact('listings', 'users', 'events', 'groups');
    }

    /**
     * Get trending search terms from the search_logs table.
     *
     * @param int $days  Look back period
     * @param int $limit Max terms to return
     * @return array Array of {term, count}
     */
    public function trending(int $days = 7, int $limit = 10): array
    {
        try {
            return DB::table('search_logs')
                ->select('query as term', DB::raw('COUNT(*) as count'))
                ->where('tenant_id', TenantContext::getId())
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            // search_logs table may not exist yet
            return [];
        }
    }

    /**
     * Apply sort order to a query builder.
     */
    private function applySortOrder(Builder $query, string $sort, string $dateColumn = 'created_at'): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc($dateColumn),
            'oldest' => $query->orderBy($dateColumn),
            default  => $query->orderByDesc('id'),
        };
    }

    /**
     * Check if the search service (Meilisearch) is available.
     *
     * Returns false — Meilisearch integration is not yet ported to Laravel.
     * The Eloquent LIKE-based search in this service is the active implementation.
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Search users by name using LIKE matching.
     *
     * @return array Array of user IDs matching the query
     */
    public static function searchUsersStatic(string $query, int $tenantId, int $limit = 200, array $extraFilters = []): array|false
    {
        $like = '%' . $query . '%';
        return User::where('tenant_id', $tenantId)
            ->where(function ($q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->where('status', '!=', 'banned')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * Index a listing for search.
     *
     * Placeholder: When Meilisearch/Scout is configured, index here.
     * For now, the LIKE-based search doesn't need indexing.
     */
    public function indexListing(Listing $listing): void
    {
        // Placeholder: When Meilisearch/Scout is configured, index here.
        // For now, the LIKE-based search doesn't need indexing.
    }

    /**
     * Remove a listing from the search index.
     *
     * Placeholder: When Meilisearch/Scout is configured, remove here.
     */
    public function removeListing(int $listingId): void
    {
        // Placeholder: When Meilisearch/Scout is configured, remove here.
    }
}
