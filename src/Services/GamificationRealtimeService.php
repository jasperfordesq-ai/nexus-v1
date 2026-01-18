<?php

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * GamificationRealtimeService - Real-time updates for gamification events
 *
 * Broadcasts achievement events via Pusher for instant UI updates
 */
class GamificationRealtimeService
{
    /**
     * Broadcast a badge earned event
     */
    public static function broadcastBadgeEarned(int $userId, array $badge): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'badge-earned',
            [
                'badge' => [
                    'key' => $badge['key'] ?? '',
                    'name' => $badge['name'] ?? '',
                    'icon' => $badge['icon'] ?? '🏆',
                    'xp' => $badge['xp'] ?? 0,
                    'description' => $badge['description'] ?? '',
                ],
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast XP gained event
     */
    public static function broadcastXPGained(int $userId, int $amount, string $reason, array $levelInfo = []): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'xp-gained',
            [
                'amount' => $amount,
                'reason' => $reason,
                'new_total' => $levelInfo['total_xp'] ?? 0,
                'level' => $levelInfo['level'] ?? 1,
                'progress' => $levelInfo['progress'] ?? 0,
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast level up event
     */
    public static function broadcastLevelUp(int $userId, int $newLevel, array $rewards = []): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'level-up',
            [
                'new_level' => $newLevel,
                'rewards' => $rewards,
                'celebration' => self::getLevelCelebration($newLevel),
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast challenge completed event
     */
    public static function broadcastChallengeCompleted(int $userId, array $challenge): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'challenge-completed',
            [
                'challenge' => [
                    'id' => $challenge['id'] ?? 0,
                    'title' => $challenge['title'] ?? '',
                    'xp_reward' => $challenge['xp_reward'] ?? 0,
                    'type' => $challenge['type'] ?? 'weekly',
                ],
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast collection completed event
     */
    public static function broadcastCollectionCompleted(int $userId, array $collection): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'collection-completed',
            [
                'collection' => [
                    'name' => $collection['name'] ?? '',
                    'icon' => $collection['icon'] ?? '📚',
                    'bonus_xp' => $collection['bonus_xp'] ?? 0,
                ],
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast daily reward claimed event
     */
    public static function broadcastDailyReward(int $userId, array $reward): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'daily-reward',
            [
                'xp' => $reward['xp'] ?? 0,
                'streak_day' => $reward['streak_day'] ?? 1,
                'milestone_bonus' => $reward['milestone_bonus'] ?? 0,
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast streak milestone event
     */
    public static function broadcastStreakMilestone(int $userId, int $days, int $bonusXP): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'streak-milestone',
            [
                'days' => $days,
                'bonus_xp' => $bonusXP,
                'message' => self::getStreakMessage($days),
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast leaderboard rank change
     */
    public static function broadcastRankChange(int $userId, int $oldRank, int $newRank, string $leaderboardType = 'xp'): bool
    {
        $direction = $newRank < $oldRank ? 'up' : 'down';
        $change = abs($oldRank - $newRank);

        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'rank-change',
            [
                'old_rank' => $oldRank,
                'new_rank' => $newRank,
                'direction' => $direction,
                'change' => $change,
                'leaderboard' => $leaderboardType,
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast progress update (near badge completion)
     */
    public static function broadcastProgressUpdate(int $userId, array $progress): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'progress-update',
            [
                'badge_key' => $progress['badge_key'] ?? '',
                'badge_name' => $progress['badge_name'] ?? '',
                'badge_icon' => $progress['badge_icon'] ?? '🎯',
                'current' => $progress['current'] ?? 0,
                'target' => $progress['target'] ?? 1,
                'percent' => $progress['percent'] ?? 0,
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Broadcast shop purchase event
     */
    public static function broadcastShopPurchase(int $userId, array $item): bool
    {
        return PusherService::trigger(
            PusherService::getUserChannel($userId),
            'shop-purchase',
            [
                'item' => [
                    'name' => $item['name'] ?? '',
                    'icon' => $item['icon'] ?? '🎁',
                    'xp_cost' => $item['xp_cost'] ?? 0,
                ],
                'timestamp' => time(),
            ]
        );
    }

    /**
     * Get celebration config based on level
     */
    private static function getLevelCelebration(int $level): array
    {
        // Milestone levels get special celebrations
        $milestones = [5, 10, 15, 20, 25, 30, 40, 50, 75, 100];

        if (in_array($level, $milestones)) {
            return [
                'type' => 'milestone',
                'confetti' => true,
                'fireworks' => $level >= 25,
                'sound' => 'fanfare',
                'duration' => 5000,
                'title' => self::getMilestoneTitle($level),
            ];
        }

        return [
            'type' => 'standard',
            'confetti' => true,
            'fireworks' => false,
            'sound' => 'levelup',
            'duration' => 3000,
            'title' => "Level {$level}!",
        ];
    }

    /**
     * Get milestone title based on level
     */
    private static function getMilestoneTitle(int $level): string
    {
        $titles = [
            5 => '🌟 Rising Star!',
            10 => '⭐ Community Member!',
            15 => '💫 Active Contributor!',
            20 => '🔥 Timebank Pro!',
            25 => '👑 Quarter Century!',
            30 => '🏆 Community Champion!',
            40 => '💎 Diamond Member!',
            50 => '🎖️ Half Century Hero!',
            75 => '🌈 Legendary Status!',
            100 => '👑🏆 CENTURION! 🏆👑',
        ];

        return $titles[$level] ?? "Level {$level} Milestone!";
    }

    /**
     * Get streak milestone message
     */
    private static function getStreakMessage(int $days): string
    {
        $messages = [
            7 => '🔥 One Week Warrior! Keep the momentum!',
            14 => '⚡ Two Week Champion! You\'re on fire!',
            30 => '🌟 Monthly Master! Incredible dedication!',
            60 => '💪 Two Month Titan! Unstoppable!',
            90 => '🏆 Quarter Year Legend! Amazing!',
            100 => '💯 Century Streak! You\'re a legend!',
            180 => '👑 Half Year Hero! Truly inspiring!',
            365 => '🎊 ONE YEAR STREAK! LEGENDARY! 🎊',
        ];

        return $messages[$days] ?? "🔥 {$days} Day Streak!";
    }

    /**
     * Batch broadcast to multiple users (e.g., group achievement)
     */
    public static function broadcastToUsers(array $userIds, string $event, array $data): int
    {
        $success = 0;
        foreach ($userIds as $userId) {
            if (PusherService::trigger(PusherService::getUserChannel($userId), $event, $data)) {
                $success++;
            }
        }
        return $success;
    }
}
