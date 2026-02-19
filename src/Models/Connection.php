<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Connection
{
    public static function sendRequest($requesterId, $receiverId)
    {
        // Check if exists
        $sql = "SELECT id FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)";
        $exists = Database::query($sql, [$requesterId, $receiverId, $receiverId, $requesterId])->fetch();

        if ($exists) return false;

        $sql = "INSERT INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')";
        Database::query($sql, [$requesterId, $receiverId]);
        return true;
    }

    /**
     * Accept a connection request - verifies the current user is the receiver
     * @param int $id Connection ID
     * @param int $receiverId User ID of the person accepting (must be the receiver)
     * @return bool True if accepted, false if not authorized
     */
    public static function acceptRequest($id, $receiverId = null)
    {
        // SECURITY: Verify the accepting user is the intended receiver
        if ($receiverId !== null) {
            $sql = "UPDATE connections SET status = 'accepted' WHERE id = ? AND receiver_id = ?";
            $stmt = Database::query($sql, [$id, $receiverId]);
            return $stmt->rowCount() > 0;
        }
        // Legacy fallback (should be avoided) - log warning
        error_log("SECURITY WARNING: acceptRequest called without receiverId verification for connection $id");
        $sql = "UPDATE connections SET status = 'accepted' WHERE id = ?";
        Database::query($sql, [$id]);
        return true;
    }

    /**
     * Remove a connection - verifies the current user is part of the connection
     * @param int $id Connection ID
     * @param int $userId User ID of the person removing (must be requester or receiver)
     * @return bool True if removed, false if not authorized
     */
    public static function removeConnection($id, $userId = null)
    {
        // SECURITY: Verify the user is part of this connection
        if ($userId !== null) {
            $sql = "DELETE FROM connections WHERE id = ? AND (requester_id = ? OR receiver_id = ?)";
            $stmt = Database::query($sql, [$id, $userId, $userId]);
            return $stmt->rowCount() > 0;
        }
        // Legacy fallback (should be avoided) - log warning
        error_log("SECURITY WARNING: removeConnection called without userId verification for connection $id");
        $sql = "DELETE FROM connections WHERE id = ?";
        Database::query($sql, [$id]);
        return true;
    }

    public static function getStatus($user1, $user2)
    {
        $sql = "SELECT * FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)";
        return Database::query($sql, [$user1, $user2, $user2, $user1])->fetch();
    }

    public static function getPending($userId)
    {
        $sql = "SELECT c.*, u.name as requester_name, u.avatar_url 
                FROM connections c 
                JOIN users u ON c.requester_id = u.id 
                WHERE c.receiver_id = ? AND c.status = 'pending'";
        return Database::query($sql, [$userId])->fetchAll();
    }

    public static function getFriends($userId)
    {
        // Complex query to get the "other" user
        $sql = "SELECT u.id, u.name, u.avatar_url, u.location 
                FROM connections c
                JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
                WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'";
        return Database::query($sql, [$userId, $userId, $userId])->fetchAll();
    }
}
