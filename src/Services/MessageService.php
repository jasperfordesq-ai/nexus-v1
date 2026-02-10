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
     * @param array $filters ['cursor' => string, 'limit' => int, 'archived' => bool]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getConversations(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;
        $showArchived = !empty($filters['archived']);

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
        // Check if archived columns exist for soft delete filtering
        $hasArchived = self::hasColumn('messages', 'archived_by_sender');

        $lastActiveSelect = $hasLastActive
            ? "CASE WHEN m.sender_id = :uid_la THEN receiver.last_active_at ELSE sender.last_active_at END as other_user_last_active"
            : "NULL as other_user_last_active";

        // Build archive filter based on whether we want archived or non-archived
        if ($hasArchived) {
            if ($showArchived) {
                // Show ONLY archived conversations
                $archiveFilter = "AND (
                    (m.sender_id = :uid_arch1 AND m.archived_by_sender IS NOT NULL)
                    OR (m.receiver_id = :uid_arch2 AND m.archived_by_receiver IS NOT NULL)
                )";
            } else {
                // Show ONLY non-archived conversations (default)
                $archiveFilter = "AND (
                    (m.sender_id = :uid_arch1 AND (m.archived_by_sender IS NULL))
                    OR (m.receiver_id = :uid_arch2 AND (m.archived_by_receiver IS NULL))
                )";
            }
        } else {
            $archiveFilter = "";
        }

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
            {$archiveFilter}
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

        if ($hasArchived) {
            $params['uid_arch1'] = $userId;
            $params['uid_arch2'] = $userId;
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

        // Check if archived columns exist for soft delete filtering
        $hasArchived = self::hasColumn('messages', 'archived_by_sender');

        // Decode cursor (message ID)
        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build archive filter: exclude messages archived by this user
        $archiveFilter = "";
        if ($hasArchived) {
            $archiveFilter = "
                AND (
                    (m.sender_id = ? AND m.archived_by_sender IS NULL)
                    OR (m.receiver_id = ? AND m.archived_by_receiver IS NULL)
                )
            ";
        }

        // Check if reactions column exists for the query
        $hasReactionsCol = self::hasColumn('messages', 'reactions');
        $reactionsSelect = $hasReactionsCol ? ", m.reactions" : ", NULL as reactions";

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
                {$reactionsSelect}
            FROM messages m
            JOIN users sender ON m.sender_id = sender.id
            WHERE m.tenant_id = ?
            AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
            {$archiveFilter}
        ";
        $params = [$tenantId, $userId, $otherUserId, $otherUserId, $userId];

        // Add archive filter params
        if ($hasArchived) {
            $params[] = $userId;
            $params[] = $userId;
        }

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

        // Check if reactions column exists
        $hasReactions = self::hasColumn('messages', 'reactions');

        // Get all message IDs for batch attachment lookup
        $messageIds = array_column($messages, 'id');
        $attachmentsByMessage = self::getAttachmentsForMessages($messageIds);

        // Format messages
        $items = [];
        $firstId = null;
        $lastId = null;

        foreach ($messages as $msg) {
            if ($firstId === null) {
                $firstId = $msg['id'];
            }
            $lastId = $msg['id'];

            // Parse reactions if available
            $reactions = [];
            if ($hasReactions && !empty($msg['reactions'])) {
                $parsedReactions = json_decode($msg['reactions'], true) ?? [];
                // Remove internal _users tracking from response
                unset($parsedReactions['_users']);
                $reactions = $parsedReactions;
            }

            $items[] = [
                'id' => (int)$msg['id'],
                'body' => $msg['body'],
                'is_voice' => !empty($msg['audio_url']),
                'audio_url' => $msg['audio_url'],
                'audio_duration' => $msg['audio_duration'] ? (int)$msg['audio_duration'] : null,
                'is_own' => (int)$msg['sender_id'] === $userId,
                'sender_id' => (int)$msg['sender_id'],
                'sender' => [
                    'id' => (int)$msg['sender_id'],
                    'name' => $msg['sender_name'],
                    'avatar_url' => $msg['sender_avatar'],
                ],
                'is_read' => (bool)$msg['is_read'],
                'created_at' => $msg['created_at'],
                'reactions' => $reactions,
                'attachments' => $attachmentsByMessage[$msg['id']] ?? [],
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

        // Must have body, voice_url, or attachments
        $body = trim($data['body'] ?? '');
        $voiceUrl = $data['voice_url'] ?? $data['audio_url'] ?? null;
        $isVoice = !empty($data['is_voice']) || !empty($voiceUrl);
        $hasAttachments = !empty($data['has_attachments']) || !empty($_FILES['attachments']);

        if (empty($body) && !$isVoice && !$hasAttachments) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message body, voice, or attachment is required', 'field' => 'body'];
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
        // Check if direct messaging is disabled for this tenant
        if (!BrokerControlConfigService::isDirectMessagingEnabled()) {
            self::$errors[] = [
                'code' => 'MESSAGING_DISABLED',
                'message' => 'Direct messaging is currently disabled. Please use the exchange request system instead.',
            ];
            return null;
        }

        // Check if sender has messaging disabled
        if (BrokerMessageVisibilityService::isMessagingDisabledForUser($senderId)) {
            self::$errors[] = [
                'code' => 'SENDER_RESTRICTED',
                'message' => 'Your messaging privileges have been restricted. Please contact support.',
            ];
            return null;
        }

        // Check if receiver has messaging disabled (they shouldn't receive messages)
        $receiverId = (int)($data['recipient_id'] ?? $data['conversation_id']);
        if (BrokerMessageVisibilityService::isMessagingDisabledForUser($receiverId)) {
            self::$errors[] = [
                'code' => 'RECIPIENT_UNAVAILABLE',
                'message' => 'This user is not currently accepting messages.',
            ];
            return null;
        }

        if (!self::validate($data, $senderId)) {
            return null;
        }

        $tenantId = TenantContext::getId();
        $receiverId = (int)($data['recipient_id'] ?? $data['conversation_id']);
        $body = trim($data['body'] ?? '');
        $subject = $data['subject'] ?? '';
        $voiceUrl = $data['voice_url'] ?? $data['audio_url'] ?? null;
        $voiceDuration = $data['voice_duration'] ?? $data['audio_duration'] ?? null;

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

            // Send email notification (respects user preferences - instant/daily/off)
            try {
                Message::sendEmailNotification($receiverId, $senderName, $preview, $senderId);
            } catch (\Throwable $e) {
                // Log but don't fail the message send
                error_log("[MessageService] Email notification error: " . $e->getMessage());
            }

            // Broker visibility: Copy message for broker review if needed
            try {
                $listingId = isset($data['listing_id']) ? (int) $data['listing_id'] : null;
                $copyReason = BrokerMessageVisibilityService::shouldCopyMessage($senderId, $receiverId, $listingId);
                if ($copyReason) {
                    BrokerMessageVisibilityService::copyMessageForBroker($messageId, $copyReason);
                }
            } catch (\Throwable $e) {
                // Log but don't fail the message send
                error_log("[MessageService] Broker visibility error: " . $e->getMessage());
            }

            // Return formatted message
            return [
                'id' => (int)$messageId,
                'body' => $body,
                'is_voice' => !empty($voiceUrl),
                'audio_url' => $voiceUrl,
                'audio_duration' => $voiceDuration ? (int)$voiceDuration : null,
                'is_own' => true,
                'sender_id' => $senderId,  // For frontend Message type compatibility
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
     * Archive (soft delete) a conversation for a specific user
     *
     * Uses per-user archival columns so each user can independently archive
     * without affecting the other user's view of the conversation.
     *
     * @param int $otherUserId The other participant in the conversation
     * @param int $userId The user archiving the conversation
     * @return int Number of messages archived
     */
    public static function archiveConversation(int $otherUserId, int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Check if the archived columns exist (for backward compatibility)
        if (!self::hasColumn('messages', 'archived_by_sender')) {
            // Fall back to hard delete if columns don't exist yet
            $stmt = $db->prepare("
                DELETE FROM messages
                WHERE tenant_id = ?
                AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ");
            $stmt->execute([$tenantId, $userId, $otherUserId, $otherUserId, $userId]);
            return $stmt->rowCount();
        }

        $now = date('Y-m-d H:i:s');
        $totalUpdated = 0;

        // Archive messages where user is the sender
        $stmt = $db->prepare("
            UPDATE messages
            SET archived_by_sender = ?
            WHERE tenant_id = ?
            AND sender_id = ?
            AND receiver_id = ?
            AND archived_by_sender IS NULL
        ");
        $stmt->execute([$now, $tenantId, $userId, $otherUserId]);
        $totalUpdated += $stmt->rowCount();

        // Archive messages where user is the receiver
        $stmt = $db->prepare("
            UPDATE messages
            SET archived_by_receiver = ?
            WHERE tenant_id = ?
            AND sender_id = ?
            AND receiver_id = ?
            AND archived_by_receiver IS NULL
        ");
        $stmt->execute([$now, $tenantId, $otherUserId, $userId]);
        $totalUpdated += $stmt->rowCount();

        return $totalUpdated;
    }

    /**
     * Unarchive a conversation for a specific user
     *
     * @param int $otherUserId The other participant in the conversation
     * @param int $userId The user unarchiving the conversation
     * @return int Number of messages unarchived
     */
    public static function unarchiveConversation(int $otherUserId, int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Check if the archived columns exist
        if (!self::hasColumn('messages', 'archived_by_sender')) {
            return 0;
        }

        $totalUpdated = 0;

        // Unarchive messages where user is the sender
        $stmt = $db->prepare("
            UPDATE messages
            SET archived_by_sender = NULL
            WHERE tenant_id = ?
            AND sender_id = ?
            AND receiver_id = ?
            AND archived_by_sender IS NOT NULL
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId]);
        $totalUpdated += $stmt->rowCount();

        // Unarchive messages where user is the receiver
        $stmt = $db->prepare("
            UPDATE messages
            SET archived_by_receiver = NULL
            WHERE tenant_id = ?
            AND sender_id = ?
            AND receiver_id = ?
            AND archived_by_receiver IS NOT NULL
        ");
        $stmt->execute([$tenantId, $otherUserId, $userId]);
        $totalUpdated += $stmt->rowCount();

        return $totalUpdated;
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
     * Edit a message
     *
     * @param int $messageId The message to edit
     * @param int $userId The user editing (must be sender)
     * @param string $newBody The new message content
     * @return array|null Updated message data or null on error
     */
    public static function editMessage(int $messageId, int $userId, string $newBody): ?array
    {
        self::$errors = [];
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Check if message exists and user is the sender
        $stmt = $db->prepare("SELECT id, sender_id, receiver_id, body, created_at FROM messages WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$messageId, $tenantId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return null;
        }

        if ((int)$message['sender_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You can only edit your own messages'];
            return null;
        }

        // Check if is_edited column exists
        $hasEditedColumn = self::hasColumn('messages', 'is_edited');

        // Update the message
        if ($hasEditedColumn) {
            $stmt = $db->prepare("UPDATE messages SET body = ?, is_edited = 1, edited_at = NOW() WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE messages SET body = ? WHERE id = ?");
        }
        $stmt->execute([$newBody, $messageId]);

        return [
            'id' => $messageId,
            'body' => $newBody,
            'is_edited' => true,
            'sender_id' => (int)$message['sender_id'],
            'created_at' => $message['created_at'],
        ];
    }

    /**
     * Delete a message (soft delete)
     *
     * @param int $messageId The message to delete
     * @param int $userId The user deleting (must be sender)
     * @return bool Success
     */
    public static function deleteMessage(int $messageId, int $userId): bool
    {
        self::$errors = [];
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Check if message exists and user is the sender
        $stmt = $db->prepare("SELECT id, sender_id FROM messages WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$messageId, $tenantId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return false;
        }

        if ((int)$message['sender_id'] !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You can only delete your own messages'];
            return false;
        }

        // Check if is_deleted column exists
        $hasDeletedColumn = self::hasColumn('messages', 'is_deleted');

        if ($hasDeletedColumn) {
            // Soft delete - mark as deleted and clear body
            $stmt = $db->prepare("UPDATE messages SET is_deleted = 1, body = '[Message deleted]', deleted_at = NOW() WHERE id = ?");
        } else {
            // Hard delete as fallback
            $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
        }
        $stmt->execute([$messageId]);

        return true;
    }

    /**
     * Toggle a reaction on a message
     *
     * @param int $messageId The message to react to
     * @param int $userId The user toggling the reaction
     * @param string $emoji The reaction emoji
     * @return bool|null True if added, false if removed, null on error
     */
    public static function toggleReaction(int $messageId, int $userId, string $emoji): ?bool
    {
        self::$errors = [];
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Check if message exists and user has access
        $stmt = $db->prepare("
            SELECT id, sender_id, receiver_id, reactions
            FROM messages
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$messageId, $tenantId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return null;
        }

        // Check if user is sender or receiver
        if ($message['sender_id'] != $userId && $message['receiver_id'] != $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You cannot react to this message'];
            return null;
        }

        // Check if reactions column exists
        if (!self::hasColumn('messages', 'reactions')) {
            // Reactions feature not available yet
            self::$errors[] = ['code' => 'FEATURE_UNAVAILABLE', 'message' => 'Reactions feature not yet enabled'];
            return null;
        }

        // Parse existing reactions
        $reactions = [];
        if (!empty($message['reactions'])) {
            $reactions = json_decode($message['reactions'], true) ?? [];
        }

        // Track user reactions (store which users reacted with which emoji)
        $userReactions = $reactions['_users'] ?? [];
        $userKey = "{$userId}_{$emoji}";

        // Toggle the reaction
        $wasAdded = false;
        if (isset($userReactions[$userKey])) {
            // Remove the reaction
            unset($userReactions[$userKey]);
            if (isset($reactions[$emoji]) && $reactions[$emoji] > 0) {
                $reactions[$emoji]--;
                if ($reactions[$emoji] <= 0) {
                    unset($reactions[$emoji]);
                }
            }
        } else {
            // Add the reaction
            $userReactions[$userKey] = true;
            $reactions[$emoji] = ($reactions[$emoji] ?? 0) + 1;
            $wasAdded = true;
        }

        $reactions['_users'] = $userReactions;

        // Update the message
        $stmt = $db->prepare("UPDATE messages SET reactions = ? WHERE id = ?");
        $stmt->execute([json_encode($reactions), $messageId]);

        return $wasAdded;
    }

    /**
     * Get reactions for a message (formatted for API response)
     *
     * @param int $messageId
     * @return array Emoji => count mapping
     */
    public static function getReactions(int $messageId): array
    {
        $db = Database::getConnection();

        // Check if reactions column exists
        if (!self::hasColumn('messages', 'reactions')) {
            return [];
        }

        $stmt = $db->prepare("SELECT reactions FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || empty($result['reactions'])) {
            return [];
        }

        $reactions = json_decode($result['reactions'], true) ?? [];

        // Remove internal _users tracking from response
        unset($reactions['_users']);

        return $reactions;
    }

    /**
     * Save attachments for a message
     *
     * @param int $messageId
     * @param array $files Array of uploaded files from $_FILES
     * @param int $tenantId
     * @return array Array of saved attachment data
     */
    public static function saveAttachments(int $messageId, array $files, int $tenantId): array
    {
        // Check if attachments table exists
        if (!self::hasTable('message_attachments')) {
            return [];
        }

        $db = Database::getConnection();
        $savedAttachments = [];
        $uploadDir = __DIR__ . '/../../httpdocs/uploads/messages/';

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($files as $file) {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }

            $originalName = $file['name'];
            $mimeType = $file['type'];
            $fileSize = $file['size'];

            // Determine file type
            $isImage = str_starts_with($mimeType, 'image/');
            $fileType = $isImage ? 'image' : 'file';

            // Generate unique filename
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = uniqid('msg_' . $messageId . '_') . '.' . $ext;
            $filePath = $uploadDir . $uniqueName;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Generate URL
                $fileUrl = '/uploads/messages/' . $uniqueName;

                // Insert into database
                $stmt = $db->prepare("
                    INSERT INTO message_attachments (message_id, tenant_id, file_name, file_path, file_url, file_type, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$messageId, $tenantId, $originalName, $filePath, $fileUrl, $fileType, $mimeType, $fileSize]);

                $attachmentId = $db->lastInsertId();
                $savedAttachments[] = [
                    'id' => (int)$attachmentId,
                    'url' => $fileUrl,
                    'type' => $fileType,
                    'name' => $originalName,
                    'size' => $fileSize,
                ];
            }
        }

        return $savedAttachments;
    }

    /**
     * Get attachments for a message
     *
     * @param int $messageId
     * @return array
     */
    public static function getAttachments(int $messageId): array
    {
        if (!self::hasTable('message_attachments')) {
            return [];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, file_url as url, file_type as type, file_name as name, file_size as size FROM message_attachments WHERE message_id = ?");
        $stmt->execute([$messageId]);
        $attachments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Convert relative URLs to absolute API URLs
        $apiBaseUrl = rtrim(getenv('API_BASE_URL') ?: 'https://api.project-nexus.ie', '/');
        foreach ($attachments as &$attachment) {
            if (!empty($attachment['url']) && strpos($attachment['url'], 'http') !== 0) {
                $attachment['url'] = $apiBaseUrl . $attachment['url'];
            }
        }

        return $attachments;
    }

    /**
     * Get attachments for multiple messages (batch query)
     *
     * @param array $messageIds
     * @return array Keyed by message_id
     */
    public static function getAttachmentsForMessages(array $messageIds): array
    {
        if (!self::hasTable('message_attachments') || empty($messageIds)) {
            return [];
        }

        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $db->prepare("SELECT id, message_id, file_url as url, file_type as type, file_name as name, file_size as size FROM message_attachments WHERE message_id IN ($placeholders)");
        $stmt->execute($messageIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Convert relative URLs to absolute API URLs
        $apiBaseUrl = rtrim(getenv('API_BASE_URL') ?: 'https://api.project-nexus.ie', '/');

        // Group by message_id
        $result = [];
        foreach ($rows as $row) {
            $msgId = $row['message_id'];
            unset($row['message_id']);

            // Make URL absolute
            if (!empty($row['url']) && strpos($row['url'], 'http') !== 0) {
                $row['url'] = $apiBaseUrl . $row['url'];
            }

            $result[$msgId][] = $row;
        }

        return $result;
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

    /**
     * Helper: Check if a table exists
     */
    private static function hasTable(string $table): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
