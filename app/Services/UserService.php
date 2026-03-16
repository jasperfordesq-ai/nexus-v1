<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * UserService — Laravel DI-based service for user/profile operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\UserService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class UserService
{
    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Get a user by ID with related data.
     */
    public function getById(int $id): ?array
    {
        $user = $this->user->newQuery()
            ->with(['listings', 'badges'])
            ->find($id);

        if (! $user) {
            return null;
        }

        $data = $user->toArray();
        $data['name'] = ($user->profile_type === 'organisation' && $user->organization_name)
            ? $user->organization_name
            : trim($user->first_name . ' ' . $user->last_name);

        return $data;
    }

    /**
     * Get the authenticated user's own profile (for /me endpoint).
     */
    public function getMe(int $userId): ?array
    {
        $user = $this->user->newQuery()
            ->with(['listings', 'badges'])
            ->find($userId);

        if (! $user) {
            return null;
        }

        $data = $user->toArray();
        $data['name'] = ($user->profile_type === 'organisation' && $user->organization_name)
            ? $user->organization_name
            : trim($user->first_name . ' ' . $user->last_name);

        $data['stats'] = [
            'balance'           => (float) $user->balance,
            'listings_count'    => $user->listings()->count(),
            'connections_count' => DB::table('connections')
                ->where('status', 'accepted')
                ->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('receiver_id', $userId))
                ->count(),
            'reviews_count'     => DB::table('reviews')->where('receiver_id', $userId)->count(),
        ];

        return $data;
    }

    /**
     * Update a user profile.
     */
    public function update(int $id, array $data): User
    {
        /** @var User $user */
        $user = $this->user->newQuery()->findOrFail($id);

        $allowed = [
            'first_name', 'last_name', 'bio', 'location', 'latitude', 'longitude',
            'phone', 'avatar_url', 'organization_name', 'profile_type',
        ];

        $user->fill(collect($data)->only($allowed)->all());
        $user->save();

        return $user->fresh();
    }

    /**
     * Search users by name, email, or location.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function search(string $term, int $limit = 20): array
    {
        $limit = min($limit, 100);
        $like = '%' . $term . '%';

        $items = $this->user->newQuery()
            ->where(function (Builder $q) use ($like) {
                $q->where('first_name', 'LIKE', $like)
                  ->orWhere('last_name', 'LIKE', $like)
                  ->orWhere('email', 'LIKE', $like)
                  ->orWhere('organization_name', 'LIKE', $like)
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->where('status', '!=', 'banned')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

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
}
