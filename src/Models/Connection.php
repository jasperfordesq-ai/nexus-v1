<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Connection
{
    public static function sendRequest($requesterId, $receiverId)
    {
        $tenantId = TenantContext::getId();
        // Check if exists within tenant
        $sql = "SELECT id FROM connections WHERE tenant_id = ? AND ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?))";
        $exists = Database::query($sql, [$tenantId, $requesterId, $receiverId, $receiverId, $requesterId])->fetch();

        if ($exists) return false;

        $sql = "INSERT INTO connections (tenant_id, requester_id, receiver_id, status) VALUES (?, ?, ?, 'pending')";
        Database::query($sql, [$tenantId, $requesterId, $receiverId]);
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
        $tenantId = TenantContext::getId();
        // SECURITY: Verify the accepting user is the intended receiver
        if ($receiverId !== null) {
            $sql = "UPDATE connections SET status = 'accepted' WHERE id = ? AND receiver_id = ? AND tenant_id = ?";
            $stmt = Database::query($sql, [$id, $receiverId, $tenantId]);
            return $stmt->rowCount() > 0;
        }
        // Legacy fallback (should be avoided) - log warning
        error_log("SECURITY WARNING: acceptRequest called without receiverId verification for connection $id");
        $sql = "UPDATE connections SET status = 'accepted' WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
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
        $tenantId = TenantContext::getId();
        // SECURITY: Verify the user is part of this connection
        if ($userId !== null) {
            $sql = "DELETE FROM connections WHERE id = ? AND (requester_id = ? OR receiver_id = ?) AND tenant_id = ?";
            $stmt = Database::query($sql, [$id, $userId, $userId, $tenantId]);
            return $stmt->rowCount() > 0;
        }
        // Legacy fallback (should be avoided) - log warning
        error_log("SECURITY WARNING: removeConnection called without userId verification for connection $id");
        $sql = "DELETE FROM connections WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
        return true;
    }

    public static function getStatus($user1, $user2)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM connections WHERE tenant_id = ? AND ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?))";
        return Database::query($sql, [$tenantId, $user1, $user2, $user2, $user1])->fetch();
    }

    public static function getPending($userId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT c.*, u.name as requester_name, u.avatar_url 
                FROM connections c 
                JOIN users u ON c.requester_id = u.id 
                WHERE c.receiver_id = ? AND c.status = 'pending' AND c.tenant_id = ?";
        return Database::query($sql, [$userId, $tenantId])->fetchAll();
    }

    public static function getFriends($userId)
    {
        $tenantId = TenantContext::getId();
        // Complex query to get the "other" user
        $sql = "SELECT u.id, u.name, u.avatar_url, u.location 
                FROM connections c
                JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
                WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted' AND c.tenant_id = ?";
        return Database::query($sql, [$userId, $userId, $userId, $tenantId])->fetchAll();
    }
}
