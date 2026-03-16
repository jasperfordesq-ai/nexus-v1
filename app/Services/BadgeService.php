<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

/**
 * BadgeService — Laravel DI-based service for badge/achievement management.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\BadgeCollectionService.
 * Manages badge definitions, awarding, and revocation.
 */
class BadgeService
{
    public function __construct(
        private readonly UserBadge $userBadge,
    ) {}

    /**
     * Get all available badges for a tenant.
     */
    public function getAll(int $tenantId): array
    {
        return DB::table('badges')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Award a badge to a user.
     */
    public function award(int $userId, int $badgeId, int $tenantId, ?int $awardedBy = null): bool
    {
        $exists = $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->exists();

        if ($exists) {
            return false;
        }

        $this->userBadge->newQuery()->insert([
            'user_id'    => $userId,
            'badge_id'   => $badgeId,
            'tenant_id'  => $tenantId,
            'awarded_by' => $awardedBy,
            'awarded_at' => now(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Revoke a badge from a user.
     */
    public function revoke(int $userId, int $badgeId, int $tenantId): bool
    {
        return $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get all badges earned by a specific user.
     */
    public function getUserBadges(int $userId, int $tenantId): array
    {
        return DB::table('user_badges as ub')
            ->join('badges as b', 'ub.badge_id', '=', 'b.id')
            ->where('ub.user_id', $userId)
            ->where('ub.tenant_id', $tenantId)
            ->select('b.*', 'ub.awarded_at', 'ub.awarded_by')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
