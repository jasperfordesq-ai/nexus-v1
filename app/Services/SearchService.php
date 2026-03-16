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

/**
 * SearchService — Laravel DI-based unified search service.
 *
 * Provides a single search() method that queries across users, listings,
 * events, and groups using basic LIKE matching. Can be extended with
 * Meilisearch/Scout integration later.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\SearchService.
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
                ->map(fn (Listing $l) => [...$l->toArray(), 'result_type' => 'listing'])
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
                ->map(fn (Group $g) => [...$g->toArray(), 'result_type' => 'group'])
                ->all();
        }

        return $results;
    }
}
