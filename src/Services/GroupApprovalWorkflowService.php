<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\NotificationDispatcher;

/**
 * GroupApprovalWorkflowService
 *
 * Manages group creation approval workflows.
 * Allows admins to review and approve/reject new groups before they go live.
 */
class GroupApprovalWorkflowService
{
    // Approval statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CHANGES_REQUESTED = 'changes_requested';

    /**
     * Submit a group for approval
     *
     * @param int $groupId Group ID
     * @param int $submittedBy User ID
     * @param string $notes Submission notes
     * @return int|null Approval request ID
     */
    public static function submitForApproval($groupId, $submittedBy, $notes = '')
    {
        $tenantId = TenantContext::getId();
        self::ensureTableExists();

        try {
            // Check if approval already exists
            $existing = Database::query(
                "SELECT id FROM group_approval_requests
                 WHERE tenant_id = ? AND group_id = ? AND status = ?",
                [$tenantId, $groupId, self::STATUS_PENDING]
            )->fetch();

            if ($existing) {
                return $existing['id'];
            }

            // Create approval request
            Database::query(
                "INSERT INTO group_approval_requests
                 (tenant_id, group_id, submitted_by, submission_notes, status)
                 VALUES (?, ?, ?, ?, ?)",
                [$tenantId, $groupId, $submittedBy, $notes, self::STATUS_PENDING]
            );

            $requestId = Database::lastInsertId();

            // Set group status to pending
            Database::query(
                "UPDATE `groups` SET status = 'pending' WHERE id = ?",
                [$groupId]
            );

            // Notify admins
            self::notifyAdmins($requestId, $groupId);

            // Log audit
            GroupAuditService::log(
                'group_submitted_for_approval',
                $groupId,
                $submittedBy,
                ['notes' => $notes]
            );

            return $requestId;
        } catch (\Exception $e) {
            error_log("GroupApprovalWorkflowService: Failed to submit for approval - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Approve a group
     *
     * @param int $requestId Approval request ID
     * @param int $approvedBy User ID
     * @param string $notes Approval notes
     * @return bool Success
     */
    public static function approveGroup($requestId, $approvedBy, $notes = '')
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) {
                return false;
            }

            // Update approval request
            Database::query(
                "UPDATE group_approval_requests
                 SET status = ?,
                     reviewed_by = ?,
                     review_notes = ?,
                     reviewed_at = NOW()
                 WHERE id = ?",
                [self::STATUS_APPROVED, $approvedBy, $notes, $requestId]
            );

            // Set group status to active
            Database::query(
                "UPDATE `groups` SET status = 'active' WHERE id = ?",
                [$request['group_id']]
            );

            // Notify submitter
            self::notifySubmitter($request, self::STATUS_APPROVED, $notes);

            // Log audit
            GroupAuditService::logGroupApproved($request['group_id'], $approvedBy);

            return true;
        } catch (\Exception $e) {
            error_log("GroupApprovalWorkflowService: Failed to approve group - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject a group
     *
     * @param int $requestId Approval request ID
     * @param int $rejectedBy User ID
     * @param string $reason Rejection reason
     * @return bool Success
     */
    public static function rejectGroup($requestId, $rejectedBy, $reason = '')
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) {
                return false;
            }

            // Update approval request
            Database::query(
                "UPDATE group_approval_requests
                 SET status = ?,
                     reviewed_by = ?,
                     review_notes = ?,
                     reviewed_at = NOW()
                 WHERE id = ?",
                [self::STATUS_REJECTED, $rejectedBy, $reason, $requestId]
            );

            // Set group status to rejected
            Database::query(
                "UPDATE `groups` SET status = 'rejected' WHERE id = ?",
                [$request['group_id']]
            );

            // Notify submitter
            self::notifySubmitter($request, self::STATUS_REJECTED, $reason);

            // Log audit
            GroupAuditService::logGroupRejected($request['group_id'], $rejectedBy, $reason);

            return true;
        } catch (\Exception $e) {
            error_log("GroupApprovalWorkflowService: Failed to reject group - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Request changes to a group
     *
     * @param int $requestId Approval request ID
     * @param int $reviewedBy User ID
     * @param string $changes Changes requested
     * @return bool Success
     */
    public static function requestChanges($requestId, $reviewedBy, $changes = '')
    {
        try {
            $request = self::getRequest($requestId);
            if (!$request) {
                return false;
            }

            // Update approval request
            Database::query(
                "UPDATE group_approval_requests
                 SET status = ?,
                     reviewed_by = ?,
                     review_notes = ?,
                     reviewed_at = NOW()
                 WHERE id = ?",
                [self::STATUS_CHANGES_REQUESTED, $reviewedBy, $changes, $requestId]
            );

            // Set group status to draft
            Database::query(
                "UPDATE `groups` SET status = 'draft' WHERE id = ?",
                [$request['group_id']]
            );

            // Notify submitter
            self::notifySubmitter($request, self::STATUS_CHANGES_REQUESTED, $changes);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resubmit after changes
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @param string $notes Notes about changes made
     * @return int|null New request ID
     */
    public static function resubmit($groupId, $userId, $notes = '')
    {
        try {
            // Mark previous request as superseded
            Database::query(
                "UPDATE group_approval_requests
                 SET status = 'superseded'
                 WHERE group_id = ? AND status = ?",
                [$groupId, self::STATUS_CHANGES_REQUESTED]
            );

            // Submit new request
            return self::submitForApproval($groupId, $userId, $notes);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get approval request
     *
     * @param int $requestId Request ID
     * @return array|null Request data
     */
    public static function getRequest($requestId)
    {
        try {
            return Database::query(
                "SELECT * FROM group_approval_requests WHERE id = ?",
                [$requestId]
            )->fetch();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get pending approval requests
     *
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Requests
     */
    public static function getPendingRequests($limit = 50, $offset = 0)
    {
        // SECURITY: Cast to int to prevent SQL injection
        $limit = (int)$limit;
        $offset = (int)$offset;

        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT r.*,
                        g.name as group_name,
                        g.description as group_description,
                        g.image_url,
                        gt.name as type_name,
                        u.first_name, u.last_name,
                        CONCAT(u.first_name, ' ', u.last_name) as submitter_name
                 FROM group_approval_requests r
                 JOIN `groups` g ON r.group_id = g.id
                 LEFT JOIN group_types gt ON g.type_id = gt.id
                 JOIN users u ON r.submitted_by = u.id
                 WHERE r.tenant_id = ? AND r.status = ?
                 ORDER BY r.created_at ASC
                 LIMIT $limit OFFSET $offset",
                [$tenantId, self::STATUS_PENDING]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get approval history
     *
     * @param array $filters Optional filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array History
     */
    public static function getApprovalHistory($filters = [], $limit = 50, $offset = 0)
    {
        // SECURITY: Cast to int to prevent SQL injection
        $limit = (int)$limit;
        $offset = (int)$offset;

        $tenantId = TenantContext::getId();

        $sql = "SELECT r.*,
                       g.name as group_name,
                       u.first_name as submitter_first_name,
                       u.last_name as submitter_last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as submitter_name,
                       a.first_name as reviewer_first_name,
                       a.last_name as reviewer_last_name,
                       CONCAT(a.first_name, ' ', a.last_name) as reviewer_name
                FROM group_approval_requests r
                LEFT JOIN `groups` g ON r.group_id = g.id
                LEFT JOIN users u ON r.submitted_by = u.id
                LEFT JOIN users a ON r.reviewed_by = a.id
                WHERE r.tenant_id = ? AND r.status != ?";

        $params = [$tenantId, self::STATUS_PENDING];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['reviewer_id'])) {
            $sql .= " AND r.reviewed_by = ?";
            $params[] = $filters['reviewer_id'];
        }

        $sql .= " ORDER BY r.reviewed_at DESC LIMIT $limit OFFSET $offset";

        try {
            return Database::query($sql, $params)->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get requests for a specific group
     *
     * @param int $groupId Group ID
     * @return array Requests
     */
    public static function getGroupRequests($groupId)
    {
        try {
            return Database::query(
                "SELECT r.*,
                        u.first_name, u.last_name,
                        CONCAT(u.first_name, ' ', u.last_name) as submitter_name,
                        a.first_name as reviewer_first_name,
                        a.last_name as reviewer_last_name,
                        CONCAT(a.first_name, ' ', a.last_name) as reviewer_name
                 FROM group_approval_requests r
                 LEFT JOIN users u ON r.submitted_by = u.id
                 LEFT JOIN users a ON r.reviewed_by = a.id
                 WHERE r.group_id = ?
                 ORDER BY r.created_at DESC",
                [$groupId]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if approval is required for group creation
     *
     * @param int $userId User ID
     * @param bool $isHub Is hub group
     * @return bool Approval required
     */
    public static function isApprovalRequired($userId, $isHub = false)
    {
        // Check if user can override approval requirement
        if (GroupPermissionManager::hasPermission($userId, GroupPermissionManager::PERM_OVERRIDE_LIMITS)) {
            return false;
        }

        // Check configuration
        if ($isHub) {
            return GroupPolicyRepository::getPolicy('require_approval_for_hubs', true);
        }

        return GroupConfigurationService::get(
            GroupConfigurationService::CONFIG_REQUIRE_GROUP_APPROVAL,
            false
        );
    }

    /**
     * Get approval statistics
     *
     * @param int $days Days to analyze
     * @return array Statistics
     */
    public static function getStatistics($days = 30)
    {
        $tenantId = TenantContext::getId();

        try {
            $stats = [
                'pending_count' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'changes_requested_count' => 0,
                'avg_approval_time' => 0,
                'approval_rate' => 0,
            ];

            // Count by status
            $counts = Database::query(
                "SELECT status, COUNT(*) as count
                 FROM group_approval_requests
                 WHERE tenant_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY status",
                [$tenantId, $days]
            )->fetchAll();

            foreach ($counts as $row) {
                $stats[$row['status'] . '_count'] = (int) $row['count'];
            }

            // Average approval time (in hours)
            $avgTime = Database::query(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_time
                 FROM group_approval_requests
                 WHERE tenant_id = ?
                 AND status IN (?, ?)
                 AND reviewed_at IS NOT NULL
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$tenantId, self::STATUS_APPROVED, self::STATUS_REJECTED, $days]
            )->fetchColumn();

            $stats['avg_approval_time'] = round($avgTime, 1);

            // Approval rate
            $total = $stats['approved_count'] + $stats['rejected_count'];
            $stats['approval_rate'] = $total > 0 ? round(($stats['approved_count'] / $total) * 100, 1) : 0;

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Notify admins of new approval request
     */
    private static function notifyAdmins($requestId, $groupId)
    {
        $tenantId = TenantContext::getId();

        try {
            // Get all admins
            $admins = Database::query(
                "SELECT id FROM users
                 WHERE tenant_id = ?
                 AND role IN ('super_admin', 'admin', 'tenant_admin')",
                [$tenantId]
            )->fetchAll();

            $group = Database::query(
                "SELECT name FROM `groups` WHERE id = ?",
                [$groupId]
            )->fetch();

            foreach ($admins as $admin) {
                NotificationDispatcher::dispatch(
                    $admin['id'],
                    'group_approval_request',
                    'New Group Approval Request',
                    "A new group '{$group['name']}' is awaiting approval.",
                    ['group_id' => $groupId, 'request_id' => $requestId]
                );
            }
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Notify submitter of decision
     */
    private static function notifySubmitter($request, $status, $notes)
    {
        try {
            $group = Database::query(
                "SELECT name FROM `groups` WHERE id = ?",
                [$request['group_id']]
            )->fetch();

            $messages = [
                self::STATUS_APPROVED => "Your group '{$group['name']}' has been approved!",
                self::STATUS_REJECTED => "Your group '{$group['name']}' was not approved. Reason: $notes",
                self::STATUS_CHANGES_REQUESTED => "Changes requested for your group '{$group['name']}': $notes",
            ];

            NotificationDispatcher::dispatch(
                $request['submitted_by'],
                'group_approval_decision',
                'Group Approval Update',
                $messages[$status] ?? 'Your group approval status has been updated.',
                ['group_id' => $request['group_id'], 'request_id' => $request['id']]
            );
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Ensure approval requests table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_approval_requests LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_approval_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    group_id INT NOT NULL,
                    submitted_by INT NOT NULL,
                    submission_notes TEXT NULL,
                    reviewed_by INT NULL,
                    review_notes TEXT NULL,
                    status VARCHAR(50) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at TIMESTAMP NULL,
                    INDEX idx_tenant_status (tenant_id, status),
                    INDEX idx_group (group_id),
                    INDEX idx_submitter (tenant_id, submitted_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
