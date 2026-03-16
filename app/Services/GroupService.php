<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * GroupService — Laravel DI-based service for group operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\GroupService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class GroupService
{
    public function __construct(
        private readonly Group $group,
    ) {}

    /**
     * Get groups with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->group->newQuery()
            ->with(['creator:id,first_name,last_name,avatar_url'])
            ->withCount('activeMembers');

        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (! empty($filters['user_id'])) {
            $query->whereHas('activeMembers', function (Builder $q) use ($filters) {
                $q->where('users.id', (int) $filters['user_id']);
            });
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term);
            });
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('is_featured')->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single group by ID.
     */
    public function getById(int $id, ?int $currentUserId = null): ?array
    {
        /** @var Group|null $group */
        $group = $this->group->newQuery()
            ->with(['creator'])
            ->withCount('activeMembers')
            ->find($id);

        if (! $group) {
            return null;
        }

        $data = $group->toArray();

        if ($currentUserId) {
            $membership = DB::table('group_members')
                ->where('group_id', $id)
                ->where('user_id', $currentUserId)
                ->first();

            $data['my_role'] = $membership?->role;
            $data['my_status'] = $membership?->status;
        }

        return $data;
    }

    /**
     * Create a new group.
     */
    public function create(int $userId, array $data): Group
    {
        return DB::transaction(function () use ($userId, $data) {
            $group = $this->group->newInstance([
                'owner_id'             => $userId,
                'name'                 => trim($data['name']),
                'description'          => trim($data['description'] ?? ''),
                'visibility'           => $data['visibility'] ?? 'public',
                'image_url'            => $data['image_url'] ?? null,
                'location'             => $data['location'] ?? null,
                'latitude'             => $data['latitude'] ?? null,
                'longitude'            => $data['longitude'] ?? null,
                'type_id'              => $data['type_id'] ?? null,
                'federated_visibility' => $data['federated_visibility'] ?? 'none',
            ]);

            $group->save();

            // Auto-join creator as admin
            $group->members()->attach($userId, [
                'role'   => 'admin',
                'status' => 'active',
            ]);

            $group->cached_member_count = 1;
            $group->save();

            return $group->fresh(['creator']);
        });
    }

    /**
     * Join a group.
     */
    public function join(int $groupId, int $userId): array
    {
        /** @var Group $group */
        $group = $this->group->newQuery()->findOrFail($groupId);

        $existing = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Already a member or request pending'];
        }

        $status = $group->visibility === 'private' ? 'pending' : 'active';

        $group->members()->attach($userId, [
            'role'   => 'member',
            'status' => $status,
        ]);

        if ($status === 'active') {
            $group->increment('cached_member_count');
        }

        return ['success' => true, 'status' => $status];
    }

    /**
     * Leave a group.
     */
    public function leave(int $groupId, int $userId): bool
    {
        /** @var Group $group */
        $group = $this->group->newQuery()->findOrFail($groupId);

        $detached = $group->members()->detach($userId);

        if ($detached > 0) {
            $group->decrement('cached_member_count');
        }

        return $detached > 0;
    }
}
