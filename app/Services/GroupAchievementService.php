<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
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
            ->where('status', GroupStatus::Active->value)
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
     * Resolve (and lazily seed) the tenant's definition row for an
     * achievement key in `group_achievements`, returning its id.
     */
    private static function resolveAchievementId(string $key, array $achievement): ?int
    {
        $tenantId = TenantContext::getId();

        $id = DB::table('group_achievements')
            ->where('tenant_id', $tenantId)
            ->where('achievement_key', $key)
            ->value('id');
        if ($id) {
            return (int) $id;
        }

        DB::table('group_achievements')->insertOrIgnore([
            'tenant_id'            => $tenantId,
            'achievement_key'      => $key,
            'name'                 => $achievement['name'],
            'description'          => $achievement['description'] ?? null,
            'icon'                 => $achievement['icon'] ?? null,
            'action_type'          => $achievement['target_type'],
            'target_count'         => $achievement['target_value'],
            'xp_reward_per_member' => $achievement['xp_reward'] ?? 25,
            'is_active'            => 1,
        ]);

        $id = DB::table('group_achievements')
            ->where('tenant_id', $tenantId)
            ->where('achievement_key', $key)
            ->value('id');

        return $id ? (int) $id : null;
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
                    "SELECT gap.*, gap.completed_at AS earned_at,
                            ga.achievement_key, ga.name, ga.icon, ga.xp_reward_per_member
                     FROM group_achievement_progress gap
                     JOIN group_achievements ga ON ga.id = gap.achievement_id
                     WHERE gap.group_id = ? AND ga.tenant_id = ? AND gap.completed_at IS NOT NULL
                     ORDER BY gap.completed_at DESC",
                    [$groupId, TenantContext::getId()]
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
                    $achievementId = self::resolveAchievementId($key, $achievement);
                    if ($achievementId === null) {
                        continue;
                    }

                    $existing = DB::table('group_achievement_progress')
                        ->where('group_id', $groupId)
                        ->where('achievement_id', $achievementId)
                        ->whereNotNull('completed_at')
                        ->exists();

                    if (!$existing) {
                        self::awardAchievement($groupId, $key, $achievement, $progress['current']);
                        $newAchievements[] = $key;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[GroupAchievement] checkAndAwardAchievements failed', [
                        'group_id' => $groupId,
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $newAchievements;
    }

    /**
     * Award a specific achievement to a group.
     */
    public static function awardAchievement($groupId, $achievementKey, $achievement, ?int $currentCount = null)
    {
        if (!self::verifyGroupTenant((int) $groupId)) {
            return false;
        }

        try {
            $achievementId = self::resolveAchievementId((string) $achievementKey, (array) $achievement);
            if ($achievementId === null) {
                return false;
            }

            DB::table('group_achievement_progress')->updateOrInsert(
                ['group_id' => $groupId, 'achievement_id' => $achievementId],
                [
                    'current_count' => $currentCount ?? ($achievement['target_value'] ?? 0),
                    'completed_at'  => now(),
                    'updated_at'    => now(),
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
