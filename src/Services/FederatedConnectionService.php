<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * FederatedConnectionService
 *
 * Handles cross-tenant connection requests between federated members.
 * Uses the federation_connections table (separate from same-tenant connections).
 */
class FederatedConnectionService
{
    /**
     * Send a cross-tenant connection request
     */
    public static function sendRequest(int $requesterId, int $receiverId, int $receiverTenantId, ?string $message = null): array
    {
        $requesterTenantId = TenantContext::getId();

        // Cannot connect to yourself
        if ($requesterId === $receiverId && $requesterTenantId === $receiverTenantId) {
            return ['success' => false, 'error' => 'Cannot connect to yourself'];
        }

        // Verify federation gateway allows this
        $canConnect = FederationGateway::canViewProfile($requesterTenantId, $receiverTenantId, $receiverId, $requesterId);
        if (!$canConnect['allowed']) {
            return ['success' => false, 'error' => 'Federation partnership does not allow connections'];
        }

        // Check for existing connection (in either direction)
        $existing = Database::query(
            "SELECT id, status FROM federation_connections
             WHERE (requester_user_id = ? AND requester_tenant_id = ? AND receiver_user_id = ? AND receiver_tenant_id = ?)
                OR (requester_user_id = ? AND requester_tenant_id = ? AND receiver_user_id = ? AND receiver_tenant_id = ?)",
            [
                $requesterId, $requesterTenantId, $receiverId, $receiverTenantId,
                $receiverId, $receiverTenantId, $requesterId, $requesterTenantId,
            ]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['status'] === 'accepted') {
                return ['success' => false, 'error' => 'You are already connected with this member'];
            }
            if ($existing['status'] === 'pending') {
                return ['success' => false, 'error' => 'A connection request is already pending'];
            }
            // If previously rejected, allow re-request by updating
            Database::query(
                "UPDATE federation_connections SET status = 'pending', message = ?, updated_at = NOW()
                 WHERE id = ?",
                [$message, $existing['id']]
            );
            $connectionId = (int)$existing['id'];
        } else {
            Database::query(
                "INSERT INTO federation_connections
                 (requester_user_id, requester_tenant_id, receiver_user_id, receiver_tenant_id, status, message, created_at)
                 VALUES (?, ?, ?, ?, 'pending', ?, NOW())",
                [$requesterId, $requesterTenantId, $receiverId, $receiverTenantId, $message]
            );
            $connectionId = (int)Database::lastInsertId();
        }

        // Send notifications to receiver
        try {
            $requesterInfo = self::getUserInfo($requesterId, $requesterTenantId);
            $requesterName = $requesterInfo['name'] ?? 'A federation member';
            $requesterTenantName = $requesterInfo['tenant_name'] ?? 'a partner community';

            $notifMessage = "{$requesterName} from {$requesterTenantName} wants to connect with you";
            Notification::create(
                $receiverId,
                $notifMessage,
                '/federation/connections',
                'federation_connection_request',
                true,
                $receiverTenantId
            );

            // Email notification
            FederationEmailService::sendConnectionRequestNotification(
                $receiverId,
                $requesterId,
                $requesterTenantId,
                $message
            );
        } catch (\Exception $e) {
            error_log("FederatedConnectionService: Failed to send request notification: " . $e->getMessage());
        }

        // Audit log
        FederationAuditService::log(
            'federation_connection_request',
            $requesterTenantId,
            $receiverTenantId,
            $requesterId,
            ['receiver_id' => $receiverId, 'connection_id' => $connectionId]
        );

        return ['success' => true, 'connection_id' => $connectionId, 'status' => 'pending'];
    }

    /**
     * Accept a cross-tenant connection request
     */
    public static function acceptRequest(int $connectionId, int $userId): array
    {
        $connection = self::getConnectionForReceiver($connectionId, $userId);
        if (!$connection) {
            return ['success' => false, 'error' => 'Connection request not found'];
        }
        if ($connection['status'] !== 'pending') {
            return ['success' => false, 'error' => 'This request is no longer pending'];
        }

        Database::query(
            "UPDATE federation_connections SET status = 'accepted', updated_at = NOW() WHERE id = ?",
            [$connectionId]
        );

        // Notify the requester that their request was accepted
        try {
            $receiverInfo = self::getUserInfo($userId, (int)$connection['receiver_tenant_id']);
            $receiverName = $receiverInfo['name'] ?? 'A federation member';
            $receiverTenantName = $receiverInfo['tenant_name'] ?? 'a partner community';

            $notifMessage = "{$receiverName} from {$receiverTenantName} accepted your connection request";
            Notification::create(
                (int)$connection['requester_user_id'],
                $notifMessage,
                '/federation/connections',
                'federation_connection_accepted',
                true,
                (int)$connection['requester_tenant_id']
            );
        } catch (\Exception $e) {
            error_log("FederatedConnectionService: Failed to send acceptance notification: " . $e->getMessage());
        }

        FederationAuditService::log(
            'federation_connection_accepted',
            (int)$connection['receiver_tenant_id'],
            (int)$connection['requester_tenant_id'],
            $userId,
            ['connection_id' => $connectionId, 'requester_id' => $connection['requester_user_id']]
        );

        return ['success' => true, 'status' => 'accepted'];
    }

    /**
     * Reject a cross-tenant connection request
     */
    public static function rejectRequest(int $connectionId, int $userId): array
    {
        $connection = self::getConnectionForReceiver($connectionId, $userId);
        if (!$connection) {
            return ['success' => false, 'error' => 'Connection request not found'];
        }
        if ($connection['status'] !== 'pending') {
            return ['success' => false, 'error' => 'This request is no longer pending'];
        }

        Database::query(
            "UPDATE federation_connections SET status = 'rejected', updated_at = NOW() WHERE id = ?",
            [$connectionId]
        );

        FederationAuditService::log(
            'federation_connection_rejected',
            (int)$connection['receiver_tenant_id'],
            (int)$connection['requester_tenant_id'],
            $userId,
            ['connection_id' => $connectionId]
        );

        return ['success' => true, 'status' => 'rejected'];
    }

    /**
     * Remove an accepted cross-tenant connection
     */
    public static function removeConnection(int $connectionId, int $userId): array
    {
        $userTenantId = TenantContext::getId();

        $connection = Database::query(
            "SELECT * FROM federation_connections
             WHERE id = ? AND status = 'accepted'
             AND ((requester_user_id = ? AND requester_tenant_id = ?) OR (receiver_user_id = ? AND receiver_tenant_id = ?))",
            [$connectionId, $userId, $userTenantId, $userId, $userTenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            return ['success' => false, 'error' => 'Connection not found'];
        }

        Database::query(
            "DELETE FROM federation_connections WHERE id = ? AND ((requester_tenant_id = ? OR receiver_tenant_id = ?))",
            [$connectionId, $userTenantId, $userTenantId]
        );

        return ['success' => true];
    }

    /**
     * Get connection status between current user and a federated user
     */
    public static function getStatus(int $userId, int $otherUserId, int $otherTenantId): array
    {
        $userTenantId = TenantContext::getId();

        $connection = Database::query(
            "SELECT * FROM federation_connections
             WHERE (requester_user_id = ? AND requester_tenant_id = ? AND receiver_user_id = ? AND receiver_tenant_id = ?)
                OR (requester_user_id = ? AND requester_tenant_id = ? AND receiver_user_id = ? AND receiver_tenant_id = ?)",
            [
                $userId, $userTenantId, $otherUserId, $otherTenantId,
                $otherUserId, $otherTenantId, $userId, $userTenantId,
            ]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$connection) {
            return ['status' => 'none', 'connection_id' => null, 'direction' => null];
        }

        $status = $connection['status'];
        $direction = null;

        if ($status === 'pending') {
            $direction = ((int)$connection['requester_user_id'] === $userId
                && (int)$connection['requester_tenant_id'] === $userTenantId) ? 'sent' : 'received';
            $status = "pending_{$direction}";
        }

        return [
            'status' => $status,
            'connection_id' => (int)$connection['id'],
            'direction' => $direction,
        ];
    }

    /**
     * Get list of federated connections for a user
     */
    public static function getConnections(int $userId, string $statusFilter = 'accepted', int $limit = 50, int $offset = 0): array
    {
        $userTenantId = TenantContext::getId();

        if ($statusFilter === 'pending_received') {
            return Database::query(
                "SELECT fc.id, fc.status, fc.message, fc.created_at,
                        u.id as user_id, u.name, u.avatar_url,
                        t.id as tenant_id, t.name as tenant_name
                 FROM federation_connections fc
                 JOIN users u ON fc.requester_user_id = u.id
                 JOIN tenants t ON fc.requester_tenant_id = t.id
                 WHERE fc.receiver_user_id = ? AND fc.receiver_tenant_id = ? AND fc.status = 'pending'
                 ORDER BY fc.created_at DESC LIMIT ? OFFSET ?",
                [$userId, $userTenantId, $limit, $offset]
            )->fetchAll(\PDO::FETCH_ASSOC);
        }

        if ($statusFilter === 'pending_sent') {
            return Database::query(
                "SELECT fc.id, fc.status, fc.message, fc.created_at,
                        u.id as user_id, u.name, u.avatar_url,
                        t.id as tenant_id, t.name as tenant_name
                 FROM federation_connections fc
                 JOIN users u ON fc.receiver_user_id = u.id
                 JOIN tenants t ON fc.receiver_tenant_id = t.id
                 WHERE fc.requester_user_id = ? AND fc.requester_tenant_id = ? AND fc.status = 'pending'
                 ORDER BY fc.created_at DESC LIMIT ? OFFSET ?",
                [$userId, $userTenantId, $limit, $offset]
            )->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Accepted connections - determine which side is the "other" user
        return Database::query(
            "SELECT fc.id, fc.status, fc.created_at, fc.updated_at,
                    CASE WHEN fc.requester_user_id = ? AND fc.requester_tenant_id = ?
                         THEN fc.receiver_user_id ELSE fc.requester_user_id END as user_id,
                    CASE WHEN fc.requester_user_id = ? AND fc.requester_tenant_id = ?
                         THEN u_recv.name ELSE u_req.name END as name,
                    CASE WHEN fc.requester_user_id = ? AND fc.requester_tenant_id = ?
                         THEN u_recv.avatar_url ELSE u_req.avatar_url END as avatar_url,
                    CASE WHEN fc.requester_user_id = ? AND fc.requester_tenant_id = ?
                         THEN fc.receiver_tenant_id ELSE fc.requester_tenant_id END as tenant_id,
                    CASE WHEN fc.requester_user_id = ? AND fc.requester_tenant_id = ?
                         THEN t_recv.name ELSE t_req.name END as tenant_name
             FROM federation_connections fc
             JOIN users u_req ON fc.requester_user_id = u_req.id
             JOIN users u_recv ON fc.receiver_user_id = u_recv.id
             JOIN tenants t_req ON fc.requester_tenant_id = t_req.id
             JOIN tenants t_recv ON fc.receiver_tenant_id = t_recv.id
             WHERE fc.status = 'accepted'
               AND ((fc.requester_user_id = ? AND fc.requester_tenant_id = ?)
                 OR (fc.receiver_user_id = ? AND fc.receiver_tenant_id = ?))
             ORDER BY fc.updated_at DESC LIMIT ? OFFSET ?",
            [
                $userId, $userTenantId,  // CASE 1
                $userId, $userTenantId,  // CASE 2
                $userId, $userTenantId,  // CASE 3
                $userId, $userTenantId,  // CASE 4
                $userId, $userTenantId,  // CASE 5
                $userId, $userTenantId,  // WHERE requester
                $userId, $userTenantId,  // WHERE receiver
                $limit, $offset          // LIMIT OFFSET
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get pending request count for a user
     */
    public static function getPendingCount(int $userId): int
    {
        $userTenantId = TenantContext::getId();

        return (int)Database::query(
            "SELECT COUNT(*) FROM federation_connections
             WHERE receiver_user_id = ? AND receiver_tenant_id = ? AND status = 'pending'",
            [$userId, $userTenantId]
        )->fetchColumn();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function getConnectionForReceiver(int $connectionId, int $userId): ?array
    {
        $userTenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT * FROM federation_connections
             WHERE id = ? AND receiver_user_id = ? AND receiver_tenant_id = ?",
            [$connectionId, $userId, $userTenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private static function getUserInfo(int $userId, int $tenantId): ?array
    {
        $result = Database::query(
            "SELECT u.name, u.first_name, u.last_name, u.avatar_url, t.name as tenant_name
             FROM users u JOIN tenants t ON u.tenant_id = t.id
             WHERE u.id = ? AND u.tenant_id = ?",
            [$userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            $result['name'] = $result['name'] ?: trim($result['first_name'] . ' ' . $result['last_name']);
        }

        return $result ?: null;
    }
}
