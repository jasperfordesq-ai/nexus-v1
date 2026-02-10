<?php

namespace Nexus\Controllers\Api;

use Nexus\Models\User;
use Nexus\Models\Listing;
use Nexus\Models\Group;
use Nexus\Core\TenantContext;
use Nexus\Core\Database;

/**
 * CoreApiController - Core platform API endpoints
 *
 * Provides fundamental API endpoints for members, listings, groups,
 * messages, and notifications.
 */
class CoreApiController extends BaseApiController
{
    // /api/members
    // Backward compatible: Returns all members when no parameters
    // Enhanced: Supports search with ?q=query and ?active=true
    public function members()
    {
        $this->getUserId();
        $this->rateLimit('members', 60, 60);

        $db = Database::getConnection();
        $tenantId = $this->getTenantId();

        $query = trim($this->query('q', ''));
        $activeOnly = $this->query('active') === 'true';
        $limit = $this->queryInt('limit', 100, 1, 500);
        $offset = $this->queryInt('offset', 0, 0);

        // Base query - IMPORTANT: Always include last_active_at for "Active Now" filtering
        // Only show members with avatars (consistent with User::count() and directory policy)
        $sql = "SELECT id, name, email, avatar_url as avatar, role, bio, location, last_active_at
                FROM users
                WHERE tenant_id = ?
                AND avatar_url IS NOT NULL
                AND LENGTH(avatar_url) > 0";
        $params = [$tenantId];

        // Add search filter if provided (minimum 2 characters)
        if (!empty($query) && strlen($query) >= 2) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR bio LIKE ? OR location LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Add active filter if requested (last 5 minutes)
        if ($activeOnly) {
            $sql .= " AND last_active_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        }

        // Order by last active (most recent first)
        $sql .= " ORDER BY last_active_at DESC";

        // Add pagination
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $members = $stmt->fetchAll();

            // Get total count for pagination (without LIMIT)
            $countSql = "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND avatar_url IS NOT NULL AND LENGTH(avatar_url) > 0";
            $countParams = [$tenantId];

            if (!empty($query) && strlen($query) >= 2) {
                $countSql .= " AND (name LIKE ? OR email LIKE ? OR bio LIKE ? OR location LIKE ?)";
                $searchTerm = "%{$query}%";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            if ($activeOnly) {
                $countSql .= " AND last_active_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
            }

            $countStmt = $db->prepare($countSql);
            $countStmt->execute($countParams);
            $totalCount = (int) $countStmt->fetchColumn();

            // Use standardized paginated response
            $page = ($offset / $limit) + 1;
            $this->paginated($members, $totalCount, $page, $limit);

        } catch (\Exception $e) {
            error_log("Members API error: " . $e->getMessage());
            $this->error('Failed to retrieve members', 500, 'SERVER_ERROR');
        }
    }

    // /api/listings
    public function listings()
    {
        $this->getUserId();
        $this->rateLimit('listings', 60, 60);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, title, description, price, type, created_at, image_url as image, user_id FROM listings WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->getTenantId()]);
        $listings = $stmt->fetchAll();

        $this->success($listings);
    }

    // /api/groups
    public function groups()
    {
        $this->getUserId();
        $this->rateLimit('groups', 60, 60);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, description, image_url as image FROM groups WHERE tenant_id = ?");
        $stmt->execute([$this->getTenantId()]);
        $groups = $stmt->fetchAll();

        foreach ($groups as &$g) {
            $g['members'] = $db->query("SELECT COUNT(*) FROM group_members WHERE group_id = ?", [$g['id']])->fetchColumn();
        }

        $this->success($groups);
    }

    // /api/messages
    public function messages()
    {
        $userId = $this->getUserId();
        $this->rateLimit('messages', 60, 60);

        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT m.*, u.name as sender_name, u.avatar_url as sender_avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.recipient_id = ?
            GROUP BY m.sender_id
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$userId]);
        $messages = $stmt->fetchAll();

        $this->success($messages);
    }

    // /api/notifications
    public function notifications()
    {
        $userId = $this->getUserId();
        $this->rateLimit('notifications', 120, 60);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        $notifs = $stmt->fetchAll();

        $this->success($notifs);
    }

    // /api/notifications/check (Polling)
    public function checkNotifications()
    {
        $userId = $this->getUserId();
        $this->rateLimit('check_notifications', 120, 60);

        $count = \Nexus\Models\Notification::countUnread($userId);
        $this->success(['unread_count' => $count]);
    }

    // /api/notifications/unread-count (For badge updates)
    public function unreadCount()
    {
        $userId = $this->getUserId();
        $this->rateLimit('unread_count', 120, 60);

        // Get unread messages count
        $messagesCount = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $messagesCount += (int)$thread['unread_count'];
                    }
                }
            }
        } catch (\Exception $e) {
            $messagesCount = 0;
        }

        // Get unread notifications count
        $notificationsCount = \Nexus\Models\Notification::countUnread($userId);

        $this->success([
            'messages' => $messagesCount,
            'notifications' => $notificationsCount,
            'total' => $messagesCount + $notificationsCount
        ]);
    }

    // /api/notifications/read
    public function markRead()
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $db = Database::getConnection();

        if ($this->inputBool('all')) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            $this->success(['message' => 'All marked read']);
        }

        $id = $this->inputInt('id');
        if ($id) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            $this->success(['message' => 'Marked read']);
        }

        $this->error('Invalid request', 400, 'VALIDATION_ERROR');
    }

    // /api/messages/poll - Polling fallback for real-time chat
    public function pollMessages()
    {
        $userId = $this->getUserId();
        $this->rateLimit('poll_messages', 120, 60);

        $db = Database::getConnection();

        $otherUserId = $this->queryInt('other_user_id', 0, 1);
        $afterId = $this->queryInt('after', 0, 0);

        if (!$otherUserId) {
            $this->error('Missing other_user_id', 400, 'VALIDATION_ERROR');
        }

        // Get new messages between these two users after the given ID
        $sql = "
            SELECT m.id, m.sender_id, m.body, m.audio_url, m.audio_duration, m.created_at
            FROM messages m
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ";
        $params = [$userId, $otherUserId, $otherUserId, $userId];

        if ($afterId > 0) {
            $sql .= " AND m.id > ?";
            $params[] = $afterId;
        }

        $sql .= " ORDER BY m.id ASC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->success($messages);
    }

    // /api/messages/unread-count - Get unread message count
    public function unreadMessagesCount()
    {
        $userId = $this->getUserId();
        $this->rateLimit('unread_messages', 120, 60);

        $count = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $count += (int)$thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $count = 0;
        }

        $this->success(['count' => $count]);
    }

    // /api/messages/send - Send a message via AJAX (for real-time chat)
    public function sendMessage()
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('send_message', 30, 60);

        $db = Database::getConnection();

        $receiverId = $this->inputInt('receiver_id', 0, 1);
        $body = trim($this->input('body', ''));

        if (!$receiverId) {
            $this->error('Missing receiver_id', 400, 'VALIDATION_ERROR');
        }

        if (empty($body)) {
            $this->error('Message body is required', 400, 'VALIDATION_ERROR');
        }

        // Don't allow messaging yourself
        if ($receiverId === $userId) {
            $this->error('Cannot send message to yourself', 400, 'VALIDATION_ERROR');
        }

        $tenantId = $this->getTenantId();

        try {
            // Insert message
            $stmt = $db->prepare("
                INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tenantId, $userId, $receiverId, $body]);
            $messageId = $db->lastInsertId();

            // Get the inserted message with timestamp
            $stmt = $db->prepare("SELECT id, sender_id, receiver_id, body, created_at FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Trigger Pusher event if available
            if (class_exists('Nexus\Services\RealtimeService')) {
                try {
                    \Nexus\Services\RealtimeService::broadcastMessage($userId, $receiverId, $message);
                } catch (\Exception $e) {
                    error_log("Pusher notification failed: " . $e->getMessage());
                }
            }

            // Create in-app notification
            try {
                $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $sender = $stmt->fetch();
                $senderName = $sender['name'] ?? 'Someone';

                if ($sender && class_exists('Nexus\Models\Notification')) {
                    \Nexus\Models\Notification::create(
                        $receiverId,
                        "New message from " . $senderName,
                        TenantContext::getBasePath() . "/messages/" . $userId,
                        'message'
                    );
                }

                // Send email notification (respects user preferences)
                $preview = mb_strlen($body) > 50 ? mb_substr($body, 0, 47) . '...' : $body;
                \Nexus\Models\Message::sendEmailNotification($receiverId, $senderName, $preview, $userId);
            } catch (\Exception $e) {
                error_log("Message notification failed: " . $e->getMessage());
            }

            $this->created(['message' => $message]);

        } catch (\Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            $this->error('Failed to send message', 500, 'SERVER_ERROR');
        }
    }

    // /api/messages/typing - Broadcast typing indicator
    public function typing()
    {
        $userId = $this->getUserId();
        $this->rateLimit('typing', 60, 60);

        $receiverId = $this->inputInt('receiver_id', 0, 1);

        if (!$receiverId) {
            $this->error('Missing receiver_id', 400, 'VALIDATION_ERROR');
        }

        // Trigger Pusher typing event if available
        if (class_exists('Nexus\Services\RealtimeService')) {
            try {
                \Nexus\Services\RealtimeService::broadcastTyping($userId, $receiverId, true);
                $this->success(['note' => 'Typing broadcast']);
            } catch (\Exception $e) {
                error_log("Pusher typing notification failed: " . $e->getMessage());
                $this->success(['note' => 'Realtime disabled']);
            }
        }

        // No Pusher - just acknowledge
        $this->success(['note' => 'Realtime not configured']);
    }
}
