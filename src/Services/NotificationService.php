<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * NotificationService - Business logic for notification operations
 *
 * This service extracts business logic from the Notification model
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Get notifications (cursor paginated)
 * - Get unread counts (by type)
 * - Mark as read (single or all)
 * - Delete notification
 */
class NotificationService
{
    /**
     * Validation error messages
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Notification type categories for grouping
     */
    private const TYPE_CATEGORIES = [
        'messages' => ['message', 'new_message', 'message_received'],
        'connections' => ['connection_request', 'connection_accepted', 'friend_request', 'friend_accepted'],
        'reviews' => ['review', 'new_review', 'review_received'],
        'transactions' => ['transaction', 'payment', 'payment_received', 'credits_received'],
        'social' => ['like', 'comment', 'mention', 'post_like', 'post_comment'],
        'events' => ['event', 'event_reminder', 'event_rsvp', 'event_update'],
        'groups' => ['group_invite', 'group_join', 'group_post'],
        'listings' => ['listing', 'listing_interest', 'listing_match'],
        'system' => ['system', 'announcement', 'welcome', 'badge', 'achievement', 'level_up'],
    ];

    /**
     * Get notifications with cursor-based pagination
     *
     * @param int $userId
     * @param array $filters [
     *   'type' => string (filter by type category),
     *   'unread_only' => bool,
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 100)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getNotifications(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 100);
        $unreadOnly = $filters['unread_only'] ?? false;
        $typeCategory = $filters['type'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build query
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND deleted_at IS NULL";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        // Filter by type category
        if ($typeCategory && isset(self::TYPE_CATEGORIES[$typeCategory])) {
            $types = self::TYPE_CATEGORIES[$typeCategory];
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $sql .= " AND type IN ($placeholders)";
            $params = array_merge($params, $types);
        }

        if ($cursorId) {
            $sql .= " AND id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY created_at DESC, id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($notifications) > $limit;
        if ($hasMore) {
            array_pop($notifications);
        }

        $items = [];
        $lastId = null;

        foreach ($notifications as $n) {
            $lastId = $n['id'];
            $items[] = self::formatNotification($n);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single notification by ID
     *
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public static function getNotification(int $id, int $userId): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            "SELECT * FROM notifications WHERE id = ? AND user_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $userId]);
        $notification = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$notification) {
            return null;
        }

        return self::formatNotification($notification);
    }

    /**
     * Format a notification for API response with actor/target data
     *
     * @param array $notification Raw database row
     * @return array Formatted notification
     */
    private static function formatNotification(array $notification): array
    {
        $data = json_decode($notification['data'] ?? '{}', true) ?: [];

        // Extract actor (who triggered the notification)
        $actor = null;
        if (!empty($data['actor_id'])) {
            $actor = [
                'id' => (int)$data['actor_id'],
                'name' => $data['actor_name'] ?? null,
                'avatar_url' => $data['actor_avatar'] ?? null,
            ];
        } elseif (!empty($data['from_user_id'])) {
            $actor = [
                'id' => (int)$data['from_user_id'],
                'name' => $data['from_user_name'] ?? null,
                'avatar_url' => $data['from_user_avatar'] ?? null,
            ];
        } elseif (!empty($data['sender_id'])) {
            $actor = [
                'id' => (int)$data['sender_id'],
                'name' => $data['sender_name'] ?? null,
                'avatar_url' => $data['sender_avatar'] ?? null,
            ];
        }

        // Extract target (what the notification is about)
        $target = self::extractTarget($notification['type'], $data, $notification['link'] ?? null);

        // Determine category
        $category = 'other';
        foreach (self::TYPE_CATEGORIES as $cat => $types) {
            if (in_array($notification['type'], $types)) {
                $category = $cat;
                break;
            }
        }

        return [
            'id' => (int)$notification['id'],
            'type' => $notification['type'],
            'category' => $category,
            'title' => $notification['title'] ?? null,
            'message' => $notification['message'],
            'is_read' => (bool)$notification['is_read'],
            'actor' => $actor,
            'target' => $target,
            'link' => $notification['link'] ?? null,
            'created_at' => $notification['created_at'],
        ];
    }

    /**
     * Extract target information from notification data
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @param string|null $link Notification link
     * @return array|null Target info for deep-linking
     */
    private static function extractTarget(string $type, array $data, ?string $link): ?array
    {
        // Message target
        if (in_array($type, ['message', 'new_message', 'message_received'])) {
            if (!empty($data['conversation_id'])) {
                return [
                    'type' => 'conversation',
                    'id' => (int)$data['conversation_id'],
                ];
            }
            if (!empty($data['thread_id'])) {
                return [
                    'type' => 'conversation',
                    'id' => (int)$data['thread_id'],
                ];
            }
        }

        // Connection target
        if (in_array($type, ['connection_request', 'connection_accepted', 'friend_request', 'friend_accepted'])) {
            if (!empty($data['connection_id'])) {
                return [
                    'type' => 'connection',
                    'id' => (int)$data['connection_id'],
                ];
            }
            if (!empty($data['from_user_id'])) {
                return [
                    'type' => 'user',
                    'id' => (int)$data['from_user_id'],
                ];
            }
        }

        // Review target
        if (in_array($type, ['review', 'new_review', 'review_received'])) {
            if (!empty($data['review_id'])) {
                return [
                    'type' => 'review',
                    'id' => (int)$data['review_id'],
                ];
            }
        }

        // Transaction target
        if (in_array($type, ['transaction', 'payment', 'payment_received', 'credits_received'])) {
            if (!empty($data['transaction_id'])) {
                return [
                    'type' => 'transaction',
                    'id' => (int)$data['transaction_id'],
                ];
            }
        }

        // Post/social target
        if (in_array($type, ['like', 'comment', 'mention', 'post_like', 'post_comment'])) {
            if (!empty($data['post_id'])) {
                return [
                    'type' => 'post',
                    'id' => (int)$data['post_id'],
                ];
            }
        }

        // Event target
        if (in_array($type, ['event', 'event_reminder', 'event_rsvp', 'event_update'])) {
            if (!empty($data['event_id'])) {
                return [
                    'type' => 'event',
                    'id' => (int)$data['event_id'],
                ];
            }
        }

        // Group target
        if (in_array($type, ['group_invite', 'group_join', 'group_post'])) {
            if (!empty($data['group_id'])) {
                return [
                    'type' => 'group',
                    'id' => (int)$data['group_id'],
                ];
            }
        }

        // Listing target
        if (in_array($type, ['listing', 'listing_interest', 'listing_match'])) {
            if (!empty($data['listing_id'])) {
                return [
                    'type' => 'listing',
                    'id' => (int)$data['listing_id'],
                ];
            }
        }

        // Try to extract from link as fallback
        if ($link) {
            return self::parseTargetFromLink($link);
        }

        return null;
    }

    /**
     * Parse target from notification link URL
     *
     * @param string $link
     * @return array|null
     */
    private static function parseTargetFromLink(string $link): ?array
    {
        // Common patterns: /messages/123, /listings/456, /events/789
        $patterns = [
            '/\/messages\/(\d+)/' => 'conversation',
            '/\/conversations\/(\d+)/' => 'conversation',
            '/\/listings\/(\d+)/' => 'listing',
            '/\/events\/(\d+)/' => 'event',
            '/\/groups\/(\d+)/' => 'group',
            '/\/members\/(\d+)/' => 'user',
            '/\/users\/(\d+)/' => 'user',
            '/\/posts\/(\d+)/' => 'post',
            '/\/feed\/(\d+)/' => 'post',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $link, $matches)) {
                return [
                    'type' => $type,
                    'id' => (int)$matches[1],
                ];
            }
        }

        return null;
    }

    /**
     * Get unread notification counts by category
     *
     * @param int $userId
     * @return array Counts by category
     */
    public static function getUnreadCounts(int $userId): array
    {
        $db = Database::getConnection();

        // Get all unread notifications with their types
        $stmt = $db->prepare(
            "SELECT type, COUNT(*) as count
             FROM notifications
             WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL
             GROUP BY type"
        );
        $stmt->execute([$userId]);
        $typeCounts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Aggregate into categories
        $counts = [
            'total' => 0,
            'messages' => 0,
            'connections' => 0,
            'reviews' => 0,
            'transactions' => 0,
            'social' => 0,
            'events' => 0,
            'groups' => 0,
            'listings' => 0,
            'system' => 0,
            'other' => 0,
        ];

        foreach ($typeCounts as $type => $count) {
            $counts['total'] += $count;
            $categorized = false;

            foreach (self::TYPE_CATEGORIES as $category => $types) {
                if (in_array($type, $types)) {
                    $counts[$category] += $count;
                    $categorized = true;
                    break;
                }
            }

            if (!$categorized) {
                $counts['other'] += $count;
            }
        }

        return $counts;
    }

    /**
     * Mark a notification as read
     *
     * @param int $id Notification ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success
     */
    public static function markRead(int $id, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Notification not found'];
            return false;
        }

        try {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            return true;
        } catch (\Exception $e) {
            error_log("NotificationService::markRead error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to mark notification as read'];
            return false;
        }
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param int $userId
     * @param string|null $category Optional category to mark read
     * @return int Number of notifications marked read
     */
    public static function markAllRead(int $userId, ?string $category = null): int
    {
        $db = Database::getConnection();

        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $params = [$userId];

        if ($category && isset(self::TYPE_CATEGORIES[$category])) {
            $types = self::TYPE_CATEGORIES[$category];
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $sql .= " AND type IN ($placeholders)";
            $params = array_merge($params, $types);
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("NotificationService::markAllRead error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete (soft delete) a notification
     *
     * @param int $id Notification ID
     * @param int $userId User ID (for ownership verification)
     * @return bool Success
     */
    public static function delete(int $id, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Notification not found'];
            return false;
        }

        try {
            $stmt = $db->prepare("UPDATE notifications SET deleted_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            return true;
        } catch (\Exception $e) {
            error_log("NotificationService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete notification'];
            return false;
        }
    }

    /**
     * Delete all notifications for a user
     *
     * @param int $userId
     * @param string|null $category Optional category to delete
     * @return int Number of notifications deleted
     */
    public static function deleteAll(int $userId, ?string $category = null): int
    {
        $db = Database::getConnection();

        $sql = "UPDATE notifications SET deleted_at = NOW() WHERE user_id = ? AND deleted_at IS NULL";
        $params = [$userId];

        if ($category && isset(self::TYPE_CATEGORIES[$category])) {
            $types = self::TYPE_CATEGORIES[$category];
            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $sql .= " AND type IN ($placeholders)";
            $params = array_merge($params, $types);
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("NotificationService::deleteAll error: " . $e->getMessage());
            return 0;
        }
    }
}
