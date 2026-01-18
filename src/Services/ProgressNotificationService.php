<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class ProgressNotificationService
{
    /**
     * Progress thresholds for notifications (as percentages)
     */
    public const THRESHOLDS = [50, 75, 90];

    /**
     * Check badge progress and send notifications for near-completion
     */
    public static function checkProgressNotifications($userId)
    {
        $progress = GamificationService::getBadgeProgress($userId);
        $notifications = [];

        foreach ($progress as $badgeKey => $data) {
            if ($data['earned']) {
                continue; // Already earned
            }

            $percent = $data['progress_percent'] ?? 0;

            foreach (self::THRESHOLDS as $threshold) {
                if ($percent >= $threshold) {
                    $sent = self::hasNotificationBeenSent($userId, $badgeKey, $threshold);

                    if (!$sent) {
                        self::sendProgressNotification($userId, $badgeKey, $data, $threshold);
                        $notifications[] = [
                            'badge_key' => $badgeKey,
                            'threshold' => $threshold
                        ];
                    }
                }
            }
        }

        return $notifications;
    }

    /**
     * Check if a progress notification has already been sent
     */
    private static function hasNotificationBeenSent($userId, $badgeKey, $threshold)
    {
        $result = Database::query(
            "SELECT id FROM progress_notifications
             WHERE user_id = ? AND badge_key = ? AND threshold = ?",
            [$userId, $badgeKey, $threshold]
        )->fetch();

        return (bool)$result;
    }

    /**
     * Record that a notification was sent
     */
    private static function recordNotification($userId, $badgeKey, $threshold)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT IGNORE INTO progress_notifications (tenant_id, user_id, badge_key, threshold, sent_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$tenantId, $userId, $badgeKey, $threshold]
        );
    }

    /**
     * Send a progress notification to user
     */
    private static function sendProgressNotification($userId, $badgeKey, $data, $threshold)
    {
        $badge = GamificationService::getBadgeByKey($badgeKey);

        if (!$badge) {
            return;
        }

        $remaining = $data['threshold'] - $data['current'];
        $icon = $badge['icon'] ?? '🎯';
        $name = $badge['name'] ?? 'Badge';

        // Create appropriate message based on threshold
        if ($threshold >= 90) {
            $message = "{$icon} So close! You're {$threshold}% of the way to earning the '{$name}' badge. Just {$remaining} more to go!";
        } elseif ($threshold >= 75) {
            $message = "{$icon} Almost there! You're {$threshold}% of the way to the '{$name}' badge. Keep it up!";
        } else {
            $message = "{$icon} Great progress! You're halfway to earning the '{$name}' badge!";
        }

        // Create notification
        \Nexus\Models\Notification::create($userId, $message);

        // Record that we sent this notification
        self::recordNotification($userId, $badgeKey, $threshold);
    }

    /**
     * Get near-completion badges for a user (for display in UI)
     */
    public static function getNearCompletionBadges($userId, $minPercent = 50, $limit = 5)
    {
        $progress = GamificationService::getBadgeProgress($userId);
        $nearCompletion = [];

        foreach ($progress as $badgeKey => $data) {
            if ($data['earned']) {
                continue;
            }

            $percent = $data['progress_percent'] ?? 0;

            if ($percent >= $minPercent && $percent < 100) {
                $badge = GamificationService::getBadgeByKey($badgeKey);

                if ($badge) {
                    $nearCompletion[] = [
                        'key' => $badgeKey,
                        'name' => $badge['name'],
                        'icon' => $badge['icon'],
                        'current' => $data['current'],
                        'threshold' => $data['threshold'],
                        'remaining' => $data['threshold'] - $data['current'],
                        'percent' => $percent,
                        'xp' => $badge['xp'] ?? 0,
                    ];
                }
            }
        }

        // Sort by percentage (highest first)
        usort($nearCompletion, fn($a, $b) => $b['percent'] - $a['percent']);

        return array_slice($nearCompletion, 0, $limit);
    }

    /**
     * Get specific progress nudge for a single badge
     */
    public static function getProgressNudge($userId, $badgeKey)
    {
        $progress = GamificationService::getBadgeProgress($userId);

        if (!isset($progress[$badgeKey]) || $progress[$badgeKey]['earned']) {
            return null;
        }

        $data = $progress[$badgeKey];
        $badge = GamificationService::getBadgeByKey($badgeKey);

        if (!$badge) {
            return null;
        }

        $remaining = $data['threshold'] - $data['current'];
        $percent = $data['progress_percent'] ?? 0;

        // Generate an encouraging message
        $messages = [
            'almost' => [
                "Just {$remaining} more to unlock this badge!",
                "You're so close! Only {$remaining} left!",
                "Almost there! {$remaining} more to go!",
            ],
            'halfway' => [
                "Great progress! {$remaining} more needed.",
                "You're making great strides! {$remaining} to go.",
                "Keep it up! {$remaining} more to unlock.",
            ],
            'starting' => [
                "{$remaining} more until you earn this badge.",
                "You've started! {$remaining} more to complete.",
                "On your way! {$remaining} remaining.",
            ],
        ];

        if ($percent >= 75) {
            $category = 'almost';
        } elseif ($percent >= 40) {
            $category = 'halfway';
        } else {
            $category = 'starting';
        }

        $message = $messages[$category][array_rand($messages[$category])];

        return [
            'badge' => $badge,
            'current' => $data['current'],
            'threshold' => $data['threshold'],
            'remaining' => $remaining,
            'percent' => $percent,
            'message' => $message,
        ];
    }

    /**
     * Batch check progress for multiple users (for cron job)
     */
    public static function batchCheckProgress($limit = 100)
    {
        $tenantId = TenantContext::getId();

        // Get active users (logged in within last 7 days)
        $users = Database::query(
            "SELECT id FROM users
             WHERE tenant_id = ? AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY RAND()
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll();

        $totalNotifications = 0;

        foreach ($users as $user) {
            $sent = self::checkProgressNotifications($user['id']);
            $totalNotifications += count($sent);
        }

        return $totalNotifications;
    }

    /**
     * Clear old progress notification records (cleanup)
     */
    public static function cleanupOldRecords($days = 30)
    {
        Database::query(
            "DELETE FROM progress_notifications WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
}
