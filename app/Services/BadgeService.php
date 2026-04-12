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
     * Award a badge to a user. The badge is identified by its string key
     * (matches the (tenant_id, user_id, badge_key) unique constraint on user_badges).
     */
    public function award(int $userId, string $badgeKey, int $tenantId, ?int $awardedBy = null): bool
    {
        // awarded_by is not a column on user_badges; the parameter is retained for API
        // compatibility but intentionally unused until the audit trail is re-introduced.
        unset($awardedBy);

        try {
            $affected = DB::affectingStatement(
                'INSERT IGNORE INTO user_badges (user_id, badge_key, tenant_id, awarded_at) VALUES (?, ?, ?, NOW())',
                [$userId, $badgeKey, $tenantId]
            );

            return $affected > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Revoke a badge from a user.
     */
    public function revoke(int $userId, string $badgeKey, int $tenantId): bool
    {
        return $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->where('badge_key', $badgeKey)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Get all badges earned by a specific user.
     */
    public function getUserBadges(int $userId, int $tenantId): array
    {
        return DB::table('user_badges as ub')
            ->join('badges as b', function ($join) {
                $join->on('ub.badge_key', '=', 'b.badge_key')
                    ->on('ub.tenant_id', '=', 'b.tenant_id');
            })
            ->where('ub.user_id', $userId)
            ->where('ub.tenant_id', $tenantId)
            ->select('b.*', 'ub.awarded_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
