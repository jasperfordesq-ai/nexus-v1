<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupAchievementService — tracks group milestones and awards achievements.
 */
class GroupAchievementService
{
    /** Group achievement definitions */
    public const GROUP_ACHIEVEMENTS = [
        'community_builders' => [
            'name'         => 'Community Builders',
            'description'  => 'Group reached 50 members',
            'target_type'  => 'member_count',
            'target_value' => 50,
            'xp_reward'    => 500,
            'icon'         => 'users',
        ],
        'active_hub' => [
            'name'         => 'Active Hub',
            'description'  => 'Group reached 100 discussion posts',
            'target_type'  => 'post_count',
            'target_value' => 100,
            'xp_reward'    => 300,
            'icon'         => 'message-circle',
        ],
        'event_masters' => [
            'name'         => 'Event Masters',
            'description'  => 'Group hosted 10 events',
            'target_type'  => 'event_count',
            'target_value' => 10,
            'xp_reward'    => 400,
            'icon'         => 'calendar',
        ],
        'first_steps' => [
            'name'         => 'First Steps',
            'description'  => 'Group reached 10 members',
            'target_type'  => 'member_count',
            'target_value' => 10,
            'xp_reward'    => 100,
            'icon'         => 'footprints',
        ],
        'discussion_starters' => [
            'name'         => 'Discussion Starters',
            'description'  => 'Group reached 10 discussion threads',
            'target_type'  => 'discussion_count',
            'target_value' => 10,
            'xp_reward'    => 200,
            'icon'         => 'message-square',
        ],
    ];

    public function __construct()
    {
    }

    /**
     * Verify a group belongs to the current tenant.
     */
    private static function verifyGroupTenant(int $groupId): bool
    {
        return DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', TenantContext::getId())
            ->exists();
    }

    /**
     * Get all achievements for a group with progress info.
     */
    public static function getGroupAchievements($groupId)
    {
        if (!self::verifyGroupTenant((int) $groupId)) {
            return [];
        }

        $earned = self::getEarnedAchievements($groupId);
        $earnedKeys = array_column($earned, 'achievement_key');

        $achievements = [];
        foreach (self::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $progress = self::calculateProgress($groupId, $achievement['target_type'], $achievement['target_value']);

            $achievements[] = [
                'key'              => $key,
                'name'             => $achievement['name'],
                'description'      => $achievement['description'],
                'target_type'      => $achievement['target_type'],
                'target_value'     => $achievement['target_value'],
                'xp_reward'        => $achievement['xp_reward'],
                'icon'             => $achievement['icon'] ?? null,
                'progress_percent' => $progress['percent'],
                'current_value'    => $progress['current'],
                'earned'           => in_array($key, $earnedKeys, true),
            ];
        }

        return $achievements;
    }

    /**
     * Calculate progress toward an achievement target.
     *
     * @return array{current: int, percent: float}
     */
    public static function calculateProgress($groupId, $targetType, $targetValue)
    {
        if ($targetValue <= 0) {
            return ['current' => 0, 'percent' => 0];
        }

        $current = 0;

        try {
            switch ($targetType) {
                case 'member_count':
                    $current = (int) DB::table('group_members')
                        ->where('group_id', $groupId)
                        ->where('status', 'active')
                        ->count();
                    break;

                case 'post_count':
                    $current = (int) DB::table('group_posts as gp')
                        ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
                        ->where('gd.group_id', $groupId)
                        ->count();
                    break;

                case 'discussion_count':
                    $current = (int) DB::table('group_discussions')
                        ->where('group_id', $groupId)
                        ->count();
                    break;

                case 'event_count':
                    $current = (int) DB::table('events')
                        ->where('group_id', $groupId)
                        ->count();
                    break;
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        $percent = min(100, round(($current / $targetValue) * 100, 1));

        return ['current' => $current, 'percent' => $percent];
    }

    /**
     * Get achievements that have been earned by a group.
     */
    public static function getEarnedAchievements($groupId)
    {
        if (!self::verifyGroupTenant((int) $groupId)) {
            return [];
        }

        try {
            return array_map(
                fn ($row) => (array) $row,
                DB::select(
                    "SELECT * FROM group_achievement_progress
                     WHERE group_id = ? AND earned = 1
                     ORDER BY earned_at DESC",
                    [$groupId]
                )
            );
        } catch (\Throwable $e) {
            Log::debug('[GroupAchievement] getEarnedAchievements failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check all achievements and award any that have been completed.
     *
     * @return array Newly awarded achievement keys
     */
    public static function checkAndAwardAchievements($groupId)
    {
        if (!self::verifyGroupTenant((int) $groupId)) {
            return [];
        }

        $newAchievements = [];

        foreach (self::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $progress = self::calculateProgress($groupId, $achievement['target_type'], $achievement['target_value']);

            if ($progress['current'] >= $achievement['target_value']) {
                // Check if already awarded
                try {
                    $existing = DB::table('group_achievement_progress')
                        ->where('group_id', $groupId)
                        ->where('achievement_key', $key)
                        ->where('earned', 1)
                        ->exists();

                    if (!$existing) {
                        self::awardAchievement($groupId, $key, $achievement);
                        $newAchievements[] = $key;
                    }
                } catch (\Throwable $e) {
                    // Table may not exist
                }
            }
        }

        return $newAchievements;
    }

    /**
     * Award a specific achievement to a group.
     */
    public static function awardAchievement($groupId, $achievementKey, $achievement)
    {
        if (!self::verifyGroupTenant((int) $groupId)) {
            return false;
        }

        try {
            DB::table('group_achievement_progress')->updateOrInsert(
                ['group_id' => $groupId, 'achievement_key' => $achievementKey],
                [
                    'earned'     => 1,
                    'earned_at'  => now(),
                    'xp_reward'  => $achievement['xp_reward'] ?? 0,
                    'updated_at' => now(),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to award group achievement', [
                'group_id' => $groupId,
                'key' => $achievementKey,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
