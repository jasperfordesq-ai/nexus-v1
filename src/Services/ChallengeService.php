<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

class ChallengeService
{
    /**
     * Get all active challenges for current tenant
     */
    public static function getActiveChallenges()
    {
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');

        return Database::query(
            "SELECT * FROM challenges
             WHERE tenant_id = ? AND is_active = 1
             AND start_date <= ? AND end_date >= ?
             ORDER BY end_date ASC",
            [$tenantId, $today, $today]
        )->fetchAll();
    }

    /**
     * Get challenges with user progress
     */
    public static function getChallengesWithProgress($userId)
    {
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');

        $challenges = Database::query(
            "SELECT c.*,
                    COALESCE(ucp.current_count, 0) as user_progress,
                    ucp.completed_at,
                    ucp.reward_claimed
             FROM challenges c
             LEFT JOIN user_challenge_progress ucp ON c.id = ucp.challenge_id AND ucp.user_id = ?
             WHERE c.tenant_id = ? AND c.is_active = 1
             AND c.start_date <= ? AND c.end_date >= ?
             ORDER BY c.end_date ASC",
            [$userId, $tenantId, $today, $today]
        )->fetchAll();

        // Calculate progress percentage and time remaining
        foreach ($challenges as &$challenge) {
            $challenge['progress_percent'] = min(100, round(($challenge['user_progress'] / $challenge['target_count']) * 100));
            $challenge['is_completed'] = $challenge['user_progress'] >= $challenge['target_count'];
            $challenge['days_remaining'] = max(0, (strtotime($challenge['end_date']) - time()) / 86400);
            $challenge['hours_remaining'] = max(0, (strtotime($challenge['end_date']) - time()) / 3600);
        }

        return $challenges;
    }

    /**
     * Update progress for a challenge action
     */
    public static function updateProgress($userId, $actionType, $increment = 1)
    {
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');

        // Find matching active challenges
        $challenges = Database::query(
            "SELECT * FROM challenges
             WHERE tenant_id = ? AND is_active = 1
             AND action_type = ?
             AND start_date <= ? AND end_date >= ?",
            [$tenantId, $actionType, $today, $today]
        )->fetchAll();

        $completed = [];

        foreach ($challenges as $challenge) {
            // Use transaction for each challenge progress update
            Database::beginTransaction();

            try {
                // Get or create progress record
                $progress = Database::query(
                    "SELECT * FROM user_challenge_progress WHERE user_id = ? AND challenge_id = ?",
                    [$userId, $challenge['id']]
                )->fetch();

                if (!$progress) {
                    Database::query(
                        "INSERT INTO user_challenge_progress (tenant_id, user_id, challenge_id, current_count)
                         VALUES (?, ?, ?, ?)",
                        [$tenantId, $userId, $challenge['id'], $increment]
                    );
                    $newCount = $increment;
                } else {
                    if ($progress['completed_at']) {
                        Database::commit();
                        continue; // Already completed
                    }
                    $newCount = $progress['current_count'] + $increment;
                    Database::query(
                        "UPDATE user_challenge_progress SET current_count = ? WHERE id = ?",
                        [$newCount, $progress['id']]
                    );
                }

                // Check if just completed
                if ($newCount >= $challenge['target_count']) {
                    Database::query(
                        "UPDATE user_challenge_progress SET completed_at = NOW() WHERE user_id = ? AND challenge_id = ?",
                        [$userId, $challenge['id']]
                    );

                    Database::commit();

                    // Award rewards outside transaction (non-critical)
                    self::awardChallengeReward($userId, $challenge);
                    $completed[] = $challenge;
                } else {
                    Database::commit();
                }
            } catch (\Throwable $e) {
                Database::rollback();
                error_log("ChallengeService::updateProgress error: " . $e->getMessage());
                // Continue to next challenge
            }
        }

        return $completed;
    }

    /**
     * Award challenge completion rewards
     */
    private static function awardChallengeReward($userId, $challenge)
    {
        // Award XP
        if ($challenge['xp_reward'] > 0) {
            GamificationService::awardXP(
                $userId,
                $challenge['xp_reward'],
                'challenge_complete',
                "Challenge: {$challenge['title']}"
            );
        }

        // Award badge if specified
        if (!empty($challenge['badge_reward'])) {
            GamificationService::awardBadgeByKey($userId, $challenge['badge_reward']);
        }

        // Send notification
        $basePath = TenantContext::getBasePath();
        Notification::create(
            $userId,
            "Challenge Complete! You finished '{$challenge['title']}' and earned {$challenge['xp_reward']} XP!",
            "{$basePath}/achievements",
            'achievement'
        );

        // Mark as claimed
        Database::query(
            "UPDATE user_challenge_progress SET reward_claimed = 1 WHERE user_id = ? AND challenge_id = ?",
            [$userId, $challenge['id']]
        );
    }

    /**
     * Create a new challenge (admin)
     */
    public static function create($data)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO challenges (tenant_id, title, description, challenge_type, action_type, target_count, xp_reward, badge_reward, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $data['title'],
                $data['description'] ?? '',
                $data['challenge_type'] ?? 'weekly',
                $data['action_type'],
                $data['target_count'] ?? 1,
                $data['xp_reward'] ?? 50,
                $data['badge_reward'] ?? null,
                $data['start_date'],
                $data['end_date']
            ]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Get challenge by ID (with tenant scoping for security)
     */
    public static function getById($id)
    {
        $tenantId = TenantContext::getId();
        return Database::query(
            "SELECT * FROM challenges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
    }

    /**
     * Update challenge
     */
    public static function update($id, $data)
    {
        Database::query(
            "UPDATE challenges SET
                title = ?, description = ?, challenge_type = ?, action_type = ?,
                target_count = ?, xp_reward = ?, badge_reward = ?,
                start_date = ?, end_date = ?, is_active = ?
             WHERE id = ?",
            [
                $data['title'],
                $data['description'] ?? '',
                $data['challenge_type'] ?? 'weekly',
                $data['action_type'],
                $data['target_count'] ?? 1,
                $data['xp_reward'] ?? 50,
                $data['badge_reward'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['is_active'] ?? 1,
                $id
            ]
        );
    }

    /**
     * Delete challenge
     */
    public static function delete($id)
    {
        Database::query("DELETE FROM challenges WHERE id = ?", [$id]);
    }

    /**
     * Get challenge statistics
     */
    public static function getStats($challengeId)
    {
        $stats = Database::query(
            "SELECT
                COUNT(*) as total_participants,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completions,
                AVG(current_count) as avg_progress
             FROM user_challenge_progress
             WHERE challenge_id = ?",
            [$challengeId]
        )->fetch();

        return $stats;
    }

    /**
     * Get available action types for challenges
     */
    public static function getActionTypes()
    {
        return [
            'transaction' => 'Complete Transactions',
            'credits_sent' => 'Send Time Credits',
            'credits_received' => 'Receive Time Credits',
            'listing_created' => 'Create Listings',
            'volunteer_hours' => 'Log Volunteer Hours',
            'connection' => 'Make Connections',
            'message' => 'Send Messages',
            'review' => 'Leave Reviews',
            'event_attend' => 'Attend Events',
            'event_create' => 'Host Events',
            'group_join' => 'Join Groups',
            'post' => 'Create Posts',
            'login' => 'Daily Logins',
        ];
    }
}
