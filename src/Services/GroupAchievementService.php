<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GroupAchievementService
{
    /**
     * Default group achievement definitions
     */
    public const GROUP_ACHIEVEMENTS = [
        'community_builders' => [
            'name' => 'Community Builders',
            'description' => 'Group collectively reaches 50 total members',
            'icon' => '🏗️',
            'target_type' => 'member_count',
            'target_value' => 50,
            'xp_reward' => 100,
        ],
        'active_hub' => [
            'name' => 'Active Hub',
            'description' => 'Group has 100 total posts and discussions',
            'icon' => '💬',
            'target_type' => 'post_count',
            'target_value' => 100,
            'xp_reward' => 150,
        ],
        'event_champions' => [
            'name' => 'Event Champions',
            'description' => 'Group hosts 10 events',
            'icon' => '🎉',
            'target_type' => 'event_count',
            'target_value' => 10,
            'xp_reward' => 200,
        ],
        'helping_hands' => [
            'name' => 'Helping Hands',
            'description' => 'Group members complete 50 transactions together',
            'icon' => '🤝',
            'target_type' => 'transaction_count',
            'target_value' => 50,
            'xp_reward' => 250,
        ],
        'volunteer_force' => [
            'name' => 'Volunteer Force',
            'description' => 'Group members log 100 volunteer hours combined',
            'icon' => '💪',
            'target_type' => 'volunteer_hours',
            'target_value' => 100,
            'xp_reward' => 300,
        ],
        'rising_stars' => [
            'name' => 'Rising Stars',
            'description' => 'Group earns 1000 XP collectively',
            'icon' => '⭐',
            'target_type' => 'collective_xp',
            'target_value' => 1000,
            'xp_reward' => 150,
        ],
        'super_community' => [
            'name' => 'Super Community',
            'description' => 'Group reaches 100 members',
            'icon' => '🌟',
            'target_type' => 'member_count',
            'target_value' => 100,
            'xp_reward' => 200,
        ],
        'legendary_hub' => [
            'name' => 'Legendary Hub',
            'description' => 'Group reaches 500 members',
            'icon' => '🏆',
            'target_type' => 'member_count',
            'target_value' => 500,
            'xp_reward' => 500,
        ],
    ];

    /**
     * Get achievements for a group with progress
     */
    public static function getGroupAchievements($groupId)
    {
        $achievements = [];
        $earnedAchievements = self::getEarnedAchievements($groupId);
        $earnedKeys = array_column($earnedAchievements, 'achievement_key');

        foreach (self::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $progress = self::calculateProgress($groupId, $achievement['target_type'], $achievement['target_value']);

            $achievements[] = [
                'key' => $key,
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon'],
                'target_value' => $achievement['target_value'],
                'current_value' => $progress['current'],
                'progress_percent' => $progress['percent'],
                'xp_reward' => $achievement['xp_reward'],
                'earned' => in_array($key, $earnedKeys),
                'earned_at' => $earnedAchievements[$key]['earned_at'] ?? null,
            ];
        }

        return $achievements;
    }

    /**
     * Calculate progress for a specific achievement type
     */
    public static function calculateProgress($groupId, $targetType, $targetValue)
    {
        $current = 0;

        switch ($targetType) {
            case 'member_count':
                $result = Database::query(
                    "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND status = 'active'",
                    [$groupId]
                )->fetch();
                $current = (int)($result['count'] ?? 0);
                break;

            case 'post_count':
                // Count discussions and posts in group
                $result = Database::query(
                    "SELECT COUNT(*) as count FROM group_discussions WHERE group_id = ?",
                    [$groupId]
                )->fetch();
                $current = (int)($result['count'] ?? 0);
                break;

            case 'event_count':
                $result = Database::query(
                    "SELECT COUNT(*) as count FROM events WHERE group_id = ?",
                    [$groupId]
                )->fetch();
                $current = (int)($result['count'] ?? 0);
                break;

            case 'transaction_count':
                // Transactions between group members
                $result = Database::query(
                    "SELECT COUNT(*) as count FROM transactions t
                     WHERE t.sender_id IN (SELECT user_id FROM group_members WHERE group_id = ?)
                     AND t.receiver_id IN (SELECT user_id FROM group_members WHERE group_id = ?)",
                    [$groupId, $groupId]
                )->fetch();
                $current = (int)($result['count'] ?? 0);
                break;

            case 'volunteer_hours':
                $result = Database::query(
                    "SELECT COALESCE(SUM(vl.hours), 0) as total FROM vol_logs vl
                     JOIN group_members gm ON vl.user_id = gm.user_id
                     WHERE gm.group_id = ? AND vl.status = 'approved'",
                    [$groupId]
                )->fetch();
                $current = (int)($result['total'] ?? 0);
                break;

            case 'collective_xp':
                $result = Database::query(
                    "SELECT COALESCE(SUM(u.xp), 0) as total FROM users u
                     JOIN group_members gm ON u.id = gm.user_id
                     WHERE gm.group_id = ? AND gm.status = 'active'",
                    [$groupId]
                )->fetch();
                $current = (int)($result['total'] ?? 0);
                break;
        }

        $percent = $targetValue > 0 ? min(100, round(($current / $targetValue) * 100)) : 0;

        return [
            'current' => $current,
            'percent' => $percent
        ];
    }

    /**
     * Get earned achievements for a group
     */
    public static function getEarnedAchievements($groupId)
    {
        $results = Database::query(
            "SELECT * FROM group_achievements WHERE group_id = ?",
            [$groupId]
        )->fetchAll();

        $earned = [];
        foreach ($results as $row) {
            $earned[$row['achievement_key']] = $row;
        }

        return $earned;
    }

    /**
     * Check and award group achievements
     */
    public static function checkAndAwardAchievements($groupId)
    {
        $earnedAchievements = self::getEarnedAchievements($groupId);
        $earnedKeys = array_column(array_values($earnedAchievements), 'achievement_key');
        $newlyEarned = [];

        foreach (self::GROUP_ACHIEVEMENTS as $key => $achievement) {
            if (in_array($key, $earnedKeys)) {
                continue; // Already earned
            }

            $progress = self::calculateProgress($groupId, $achievement['target_type'], $achievement['target_value']);

            if ($progress['current'] >= $achievement['target_value']) {
                // Award the achievement
                self::awardAchievement($groupId, $key, $achievement);
                $newlyEarned[] = $key;
            }
        }

        return $newlyEarned;
    }

    /**
     * Award achievement to group and distribute XP to members
     */
    public static function awardAchievement($groupId, $achievementKey, $achievement)
    {
        $tenantId = TenantContext::getId();

        // Record the achievement
        Database::query(
            "INSERT IGNORE INTO group_achievements (tenant_id, group_id, achievement_key, earned_at)
             VALUES (?, ?, ?, NOW())",
            [$tenantId, $groupId, $achievementKey]
        );

        // Get group members to distribute XP
        $members = Database::query(
            "SELECT user_id FROM group_members WHERE group_id = ? AND status = 'approved'",
            [$groupId]
        )->fetchAll();

        // Calculate XP per member (shared equally)
        $xpPerMember = count($members) > 0 ? max(10, round($achievement['xp_reward'] / count($members))) : 0;

        // Award XP to each member
        foreach ($members as $member) {
            GamificationService::awardXP(
                $member['user_id'],
                $xpPerMember,
                'group_achievement',
                "Group achievement: {$achievement['name']}"
            );
        }

        // Get group name for notification
        $group = Database::query("SELECT name FROM groups WHERE id = ?", [$groupId])->fetch();
        $groupName = $group['name'] ?? 'Your group';

        // Notify all members
        foreach ($members as $member) {
            \Nexus\Models\Notification::create(
                $member['user_id'],
                "{$achievement['icon']} {$groupName} earned the '{$achievement['name']}' achievement! You received {$xpPerMember} XP."
            );
        }

        return true;
    }

    /**
     * Get summary stats for a group's achievements
     */
    public static function getGroupAchievementSummary($groupId)
    {
        $achievements = self::getGroupAchievements($groupId);
        $earned = array_filter($achievements, fn($a) => $a['earned']);

        return [
            'total' => count($achievements),
            'earned' => count($earned),
            'total_xp_earned' => array_sum(array_column($earned, 'xp_reward')),
            'next_achievement' => self::getNextAchievement($achievements),
        ];
    }

    /**
     * Get the next closest achievement to unlock
     */
    public static function getNextAchievement($achievements)
    {
        $unearned = array_filter($achievements, fn($a) => !$a['earned']);

        if (empty($unearned)) {
            return null;
        }

        // Sort by progress percentage (highest first)
        usort($unearned, fn($a, $b) => $b['progress_percent'] - $a['progress_percent']);

        return $unearned[0];
    }

    /**
     * Get leaderboard of groups by achievements
     */
    public static function getGroupLeaderboard($limit = 10)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT g.id, g.name, g.photo,
                    COUNT(ga.id) as achievement_count,
                    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND status = 'approved') as member_count
             FROM groups g
             LEFT JOIN group_achievements ga ON g.id = ga.group_id
             WHERE g.tenant_id = ?
             GROUP BY g.id
             ORDER BY achievement_count DESC, member_count DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll();
    }
}
