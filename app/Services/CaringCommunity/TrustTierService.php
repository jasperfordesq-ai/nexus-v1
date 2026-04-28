<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AG67 — Platform-Wide Member Trust-Level Tier System
 *
 * Computes and stores the trust tier for members within a tenant.
 * Tiers: Newcomer (0) → Member (1) → Trusted (2) → Verified (3) → Coordinator (4)
 *
 * Tier is calculated from configurable per-tenant criteria:
 *   - hours_logged: count of approved vol_logs
 *   - reviews_received: count of reviews received
 *   - identity_verified: whether identity_verified_at is set on user
 */
class TrustTierService
{
    public const TIER_NEWCOMER    = 0;
    public const TIER_MEMBER      = 1;
    public const TIER_TRUSTED     = 2;
    public const TIER_VERIFIED    = 3;
    public const TIER_COORDINATOR = 4;

    public const TIER_LABELS = [
        0 => 'newcomer',
        1 => 'member',
        2 => 'trusted',
        3 => 'verified',
        4 => 'coordinator',
    ];

    public const DEFAULT_CRITERIA = [
        'member'      => ['hours_logged' => 1,  'reviews_received' => 0, 'identity_verified' => false],
        'trusted'     => ['hours_logged' => 10, 'reviews_received' => 3, 'identity_verified' => false],
        'verified'    => ['hours_logged' => 10, 'reviews_received' => 3, 'identity_verified' => true],
        'coordinator' => ['hours_logged' => 50, 'reviews_received' => 5, 'identity_verified' => true],
    ];

    /**
     * Returns the criteria config for the given tenant (from DB or defaults).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getConfig(int $tenantId): array
    {
        if (!Schema::hasTable('caring_trust_tier_config')) {
            return self::DEFAULT_CRITERIA;
        }

        $row = DB::table('caring_trust_tier_config')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($row === null) {
            return self::DEFAULT_CRITERIA;
        }

        $decoded = json_decode((string) $row->criteria, true);

        if (!is_array($decoded) || empty($decoded)) {
            return self::DEFAULT_CRITERIA;
        }

        // Merge with defaults to ensure all tiers exist
        $config = self::DEFAULT_CRITERIA;
        foreach (array_keys(self::DEFAULT_CRITERIA) as $tierName) {
            if (isset($decoded[$tierName]) && is_array($decoded[$tierName])) {
                $config[$tierName] = array_merge(self::DEFAULT_CRITERIA[$tierName], $decoded[$tierName]);
            }
        }

        return $config;
    }

    /**
     * Upsert the trust tier criteria config for a tenant.
     *
     * @param array<string, array<string, mixed>> $criteria
     */
    public function updateConfig(int $tenantId, array $criteria): void
    {
        $now = now();

        DB::table('caring_trust_tier_config')->upsert(
            [
                'tenant_id'  => $tenantId,
                'criteria'   => json_encode($criteria),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['tenant_id'],
            ['criteria', 'updated_at']
        );
    }

    /**
     * Compute the trust tier for a user based on live data.
     *
     * Walks tiers from highest (coordinator) to lowest (member).
     * Returns TIER_NEWCOMER if no tier's criteria pass.
     */
    public function computeTier(int $userId, int $tenantId): int
    {
        $criteria = $this->getConfig($tenantId);

        // Gather actual stats for this user
        $hoursLogged       = $this->countApprovedHours($userId, $tenantId);
        $reviewsReceived   = $this->countReviewsReceived($userId, $tenantId);
        $identityVerified  = $this->isIdentityVerified($userId, $tenantId);

        // Walk from highest tier to lowest
        $tierOrder = [
            self::TIER_COORDINATOR => 'coordinator',
            self::TIER_VERIFIED    => 'verified',
            self::TIER_TRUSTED     => 'trusted',
            self::TIER_MEMBER      => 'member',
        ];

        foreach ($tierOrder as $tierInt => $tierName) {
            $threshold = $criteria[$tierName] ?? self::DEFAULT_CRITERIA[$tierName];

            $hoursOk    = $hoursLogged    >= (int) ($threshold['hours_logged']      ?? 0);
            $reviewsOk  = $reviewsReceived >= (int) ($threshold['reviews_received'] ?? 0);
            $identityOk = !((bool) ($threshold['identity_verified'] ?? false)) || $identityVerified;

            if ($hoursOk && $reviewsOk && $identityOk) {
                return $tierInt;
            }
        }

        return self::TIER_NEWCOMER;
    }

    /**
     * Read the stored trust_tier value directly from the users table.
     */
    public function getTier(int $userId, int $tenantId): int
    {
        $row = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('trust_tier');

        return $row !== null ? (int) $row : self::TIER_NEWCOMER;
    }

    /**
     * Return the string label for a tier integer.
     */
    public function getTierLabel(int $tier): string
    {
        return self::TIER_LABELS[$tier] ?? self::TIER_LABELS[self::TIER_NEWCOMER];
    }

    /**
     * Recompute and store trust tiers for ALL active users in a tenant.
     *
     * @return int Number of users updated
     */
    public function recomputeAll(int $tenantId): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $userIds = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        $count = 0;
        foreach ($userIds as $userId) {
            try {
                $this->recomputeForUser((int) $userId, $tenantId);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('[TrustTierService] recomputeAll failed for user', [
                    'user_id'   => $userId,
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Recompute and store the trust tier for a single user.
     *
     * @return int The new tier value
     */
    public function recomputeForUser(int $userId, int $tenantId): int
    {
        $newTier = $this->computeTier($userId, $tenantId);

        DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update(['trust_tier' => $newTier]);

        return $newTier;
    }

    /**
     * Whether the trust tier system tables and columns exist and are ready.
     */
    public function isAvailable(): bool
    {
        return Schema::hasTable('caring_trust_tier_config')
            && Schema::hasColumn('users', 'trust_tier');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function countApprovedHours(int $userId, int $tenantId): int
    {
        if (!Schema::hasTable('vol_logs')) {
            return 0;
        }

        return (int) DB::table('vol_logs')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->count();
    }

    private function countReviewsReceived(int $userId, int $tenantId): int
    {
        if (!Schema::hasTable('reviews')) {
            return 0;
        }

        // Use reviewed_id if present, else reviewee_id (legacy column name)
        $hasReviewedId = Schema::hasColumn('reviews', 'reviewed_id');
        $column        = $hasReviewedId ? 'reviewed_id' : 'reviewee_id';

        if (!Schema::hasColumn('reviews', $column)) {
            return 0;
        }

        return (int) DB::table('reviews')
            ->where($column, $userId)
            ->where('tenant_id', $tenantId)
            ->count();
    }

    private function isIdentityVerified(int $userId, int $tenantId): bool
    {
        if (!Schema::hasColumn('users', 'identity_verified_at')) {
            return false;
        }

        $verifiedAt = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('identity_verified_at');

        return $verifiedAt !== null;
    }
}
