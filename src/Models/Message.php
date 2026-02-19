<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;
use Nexus\Services\RealtimeService;

class Message
{
    /**
     * Send an email notification for a new message
     * Sends immediately for 'instant' frequency, queues for 'daily' digest
     * Respects user's notification preferences
     *
     * Made public so it can be called from MessageService for V2 API messages
     */
    public static function sendEmailNotification(int $receiverId, string $senderName, string $messagePreview, int $senderId): void
    {
        try {
            $db = Database::getConnection();

            // Get receiver's email notification preferences
            $prefStmt = $db->prepare("
                SELECT frequency FROM notification_settings
                WHERE user_id = ? AND context_type = 'global' AND context_id = 0
            ");
            $prefStmt->execute([$receiverId]);
            $pref = $prefStmt->fetch();

            // Default to 'instant' for messages if no preference set, 'off' means no email
            $frequency = $pref['frequency'] ?? 'instant';

            if ($frequency === 'off') {
                return; // User has disabled email notifications
            }

            // Get receiver info for email
            $receiverStmt = $db->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $receiverStmt->execute([$receiverId]);
            $receiver = $receiverStmt->fetch();

            if (!$receiver || empty($receiver['email'])) {
                return;
            }

            // Build email content
            $content = "{$senderName} sent you a message: {$messagePreview}";
            $link = "/messages/{$senderId}";

            // Build HTML email body
            $receiverName = $receiver['first_name'] ?? 'there';
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $baseUrl = TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');

            $htmlBody = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">💬 New Message</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">You have a new message from {$senderName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #64748b; margin: 0 0 8px;">Hi {$receiverName},</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #1e293b; margin: 0; font-style: italic;">"{$messagePreview}"</p>
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$baseUrl}{$link}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                Read Message
            </a>
        </div>
    </div>
    <div style="text-align: center; padding: 16px; color: #94a3b8; font-size: 12px;">
        <p>You received this because you have a {$tenantName} account.</p>
        <p><a href="{$baseUrl}/settings?tab=notifications" style="color: #6366f1;">Manage notification preferences</a></p>
    </div>
</div>
HTML;

            // For instant: send immediately. For daily: queue for digest.
            if ($frequency === 'instant') {
                // Send email immediately - no cron needed
                $mailer = new Mailer();
                $result = $mailer->send($receiver['email'], "💬 New Message from {$senderName}", $htmlBody);
                if ($result) {
                    error_log("[Message] Email sent successfully to {$receiver['email']} from {$senderName}");
                } else {
                    error_log("[Message] Email FAILED to send to {$receiver['email']} from {$senderName}");
                }
            } else {
                // Queue for daily digest
                $insertStmt = $db->prepare("
                    INSERT INTO notification_queue (user_id, activity_type, content_snippet, link, frequency, email_body, created_at, status)
                    VALUES (?, 'new_message', ?, ?, ?, ?, NOW(), 'pending')
                ");
                $insertStmt->execute([$receiverId, substr($content, 0, 250), $link, $frequency, $htmlBody]);
            }

        } catch (\Exception $e) {
            // Log error but don't fail message creation
            error_log('[Message] Email notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new message
     */
    public static function create($tenantId, $senderId, $receiverId, $subject, $body)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $senderId, $receiverId, $subject, $body]);
        $messageId = $db->lastInsertId();

        // Broadcast via Pusher for instant real-time updates
        RealtimeService::broadcastMessage($senderId, $receiverId, [
            'id' => $messageId,
            'subject' => $subject,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get sender name for notification
        $senderStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $senderStmt->execute([$senderId]);
        $sender = $senderStmt->fetch();
        $senderName = $sender ? trim($sender['name']) : 'Someone';

        // Create notification for receiver (triggers Web Push)
        $preview = mb_strlen($body) > 50 ? mb_substr($body, 0, 47) . '...' : $body;
        Notification::create(
            $receiverId,
            "{$senderName} sent you a message: {$preview}",
            "/messages/{$senderId}",
            'message'
        );

        // Queue email notification based on user preferences
        self::sendEmailNotification($receiverId, $senderName, $preview, $senderId);

        return $messageId;
    }

    /**
     * Create a voice message
     */
    public static function createVoice($tenantId, $senderId, $receiverId, $audioUrl, $duration)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, audio_url, audio_duration, created_at)
            VALUES (?, ?, ?, '', '', ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $senderId, $receiverId, $audioUrl, $duration]);
        $messageId = $db->lastInsertId();

        // Broadcast via Pusher for instant real-time updates
        RealtimeService::broadcastMessage($senderId, $receiverId, [
            'id' => $messageId,
            'subject' => '',
            'body' => '',
            'audio_url' => $audioUrl,
            'audio_duration' => $duration,
            'is_voice' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get sender name for notification
        $senderStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $senderStmt->execute([$senderId]);
        $sender = $senderStmt->fetch();
        $senderName = $sender ? trim($sender['name']) : 'Someone';

        // Create notification for receiver (triggers Web Push)
        Notification::create(
            $receiverId,
            "{$senderName} sent you a voice message",
            "/messages/{$senderId}",
            'message'
        );

        // Queue email notification based on user preferences
        self::sendEmailNotification($receiverId, $senderName, '🎤 Voice message', $senderId);

        return $messageId;
    }

    /**
     * Get all conversations for a user (Inbox view)
     * Groups by the *other* user in the conversation.
     * Shows latest message snippet.
     */
    /**
     * Get all conversations for a user (Inbox view)
     * Groups by the *other* user in the conversation.
     * Shows latest message snippet.
     */
    public static function getInbox($userId, $tenantId)
    {
        $db = Database::getConnection();

        // Check if last_active_at column exists
        $hasLastActive = false;
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'last_active_at'");
            $hasLastActive = $checkCol->rowCount() > 0;
        } catch (\Exception $e) {
            // Column check failed, assume it doesn't exist
        }

        // Build query with or without last_active_at
        $lastActiveSelect = $hasLastActive
            ? "CASE WHEN m.sender_id = :uid7 THEN receiver.last_active_at ELSE sender.last_active_at END as other_user_last_active"
            : "NULL as other_user_last_active";

        $sql = "
            SELECT
                m.*,
                CASE WHEN m.sender_id = :uid1 THEN receiver.name ELSE sender.name END as other_user_name,
                CASE WHEN m.sender_id = :uid2 THEN receiver.id ELSE sender.id END as other_user_id,
                CASE WHEN m.sender_id = :uid3 THEN receiver.avatar_url ELSE sender.avatar_url END as other_user_avatar,
                {$lastActiveSelect}
            FROM messages m
            JOIN users sender ON m.sender_id = sender.id
            JOIN users receiver ON m.receiver_id = receiver.id
            WHERE m.id IN (
                SELECT MAX(id)
                FROM messages
                WHERE (sender_id = :uid4 OR receiver_id = :uid5)
                AND sender_id != receiver_id
                GROUP BY
                    CASE WHEN sender_id = :uid6 THEN receiver_id ELSE sender_id END
            )
            AND m.sender_id != m.receiver_id
            ORDER BY m.created_at DESC
        ";

        $params = [
            'uid1' => $userId,
            'uid2' => $userId,
            'uid3' => $userId,
            'uid4' => $userId,
            'uid5' => $userId,
            'uid6' => $userId
        ];

        if ($hasLastActive) {
            $params['uid7'] = $userId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get thread messages between two users
     */
    public static function getThread($tenantId, $userId1, $userId2)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                m.*, 
                sender.name as sender_name, 
                receiver.name as receiver_name,
                sender.avatar_url as sender_avatar,
                receiver.avatar_url as receiver_avatar
            FROM messages m
            JOIN users sender ON m.sender_id = sender.id
            JOIN users receiver ON m.receiver_id = receiver.id
            WHERE m.tenant_id = ?
            AND (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$tenantId, $userId1, $userId2, $userId2, $userId1]);
        return $stmt->fetchAll();
    }

    /**
     * Mark messages in thread as read
     */
    public static function markThreadRead($tenantId, $userId, $otherUserId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE tenant_id = ?
            AND receiver_id = ?
            AND sender_id = ?
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId]);
    }

    /**
     * Delete all messages in a conversation between two users
     */
    public static function deleteConversation($tenantId, $userId, $otherUserId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            DELETE FROM messages
            WHERE tenant_id = ?
            AND (
                (sender_id = ? AND receiver_id = ?)
                OR
                (sender_id = ? AND receiver_id = ?)
            )
        ");
        $stmt->execute([$tenantId, $userId, $otherUserId, $otherUserId, $userId]);
        return $stmt->rowCount();
    }

    /**
     * Delete a single message (only if user is sender or receiver)
     */
    public static function deleteSingle($tenantId, $messageId, $userId)
    {
        $db = Database::getConnection();

        // Verify ownership - user must be sender or receiver
        $stmt = $db->prepare("
            SELECT id, audio_url FROM messages
            WHERE id = ? AND tenant_id = ? AND (sender_id = ? OR receiver_id = ?)
        ");
        $stmt->execute([$messageId, $tenantId, $userId, $userId]);
        $message = $stmt->fetch();

        if (!$message) {
            return false;
        }

        // Delete the message
        $deleteStmt = $db->prepare("DELETE FROM messages WHERE id = ? AND tenant_id = ?");
        $deleteStmt->execute([$messageId, $tenantId]);

        // Return audio_url for cleanup if it was a voice message
        return [
            'deleted' => $deleteStmt->rowCount() > 0,
            'audio_url' => $message['audio_url'] ?? null
        ];
    }

    /**
     * Get a single message by ID (for authorization checks)
     */
    public static function findById($tenantId, $messageId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM messages WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$messageId, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Add or toggle a reaction on a message
     */
    public static function toggleReaction($tenantId, $messageId, $userId, $emoji)
    {
        $db = Database::getConnection();

        // First verify user can access this message (is sender or receiver)
        $msgStmt = $db->prepare("
            SELECT id, sender_id, receiver_id FROM messages
            WHERE id = ? AND tenant_id = ? AND (sender_id = ? OR receiver_id = ?)
        ");
        $msgStmt->execute([$messageId, $tenantId, $userId, $userId]);
        $message = $msgStmt->fetch();

        if (!$message) {
            return ['success' => false, 'error' => 'Message not found'];
        }

        // Check if user already has this reaction
        $checkStmt = $db->prepare("
            SELECT id FROM message_reactions
            WHERE message_id = ? AND user_id = ? AND emoji = ?
        ");
        $checkStmt->execute([$messageId, $userId, $emoji]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Remove reaction (toggle off)
            $deleteStmt = $db->prepare("DELETE FROM message_reactions WHERE id = ?");
            $deleteStmt->execute([$existing['id']]);
            $action = 'removed';
        } else {
            // Add reaction
            $insertStmt = $db->prepare("
                INSERT INTO message_reactions (message_id, user_id, emoji, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $insertStmt->execute([$messageId, $userId, $emoji]);
            $action = 'added';
        }

        // Get updated reactions for this message
        $reactions = self::getReactions($messageId);

        return [
            'success' => true,
            'action' => $action,
            'reactions' => $reactions
        ];
    }

    /**
     * Get all reactions for a message
     */
    public static function getReactions($messageId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT
                emoji,
                COUNT(*) as count,
                GROUP_CONCAT(user_id) as user_ids
            FROM message_reactions
            WHERE message_id = ?
            GROUP BY emoji
            ORDER BY MIN(created_at)
        ");
        $stmt->execute([$messageId]);
        $results = $stmt->fetchAll();

        $reactions = [];
        foreach ($results as $row) {
            $reactions[] = [
                'emoji' => $row['emoji'],
                'count' => (int)$row['count'],
                'user_ids' => array_map('intval', explode(',', $row['user_ids']))
            ];
        }
        return $reactions;
    }

    /**
     * Get reactions for multiple messages (batch)
     */
    public static function getReactionsBatch($messageIds)
    {
        if (empty($messageIds)) {
            return [];
        }

        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $db->prepare("
            SELECT
                message_id,
                emoji,
                COUNT(*) as count,
                GROUP_CONCAT(user_id) as user_ids
            FROM message_reactions
            WHERE message_id IN ({$placeholders})
            GROUP BY message_id, emoji
            ORDER BY message_id, MIN(created_at)
        ");
        $stmt->execute($messageIds);
        $results = $stmt->fetchAll();

        $grouped = [];
        foreach ($results as $row) {
            $msgId = $row['message_id'];
            if (!isset($grouped[$msgId])) {
                $grouped[$msgId] = [];
            }
            $grouped[$msgId][] = [
                'emoji' => $row['emoji'],
                'count' => (int)$row['count'],
                'user_ids' => array_map('intval', explode(',', $row['user_ids']))
            ];
        }
        return $grouped;
    }
}
