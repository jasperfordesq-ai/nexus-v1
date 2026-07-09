<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupChallengeService — Time-bound group goals with collective rewards.
 *
 * Features: create challenges, track progress, award rewards on completion.
 */
class GroupChallengeService
{
    /**
     * Get active challenges for a group.
     */
    public static function getActive(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_challenges')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->get()
            ->map(function ($row) {
                $row = (array) $row;
                $row['progress_percentage'] = $row['target_value'] > 0
                    ? min(100, round(($row['current_value'] / $row['target_value']) * 100, 1))
                    : 0;
                return $row;
            })
            ->toArray();
    }

    /**
     * Get all challenges (including completed/expired).
     */
    public static function getAll(int $groupId, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_challenges')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row = (array) $row;
                $row['progress_percentage'] = $row['target_value'] > 0
                    ? min(100, round(($row['current_value'] / $row['target_value']) * 100, 1))
                    : 0;
                return $row;
            })
            ->toArray();
    }

    /**
     * Create a challenge.
     */
    public static function create(int $groupId, int $createdBy, array $data): int
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_challenges')->insertGetId([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'created_by' => $createdBy,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'metric' => $data['metric'], // e.g., 'posts', 'discussions', 'events', 'members', 'files'
            'target_value' => (int) $data['target_value'],
            'current_value' => 0,
            'reward_xp' => (int) ($data['reward_xp'] ?? 100),
            'reward_badge' => $data['reward_badge'] ?? null,
            'status' => 'active',
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Increment challenge progress for a metric.
     * Called automatically when relevant actions happen (post created, member joined, etc.)
     */
    public static function incrementProgress(int $groupId, string $metric, int $amount = 1): void
    {
        $tenantId = TenantContext::getId();

        $challenges = DB::table('group_challenges')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('metric', $metric)
            ->where('ends_at', '>', now())
            ->get();

        foreach ($challenges as $challenge) {
            $newValue = min($challenge->current_value + $amount, $challenge->target_value);

            // No tenant filter needed: $challenge->id came from the
            // tenant-scoped query above (id-from-scoped-context).
            DB::table('group_challenges')
                ->where('id', $challenge->id)
                ->update(['current_value' => $newValue, 'updated_at' => now()]);

            // Check for completion
            if ($newValue >= $challenge->target_value && $challenge->current_value < $challenge->target_value) {
                self::complete($challenge->id);
            }
        }
    }

    /**
     * Mark a challenge as completed and award rewards.
     *
     * Private, and only reachable with ids that came from tenant-scoped
     * queries (id-from-scoped-context) — hence no tenant filter here.
     */
    private static function complete(int $challengeId): void
    {
        $challenge = DB::table('group_challenges')->where('id', $challengeId)->first();
        if (!$challenge) return;

        DB::table('group_challenges')
            ->where('id', $challengeId)
            ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);

        // Award XP to all active group members
        if ($challenge->reward_xp > 0) {
            $members = DB::table('group_members')
                ->where('group_id', $challenge->group_id)
                ->where('status', 'active')
                ->pluck('user_id');

            foreach ($members as $userId) {
                // Canonical XP path: logs to user_xp_log AND increments
                // users.xp (the balance the shop/leaderboard read), plus
                // leaderboard invalidation, broadcast, and level-up check.
                // A bare user_xp_log insert leaves the reward unspendable.
                GamificationService::awardXP(
                    (int) $userId,
                    (int) $challenge->reward_xp,
                    'group_challenge',
                    __('api.group_challenge_completed_xp', ['title' => $challenge->title])
                );
            }
        }
    }

    /**
     * Expire overdue challenges.
     *
     * DELIBERATELY CROSS-TENANT (2026-07-09 audit): cron maintenance — a
     * challenge is overdue by its own ends_at regardless of tenant, so the
     * UPDATE intentionally omits tenant_id. Do NOT add a tenant filter, and
     * do NOT copy this query shape into request-path code.
     */
    public static function expireOverdue(): int
    {
        return DB::table('group_challenges')
            ->where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /**
     * Delete a challenge.
     */
    public static function delete(int $groupId, int $challengeId): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_challenges')
            ->where('id', $challengeId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }
}
