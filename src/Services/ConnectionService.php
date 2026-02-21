<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Connection;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;

/**
 * ConnectionService - Business logic for user connections (friend requests)
 *
 * This service extracts business logic from the Connection model and ConnectionController
 * to be shared between HTML and API controllers.
 *
 * Connection states:
 * - none: No relationship
 * - pending_sent: Current user sent request
 * - pending_received: Current user received request
 * - accepted: Connected/friends
 *
 * Key operations:
 * - Send connection request
 * - Accept connection request
 * - Reject/cancel connection request
 * - Remove existing connection
 * - List connections and pending requests
 */
class ConnectionService
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
     * Get connections for a user with cursor-based pagination
     *
     * @param int $userId User ID
     * @param array $filters [
     *   'status' => 'accepted' (default) | 'pending_sent' | 'pending_received',
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 100)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getConnections(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 100);
        $status = $filters['status'] ?? 'accepted';
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build query based on status filter
        if ($status === 'pending_sent') {
            // Requests sent by the user
            $sql = "
                SELECT c.id as connection_id, c.status, c.created_at,
                       u.id, u.name, u.avatar_url, u.location, u.bio
                FROM connections c
                JOIN users u ON c.receiver_id = u.id
                WHERE c.requester_id = ? AND c.status = 'pending'
            ";
        } elseif ($status === 'pending_received') {
            // Requests received by the user
            $sql = "
                SELECT c.id as connection_id, c.status, c.created_at,
                       u.id, u.name, u.avatar_url, u.location, u.bio
                FROM connections c
                JOIN users u ON c.requester_id = u.id
                WHERE c.receiver_id = ? AND c.status = 'pending'
            ";
        } else {
            // Accepted connections (friends)
            $sql = "
                SELECT c.id as connection_id, c.status, c.created_at,
                       u.id, u.name, u.avatar_url, u.location, u.bio
                FROM connections c
                JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
                WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'
            ";
        }

        $params = [];
        if ($status === 'accepted') {
            $params = [$userId, $userId, $userId];
        } else {
            $params = [$userId];
        }

        if ($cursorId) {
            $sql .= " AND c.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY c.created_at DESC, c.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $connections = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($connections) > $limit;
        if ($hasMore) {
            array_pop($connections);
        }

        $items = [];
        $lastId = null;

        foreach ($connections as $c) {
            $lastId = $c['connection_id'];
            $items[] = [
                'connection_id' => (int)$c['connection_id'],
                'user' => [
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                    'avatar_url' => $c['avatar_url'],
                    'location' => $c['location'],
                    'bio' => self::truncate($c['bio'] ?? '', 150),
                ],
                'status' => $c['status'],
                'created_at' => $c['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get connection status between two users
     *
     * @param int $userId Current user
     * @param int $otherUserId Other user
     * @return array ['status' => string, 'connection_id' => int|null, 'direction' => string|null]
     */
    public static function getStatus(int $userId, int $otherUserId): array
    {
        $connection = Connection::getStatus($userId, $otherUserId);

        if (!$connection) {
            return [
                'status' => 'none',
                'connection_id' => null,
                'direction' => null,
            ];
        }

        $direction = null;
        $status = $connection['status'];

        if ($status === 'pending') {
            $direction = ((int)$connection['requester_id'] === $userId) ? 'sent' : 'received';
            $status = "pending_{$direction}";
        } elseif ($status === 'accepted') {
            // Normalize 'accepted' to 'connected' for frontend consistency
            $status = 'connected';
        }

        return [
            'status' => $status,
            'connection_id' => (int)$connection['id'],
            'direction' => $direction,
        ];
    }

    /**
     * Send a connection request
     *
     * @param int $requesterId User sending the request
     * @param int $receiverId User receiving the request
     * @return bool Success
     */
    public static function sendRequest(int $requesterId, int $receiverId): bool
    {
        self::$errors = [];

        // Can't connect to yourself
        if ($requesterId === $receiverId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'You cannot connect to yourself'];
            return false;
        }

        // Check receiver exists
        $receiver = User::findById($receiverId);
        if (!$receiver) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return false;
        }

        // Check existing connection
        $existing = Connection::getStatus($requesterId, $receiverId);
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                self::$errors[] = ['code' => 'ALREADY_CONNECTED', 'message' => 'You are already connected'];
                return false;
            }
            if ($existing['status'] === 'pending') {
                if ((int)$existing['requester_id'] === $requesterId) {
                    self::$errors[] = ['code' => 'REQUEST_EXISTS', 'message' => 'Connection request already sent'];
                    return false;
                } else {
                    // They already sent us a request - auto-accept it
                    return self::acceptRequest((int)$existing['id'], $requesterId);
                }
            }
        }

        try {
            $success = Connection::sendRequest($requesterId, $receiverId);

            if ($success) {
                // Get requester name
                $requester = User::findById($requesterId);
                $requesterName = $requester['name'] ?? 'Someone';

                // Create notification
                Notification::create($receiverId, "You have a new connection request from {$requesterName}");

                // Send email
                self::sendRequestEmail($receiverId, $requester);

                // Gamification
                try {
                    GamificationService::checkConnectionBadges($requesterId);
                } catch (\Throwable $e) {
                    error_log("Gamification connection error: " . $e->getMessage());
                }
            }

            return $success;
        } catch (\Exception $e) {
            error_log("ConnectionService::sendRequest error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to send connection request'];
            return false;
        }
    }

    /**
     * Accept a connection request
     *
     * @param int $connectionId Connection ID
     * @param int $receiverId User accepting (must be receiver)
     * @return bool Success
     */
    public static function acceptRequest(int $connectionId, int $receiverId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify the request exists and user is the receiver
        $stmt = $db->prepare("SELECT * FROM connections WHERE id = ?");
        $stmt->execute([$connectionId]);
        $connection = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Connection request not found'];
            return false;
        }

        if ((int)$connection['receiver_id'] !== $receiverId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You can only accept requests sent to you'];
            return false;
        }

        if ($connection['status'] !== 'pending') {
            self::$errors[] = ['code' => 'INVALID_STATE', 'message' => 'Request is not pending'];
            return false;
        }

        try {
            $success = Connection::acceptRequest($connectionId, $receiverId);

            if ($success) {
                $requesterId = (int)$connection['requester_id'];

                // Get receiver info for notification/email
                $receiver = User::findById($receiverId);
                $receiverName = $receiver['name'] ?? 'Someone';

                // Notify requester
                Notification::create($requesterId, "{$receiverName} accepted your connection request.");

                // Send acceptance email
                self::sendAcceptanceEmail($requesterId, $receiver);

                // Gamification for both users
                try {
                    GamificationService::checkConnectionBadges($receiverId);
                    GamificationService::checkConnectionBadges($requesterId);
                } catch (\Throwable $e) {
                    error_log("Gamification connection error: " . $e->getMessage());
                }
            }

            return $success;
        } catch (\Exception $e) {
            error_log("ConnectionService::acceptRequest error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to accept request'];
            return false;
        }
    }

    /**
     * Reject a connection request (receiver) or cancel request (requester)
     *
     * @param int $connectionId Connection ID
     * @param int $userId User rejecting/canceling
     * @return bool Success
     */
    public static function rejectRequest(int $connectionId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify the request exists and user is involved
        $stmt = $db->prepare("SELECT * FROM connections WHERE id = ? AND status = 'pending'");
        $stmt->execute([$connectionId]);
        $connection = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Connection request not found'];
            return false;
        }

        $isRequester = (int)$connection['requester_id'] === $userId;
        $isReceiver = (int)$connection['receiver_id'] === $userId;

        if (!$isRequester && !$isReceiver) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not part of this connection request'];
            return false;
        }

        try {
            return Connection::removeConnection($connectionId, $userId);
        } catch (\Exception $e) {
            error_log("ConnectionService::rejectRequest error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to reject request'];
            return false;
        }
    }

    /**
     * Remove an existing connection (unfriend)
     *
     * @param int $connectionId Connection ID
     * @param int $userId User removing the connection
     * @return bool Success
     */
    public static function removeConnection(int $connectionId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify the connection exists and user is part of it
        $stmt = $db->prepare("SELECT * FROM connections WHERE id = ? AND status = 'accepted'");
        $stmt->execute([$connectionId]);
        $connection = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Connection not found'];
            return false;
        }

        $isRequester = (int)$connection['requester_id'] === $userId;
        $isReceiver = (int)$connection['receiver_id'] === $userId;

        if (!$isRequester && !$isReceiver) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not part of this connection'];
            return false;
        }

        try {
            return Connection::removeConnection($connectionId, $userId);
        } catch (\Exception $e) {
            error_log("ConnectionService::removeConnection error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to remove connection'];
            return false;
        }
    }

    /**
     * Get pending request counts
     *
     * @param int $userId
     * @return array ['received' => int, 'sent' => int]
     */
    public static function getPendingCounts(int $userId): array
    {
        $db = Database::getConnection();

        $received = $db->prepare("SELECT COUNT(*) FROM connections WHERE receiver_id = ? AND status = 'pending'");
        $received->execute([$userId]);
        $receivedCount = (int)$received->fetchColumn();

        $sent = $db->prepare("SELECT COUNT(*) FROM connections WHERE requester_id = ? AND status = 'pending'");
        $sent->execute([$userId]);
        $sentCount = (int)$sent->fetchColumn();

        return [
            'received' => $receivedCount,
            'sent' => $sentCount,
        ];
    }

    /**
     * Get total friends count
     */
    public static function getFriendsCount(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted'");
        $stmt->execute([$userId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if two users are connected
     */
    public static function areConnected(int $user1, int $user2): bool
    {
        $status = self::getStatus($user1, $user2);
        return $status['status'] === 'accepted';
    }

    /**
     * Send connection request email
     */
    private static function sendRequestEmail(int $receiverId, array $requester): void
    {
        try {
            $receiver = User::findById($receiverId);
            if (!$receiver || empty($receiver['email'])) {
                return;
            }

            $mailer = new Mailer();
            $baseUrl = TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');
            $profileLink = rtrim($baseUrl, '/') . "/profile/{$requester['id']}";
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');

            $html = EmailTemplate::render(
                "New Connection Request",
                "{$requester['name']} sent you a connection request.",
                "Expand your network on {$tenantName}. View their profile to accept or decline the request.",
                "View Profile",
                $profileLink,
                $tenantName
            );

            $mailer->send($receiver['email'], "New Connection Request", $html);
        } catch (\Throwable $e) {
            error_log("Connection request email failed: " . $e->getMessage());
        }
    }

    /**
     * Send acceptance email
     */
    private static function sendAcceptanceEmail(int $requesterId, array $receiver): void
    {
        try {
            $requester = User::findById($requesterId);
            if (!$requester || empty($requester['email'])) {
                return;
            }

            $mailer = new Mailer();
            $baseUrl = TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');
            $profileLink = rtrim($baseUrl, '/') . "/profile/{$receiver['id']}";
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');

            $html = EmailTemplate::render(
                "Request Accepted",
                "{$receiver['name']} accepted your connection request!",
                "You are now connected. You can send messages, exchange credits, and see their updates.",
                "View Profile",
                $profileLink,
                $tenantName
            );

            $mailer->send($requester['email'], "Connection Request Accepted", $html);
        } catch (\Throwable $e) {
            error_log("Connection acceptance email failed: " . $e->getMessage());
        }
    }

    /**
     * Truncate text
     */
    private static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
