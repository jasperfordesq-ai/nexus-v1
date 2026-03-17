<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

/**
 * GamificationService — Eloquent-based service for gamification.
 *
 * XP is tracked on the users table (xp/points column). Badges in user_badges.
 */
class GamificationService
{
    public const XP_VALUES = [
        'send_credits'         => 10,
        'receive_credits'      => 5,
        'volunteer_hour'       => 20,
        'create_listing'       => 15,
        'complete_transaction' => 25,
        'leave_review'         => 10,
        'attend_event'         => 15,
        'create_event'         => 30,
        'join_group'           => 10,
        'create_group'         => 50,
        'create_post'          => 5,
        'daily_login'          => 5,
    ];

    public const LEVEL_THRESHOLDS = [
        1 => 0, 2 => 100, 3 => 300, 4 => 600, 5 => 1000,
        6 => 1500, 7 => 2200, 8 => 3000, 9 => 4000, 10 => 5500,
    ];

    public function __construct(
        private readonly User $user,
        private readonly UserBadge $userBadge,
    ) {}

    /**
     * Get gamification profile for a user (XP, level, badge count, showcased badges).
     */
    public function getProfile(int $userId, ?int $tenantId = null): array
    {
        $user = $this->user->newQuery()
            ->find($userId, ['id', 'first_name', 'last_name', 'avatar_url', 'xp', 'level', 'points']);

        if (! $user) {
            return [];
        }

        $xp = (int) ($user->xp ?? $user->points ?? 0);
        $level = (int) ($user->level ?? 1);

        // Recalculate level from XP
        foreach (self::LEVEL_THRESHOLDS as $lvl => $threshold) {
            if ($xp >= $threshold) {
                $level = $lvl;
            }
        }

        $nextThreshold = self::LEVEL_THRESHOLDS[$level + 1] ?? null;
        $currentThreshold = self::LEVEL_THRESHOLDS[$level] ?? 0;
        $progress = $nextThreshold
            ? min(100, round(($xp - $currentThreshold) / ($nextThreshold - $currentThreshold) * 100, 1))
            : 100;

        $badgeCount = $this->userBadge->where('user_id', $userId)->count();
        $showcased = $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->where('is_showcased', true)
            ->orderBy('showcase_order')
            ->get()
            ->toArray();

        // Enrich showcased with definitions from legacy service if available
        foreach ($showcased as &$badge) {
            if (class_exists('\Nexus\Services\GamificationService')) {
                $def = \Nexus\Services\GamificationService::getBadgeByKey($badge['badge_key']);
                if ($def) {
                    $badge = array_merge($badge, $def);
                }
            }
        }

        return [
            'user' => [
                'id'         => $user->id,
                'name'       => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'avatar_url' => $user->avatar_url,
            ],
            'xp'               => $xp,
            'level'            => $level,
            'level_progress'   => [
                'current_xp'       => $xp,
                'current_level'    => $level,
                'next_level_xp'    => $nextThreshold,
                'progress_percent' => $progress,
            ],
            'badges_count'     => $badgeCount,
            'showcased_badges' => $showcased,
            'xp_values'        => self::XP_VALUES,
            'level_thresholds' => self::LEVEL_THRESHOLDS,
        ];
    }

    /**
     * Get all badges earned by a user, enriched with definitions.
     */
    public function getBadges(int $userId, ?int $tenantId = null): array
    {
        $badges = $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('awarded_at')
            ->get()
            ->toArray();

        foreach ($badges as &$badge) {
            if (class_exists('\Nexus\Services\GamificationService')) {
                $def = \Nexus\Services\GamificationService::getBadgeByKey($badge['badge_key']);
                if ($def) {
                    $badge = array_merge($badge, $def);
                    $badge['description'] = $badge['msg'] ?? $badge['description'] ?? null;
                }
            }
        }

        return $badges;
    }

    /**
     * Get XP leaderboard for the current tenant.
     */
    public function getLeaderboard(?int $tenantId = null, string $period = 'all_time', int $limit = 20): array
    {
        $query = $this->user->newQuery()
            ->select(['id', 'first_name', 'last_name', 'avatar_url', 'xp', 'level', 'points'])
            ->where('is_approved', true);

        $query->orderByRaw('COALESCE(xp, points, 0) DESC')
              ->limit($limit);

        return $query->get()
            ->map(fn (User $u, int $i) => [
                'position'        => $i + 1,
                'user'            => [
                    'id'         => $u->id,
                    'name'       => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                    'avatar_url' => $u->avatar_url,
                ],
                'xp'              => (int) ($u->xp ?? $u->points ?? 0),
                'level'           => (int) ($u->level ?? 1),
                'score'           => (float) ($u->xp ?? $u->points ?? 0),
                'is_current_user' => false,
            ])
            ->all();
    }

    /**
     * Claim daily login reward (idempotent per calendar day).
     *
     * @return array|null Reward data, or null if already claimed
     */
    public function claimDailyReward(int $userId, ?int $tenantId = null): ?array
    {
        $today = now()->toDateString();

        $alreadyClaimed = DB::table('activity_log')
            ->where('user_id', $userId)
            ->where('action_type', 'daily_login')
            ->whereDate('created_at', $today)
            ->exists();

        if ($alreadyClaimed) {
            return null;
        }

        $xp = self::XP_VALUES['daily_login'];
        DB::table('users')->where('id', $userId)->increment('xp', $xp);

        DB::table('activity_log')->insert([
            'user_id'     => $userId,
            'action'      => 'Claimed daily login reward',
            'action_type' => 'daily_login',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return ['claimed' => true, 'reward' => ['xp' => $xp]];
    }

    // =========================================================================
    // Legacy delegation methods — used by AdminGamificationController
    // =========================================================================

    /**
     * Delegates to legacy GamificationService::getBadgeDefinitions().
     */
    public function getBadgeDefinitions(): array
    {
        return \Nexus\Services\GamificationService::getBadgeDefinitions();
    }

    /**
     * Delegates to legacy GamificationService::runAllBadgeChecks().
     */
    public function runAllBadgeChecks(int $userId): void
    {
        \Nexus\Services\GamificationService::runAllBadgeChecks($userId);
    }

    /**
     * Delegates to legacy GamificationService::awardBadgeByKey().
     */
    public function awardBadgeByKey(int $userId, string $badgeKey): void
    {
        \Nexus\Services\GamificationService::awardBadgeByKey($userId, $badgeKey);
    }
}
