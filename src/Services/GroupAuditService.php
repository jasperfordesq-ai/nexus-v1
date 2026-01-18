<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupAuditService
 *
 * Comprehensive audit logging for groups module.
 * Tracks all group-related actions for compliance, security, and analytics.
 */
class GroupAuditService
{
    // Group action types
    const ACTION_GROUP_CREATED = 'group_created';
    const ACTION_GROUP_UPDATED = 'group_updated';
    const ACTION_GROUP_DELETED = 'group_deleted';
    const ACTION_GROUP_FEATURED = 'group_featured';
    const ACTION_GROUP_UNFEATURED = 'group_unfeatured';
    const ACTION_GROUP_APPROVED = 'group_approved';
    const ACTION_GROUP_REJECTED = 'group_rejected';
    const ACTION_GROUP_ARCHIVED = 'group_archived';
    const ACTION_GROUP_RESTORED = 'group_restored';

    // Member action types
    const ACTION_MEMBER_JOINED = 'member_joined';
    const ACTION_MEMBER_LEFT = 'member_left';
    const ACTION_MEMBER_INVITED = 'member_invited';
    const ACTION_MEMBER_APPROVED = 'member_approved';
    const ACTION_MEMBER_REJECTED = 'member_rejected';
    const ACTION_MEMBER_KICKED = 'member_kicked';
    const ACTION_MEMBER_BANNED = 'member_banned';
    const ACTION_MEMBER_UNBANNED = 'member_unbanned';
    const ACTION_MEMBER_PROMOTED = 'member_promoted';
    const ACTION_MEMBER_DEMOTED = 'member_demoted';

    // Content action types
    const ACTION_DISCUSSION_CREATED = 'discussion_created';
    const ACTION_DISCUSSION_UPDATED = 'discussion_updated';
    const ACTION_DISCUSSION_DELETED = 'discussion_deleted';
    const ACTION_DISCUSSION_PINNED = 'discussion_pinned';
    const ACTION_DISCUSSION_UNPINNED = 'discussion_unpinned';
    const ACTION_POST_CREATED = 'post_created';
    const ACTION_POST_UPDATED = 'post_updated';
    const ACTION_POST_DELETED = 'post_deleted';
    const ACTION_POST_MODERATED = 'post_moderated';

    // Settings action types
    const ACTION_SETTINGS_CHANGED = 'settings_changed';
    const ACTION_PERMISSIONS_CHANGED = 'permissions_changed';
    const ACTION_TYPE_CHANGED = 'type_changed';
    const ACTION_IMAGE_UPDATED = 'image_updated';
    const ACTION_COVER_UPDATED = 'cover_updated';

    // Feedback action types
    const ACTION_FEEDBACK_SUBMITTED = 'feedback_submitted';
    const ACTION_FEEDBACK_UPDATED = 'feedback_updated';
    const ACTION_FEEDBACK_DELETED = 'feedback_deleted';

    /**
     * Log an action
     *
     * @param string $action Action type constant
     * @param int|null $groupId Group ID
     * @param int|null $userId User performing action
     * @param array $details Additional context
     * @param int|null $targetUserId Target user (for member actions)
     * @return int|null Log entry ID
     */
    public static function log($action, $groupId = null, $userId = null, $details = [], $targetUserId = null)
    {
        $tenantId = TenantContext::getId();
        self::ensureTableExists();

        $ipAddress = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $detailsJson = !empty($details) ? json_encode($details) : null;

        try {
            Database::query(
                "INSERT INTO group_audit_log
                 (tenant_id, group_id, user_id, target_user_id, action, details, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $groupId, $userId, $targetUserId, $action, $detailsJson, $ipAddress, $userAgent]
            );

            return Database::lastInsertId();
        } catch (\Exception $e) {
            error_log("GroupAuditService: Failed to log action - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Log group creation
     */
    public static function logGroupCreated($groupId, $userId, $groupData = [])
    {
        return self::log(self::ACTION_GROUP_CREATED, $groupId, $userId, [
            'group_name' => $groupData['name'] ?? null,
            'group_type' => $groupData['type_id'] ?? null,
            'visibility' => $groupData['visibility'] ?? null,
        ]);
    }

    /**
     * Log group update
     */
    public static function logGroupUpdated($groupId, $userId, $changes = [])
    {
        return self::log(self::ACTION_GROUP_UPDATED, $groupId, $userId, [
            'changes' => $changes,
        ]);
    }

    /**
     * Log group deletion
     */
    public static function logGroupDeleted($groupId, $userId, $reason = '')
    {
        return self::log(self::ACTION_GROUP_DELETED, $groupId, $userId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log group featured/unfeatured
     */
    public static function logGroupFeatured($groupId, $userId, $featured = true)
    {
        $action = $featured ? self::ACTION_GROUP_FEATURED : self::ACTION_GROUP_UNFEATURED;
        return self::log($action, $groupId, $userId);
    }

    /**
     * Log group approved
     */
    public static function logGroupApproved($groupId, $userId)
    {
        return self::log(self::ACTION_GROUP_APPROVED, $groupId, $userId);
    }

    /**
     * Log group rejected
     */
    public static function logGroupRejected($groupId, $userId, $reason = '')
    {
        return self::log(self::ACTION_GROUP_REJECTED, $groupId, $userId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log member joined
     */
    public static function logMemberJoined($groupId, $userId, $method = 'request')
    {
        return self::log(self::ACTION_MEMBER_JOINED, $groupId, $userId, [
            'join_method' => $method, // request, invite, auto
        ]);
    }

    /**
     * Log member left
     */
    public static function logMemberLeft($groupId, $userId, $reason = '')
    {
        return self::log(self::ACTION_MEMBER_LEFT, $groupId, $userId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log member invited
     */
    public static function logMemberInvited($groupId, $inviterId, $invitedUserId)
    {
        return self::log(self::ACTION_MEMBER_INVITED, $groupId, $inviterId, [
            'invited_user_id' => $invitedUserId,
        ], $invitedUserId);
    }

    /**
     * Log member approved
     */
    public static function logMemberApproved($groupId, $approverId, $memberId)
    {
        return self::log(self::ACTION_MEMBER_APPROVED, $groupId, $approverId, [], $memberId);
    }

    /**
     * Log member rejected
     */
    public static function logMemberRejected($groupId, $approverId, $memberId, $reason = '')
    {
        return self::log(self::ACTION_MEMBER_REJECTED, $groupId, $approverId, [
            'reason' => $reason,
        ], $memberId);
    }

    /**
     * Log member kicked
     */
    public static function logMemberKicked($groupId, $kickedBy, $memberId, $reason = '')
    {
        return self::log(self::ACTION_MEMBER_KICKED, $groupId, $kickedBy, [
            'reason' => $reason,
        ], $memberId);
    }

    /**
     * Log member banned
     */
    public static function logMemberBanned($groupId, $bannedBy, $memberId, $reason = '', $duration = null)
    {
        return self::log(self::ACTION_MEMBER_BANNED, $groupId, $bannedBy, [
            'reason' => $reason,
            'duration_days' => $duration,
        ], $memberId);
    }

    /**
     * Log member unbanned
     */
    public static function logMemberUnbanned($groupId, $unbannedBy, $memberId)
    {
        return self::log(self::ACTION_MEMBER_UNBANNED, $groupId, $unbannedBy, [], $memberId);
    }

    /**
     * Log member role change
     */
    public static function logMemberRoleChanged($groupId, $changedBy, $memberId, $oldRole, $newRole)
    {
        $action = ($oldRole === 'member' && $newRole === 'admin') ? self::ACTION_MEMBER_PROMOTED : self::ACTION_MEMBER_DEMOTED;

        return self::log($action, $groupId, $changedBy, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ], $memberId);
    }

    /**
     * Log discussion created
     */
    public static function logDiscussionCreated($groupId, $userId, $discussionId, $title)
    {
        return self::log(self::ACTION_DISCUSSION_CREATED, $groupId, $userId, [
            'discussion_id' => $discussionId,
            'title' => $title,
        ]);
    }

    /**
     * Log discussion deleted
     */
    public static function logDiscussionDeleted($groupId, $userId, $discussionId, $reason = '')
    {
        return self::log(self::ACTION_DISCUSSION_DELETED, $groupId, $userId, [
            'discussion_id' => $discussionId,
            'reason' => $reason,
        ]);
    }

    /**
     * Log discussion pinned
     */
    public static function logDiscussionPinned($groupId, $userId, $discussionId, $pinned = true)
    {
        $action = $pinned ? self::ACTION_DISCUSSION_PINNED : self::ACTION_DISCUSSION_UNPINNED;
        return self::log($action, $groupId, $userId, [
            'discussion_id' => $discussionId,
        ]);
    }

    /**
     * Log post created
     */
    public static function logPostCreated($groupId, $userId, $postId, $discussionId)
    {
        return self::log(self::ACTION_POST_CREATED, $groupId, $userId, [
            'post_id' => $postId,
            'discussion_id' => $discussionId,
        ]);
    }

    /**
     * Log post deleted
     */
    public static function logPostDeleted($groupId, $userId, $postId, $reason = '')
    {
        return self::log(self::ACTION_POST_DELETED, $groupId, $userId, [
            'post_id' => $postId,
            'reason' => $reason,
        ]);
    }

    /**
     * Log post moderated
     */
    public static function logPostModerated($groupId, $moderatorId, $postId, $action, $reason = '')
    {
        return self::log(self::ACTION_POST_MODERATED, $groupId, $moderatorId, [
            'post_id' => $postId,
            'moderation_action' => $action, // hidden, flagged, approved
            'reason' => $reason,
        ]);
    }

    /**
     * Log settings changed
     */
    public static function logSettingsChanged($groupId, $userId, $changes = [])
    {
        return self::log(self::ACTION_SETTINGS_CHANGED, $groupId, $userId, [
            'changes' => $changes,
        ]);
    }

    /**
     * Log feedback submitted
     */
    public static function logFeedbackSubmitted($groupId, $userId, $rating, $comment = '')
    {
        return self::log(self::ACTION_FEEDBACK_SUBMITTED, $groupId, $userId, [
            'rating' => $rating,
            'has_comment' => !empty($comment),
        ]);
    }

    /**
     * Get audit log for a group
     *
     * @param int $groupId Group ID
     * @param array $filters Optional filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Audit logs
     */
    public static function getGroupLog($groupId, $filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT al.*,
                       u.first_name, u.last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       u.profile_image_url,
                       tu.first_name as target_first_name, tu.last_name as target_last_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as target_user_name
                FROM group_audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN users tu ON al.target_user_id = tu.id
                WHERE al.tenant_id = ? AND al.group_id = ?";

        $params = [$tenantId, $groupId];

        // Apply filters
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['userId'])) {
            $sql .= " AND (al.user_id = ? OR al.target_user_id = ?)";
            $params[] = $filters['userId'];
            $params[] = $filters['userId'];
        }

        if (!empty($filters['startDate'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['startDate'] . ' 00:00:00';
        }

        if (!empty($filters['endDate'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['endDate'] . ' 23:59:59';
        }

        if (!empty($filters['actionCategory'])) {
            $sql .= " AND al.action LIKE ?";
            $params[] = $filters['actionCategory'] . '%';
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        try {
            $logs = Database::query($sql, $params)->fetchAll();

            foreach ($logs as &$log) {
                $log['details'] = $log['details'] ? json_decode($log['details'], true) : [];
                $log['action_label'] = self::getActionLabel($log['action']);
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get audit log count for group
     */
    public static function getGroupLogCount($groupId, $filters = [])
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT COUNT(*) FROM group_audit_log WHERE tenant_id = ? AND group_id = ?";
        $params = [$tenantId, $groupId];

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['userId'])) {
            $sql .= " AND (user_id = ? OR target_user_id = ?)";
            $params[] = $filters['userId'];
            $params[] = $filters['userId'];
        }

        try {
            return (int) Database::query($sql, $params)->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent activity across all groups
     */
    public static function getRecentActivity($filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT al.*,
                       g.name as group_name,
                       u.first_name, u.last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM group_audit_log al
                LEFT JOIN groups g ON al.group_id = g.id
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.tenant_id = ?";

        $params = [$tenantId];

        if (!empty($filters['groupId'])) {
            $sql .= " AND al.group_id = ?";
            $params[] = $filters['groupId'];
        }

        if (!empty($filters['userId'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['userId'];
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        try {
            $logs = Database::query($sql, $params)->fetchAll();

            foreach ($logs as &$log) {
                $log['details'] = $log['details'] ? json_decode($log['details'], true) : [];
                $log['action_label'] = self::getActionLabel($log['action']);
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get activity summary for a group
     */
    public static function getActivitySummary($groupId, $days = 30)
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT action, COUNT(*) as count
                 FROM group_audit_log
                 WHERE tenant_id = ? AND group_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY action
                 ORDER BY count DESC",
                [$tenantId, $groupId, $days]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user activity in a group
     */
    public static function getUserGroupActivity($groupId, $userId, $limit = 20)
    {
        $tenantId = TenantContext::getId();

        try {
            $logs = Database::query(
                "SELECT * FROM group_audit_log
                 WHERE tenant_id = ? AND group_id = ?
                 AND (user_id = ? OR target_user_id = ?)
                 ORDER BY created_at DESC
                 LIMIT ?",
                [$tenantId, $groupId, $userId, $userId, $limit]
            )->fetchAll();

            foreach ($logs as &$log) {
                $log['details'] = $log['details'] ? json_decode($log['details'], true) : [];
                $log['action_label'] = self::getActionLabel($log['action']);
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Export audit log to CSV
     */
    public static function exportToCSV($groupId, $filters = [])
    {
        $logs = self::getGroupLog($groupId, $filters, 10000, 0);

        if (empty($logs)) {
            return null;
        }

        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        // Headers
        fputcsv($output, ['Date', 'Action', 'User', 'Target User', 'Details', 'IP Address']);

        foreach ($logs as $log) {
            $detailsStr = '';
            if (!empty($log['details'])) {
                $parts = [];
                foreach ($log['details'] as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $parts[] = "$key: $value";
                }
                $detailsStr = implode('; ', $parts);
            }

            fputcsv($output, [
                $log['created_at'],
                $log['action_label'],
                $log['user_name'] ?? 'System',
                $log['target_user_name'] ?? '-',
                $detailsStr,
                $log['ip_address'] ?? '-'
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get human-readable action label
     */
    public static function getActionLabel($action)
    {
        $labels = [
            self::ACTION_GROUP_CREATED => 'Group Created',
            self::ACTION_GROUP_UPDATED => 'Group Updated',
            self::ACTION_GROUP_DELETED => 'Group Deleted',
            self::ACTION_GROUP_FEATURED => 'Group Featured',
            self::ACTION_GROUP_UNFEATURED => 'Group Unfeatured',
            self::ACTION_GROUP_APPROVED => 'Group Approved',
            self::ACTION_GROUP_REJECTED => 'Group Rejected',
            self::ACTION_GROUP_ARCHIVED => 'Group Archived',
            self::ACTION_GROUP_RESTORED => 'Group Restored',
            self::ACTION_MEMBER_JOINED => 'Member Joined',
            self::ACTION_MEMBER_LEFT => 'Member Left',
            self::ACTION_MEMBER_INVITED => 'Member Invited',
            self::ACTION_MEMBER_APPROVED => 'Member Approved',
            self::ACTION_MEMBER_REJECTED => 'Member Rejected',
            self::ACTION_MEMBER_KICKED => 'Member Kicked',
            self::ACTION_MEMBER_BANNED => 'Member Banned',
            self::ACTION_MEMBER_UNBANNED => 'Member Unbanned',
            self::ACTION_MEMBER_PROMOTED => 'Member Promoted',
            self::ACTION_MEMBER_DEMOTED => 'Member Demoted',
            self::ACTION_DISCUSSION_CREATED => 'Discussion Created',
            self::ACTION_DISCUSSION_UPDATED => 'Discussion Updated',
            self::ACTION_DISCUSSION_DELETED => 'Discussion Deleted',
            self::ACTION_DISCUSSION_PINNED => 'Discussion Pinned',
            self::ACTION_DISCUSSION_UNPINNED => 'Discussion Unpinned',
            self::ACTION_POST_CREATED => 'Post Created',
            self::ACTION_POST_UPDATED => 'Post Updated',
            self::ACTION_POST_DELETED => 'Post Deleted',
            self::ACTION_POST_MODERATED => 'Post Moderated',
            self::ACTION_SETTINGS_CHANGED => 'Settings Changed',
            self::ACTION_PERMISSIONS_CHANGED => 'Permissions Changed',
            self::ACTION_TYPE_CHANGED => 'Type Changed',
            self::ACTION_IMAGE_UPDATED => 'Image Updated',
            self::ACTION_COVER_UPDATED => 'Cover Updated',
            self::ACTION_FEEDBACK_SUBMITTED => 'Feedback Submitted',
            self::ACTION_FEEDBACK_UPDATED => 'Feedback Updated',
            self::ACTION_FEEDBACK_DELETED => 'Feedback Deleted',
        ];

        return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get client IP address
     */
    private static function getClientIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Ensure audit log table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_audit_log LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    group_id INT NULL,
                    user_id INT NULL,
                    target_user_id INT NULL,
                    action VARCHAR(50) NOT NULL,
                    details JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_group_audit (tenant_id, group_id, created_at),
                    INDEX idx_user_audit (tenant_id, user_id, created_at),
                    INDEX idx_action (tenant_id, action, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Clean up old audit logs
     *
     * @param int $daysToKeep Days of logs to retain
     * @return int Number of deleted entries
     */
    public static function cleanup($daysToKeep = 365)
    {
        try {
            $stmt = Database::query(
                "DELETE FROM group_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("GroupAuditService: Cleanup failed - " . $e->getMessage());
            return 0;
        }
    }
}
