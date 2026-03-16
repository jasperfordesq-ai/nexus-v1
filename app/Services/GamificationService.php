<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

/**
 * GamificationService — Laravel DI-based service for gamification.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\GamificationService.
 * XP is tracked on the users table (points column). Badges in user_badges.
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
     * Get gamification profile for a user (XP, level, badge count).
     */
    public function getProfile(int $userId): array
    {
        $user = $this->user->newQuery()->find($userId, ['id', 'points', 'first_name', 'last_name', 'avatar_url']);

        if (! $user) {
            return [];
        }

        $xp = (int) ($user->points ?? 0);
        $level = 1;
        foreach (self::LEVEL_THRESHOLDS as $lvl => $threshold) {
            if ($xp >= $threshold) {
                $level = $lvl;
            }
        }

        $nextThreshold = self::LEVEL_THRESHOLDS[$level + 1] ?? null;

        return [
            'user_id'       => $userId,
            'xp'            => $xp,
            'level'         => $level,
            'next_level_xp' => $nextThreshold,
            'badge_count'   => $this->userBadge->where('user_id', $userId)->count(),
        ];
    }

    /**
     * Get all badges earned by a user.
     */
    public function getBadges(int $userId): array
    {
        return $this->userBadge->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('awarded_at')
            ->get()
            ->toArray();
    }

    /**
     * Get XP leaderboard for the current tenant.
     */
    public function getLeaderboard(int $limit = 20): array
    {
        return $this->user->newQuery()
            ->select(['id', 'first_name', 'last_name', 'avatar_url', 'points'])
            ->where('status', 'active')
            ->orderByDesc('points')
            ->limit($limit)
            ->get()
            ->map(fn (User $u, int $i) => [
                'rank'       => $i + 1,
                'user_id'    => $u->id,
                'name'       => trim($u->first_name . ' ' . $u->last_name),
                'avatar_url' => $u->avatar_url,
                'xp'         => (int) ($u->points ?? 0),
            ])
            ->all();
    }

    /**
     * Claim daily login reward (idempotent per calendar day).
     */
    public function claimDailyReward(int $userId): array
    {
        $today = now()->toDateString();

        $alreadyClaimed = DB::table('activity_log')
            ->where('user_id', $userId)
            ->where('action_type', 'daily_login')
            ->whereDate('created_at', $today)
            ->exists();

        if ($alreadyClaimed) {
            return ['claimed' => false, 'reason' => 'already_claimed'];
        }

        $xp = self::XP_VALUES['daily_login'];
        DB::table('users')->where('id', $userId)->increment('points', $xp);

        DB::table('activity_log')->insert([
            'user_id'     => $userId,
            'action'      => 'Claimed daily login reward',
            'action_type' => 'daily_login',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return ['claimed' => true, 'xp_awarded' => $xp];
    }
}
