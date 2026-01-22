<?php

namespace Nexus\Controllers\Api;

use Nexus\Models\User;
use Nexus\Models\Listing;
use Nexus\Models\Group;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class CoreApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        // Use unified auth that supports both session and Bearer token
        return $this->requireAuth();
    }

    // /api/members
    // Backward compatible: Returns all members when no parameters
    // Enhanced: Supports search with ?q=query and ?active=true
    public function members()
    {
        $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();

        $tenantId = TenantContext::getId();
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        $activeOnly = isset($_GET['active']) && $_GET['active'] === 'true';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

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
            // Only count members with avatars (consistent with User::count())
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
            $totalCount = $countStmt->fetchColumn();

            // Enhanced response format (backward compatible)
            $this->jsonResponse([
                'data' => $members,
                'meta' => [
                    'total' => (int)$totalCount,
                    'showing' => count($members),
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Members API error: " . $e->getMessage());
            $this->jsonResponse([
                'error' => 'Failed to retrieve members',
                'message' => 'An error occurred while searching members'
            ], 500);
        }
    }

    // /api/listings
    public function listings()
    {
        $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();
        // Fix: Generic SELECT * is risky. Let's assume schema matches or we fix later.
        // If listings has 'image_url', alias it.
        // Safe Check:
        $stmt = $db->prepare("SELECT id, title, description, price, type, created_at, image_url as image, user_id FROM listings WHERE tenant_id = ? ORDER BY created_at DESC");
        $stmt->execute([TenantContext::getId()]);
        $listings = $stmt->fetchAll();
        $this->jsonResponse(['data' => $listings]);
    }

    // /api/groups
    public function groups()
    {
        $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();
        // Fix: Group image alias
        $stmt = $db->prepare("SELECT id, name, description, image_url as image FROM groups WHERE tenant_id = ?");
        $stmt->execute([TenantContext::getId()]);
        $groups = $stmt->fetchAll();
        foreach ($groups as &$g) {
            $g['members'] = $db->query("SELECT COUNT(*) FROM group_members WHERE group_id = ?", [$g['id']])->fetchColumn();
        }
        $this->jsonResponse(['data' => $groups]);
    }

    // /api/messages
    public function messages()
    {
        $userId = $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();
        // Fix: Sender matches User model, so avatar_url -> sender_avatar
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
        $this->jsonResponse(['data' => $messages]);
    }

    // /api/notifications
    public function notifications()
    {
        $userId = $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
        $notifs = $stmt->fetchAll();
        $this->jsonResponse(['data' => $notifs]);
    }

    // /api/notifications/check (Polling)
    public function checkNotifications()
    {
        $userId = $this->getUserId();
        // Use the Model if available, or direct query
        // Model was audited in Step 14605: Nexus\Models\Notification::countUnread($userId)
        $count = \Nexus\Models\Notification::countUnread($userId);

        $this->jsonResponse(['unread_count' => $count]);
    }

    // /api/notifications/unread-count (For badge updates)
    public function unreadCount()
    {
        $userId = $this->getUserId();

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

        $this->jsonResponse([
            'messages' => $messagesCount,
            'notifications' => $notificationsCount,
            'total' => $messagesCount + $notificationsCount
        ]);
    }
    // /api/notifications/read
    public function markRead()
    {
        $userId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);
        $db = \Nexus\Core\Database::getConnection();

        if (isset($input['all']) && $input['all'] === true) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            $this->jsonResponse(['success' => true, 'message' => 'All marked read']);
        } elseif (isset($input['id'])) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['id'], $userId]);
            $this->jsonResponse(['success' => true, 'message' => 'Marked read']);
        }

        $this->jsonResponse(['error' => 'Invalid request'], 400);
    }

    // /api/messages/poll - Polling fallback for real-time chat
    public function pollMessages()
    {
        $userId = $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();

        $otherUserId = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
        $afterId = isset($_GET['after']) ? (int)$_GET['after'] : 0;

        if (!$otherUserId) {
            $this->jsonResponse(['error' => 'Missing other_user_id'], 400);
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

        $this->jsonResponse(['messages' => $messages]);
    }

    // /api/messages/unread-count - Get unread message count
    public function unreadMessagesCount()
    {
        $userId = $this->getUserId();

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

        $this->jsonResponse(['count' => $count]);
    }

    // /api/messages/send - Send a message via AJAX (for real-time chat)
    public function sendMessage()
    {
        $userId = $this->getUserId();
        $db = \Nexus\Core\Database::getConnection();

        // Get JSON body or form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
        $body = isset($input['body']) ? trim($input['body']) : '';

        if (!$receiverId) {
            $this->jsonResponse(['error' => 'Missing receiver_id'], 400);
        }

        if (empty($body)) {
            $this->jsonResponse(['error' => 'Message body is required'], 400);
        }

        // Don't allow messaging yourself
        if ($receiverId === $userId) {
            $this->jsonResponse(['error' => 'Cannot send message to yourself'], 400);
        }

        $tenantId = TenantContext::getId();

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
                    // Pusher failed, but message was saved - continue
                    error_log("Pusher notification failed: " . $e->getMessage());
                }
            }

            // Create in-app notification
            try {
                $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $sender = $stmt->fetch();

                if ($sender && class_exists('Nexus\Models\Notification')) {
                    \Nexus\Models\Notification::create(
                        $receiverId,
                        "New message from " . $sender['name'],
                        TenantContext::getBasePath() . "/messages/" . $userId,
                        'message'
                    );
                }
            } catch (\Exception $e) {
                // Notification failed but message was saved
                error_log("Message notification failed: " . $e->getMessage());
            }

            $this->jsonResponse([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to send message'], 500);
        }
    }

    // /api/messages/typing - Broadcast typing indicator
    public function typing()
    {
        $userId = $this->getUserId();

        // Get JSON body or form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $receiverId = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;

        if (!$receiverId) {
            $this->jsonResponse(['error' => 'Missing receiver_id'], 400);
        }

        // Trigger Pusher typing event if available
        if (class_exists('Nexus\Services\RealtimeService')) {
            try {
                \Nexus\Services\RealtimeService::broadcastTyping($userId, $receiverId, true);
                $this->jsonResponse(['success' => true]);
            } catch (\Exception $e) {
                // Pusher failed - not critical
                error_log("Pusher typing notification failed: " . $e->getMessage());
                $this->jsonResponse(['success' => true, 'note' => 'Realtime disabled']);
            }
        }

        // No Pusher - just acknowledge
        $this->jsonResponse(['success' => true, 'note' => 'Realtime not configured']);
    }
}
