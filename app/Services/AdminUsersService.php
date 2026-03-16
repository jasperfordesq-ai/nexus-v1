<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AdminUsersService — Laravel DI-based service for admin user management.
 *
 * Manages user listing, banning, unbanning, and statistics for admin dashboards.
 */
class AdminUsersService
{
    public function __construct(
        private readonly User $user,
    ) {}

    /**
     * Get all users for a tenant with filtering and pagination.
     */
    public function getAll(int $tenantId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $query = $this->user->newQuery()->where('tenant_id', $tenantId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name', 'email', 'status', 'role', 'created_at', 'last_active_at'])
            ->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Ban a user.
     */
    public function ban(int $userId, int $tenantId, ?string $reason = null): bool
    {
        return $this->user->newQuery()
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update(['status' => 'banned', 'ban_reason' => $reason, 'updated_at' => now()]) > 0;
    }

    /**
     * Unban a user.
     */
    public function unban(int $userId, int $tenantId): bool
    {
        return $this->user->newQuery()
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'banned')
            ->update(['status' => 'active', 'ban_reason' => null, 'updated_at' => now()]) > 0;
    }

    /**
     * Get user statistics for admin dashboard.
     */
    public function getStats(int $tenantId): array
    {
        $byStatus = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $activeLastWeek = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('last_active_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total'            => array_sum(array_map('intval', $byStatus)),
            'by_status'        => $byStatus,
            'active_last_week' => $activeLastWeek,
        ];
    }
}
