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
        // Use INSERT IGNORE to atomically prevent duplicate badge awards.
        // The user_badge_unique (user_id, badge_key) constraint enforces uniqueness.
        try {
            $affected = DB::affectingStatement(
                'INSERT IGNORE INTO user_badges (user_id, badge_id, tenant_id, awarded_by, awarded_at, created_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
                [$userId, $badgeId, $tenantId, $awardedBy]
            );

            return $affected > 0;
        } catch (\Throwable $e) {
            // Duplicate key — badge already awarded
            return false;
        }
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
