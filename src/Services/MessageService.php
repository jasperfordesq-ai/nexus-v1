<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Message;
use Nexus\Models\User;
use Nexus\Models\Notification;

/**
 * MessageService - Business logic for messages and conversations
 *
 * This service extracts business logic from the Message model and MessageController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Conversation listing with cursor pagination
 * - Message retrieval within conversations
 * - Sending messages (text and voice)
 * - Read receipts and unread counts
 * - Conversation archiving
 */
class MessageService
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
     * Get user's conversations (inbox) with cursor-based pagination
     *
     * @param int $userId
     * @param array $filters ['cursor' => string, 'limit' => int]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getConversations(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor (it's the last conversation's latest message timestamp)
        $cursorTime = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded) {
                $cursorTime = $decoded;
            }
        }

        // Check if last_active_at column exists for online status
        $hasLastActive = self::hasColumn('users', 'last_active_at');

        $lastActiveSelect = $hasLastActive
            ? "CASE WHEN m.sender_id = :uid_la THEN receiver.last_active_at ELSE sender.last_active_at END as other_user_last_active"
            : "NULL as other_user_last_active";

        // Build the query - get latest message per conversation partner
        $sql = "
            SELECT
                m.id as last_message_id,
                m.body as last_message_body,
                m.audio_url as last_message_audio_url,
                m.created_at as last_message_at,
                m.sender_id as last_message_sender_id,
                CASE WHEN m.sender_id = :uid1 THEN m.receiver_id ELSE m.sender_id END as other_user_id,
                CASE WHEN m.sender_id = :uid2 THEN receiver.name ELSE sender.name END as other_user_name,
                CASE WHEN m.sender_id = :uid3 THEN receiver.first_name ELSE sender.first_name END as other_user_first_name,
                CASE WHEN m.sender_id = :uid4 THEN receiver.last_name ELSE sender.last_name END as other_user_last_name,
                CASE WHEN m.sender_id = :uid5 THEN receiver.avatar_url ELSE sender.avatar_url END as other_user_avatar,
                {$lastActiveSelect},
                (
                    SELECT COUNT(*) FROM messages unread
                    WHERE unread.sender_id = CASE WHEN m.sender_id = :uid6 THEN m.receiver_id ELSE m.sender_id END
                    AND unread.receiver_id = :uid7
                    AND unread.is_read = 0
                    AND unread.tenant_id = :tenant1
                ) as unread_count
            FROM messages m
            JOIN users sender ON m.sender_id = sender.id
            JOIN users receiver ON m.receiver_id = receiver.id
            WHERE m.tenant_id = :tenant2
            AND m.id IN (
                SELECT MAX(id)
                FROM messages
                WHERE tenant_id = :tenant3
                AND (sender_id = :uid8 OR receiver_id = :uid9)
                AND sender_id != receiver_id
                GROUP BY CASE WHEN sender_id = :uid10 THEN receiver_id ELSE sender_id END
            )
            AND m.sender_id != m.receiver_id
        ";

        $params = [
            'uid1' => $userId,
            'uid2' => $userId,
            'uid3' => $userId,
            'uid4' => $userId,
            'uid5' => $userId,
            'uid6' => $userId,
            'uid7' => $userId,
            'uid8' => $userId,
            'uid9' => $userId,
            'uid10' => $userId,
            'tenant1' => $tenantId,
            'tenant2' => $tenantId,
            'tenant3' => $tenantId,
        ];

        if ($hasLastActive) {
            $params['uid_la'] = $userId;
        }

        // Apply cursor filter
        if ($cursorTime) {
            $sql .= " AND m.created_at < :cursor_time";
            $params['cursor_time'] = $cursorTime;
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Check if there are more results
        $hasMore = count($conversations) > $limit;
        if ($hasMore) {
            array_pop($conversations);
        }

        // Format conversations and generate next cursor
        $nextCursor = null;
        $items = [];

        foreach ($conversations as $conv) {
            $items[] = [
                'id' => (int)$conv['other_user_id'], // Using other_user_id as conversation identifier
                'other_user' => [
                    'id' => (int)$conv['other_user_id'],
                    'name' => $conv['other_user_name'] ?? trim(($conv['other_user_first_name'] ?? '') . ' ' . ($conv['other_user_last_name'] ?? '')),
                    'first_name' => $conv['other_user_first_name'],
                    'last_name' => $conv['other_user_last_name'],
                    'avatar_url' => $conv['other_user_avatar'],
                    'is_online' => $conv['other_user_last_active'] ? User::isOnline(null, $conv['other_user_last_active']) : false,
                ],
                'last_message' => [
                    'id' => (int)$conv['last_message_id'],
                    'body' => $conv['last_message_body'],
                    'is_voice' => !empty($conv['last_message_audio_url']),
                    'is_own' => (int)$conv['last_message_sender_id'] === $userId,
                    'created_at' => $conv['last_message_at'],
                ],
                'unread_count' => (int)$conv['unread_count'],
            ];

            // Set cursor to last item's timestamp
            $nextCursor = $conv['last_message_at'];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $nextCursor ? base64_encode($nextCursor) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single conversation's details
     *
     * @param int $otherUserId The other participant's ID (acts as conversation ID)
     * @param int $userId The requesting user's ID
     * @return array|null Conversation details or null if not found/unauthorized
     */
    public static function getConversation(int $otherUserId, int $userId): ?array
    {
        self::$errors = [];

        // Get the other user
        $otherUser = User::findById($otherUserId);
        if (!$otherUser) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Get unread count
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM messages
            WHERE tenant_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$tenantId, $otherUserId, $userId]);
        $unreadCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        // Get message count
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM messages
            WHERE tenant_id = ?
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId, $otherUserId, $userId]);
        $messageCount = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        return [
            'id' => $otherUserId,
            'other_user' => [
                'id' => (int)$otherUser['id'],
                'name' => $otherUser['name'] ?? trim(($otherUser['first_name'] ?? '') . ' ' . ($otherUser['last_name'] ?? '')),
                'first_name' => $otherUser['first_name'] ?? null,
                'last_name' => $otherUser['last_name'] ?? null,
                'avatar_url' => $otherUser['avatar_url'] ?? null,
                'is_online' => User::isOnline($otherUserId),
            ],
            'unread_count' => $unreadCount,
            'message_count' => $messageCount,
        ];
    }

    /**
     * Get messages within a conversation with cursor-based pagination
     *
     * @param int $otherUserId The other participant's ID
     * @param int $userId The requesting user's ID
     * @param array $filters ['cursor' => string, 'limit' => int, 'direction' => 'older'|'newer']
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getMessages(int $otherUserId, int $userId, array $filters = []): array
    {
        self::$errors = [];
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 50, 100);
        $cursor = $filters['cursor'] ?? null;
        $direction = $filters['direction'] ?? 'older'; // 'older' = go back in time, 'newer' = go forward

        // Decode cursor (message ID)
        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build query
        $sql = "
            SELECT
                m.id,
                m.sender_id,
                m.receiver_id,
                m.subject,
                m.body,
                m.audio_url,
                m.audio_duration,
                m.is_read,
                m.created_at,
                sender.name as sender_name,
                sender.avatar_url as sender_avatar
            FROM messages m
            JOIN users sender ON m.sender_id = sender.id
            WHERE m.tenant_id = ?
            AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ";
        $params = [$tenantId, $userId, $otherUserId, $otherUserId, $userId];

        // Apply cursor
        if ($cursorId) {
            if ($direction === 'newer') {
                $sql .= " AND m.id > ?";
            } else {
                $sql .= " AND m.id < ?";
            }
            $params[] = $cursorId;
        }

        // Order: for 'older' we want descending to get older messages, then reverse for display
        // For 'newer' we want ascending
        if ($direction === 'newer') {
            $sql .= " ORDER BY m.id ASC";
        } else {
            $sql .= " ORDER BY m.id DESC";
        }

        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Check if there are more results
        $hasMore = count($messages) > $limit;
        if ($hasMore) {
            array_pop($messages);
        }

        // For 'older' direction, reverse to get chronological order (oldest first)
        if ($direction === 'older') {
            $messages = array_reverse($messages);
        }

        // Format messages
        $items = [];
        $firstId = null;
        $lastId = null;

        foreach ($messages as $msg) {
            if ($firstId === null) {
                $firstId = $msg['id'];
            }
            $lastId = $msg['id'];

            $items[] = [
                'id' => (int)$msg['id'],
                'body' => $msg['body'],
                'is_voice' => !empty($msg['audio_url']),
                'audio_url' => $msg['audio_url'],
                'audio_duration' => $msg['audio_duration'] ? (int)$msg['audio_duration'] : null,
                'is_own' => (int)$msg['sender_id'] === $userId,
                'sender' => [
                    'id' => (int)$msg['sender_id'],
                    'name' => $msg['sender_name'],
                    'avatar_url' => $msg['sender_avatar'],
                ],
                'is_read' => (bool)$msg['is_read'],
                'created_at' => $msg['created_at'],
            ];
        }

        // Generate cursor based on direction
        // For 'older': cursor points to oldest message for next page of older messages
        // For 'newer': cursor points to newest message for next page of newer messages
        $nextCursor = null;
        if ($hasMore) {
            if ($direction === 'older') {
                $nextCursor = $firstId ? base64_encode((string)$firstId) : null;
            } else {
                $nextCursor = $lastId ? base64_encode((string)$lastId) : null;
            }
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Validate message data before sending
     *
     * @param array $data
     * @param int $senderId
     * @return bool
     */
    public static function validate(array $data, int $senderId): bool
    {
        self::$errors = [];

        // Must have either recipient_id (new conversation) or conversation_id (existing)
        $recipientId = $data['recipient_id'] ?? $data['conversation_id'] ?? null;

        if (!$recipientId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Recipient is required', 'field' => 'recipient_id'];
            return false;
        }

        $recipientId = (int)$recipientId;

        // Can't message yourself
        if ($recipientId === $senderId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot send message to yourself', 'field' => 'recipient_id'];
            return false;
        }

        // Check recipient exists
        $recipient = User::findById($recipientId);
        if (!$recipient) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Recipient not found', 'field' => 'recipient_id'];
            return false;
        }

        // Must have body or voice_url
        $body = trim($data['body'] ?? '');
        $voiceUrl = $data['voice_url'] ?? null;

        if (empty($body) && empty($voiceUrl)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message body or voice attachment is required', 'field' => 'body'];
            return false;
        }

        // Body length check
        if (!empty($body) && strlen($body) > 10000) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message is too long (max 10000 characters)', 'field' => 'body'];
            return false;
        }

        return true;
    }

    /**
     * Send a message
     *
     * @param int $senderId
     * @param array $data ['recipient_id' or 'conversation_id', 'body', 'voice_url'?, 'voice_duration'?]
     * @return array|null The sent message or null on failure
     */
    public static function send(int $senderId, array $data): ?array
    {
        if (!self::validate($data, $senderId)) {
            return null;
        }

        $tenantId = TenantContext::getId();
        $receiverId = (int)($data['recipient_id'] ?? $data['conversation_id']);
        $body = trim($data['body'] ?? '');
        $subject = $data['subject'] ?? '';
        $voiceUrl = $data['voice_url'] ?? null;
        $voiceDuration = $data['voice_duration'] ?? null;

        $db = Database::getConnection();

        try {
            // Insert message
            if ($voiceUrl) {
                // Voice message
                $stmt = $db->prepare("
                    INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, audio_url, audio_duration, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$tenantId, $senderId, $receiverId, $subject, $body, $voiceUrl, $voiceDuration]);
            } else {
                // Text message
                $stmt = $db->prepare("
                    INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$tenantId, $senderId, $receiverId, $subject, $body]);
            }

            $messageId = $db->lastInsertId();

            // Get the inserted message
            $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get sender info for notifications
            $stmt = $db->prepare("SELECT name, first_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch(\PDO::FETCH_ASSOC);
            $senderName = $sender['name'] ?? trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

            // Broadcast via Pusher for real-time updates
            RealtimeService::broadcastMessage($senderId, $receiverId, [
                'id' => $messageId,
                'subject' => $subject,
                'body' => $body,
                'audio_url' => $voiceUrl,
                'audio_duration' => $voiceDuration,
                'is_voice' => !empty($voiceUrl),
                'created_at' => $message['created_at'],
            ]);

            // Create in-app notification
            $preview = $voiceUrl
                ? 'sent you a voice message'
                : (mb_strlen($body) > 50 ? mb_substr($body, 0, 47) . '...' : $body);

            Notification::create(
                $receiverId,
                "{$senderName}: {$preview}",
                "/messages/{$senderId}",
                'message'
            );

            // Gamification: Check message badges
            try {
                GamificationService::checkMessageBadges($senderId);
            } catch (\Throwable $e) {
                error_log("Gamification message error: " . $e->getMessage());
            }

            // Return formatted message
            return [
                'id' => (int)$messageId,
                'body' => $body,
                'is_voice' => !empty($voiceUrl),
                'audio_url' => $voiceUrl,
                'audio_duration' => $voiceDuration ? (int)$voiceDuration : null,
                'is_own' => true,
                'sender' => [
                    'id' => $senderId,
                    'name' => $senderName,
                ],
                'recipient_id' => $receiverId,
                'is_read' => false,
                'created_at' => $message['created_at'],
            ];
        } catch (\Exception $e) {
            error_log("MessageService::send error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to send message'];
            return null;
        }
    }

    /**
     * Mark all messages in a conversation as read
     *
     * @param int $otherUserId The other participant
     * @param int $userId The user marking as read
     * @return int Number of messages marked as read
     */
    public static function markAsRead(int $otherUserId, int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE tenant_id = ?
            AND sender_id = ?
            AND receiver_id = ?
            AND is_read = 0
        ");
        $stmt->execute([$tenantId, $otherUserId, $userId]);

        return $stmt->rowCount();
    }

    /**
     * Get total unread message count for a user
     *
     * @param int $userId
     * @return int
     */
    public static function getUnreadCount(int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt FROM messages
            WHERE tenant_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$tenantId, $userId]);

        return (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];
    }

    /**
     * Archive (soft delete) a conversation
     * Since there's no archive column, we'll do a hard delete but return the count
     *
     * @param int $otherUserId
     * @param int $userId
     * @return int Number of messages deleted
     */
    public static function archiveConversation(int $otherUserId, int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // For now, this performs a hard delete (matching existing behavior)
        // TODO: Add archived_at column for true soft delete
        $stmt = $db->prepare("
            DELETE FROM messages
            WHERE tenant_id = ?
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId, $otherUserId, $userId]);

        return $stmt->rowCount();
    }

    /**
     * Check if a user is a participant in a conversation
     *
     * @param int $otherUserId
     * @param int $userId
     * @return bool
     */
    public static function isParticipant(int $otherUserId, int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Check if any messages exist between these users
        $stmt = $db->prepare("
            SELECT 1 FROM messages
            WHERE tenant_id = ?
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId, $otherUserId, $userId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Set typing indicator (broadcasts via Pusher)
     *
     * @param int $otherUserId
     * @param int $userId
     * @param bool $isTyping
     * @return bool
     */
    public static function setTypingIndicator(int $otherUserId, int $userId, bool $isTyping): bool
    {
        return RealtimeService::broadcastTyping($userId, $otherUserId, $isTyping);
    }

    /**
     * Helper: Check if a column exists in a table
     */
    private static function hasColumn(string $table, string $column): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
