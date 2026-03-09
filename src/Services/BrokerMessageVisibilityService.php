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
 * BrokerMessageVisibilityService
 *
 * Manages broker visibility into member messages for compliance and safeguarding.
 * Copies messages to a broker review queue based on configurable criteria:
 * - First contact between members
 * - Messages involving new members
 * - Messages related to high-risk listings
 * - Messages from flagged/monitored users
 * - Random sampling for compliance
 */
class BrokerMessageVisibilityService
{
    /**
     * Copy reasons
     */
    public const REASON_FIRST_CONTACT = 'first_contact';
    public const REASON_HIGH_RISK_LISTING = 'high_risk_listing';
    public const REASON_NEW_MEMBER = 'new_member';
    public const REASON_FLAGGED_USER = 'flagged_user';
    public const REASON_MONITORING = 'random_sample';

    /**
     * Check if a message should be copied for broker review
     *
     * @param int $senderId Sender user ID
     * @param int $receiverId Receiver user ID
     * @param int|null $listingId Related listing ID (if any)
     * @return string|null Copy reason or null if no copy needed
     */
    public static function shouldCopyMessage(int $senderId, int $receiverId, ?int $listingId = null): ?string
    {
        // Check if broker visibility is enabled
        if (!BrokerControlConfigService::isBrokerVisibilityEnabled()) {
            return null;
        }

        // Check if sender is under monitoring
        if (self::isUserUnderMonitoring($senderId)) {
            return self::REASON_FLAGGED_USER;
        }

        // Check first contact
        if (BrokerControlConfigService::isFirstContactMonitoringEnabled()) {
            if (self::isFirstContact($senderId, $receiverId)) {
                return self::REASON_FIRST_CONTACT;
            }
        }

        // Check if sender is a new member
        if (BrokerControlConfigService::shouldCopyNewMemberMessages()) {
            $monitoringDays = BrokerControlConfigService::getNewMemberMonitoringDays();
            if ($monitoringDays > 0 && self::isNewMember($senderId, $monitoringDays)) {
                return self::REASON_NEW_MEMBER;
            }
        }

        // Check if listing is high risk
        if ($listingId && BrokerControlConfigService::shouldCopyHighRiskMessages()) {
            if (ListingRiskTagService::isHighRisk($listingId)) {
                return self::REASON_HIGH_RISK_LISTING;
            }
        }

        // Check random sampling
        $sampleRate = BrokerControlConfigService::getRandomSamplePercentage();
        if ($sampleRate > 0 && rand(1, 100) <= $sampleRate) {
            return self::REASON_MONITORING;
        }

        return null;
    }

    /**
     * Copy a message for broker review
     *
     * @param int $messageId Original message ID
     * @param string $reason Copy reason
     * @return int|null Copy ID or null on failure
     */
    public static function copyMessageForBroker(int $messageId, string $reason): ?int
    {
        $tenantId = TenantContext::getId();

        // Get message details
        $stmt = Database::query(
            "SELECT id, sender_id, receiver_id, body, listing_id, created_at
             FROM messages
             WHERE id = ? AND tenant_id = ?",
            [$messageId, $tenantId]
        );
        $message = $stmt->fetch();

        if (!$message) {
            return null;
        }

        // Check if already copied
        $existingStmt = Database::query(
            "SELECT id FROM broker_message_copies WHERE original_message_id = ?",
            [$messageId]
        );
        if ($existingStmt->fetch()) {
            return null; // Already copied
        }

        // Generate conversation_key as hash of sorted sender+receiver IDs
        $ids = [(int)$message['sender_id'], (int)$message['receiver_id']];
        sort($ids);
        $conversationKey = md5(implode('-', $ids));

        Database::query(
            "INSERT INTO broker_message_copies
             (tenant_id, original_message_id, conversation_key, sender_id, receiver_id, message_body, sent_at, copy_reason, related_listing_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $message['id'],
                $conversationKey,
                $message['sender_id'],
                $message['receiver_id'],
                $message['body'],
                $message['created_at'],
                $reason,
                $message['listing_id'] ?? null,
            ]
        );

        $copyId = Database::lastInsertId();

        // Notify all tenant admins about the new broker message copy
        try {
            $senderStmt = Database::query(
                "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name
                 FROM users WHERE id = ? AND tenant_id = ?",
                [$message['sender_id'], $tenantId]
            );
            $senderRow = $senderStmt->fetch();
            $senderDisplayName = trim($senderRow['name'] ?? '') ?: 'A user';

            $notifMessage = "New message for review from {$senderDisplayName}";
            $notifLink = '/admin/broker-controls/messages';

            $adminIds = self::getTenantBrokerAdminIds();
            foreach ($adminIds as $adminId) {
                // Don't notify the sender if they happen to be an admin
                if ($adminId === (int) $message['sender_id']) {
                    continue;
                }
                Notification::create($adminId, $notifMessage, $notifLink, 'broker_review', true);
            }
        } catch (\Throwable $e) {
            error_log("[BrokerMessageVisibilityService] Admin notification error: " . $e->getMessage());
        }

        // Record first contact if applicable
        if ($reason === self::REASON_FIRST_CONTACT) {
            self::recordFirstContact($message['sender_id'], $message['receiver_id'], $messageId);
        }

        return $copyId;
    }

    /**
     * Get unreviewed messages for broker
     *
     * @param int $limit Max number of messages
     * @param int $offset Offset for pagination
     * @return array Messages
     */
    public static function getUnreviewedMessages(int $limit = 50, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT mc.*,
                    sender.name as sender_name, sender.avatar_url as sender_avatar,
                    receiver.name as receiver_name, receiver.avatar_url as receiver_avatar
             FROM broker_message_copies mc
             JOIN users sender ON mc.sender_id = sender.id
             JOIN users receiver ON mc.receiver_id = receiver.id
             WHERE mc.tenant_id = ? AND mc.reviewed_at IS NULL
             ORDER BY mc.flagged DESC, mc.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get messages by filter
     *
     * @param string $filter Filter type (unreviewed, flagged, reviewed, all)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getMessages(string $filter = 'unreviewed', int $page = 1, int $perPage = 50): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $whereClause = "mc.tenant_id = ?";
        $params = [$tenantId];

        switch ($filter) {
            case 'unreviewed':
                $whereClause .= " AND mc.reviewed_at IS NULL";
                break;
            case 'flagged':
                $whereClause .= " AND mc.flagged = 1";
                break;
            case 'reviewed':
                $whereClause .= " AND mc.reviewed_at IS NOT NULL";
                break;
            // 'all' - no additional filter
        }

        // Get total
        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM broker_message_copies mc WHERE $whereClause",
            $params
        );
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        // Get items
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::query(
            "SELECT mc.*,
                    sender.name as sender_name, sender.avatar_url as sender_avatar,
                    receiver.name as receiver_name, receiver.avatar_url as receiver_avatar,
                    reviewer.name as reviewer_name
             FROM broker_message_copies mc
             JOIN users sender ON mc.sender_id = sender.id
             JOIN users receiver ON mc.receiver_id = receiver.id
             LEFT JOIN users reviewer ON mc.reviewed_by = reviewer.id
             WHERE $whereClause
             ORDER BY mc.flagged DESC, mc.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Mark a message copy as reviewed
     *
     * @param int $copyId Message copy ID
     * @param int $brokerId Reviewer user ID
     * @return bool Success
     */
    public static function markAsReviewed(int $copyId, int $brokerId): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE broker_message_copies
             SET reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$brokerId, $copyId, $tenantId]
        );

        return $result !== false;
    }

    /**
     * Flag a message for concern
     *
     * @param int $copyId Message copy ID
     * @param int $brokerId Broker flagging the message
     * @param string $reason Flag reason
     * @return bool Success
     */
    public static function flagMessage(int $copyId, int $brokerId, string $reason = ''): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE broker_message_copies
             SET flagged = 1, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$brokerId, $copyId, $tenantId]
        );

        if ($result) {
            // Log the flag action
            AuditLogService::log('message_flagged', null, $brokerId, [
                'copy_id' => $copyId,
                'reason' => $reason,
            ]);
        }

        return $result !== false;
    }

    /**
     * Check if this is first contact between two users
     *
     * @param int $senderId Sender user ID
     * @param int $receiverId Receiver user ID
     * @return bool True if first contact
     */
    public static function isFirstContact(int $senderId, int $receiverId): bool
    {
        $tenantId = TenantContext::getId();

        // Check if there's a record of previous contact
        $stmt = Database::query(
            "SELECT id FROM user_first_contacts
             WHERE tenant_id = ?
             AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))",
            [$tenantId, $senderId, $receiverId, $receiverId, $senderId]
        );

        return $stmt->fetch() === false;
    }

    /**
     * Record first contact between two users
     *
     * @param int $userA First user ID
     * @param int $userB Second user ID
     * @param int $messageId Message that initiated contact
     */
    public static function recordFirstContact(int $userA, int $userB, int $messageId): void
    {
        $tenantId = TenantContext::getId();

        // Ensure consistent ordering (lower ID first)
        if ($userA > $userB) {
            [$userA, $userB] = [$userB, $userA];
        }

        Database::query(
            "INSERT IGNORE INTO user_first_contacts
             (tenant_id, user1_id, user2_id, first_message_id, first_contact_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$tenantId, $userA, $userB, $messageId]
        );
    }

    /**
     * Check if a user is a new member
     *
     * @param int $userId User ID
     * @param int $days Days threshold
     * @return bool True if joined within threshold
     */
    public static function isNewMember(int $userId, int $days = 30): bool
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT created_at FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $joinedAt = strtotime($user['created_at']);
        $threshold = strtotime("-$days days");

        return $joinedAt >= $threshold;
    }

    /**
     * Check if a user is under monitoring
     *
     * @param int $userId User ID
     * @return bool True if under monitoring
     */
    public static function isUserUnderMonitoring(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT under_monitoring, monitoring_expires_at FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $restriction = $stmt->fetch();

        if (($restriction['under_monitoring'] ?? 0) != 1) {
            return false;
        }

        // Check expiry — if set and past, auto-clear monitoring
        $expiresAt = $restriction['monitoring_expires_at'] ?? null;
        if ($expiresAt && strtotime($expiresAt) <= time()) {
            self::clearExpiredMonitoring($userId, $tenantId);
            return false;
        }

        return true;
    }

    /**
     * Clear monitoring for a user whose monitoring_expires_at has passed.
     * Also clears messaging_disabled to fully unblock the user.
     */
    private static function clearExpiredMonitoring(int $userId, int $tenantId): void
    {
        try {
            Database::query(
                "UPDATE user_messaging_restrictions
                 SET under_monitoring = 0, messaging_disabled = 0,
                     monitoring_reason = CONCAT(COALESCE(monitoring_reason, ''), ' [auto-expired]'),
                     monitoring_expires_at = NULL
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            AuditLogService::log('user_monitoring_expired', null, null, [
                'user_id' => $userId,
                'reason' => 'Monitoring period expired automatically',
            ]);
        } catch (\Throwable $e) {
            error_log("[BrokerMessageVisibilityService] Failed to clear expired monitoring: " . $e->getMessage());
        }
    }

    /**
     * Check if user has messaging disabled.
     * Also checks monitoring_expires_at — if monitoring has expired,
     * auto-clears both monitoring and messaging_disabled.
     *
     * @param int $userId User ID
     * @return bool True if messaging disabled
     */
    public static function isMessagingDisabledForUser(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT messaging_disabled, under_monitoring, monitoring_expires_at
             FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $restriction = $stmt->fetch();

        if (!$restriction || ($restriction['messaging_disabled'] ?? 0) != 1) {
            return false;
        }

        // If monitoring has expired, auto-clear everything including messaging_disabled
        $underMonitoring = ($restriction['under_monitoring'] ?? 0) == 1;
        $expiresAt = $restriction['monitoring_expires_at'] ?? null;
        if ($underMonitoring && $expiresAt && strtotime($expiresAt) <= time()) {
            self::clearExpiredMonitoring($userId, $tenantId);
            return false;
        }

        return true;
    }

    /**
     * Get monitoring statistics
     *
     * @return array Statistics
     */
    public static function getStatistics(): array
    {
        $tenantId = TenantContext::getId();
        $monitoringDays = BrokerControlConfigService::getNewMemberMonitoringDays();

        $stmt = Database::query(
            "SELECT
                (SELECT COUNT(*) FROM user_messaging_restrictions WHERE tenant_id = ? AND messaging_disabled = 1) as restricted,
                (SELECT COUNT(*) FROM user_messaging_restrictions WHERE tenant_id = ? AND under_monitoring = 1 AND (monitoring_expires_at IS NULL OR monitoring_expires_at > NOW())) as monitored,
                (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) as new_members,
                (SELECT COUNT(*) FROM user_first_contacts WHERE tenant_id = ? AND first_contact_at >= CURDATE()) as first_contacts_today,
                (SELECT COUNT(*) FROM broker_message_copies WHERE tenant_id = ? AND reviewed_at IS NULL) as unreviewed_messages,
                (SELECT COUNT(*) FROM broker_message_copies WHERE tenant_id = ? AND flagged = 1) as flagged_messages",
            [$tenantId, $tenantId, $tenantId, $monitoringDays, $tenantId, $tenantId, $tenantId]
        );

        return $stmt->fetch() ?: [
            'restricted' => 0,
            'monitored' => 0,
            'new_members' => 0,
            'first_contacts_today' => 0,
            'unreviewed_messages' => 0,
            'flagged_messages' => 0,
        ];
    }

    /**
     * Count unreviewed messages
     *
     * @return int Count
     */
    public static function countUnreviewed(): int
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT COUNT(*) as cnt FROM broker_message_copies WHERE tenant_id = ? AND reviewed_at IS NULL",
            [$tenantId]
        );

        return (int) ($stmt->fetch()['cnt'] ?? 0);
    }

    /**
     * Clean up old message copies based on retention policy
     *
     * @return int Number of deleted records
     */
    public static function cleanupOldCopies(): int
    {
        $retentionDays = BrokerControlConfigService::getRetentionDays();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));

        $result = Database::query(
            "DELETE FROM broker_message_copies
             WHERE created_at < ? AND reviewed_at IS NOT NULL AND flagged = 0",
            [$cutoffDate]
        );

        // Return affected rows count
        return $result ? $result->rowCount() : 0;
    }

    /**
     * Get user IDs of all broker admins for the current tenant
     *
     * @return int[] Admin user IDs
     */
    public static function getTenantBrokerAdminIds(): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT id FROM users
             WHERE tenant_id = ?
             AND role IN ('admin', 'tenant_admin', 'super_admin')
             AND status = 'active'",
            [$tenantId]
        );

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($row) => (int) $row['id'], $rows ?: []);
    }

    /**
     * Batch-expire all monitoring records that have passed their monitoring_expires_at.
     * Designed to be called from cron. Notifies tenant admins about each expiry.
     *
     * @return int Number of records expired
     */
    public static function expireMonitoringBatch(): int
    {
        $stmt = Database::query(
            "SELECT umr.user_id, umr.tenant_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
             FROM user_messaging_restrictions umr
             JOIN users u ON u.id = umr.user_id AND u.tenant_id = umr.tenant_id
             WHERE umr.under_monitoring = 1
             AND umr.monitoring_expires_at IS NOT NULL
             AND umr.monitoring_expires_at <= NOW()"
        );
        $expired = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($expired)) {
            return 0;
        }

        // Batch update all expired records — clear monitoring AND messaging_disabled
        Database::query(
            "UPDATE user_messaging_restrictions
             SET under_monitoring = 0, messaging_disabled = 0,
                 monitoring_reason = CONCAT(COALESCE(monitoring_reason, ''), ' [auto-expired]'),
                 monitoring_expires_at = NULL
             WHERE under_monitoring = 1
             AND monitoring_expires_at IS NOT NULL
             AND monitoring_expires_at <= NOW()"
        );

        // Notify admins and affected users per tenant
        $byTenant = [];
        foreach ($expired as $row) {
            $byTenant[(int) $row['tenant_id']][] = $row;
        }

        foreach ($byTenant as $tenantId => $users) {
            try {
                TenantContext::setById($tenantId);
                $adminIds = self::getTenantBrokerAdminIds();
                foreach ($users as $row) {
                    $userId = (int) $row['user_id'];
                    $userName = trim($row['user_name']) ?: 'User #' . $userId;

                    // Notify admins
                    foreach ($adminIds as $adminId) {
                        Notification::create(
                            $adminId,
                            "Monitoring expired for {$userName}",
                            '/admin/broker-controls/monitoring',
                            'broker_review',
                            false // No push for batch expiry — in-app only
                        );
                    }

                    // Notify the affected user that restrictions have been lifted
                    Notification::create(
                        $userId,
                        'Your messaging restrictions have been lifted.',
                        '/messages',
                        'system',
                        true
                    );
                }
            } catch (\Throwable $e) {
                error_log("[BrokerMessageVisibilityService] Expiry notification error (tenant {$tenantId}): " . $e->getMessage());
            }
        }

        return count($expired);
    }

    /**
     * Get the messaging restriction status for a specific user.
     * Used by user-facing API to show restriction warnings.
     * Checks monitoring_expires_at and auto-clears expired monitoring inline.
     *
     * @param int $userId User ID
     * @return array{messaging_disabled: bool, under_monitoring: bool, restriction_reason: string|null}
     */
    public static function getUserRestrictionStatus(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT messaging_disabled, under_monitoring, monitoring_reason, restriction_reason, monitoring_expires_at
             FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'messaging_disabled' => false,
                'under_monitoring' => false,
                'restriction_reason' => null,
            ];
        }

        $underMonitoring = (bool) ($row['under_monitoring'] ?? 0);
        $messagingDisabled = (bool) ($row['messaging_disabled'] ?? 0);

        // Auto-clear if monitoring has expired
        $expiresAt = $row['monitoring_expires_at'] ?? null;
        if ($underMonitoring && $expiresAt && strtotime($expiresAt) <= time()) {
            self::clearExpiredMonitoring($userId, $tenantId);
            $underMonitoring = false;
            $messagingDisabled = false;
        }

        return [
            'messaging_disabled' => $messagingDisabled,
            'under_monitoring' => $underMonitoring,
            'restriction_reason' => $row['monitoring_reason'] ?? $row['restriction_reason'] ?? null,
        ];
    }
}
