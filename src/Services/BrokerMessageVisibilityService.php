<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

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
    public const REASON_MONITORING = 'monitoring';

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
            "SELECT id, sender_id, receiver_id, body, created_at
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

        Database::query(
            "INSERT INTO broker_message_copies
             (tenant_id, original_message_id, sender_id, receiver_id, message_body, sent_at, copy_reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $tenantId,
                $message['id'],
                $message['sender_id'],
                $message['receiver_id'],
                $message['body'],
                $message['created_at'],
                $reason,
            ]
        );

        $copyId = Database::lastInsertId();

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
            AuditLogService::log('message_flagged', [
                'copy_id' => $copyId,
                'flagged_by' => $brokerId,
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
             AND ((user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?))",
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
             (tenant_id, user_a, user_b, first_message_id, created_at)
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
        $stmt = Database::query(
            "SELECT created_at FROM users WHERE id = ?",
            [$userId]
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
            "SELECT under_monitoring FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $restriction = $stmt->fetch();

        return ($restriction['under_monitoring'] ?? 0) == 1;
    }

    /**
     * Check if user has messaging disabled
     *
     * @param int $userId User ID
     * @return bool True if messaging disabled
     */
    public static function isMessagingDisabledForUser(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT messaging_disabled FROM user_messaging_restrictions
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $restriction = $stmt->fetch();

        return ($restriction['messaging_disabled'] ?? 0) == 1;
    }

    /**
     * Set user monitoring status
     *
     * @param int $userId User ID
     * @param bool $messagingDisabled Disable messaging
     * @param bool $underMonitoring Enable monitoring
     * @param string|null $reason Reason for restriction
     * @param int|null $setBy Admin who set the restriction
     * @return bool Success
     */
    public static function setUserMonitoring(
        int $userId,
        bool $messagingDisabled,
        bool $underMonitoring,
        ?string $reason = null,
        ?int $setBy = null
    ): bool {
        $tenantId = TenantContext::getId();

        // Check if record exists
        $stmt = Database::query(
            "SELECT id FROM user_messaging_restrictions WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $existing = $stmt->fetch();

        if ($existing) {
            $result = Database::query(
                "UPDATE user_messaging_restrictions
                 SET messaging_disabled = ?, under_monitoring = ?, restriction_reason = ?, set_by = ?, updated_at = NOW()
                 WHERE user_id = ? AND tenant_id = ?",
                [
                    $messagingDisabled ? 1 : 0,
                    $underMonitoring ? 1 : 0,
                    $reason,
                    $setBy,
                    $userId,
                    $tenantId,
                ]
            );
        } else {
            $result = Database::query(
                "INSERT INTO user_messaging_restrictions
                 (tenant_id, user_id, messaging_disabled, under_monitoring, restriction_reason, set_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId,
                    $userId,
                    $messagingDisabled ? 1 : 0,
                    $underMonitoring ? 1 : 0,
                    $reason,
                    $setBy,
                ]
            );
        }

        if ($result) {
            AuditLogService::log('user_monitoring_updated', [
                'user_id' => $userId,
                'messaging_disabled' => $messagingDisabled,
                'under_monitoring' => $underMonitoring,
                'reason' => $reason,
                'set_by' => $setBy,
            ]);
        }

        return $result !== false;
    }

    /**
     * Get users with monitoring/restrictions
     *
     * @param string $filter Filter type (all, restricted, monitored, new_members)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['items' => [...], 'total' => int, 'pages' => int]
     */
    public static function getMonitoredUsers(string $filter = 'all', int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $offset = ($page - 1) * $perPage;

        $baseQuery = "
            SELECT u.id, u.name, u.email, u.avatar_url as avatar, u.created_at,
                   COALESCE(umr.messaging_disabled, 0) as messaging_disabled,
                   COALESCE(umr.under_monitoring, 0) as under_monitoring,
                   umr.restriction_reason,
                   (SELECT COUNT(*) FROM user_first_contacts ufc WHERE ufc.user_a = u.id OR ufc.user_b = u.id) as first_contact_count
            FROM users u
            LEFT JOIN user_messaging_restrictions umr ON u.id = umr.user_id AND umr.tenant_id = u.tenant_id
            WHERE u.tenant_id = ?
        ";

        $countQuery = "SELECT COUNT(*) as total FROM users u
                       LEFT JOIN user_messaging_restrictions umr ON u.id = umr.user_id AND umr.tenant_id = u.tenant_id
                       WHERE u.tenant_id = ?";

        $params = [$tenantId];

        switch ($filter) {
            case 'restricted':
                $baseQuery .= " AND umr.messaging_disabled = 1";
                $countQuery .= " AND umr.messaging_disabled = 1";
                break;
            case 'monitored':
                $baseQuery .= " AND umr.under_monitoring = 1";
                $countQuery .= " AND umr.under_monitoring = 1";
                break;
            case 'new_members':
                $days = BrokerControlConfigService::getNewMemberMonitoringDays();
                $baseQuery .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
                $countQuery .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
                break;
        }

        // Get total
        $countStmt = Database::query($countQuery, $params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        // Get items
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::query($baseQuery . " ORDER BY u.created_at DESC LIMIT ? OFFSET ?", $params);

        return [
            'items' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'pages' => ceil($total / $perPage),
        ];
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
                (SELECT COUNT(*) FROM user_messaging_restrictions WHERE tenant_id = ? AND under_monitoring = 1) as monitored,
                (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) as new_members,
                (SELECT COUNT(*) FROM user_first_contacts WHERE tenant_id = ? AND created_at >= CURDATE()) as first_contacts_today,
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
}
