<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Services\WebPushService;
use Nexus\Services\FCMPushService;
use Nexus\Services\RealtimeService;

class Notification
{
    /**
     * Create a notification and automatically send Web Push to user's devices
     *
     * @param int $userId Target user ID
     * @param string $message Notification message
     * @param string|null $link URL to open when clicked
     * @param string $type Notification type (system, message, transaction, event, reminder, etc.)
     * @param bool $sendPush Whether to send Web Push notification (default: true)
     * @return void
     */
    public static function create($userId, $message, $link = null, $type = 'system', $sendPush = true)
    {
        $sql = "INSERT INTO notifications (user_id, message, link, type) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$userId, $message, $link, $type]);

        // Get the inserted notification ID
        $notificationId = Database::getConnection()->lastInsertId();

        // Broadcast via Pusher for instant real-time updates
        RealtimeService::broadcastNotification($userId, [
            'id' => $notificationId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Automatically send push notifications to user's devices
        if ($sendPush) {
            // Send Web Push (PWA)
            self::sendWebPush($userId, $message, $link, $type);

            // Send FCM Push (Native Android app)
            self::sendFCMPush($userId, $message, $link, $type);
        }
    }

    /**
     * Send Web Push notification to user's devices
     *
     * @param int $userId Target user ID
     * @param string $message Notification message
     * @param string|null $link URL to open when clicked
     * @param string $type Notification type
     */
    private static function sendWebPush($userId, $message, $link, $type)
    {
        try {
            // Map notification types to push notification types
            $pushType = self::mapNotificationType($type);

            // Generate a title based on type
            $title = self::generateTitle($type);

            // Truncate message for push notification body (max 200 chars)
            $body = mb_strlen($message) > 200 ? mb_substr($message, 0, 197) . '...' : $message;

            // Send the push notification
            WebPushService::sendToUser($userId, $title, $body, $link, $pushType);

        } catch (\Exception $e) {
            // Log error but don't fail the notification creation
            error_log('[Notification] Web Push failed: ' . $e->getMessage());
        }
    }

    /**
     * Send FCM Push notification to user's native Android devices
     *
     * @param int $userId Target user ID
     * @param string $message Notification message
     * @param string|null $link URL to open when clicked
     * @param string $type Notification type
     */
    private static function sendFCMPush($userId, $message, $link, $type)
    {
        try {
            // Map notification types to push notification types
            $pushType = self::mapNotificationType($type);

            // Generate a title based on type
            $title = self::generateTitle($type);

            // Truncate message for push notification body (max 200 chars)
            $body = mb_strlen($message) > 200 ? mb_substr($message, 0, 197) . '...' : $message;

            // Build data payload for navigation
            $data = [
                'type' => $pushType,
                'url' => $link,
            ];

            // Send the FCM notification
            FCMPushService::sendToUser($userId, $title, $body, $data);

        } catch (\Exception $e) {
            // Log error but don't fail the notification creation
            error_log('[Notification] FCM Push failed: ' . $e->getMessage());
        }
    }

    /**
     * Map internal notification type to push notification type
     */
    private static function mapNotificationType($type)
    {
        $typeMap = [
            'system' => 'general',
            'message' => 'message',
            'new_message' => 'message',
            'reply' => 'message',
            'mention' => 'message',
            'transaction' => 'transaction',
            'payment' => 'transaction',
            'wallet' => 'transaction',
            'event' => 'event',
            'new_event' => 'event',
            'event_reminder' => 'reminder',
            'reminder' => 'reminder',
            'new_topic' => 'general',
            'new_reply' => 'message',
            'like' => 'general',
            'comment' => 'message',
            'share' => 'general',
            'follow' => 'general',
            'group' => 'general',
        ];

        return $typeMap[$type] ?? 'general';
    }

    /**
     * Generate a title based on notification type
     */
    private static function generateTitle($type)
    {
        $titles = [
            'system' => 'Notification',
            'message' => 'New Message',
            'new_message' => 'New Message',
            'reply' => 'New Reply',
            'mention' => 'You were mentioned',
            'transaction' => 'Transaction Update',
            'payment' => 'Payment Received',
            'wallet' => 'Wallet Update',
            'event' => 'Event Update',
            'new_event' => 'New Event',
            'event_reminder' => 'Event Reminder',
            'reminder' => 'Reminder',
            'new_topic' => 'New Discussion',
            'new_reply' => 'New Reply',
            'like' => 'Someone liked your post',
            'comment' => 'New Comment',
            'share' => 'Your content was shared',
            'follow' => 'New Follower',
            'group' => 'Group Update',
        ];

        return $titles[$type] ?? 'Notification';
    }

    public static function getUnread($userId)
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL ORDER BY created_at DESC";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function markRead($id, $userId)
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        Database::query($sql, [$id, $userId]);
    }

    public static function markAllRead($userId)
    {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
        Database::query($sql, [$userId]);
    }
    public static function countUnread($userId)
    {
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL";
        return Database::query($sql, [$userId])->fetch()['count'];
    }

    public static function getLatest($userId, $limit = 10)
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT " . (int)$limit;
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getAll($userId, $limit = 50)
    {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT " . (int)$limit;
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getSettings($userId)
    {
        $sql = "SELECT * FROM notification_settings WHERE user_id = ?";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function delete($id, $userId)
    {
        $sql = "UPDATE notifications SET deleted_at = NOW() WHERE id = ? AND user_id = ?";
        Database::query($sql, [$id, $userId]);
    }

    public static function deleteAll($userId)
    {
        $sql = "UPDATE notifications SET deleted_at = NOW() WHERE user_id = ?";
        Database::query($sql, [$userId]);
    }
}
