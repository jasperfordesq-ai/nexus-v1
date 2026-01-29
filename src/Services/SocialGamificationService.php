<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Social Gamification Service
 * Friend comparisons, challenges, and social competition features
 */
class SocialGamificationService
{
    /**
     * Get comparison data between current user and a friend
     */
    public static function getFriendComparison(int $userId, int $friendId): array
    {
        $user = self::getUserStats($userId);
        $friend = self::getUserStats($friendId);

        if (!$user || !$friend) {
            return [];
        }

        // Get shared badges
        $userBadges = array_column(
            Database::query("SELECT badge_key FROM user_badges WHERE user_id = ?", [$userId])->fetchAll(),
            'badge_key'
        );
        $friendBadges = array_column(
            Database::query("SELECT badge_key FROM user_badges WHERE user_id = ?", [$friendId])->fetchAll(),
            'badge_key'
        );

        $sharedBadges = array_intersect($userBadges, $friendBadges);
        $userUniqueBadges = array_diff($userBadges, $friendBadges);
        $friendUniqueBadges = array_diff($friendBadges, $userBadges);

        // Calculate who's ahead
        $comparison = [
            'user' => $user,
            'friend' => $friend,
            'xp_difference' => $user['xp'] - $friend['xp'],
            'level_difference' => $user['level'] - $friend['level'],
            'badge_count_difference' => count($userBadges) - count($friendBadges),
            'shared_badges' => $sharedBadges,
            'user_unique_badges' => $userUniqueBadges,
            'friend_unique_badges' => $friendUniqueBadges,
            'user_winning' => [
                'xp' => $user['xp'] > $friend['xp'],
                'level' => $user['level'] > $friend['level'],
                'badges' => count($userBadges) > count($friendBadges),
            ],
        ];

        return $comparison;
    }

    /**
     * Get user stats for comparison
     */
    private static function getUserStats(int $userId): ?array
    {
        return Database::query(
            "SELECT id, first_name, last_name, avatar_url, xp, level,
                    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
             FROM users u WHERE id = ?",
            [$userId]
        )->fetch() ?: null;
    }

    /**
     * Get friends leaderboard (users you follow/connected with)
     */
    public static function getFriendsLeaderboard(int $userId, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();

        // Get connected users (assuming a connections table exists)
        $friends = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.xp, u.level,
                    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
             FROM users u
             JOIN connections uc ON (uc.requester_id = ? AND uc.receiver_id = u.id)
                                      OR (uc.receiver_id = ? AND uc.requester_id = u.id)
             WHERE u.tenant_id = ? AND u.is_approved = 1 AND uc.status = 'accepted'
             ORDER BY u.xp DESC
             LIMIT ?",
            [$userId, $userId, $tenantId, $limit]
        )->fetchAll();

        // Add current user
        $currentUser = self::getUserStats($userId);
        if ($currentUser) {
            $friends[] = $currentUser;
        }

        // Sort by XP
        usort($friends, fn($a, $b) => $b['xp'] - $a['xp']);

        // Add rank
        foreach ($friends as $i => &$friend) {
            $friend['rank'] = $i + 1;
            $friend['is_current_user'] = $friend['id'] == $userId;
        }

        return array_slice($friends, 0, $limit);
    }

    /**
     * Create a head-to-head challenge with a friend
     */
    public static function createFriendChallenge(int $challengerId, int $challengedId, array $challengeData): ?int
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO friend_challenges
             (tenant_id, challenger_id, challenged_id, challenge_type, target_value,
              xp_stake, start_date, end_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [
                $tenantId,
                $challengerId,
                $challengedId,
                $challengeData['type'],
                $challengeData['target_value'],
                $challengeData['xp_stake'] ?? 50,
                $challengeData['start_date'] ?? date('Y-m-d'),
                $challengeData['end_date'] ?? date('Y-m-d', strtotime('+7 days')),
            ]
        );

        $challengeId = Database::getInstance()->lastInsertId();

        // Notify the challenged user
        \Nexus\Models\Notification::create(
            $challengedId,
            "You've been challenged! Check your gamification challenges.",
            TenantContext::getBasePath() . '/achievements/challenges',
            'challenge'
        );

        return $challengeId;
    }

    /**
     * Accept a friend challenge
     */
    public static function acceptChallenge(int $challengeId, int $userId): bool
    {
        $challenge = Database::query(
            "SELECT * FROM friend_challenges WHERE id = ? AND challenged_id = ? AND status = 'pending'",
            [$challengeId, $userId]
        )->fetch();

        if (!$challenge) {
            return false;
        }

        Database::query(
            "UPDATE friend_challenges SET status = 'active', accepted_at = NOW() WHERE id = ?",
            [$challengeId]
        );

        // Notify challenger
        \Nexus\Models\Notification::create(
            $challenge['challenger_id'],
            "Your challenge was accepted! Let the competition begin!",
            TenantContext::getBasePath() . '/achievements/challenges',
            'challenge'
        );

        return true;
    }

    /**
     * Decline a friend challenge
     */
    public static function declineChallenge(int $challengeId, int $userId): bool
    {
        Database::query(
            "UPDATE friend_challenges SET status = 'declined', declined_at = NOW()
             WHERE id = ? AND challenged_id = ? AND status = 'pending'",
            [$challengeId, $userId]
        );

        return true;
    }

    /**
     * Get active friend challenges for a user
     */
    public static function getUserFriendChallenges(int $userId): array
    {
        return Database::query(
            "SELECT fc.*,
                    challenger.first_name as challenger_first, challenger.last_name as challenger_last,
                    challenger.avatar_url as challenger_avatar,
                    challenged.first_name as challenged_first, challenged.last_name as challenged_last,
                    challenged.avatar_url as challenged_avatar
             FROM friend_challenges fc
             JOIN users challenger ON fc.challenger_id = challenger.id
             JOIN users challenged ON fc.challenged_id = challenged.id
             WHERE (fc.challenger_id = ? OR fc.challenged_id = ?)
             AND fc.status IN ('pending', 'active')
             ORDER BY fc.created_at DESC",
            [$userId, $userId]
        )->fetchAll();
    }

    /**
     * Update challenge progress and check for completion
     */
    public static function updateChallengeProgress(int $userId, string $actionType, int $amount = 1): void
    {
        // Get active challenges involving this user
        $challenges = Database::query(
            "SELECT * FROM friend_challenges
             WHERE (challenger_id = ? OR challenged_id = ?)
             AND status = 'active'
             AND challenge_type = ?
             AND end_date >= CURDATE()",
            [$userId, $userId, $actionType]
        )->fetchAll();

        foreach ($challenges as $challenge) {
            if ($challenge['challenger_id'] == $userId) {
                Database::query(
                    "UPDATE friend_challenges SET challenger_progress = challenger_progress + ? WHERE id = ?",
                    [$amount, $challenge['id']]
                );
            } else {
                Database::query(
                    "UPDATE friend_challenges SET challenged_progress = challenged_progress + ? WHERE id = ?",
                    [$amount, $challenge['id']]
                );
            }

            // Check if challenge is complete
            self::checkChallengeCompletion($challenge['id']);
        }
    }

    /**
     * Check if a challenge has been completed
     */
    private static function checkChallengeCompletion(int $challengeId): void
    {
        $challenge = Database::query(
            "SELECT * FROM friend_challenges WHERE id = ?",
            [$challengeId]
        )->fetch();

        if (!$challenge || $challenge['status'] !== 'active') {
            return;
        }

        $challengerWon = $challenge['challenger_progress'] >= $challenge['target_value'];
        $challengedWon = $challenge['challenged_progress'] >= $challenge['target_value'];

        // Both reached target - first one wins
        if ($challengerWon && $challengedWon) {
            // Check timestamps or progress amount
            $winnerId = $challenge['challenger_progress'] >= $challenge['challenged_progress']
                ? $challenge['challenger_id']
                : $challenge['challenged_id'];
        } elseif ($challengerWon) {
            $winnerId = $challenge['challenger_id'];
        } elseif ($challengedWon) {
            $winnerId = $challenge['challenged_id'];
        } else {
            return; // Not complete yet
        }

        $loserId = $winnerId == $challenge['challenger_id']
            ? $challenge['challenged_id']
            : $challenge['challenger_id'];

        // Update challenge status
        Database::query(
            "UPDATE friend_challenges SET status = 'completed', winner_id = ?, completed_at = NOW() WHERE id = ?",
            [$winnerId, $challengeId]
        );

        // Award XP to winner
        $xpReward = ($challenge['xp_stake'] ?? 50) * 2; // Double stake
        GamificationService::awardXP($winnerId, $xpReward, 'friend_challenge', 'Won friend challenge');

        // Notify both users
        $basePath = TenantContext::getBasePath();
        \Nexus\Models\Notification::create(
            $winnerId,
            "🎉 You won the challenge! +{$xpReward} XP",
            "{$basePath}/achievements/challenges",
            'challenge'
        );
        \Nexus\Models\Notification::create(
            $loserId,
            "Challenge completed! Better luck next time.",
            "{$basePath}/achievements/challenges",
            'challenge'
        );
    }

    /**
     * Get challenge history for a user
     */
    public static function getChallengeHistory(int $userId, int $limit = 10): array
    {
        return Database::query(
            "SELECT fc.*,
                    challenger.first_name as challenger_first, challenger.avatar_url as challenger_avatar,
                    challenged.first_name as challenged_first, challenged.avatar_url as challenged_avatar,
                    CASE WHEN fc.winner_id = ? THEN 'won'
                         WHEN fc.winner_id IS NOT NULL THEN 'lost'
                         ELSE 'draw' END as result
             FROM friend_challenges fc
             JOIN users challenger ON fc.challenger_id = challenger.id
             JOIN users challenged ON fc.challenged_id = challenged.id
             WHERE (fc.challenger_id = ? OR fc.challenged_id = ?)
             AND fc.status = 'completed'
             ORDER BY fc.completed_at DESC
             LIMIT ?",
            [$userId, $userId, $userId, $limit]
        )->fetchAll();
    }

    /**
     * Get user's challenge stats
     */
    public static function getUserChallengeStats(int $userId): array
    {
        $stats = Database::query(
            "SELECT
                COUNT(*) as total_challenges,
                SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN winner_id IS NULL AND status = 'completed' THEN 1 ELSE 0 END) as draws
             FROM friend_challenges
             WHERE (challenger_id = ? OR challenged_id = ?) AND status = 'completed'",
            [$userId, $userId, $userId, $userId]
        )->fetch();

        $stats['win_rate'] = $stats['total_challenges'] > 0
            ? round(($stats['wins'] / $stats['total_challenges']) * 100)
            : 0;

        return $stats;
    }

    /**
     * Get activity feed of friends' achievements
     */
    public static function getFriendsActivityFeed(int $userId, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT
                'badge' as activity_type,
                ub.user_id,
                u.first_name, u.last_name, u.avatar_url,
                ub.badge_key, ub.name as badge_name, ub.icon as badge_icon,
                ub.awarded_at as activity_date,
                NULL as xp_amount
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             JOIN connections uc ON (uc.requester_id = ? AND uc.receiver_id = u.id)
                                      OR (uc.receiver_id = ? AND uc.requester_id = u.id)
             WHERE u.tenant_id = ? AND uc.status = 'accepted'
             AND ub.awarded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)

             UNION ALL

             SELECT
                'level_up' as activity_type,
                xh.user_id,
                u.first_name, u.last_name, u.avatar_url,
                NULL, NULL, NULL,
                xh.created_at as activity_date,
                xh.xp_amount
             FROM xp_history xh
             JOIN users u ON xh.user_id = u.id
             JOIN connections uc ON (uc.requester_id = ? AND uc.receiver_id = u.id)
                                      OR (uc.receiver_id = ? AND uc.requester_id = u.id)
             WHERE u.tenant_id = ? AND uc.status = 'accepted'
             AND xh.action = 'level_up'
             AND xh.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)

             ORDER BY activity_date DESC
             LIMIT ?",
            [$userId, $userId, $tenantId, $userId, $userId, $tenantId, $limit]
        )->fetchAll();
    }

    /**
     * Celebrate a friend's achievement (like/react)
     */
    public static function celebrateAchievement(int $userId, int $achievementUserId, string $achievementType, int $achievementId): bool
    {
        Database::query(
            "INSERT IGNORE INTO achievement_celebrations
             (user_id, achievement_user_id, achievement_type, achievement_id, celebrated_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $achievementUserId, $achievementType, $achievementId]
        );

        // Notify the achievement owner
        $celebrator = Database::query(
            "SELECT first_name FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        \Nexus\Models\Notification::create(
            $achievementUserId,
            "{$celebrator['first_name']} celebrated your achievement! 🎉",
            TenantContext::getBasePath() . '/achievements',
            'social'
        );

        return true;
    }
}
