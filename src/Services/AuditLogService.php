<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AuditLogService
 *
 * Enhanced audit logging for organization wallet operations.
 * Tracks all significant actions with IP addresses, user agents, and detailed context.
 */
class AuditLogService
{
    // Action types
    const ACTION_WALLET_DEPOSIT = 'wallet_deposit';
    const ACTION_WALLET_WITHDRAWAL = 'wallet_withdrawal';
    const ACTION_TRANSFER_REQUEST = 'transfer_request';
    const ACTION_TRANSFER_APPROVE = 'transfer_approve';
    const ACTION_TRANSFER_REJECT = 'transfer_reject';
    const ACTION_TRANSFER_CANCEL = 'transfer_cancel';
    const ACTION_MEMBER_ADDED = 'member_added';
    const ACTION_MEMBER_REMOVED = 'member_removed';
    const ACTION_MEMBER_ROLE_CHANGED = 'member_role_changed';
    const ACTION_OWNERSHIP_TRANSFERRED = 'ownership_transferred';
    const ACTION_SETTINGS_CHANGED = 'settings_changed';
    const ACTION_LIMITS_CHANGED = 'limits_changed';
    const ACTION_BULK_APPROVE = 'bulk_approve';
    const ACTION_BULK_REJECT = 'bulk_reject';

    // Admin user management actions
    const ACTION_ADMIN_USER_CREATED = 'admin_user_created';
    const ACTION_ADMIN_USER_UPDATED = 'admin_user_updated';
    const ACTION_ADMIN_USER_DELETED = 'admin_user_deleted';
    const ACTION_ADMIN_USER_SUSPENDED = 'admin_user_suspended';
    const ACTION_ADMIN_USER_BANNED = 'admin_user_banned';
    const ACTION_ADMIN_USER_REACTIVATED = 'admin_user_reactivated';
    const ACTION_ADMIN_USER_APPROVED = 'admin_user_approved';
    const ACTION_ADMIN_ROLE_CHANGED = 'admin_role_changed';
    const ACTION_ADMIN_2FA_RESET = 'admin_2fa_reset';
    const ACTION_ADMIN_USER_IMPERSONATED = 'admin_user_impersonated';
    const ACTION_ADMIN_SUPER_ADMIN_REVOKED = 'admin_super_admin_revoked';
    const ACTION_ADMIN_BULK_IMPORT = 'admin_bulk_import';

    /**
     * Log an action
     *
     * @param string $action Action type constant
     * @param int|null $organizationId Organization ID (if applicable)
     * @param int|null $userId User performing the action
     * @param array $details Additional context data
     * @param int|null $targetUserId User being affected (if applicable)
     * @return int|null Log entry ID
     */
    public static function log($action, $organizationId = null, $userId = null, $details = [], $targetUserId = null)
    {
        $tenantId = TenantContext::getId();

        // Ensure table exists
        self::ensureTableExists();

        // Get request context
        $ipAddress = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Prepare details JSON
        $detailsJson = !empty($details) ? json_encode($details) : null;

        try {
            Database::query(
                "INSERT INTO org_audit_log
                 (tenant_id, organization_id, user_id, target_user_id, action, details, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$tenantId, $organizationId, $userId, $targetUserId, $action, $detailsJson, $ipAddress, $userAgent]
            );

            return Database::lastInsertId();
        } catch (\Exception $e) {
            error_log("AuditLogService: Failed to log action - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Log a wallet transaction
     */
    public static function logTransaction($organizationId, $userId, $type, $amount, $recipientId = null, $description = '')
    {
        $action = $type === 'deposit' ? self::ACTION_WALLET_DEPOSIT : self::ACTION_WALLET_WITHDRAWAL;

        return self::log($action, $organizationId, $userId, [
            'amount' => $amount,
            'description' => $description,
            'recipient_id' => $recipientId
        ], $recipientId);
    }

    /**
     * Log a transfer request action
     */
    public static function logTransferRequest($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        return self::log(self::ACTION_TRANSFER_REQUEST, $organizationId, $requesterId, [
            'amount' => $amount,
            'description' => $description
        ], $recipientId);
    }

    /**
     * Log transfer approval
     */
    public static function logTransferApproval($organizationId, $approverId, $requestId, $recipientId, $amount)
    {
        return self::log(self::ACTION_TRANSFER_APPROVE, $organizationId, $approverId, [
            'request_id' => $requestId,
            'amount' => $amount
        ], $recipientId);
    }

    /**
     * Log transfer rejection
     */
    public static function logTransferRejection($organizationId, $approverId, $requestId, $recipientId, $reason = '')
    {
        return self::log(self::ACTION_TRANSFER_REJECT, $organizationId, $approverId, [
            'request_id' => $requestId,
            'reason' => $reason
        ], $recipientId);
    }

    /**
     * Log member addition
     */
    public static function logMemberAdded($organizationId, $addedBy, $memberId, $role)
    {
        return self::log(self::ACTION_MEMBER_ADDED, $organizationId, $addedBy, [
            'role' => $role
        ], $memberId);
    }

    /**
     * Log member removal
     */
    public static function logMemberRemoved($organizationId, $removedBy, $memberId, $reason = '')
    {
        return self::log(self::ACTION_MEMBER_REMOVED, $organizationId, $removedBy, [
            'reason' => $reason
        ], $memberId);
    }

    /**
     * Log role change
     */
    public static function logRoleChanged($organizationId, $changedBy, $memberId, $oldRole, $newRole)
    {
        return self::log(self::ACTION_MEMBER_ROLE_CHANGED, $organizationId, $changedBy, [
            'old_role' => $oldRole,
            'new_role' => $newRole
        ], $memberId);
    }

    /**
     * Log ownership transfer
     */
    public static function logOwnershipTransfer($organizationId, $oldOwnerId, $newOwnerId)
    {
        return self::log(self::ACTION_OWNERSHIP_TRANSFERRED, $organizationId, $oldOwnerId, [
            'previous_owner_id' => $oldOwnerId
        ], $newOwnerId);
    }

    /**
     * Log settings change
     */
    public static function logSettingsChanged($organizationId, $userId, $changes)
    {
        return self::log(self::ACTION_SETTINGS_CHANGED, $organizationId, $userId, [
            'changes' => $changes
        ]);
    }

    /**
     * Log limits change
     */
    public static function logLimitsChanged($organizationId, $userId, $oldLimits, $newLimits)
    {
        return self::log(self::ACTION_LIMITS_CHANGED, $organizationId, $userId, [
            'old_limits' => $oldLimits,
            'new_limits' => $newLimits
        ]);
    }

    /**
     * Log bulk approval
     */
    public static function logBulkApproval($organizationId, $approverId, $requestIds, $successCount, $failCount)
    {
        return self::log(self::ACTION_BULK_APPROVE, $organizationId, $approverId, [
            'request_ids' => $requestIds,
            'approved_count' => $successCount,
            'failed_count' => $failCount
        ]);
    }

    /**
     * Log bulk rejection
     */
    public static function logBulkRejection($organizationId, $approverId, $requestIds, $successCount, $failCount, $reason = '')
    {
        return self::log(self::ACTION_BULK_REJECT, $organizationId, $approverId, [
            'request_ids' => $requestIds,
            'rejected_count' => $successCount,
            'failed_count' => $failCount,
            'reason' => $reason
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Admin user management audit helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Log an admin user management action (no organization context)
     */
    public static function logAdminAction($action, $adminUserId, $targetUserId = null, $details = [])
    {
        return self::log($action, null, $adminUserId, $details, $targetUserId);
    }

    /**
     * Log user suspension by admin
     */
    public static function logUserSuspended($adminUserId, $targetUserId, $reason = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_SUSPENDED, $adminUserId, $targetUserId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log user ban by admin
     */
    public static function logUserBanned($adminUserId, $targetUserId, $reason = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_BANNED, $adminUserId, $targetUserId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log user reactivation by admin
     */
    public static function logUserReactivated($adminUserId, $targetUserId, $previousStatus = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_REACTIVATED, $adminUserId, $targetUserId, [
            'previous_status' => $previousStatus,
        ]);
    }

    /**
     * Log user deletion by admin
     */
    public static function logUserDeleted($adminUserId, $targetUserId, $targetEmail = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_DELETED, $adminUserId, $targetUserId, [
            'deleted_email' => $targetEmail,
        ]);
    }

    /**
     * Log user approval by admin
     */
    public static function logUserApproved($adminUserId, $targetUserId, $targetEmail = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_APPROVED, $adminUserId, $targetUserId, [
            'approved_email' => $targetEmail,
        ]);
    }

    /**
     * Log role change by admin
     */
    public static function logAdminRoleChanged($adminUserId, $targetUserId, $oldRole, $newRole)
    {
        return self::logAdminAction(self::ACTION_ADMIN_ROLE_CHANGED, $adminUserId, $targetUserId, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);
    }

    /**
     * Log 2FA reset by admin
     */
    public static function log2faReset($adminUserId, $targetUserId, $reason = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_2FA_RESET, $adminUserId, $targetUserId, [
            'reason' => $reason,
        ]);
    }

    /**
     * Log user impersonation by admin
     */
    public static function logUserImpersonated($adminUserId, $targetUserId, $targetEmail = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_IMPERSONATED, $adminUserId, $targetUserId, [
            'impersonated_email' => $targetEmail,
        ]);
    }

    /**
     * Log user creation by admin
     */
    public static function logUserCreated($adminUserId, $targetUserId, $targetEmail = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_CREATED, $adminUserId, $targetUserId, [
            'created_email' => $targetEmail,
        ]);
    }

    /**
     * Log user profile update by admin
     */
    public static function logUserUpdated($adminUserId, $targetUserId, $changedFields = [])
    {
        return self::logAdminAction(self::ACTION_ADMIN_USER_UPDATED, $adminUserId, $targetUserId, [
            'changed_fields' => $changedFields,
        ]);
    }

    /**
     * Log super admin revocation
     */
    public static function logSuperAdminRevoked($adminUserId, $targetUserId, $targetEmail = '')
    {
        return self::logAdminAction(self::ACTION_ADMIN_SUPER_ADMIN_REVOKED, $adminUserId, $targetUserId, [
            'revoked_email' => $targetEmail,
        ]);
    }

    /**
     * Log bulk user import by admin
     */
    public static function logBulkImport($adminUserId, $importedCount, $skippedCount, $totalRows)
    {
        return self::logAdminAction(self::ACTION_ADMIN_BULK_IMPORT, $adminUserId, null, [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'total_rows' => $totalRows,
        ]);
    }

    /**
     * Get audit log for an organization
     *
     * @param int $organizationId
     * @param array $filters Optional filters: action, userId, startDate, endDate
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getLog($organizationId, $filters = [], $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT al.*,
                       u.first_name, u.last_name,
                       CONCAT(u.first_name, ' ', u.last_name) as user_name,
                       tu.first_name as target_first_name, tu.last_name as target_last_name,
                       CONCAT(tu.first_name, ' ', tu.last_name) as target_user_name
                FROM org_audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN users tu ON al.target_user_id = tu.id
                WHERE al.tenant_id = ? AND al.organization_id = ?";

        $params = [$tenantId, $organizationId];

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

        $sql .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        try {
            $logs = Database::query($sql, $params)->fetchAll();

            // Decode JSON details
            foreach ($logs as &$log) {
                $log['details'] = $log['details'] ? json_decode($log['details'], true) : [];
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get audit log count for pagination
     */
    public static function getLogCount($organizationId, $filters = [])
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT COUNT(*) FROM org_audit_log
                WHERE tenant_id = ? AND organization_id = ?";

        $params = [$tenantId, $organizationId];

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
     * Get action summary for dashboard
     */
    public static function getActionSummary($organizationId, $days = 30)
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT action, COUNT(*) as count
                 FROM org_audit_log
                 WHERE tenant_id = ? AND organization_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY action
                 ORDER BY count DESC",
                [$tenantId, $organizationId, $days]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recent activity for a user
     */
    public static function getUserActivity($userId, $limit = 20)
    {
        $tenantId = TenantContext::getId();

        try {
            $logs = Database::query(
                "SELECT al.*, vo.name as org_name
                 FROM org_audit_log al
                 LEFT JOIN vol_organizations vo ON al.organization_id = vo.id
                 WHERE al.tenant_id = ? AND (al.user_id = ? OR al.target_user_id = ?)
                 ORDER BY al.created_at DESC
                 LIMIT ?",
                [$tenantId, $userId, $userId, $limit]
            )->fetchAll();

            foreach ($logs as &$log) {
                $log['details'] = $log['details'] ? json_decode($log['details'], true) : [];
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Export audit log to CSV
     */
    public static function exportToCSV($organizationId, $filters = [])
    {
        $logs = self::getLog($organizationId, $filters, 10000, 0); // Max 10000 entries

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
                self::getActionLabel($log['action']),
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
            self::ACTION_WALLET_DEPOSIT => 'Wallet Deposit',
            self::ACTION_WALLET_WITHDRAWAL => 'Wallet Withdrawal',
            self::ACTION_TRANSFER_REQUEST => 'Transfer Request Created',
            self::ACTION_TRANSFER_APPROVE => 'Transfer Request Approved',
            self::ACTION_TRANSFER_REJECT => 'Transfer Request Rejected',
            self::ACTION_TRANSFER_CANCEL => 'Transfer Request Cancelled',
            self::ACTION_MEMBER_ADDED => 'Member Added',
            self::ACTION_MEMBER_REMOVED => 'Member Removed',
            self::ACTION_MEMBER_ROLE_CHANGED => 'Member Role Changed',
            self::ACTION_OWNERSHIP_TRANSFERRED => 'Ownership Transferred',
            self::ACTION_SETTINGS_CHANGED => 'Settings Changed',
            self::ACTION_LIMITS_CHANGED => 'Limits Changed',
            self::ACTION_BULK_APPROVE => 'Bulk Approval',
            self::ACTION_BULK_REJECT => 'Bulk Rejection',
            // Admin user management
            self::ACTION_ADMIN_USER_CREATED => 'Admin Created User',
            self::ACTION_ADMIN_USER_UPDATED => 'Admin Updated User',
            self::ACTION_ADMIN_USER_DELETED => 'Admin Deleted User',
            self::ACTION_ADMIN_USER_SUSPENDED => 'Admin Suspended User',
            self::ACTION_ADMIN_USER_BANNED => 'Admin Banned User',
            self::ACTION_ADMIN_USER_REACTIVATED => 'Admin Reactivated User',
            self::ACTION_ADMIN_USER_APPROVED => 'Admin Approved User',
            self::ACTION_ADMIN_ROLE_CHANGED => 'Admin Changed User Role',
            self::ACTION_ADMIN_2FA_RESET => 'Admin Reset 2FA',
            self::ACTION_ADMIN_USER_IMPERSONATED => 'Admin Impersonated User',
            self::ACTION_ADMIN_SUPER_ADMIN_REVOKED => 'Super Admin Revoked',
            self::ACTION_ADMIN_BULK_IMPORT => 'Admin Bulk Import Users',
        ];

        return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get client IP address
     */
    private static function getClientIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
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
     * Ensure the audit log table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM org_audit_log LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS org_audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    organization_id INT NULL,
                    user_id INT NULL,
                    target_user_id INT NULL,
                    action VARCHAR(50) NOT NULL,
                    details JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_org_audit (tenant_id, organization_id, created_at),
                    INDEX idx_user_audit (tenant_id, user_id, created_at),
                    INDEX idx_action (tenant_id, action, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Clean up old audit logs (for maintenance)
     *
     * @param int $daysToKeep Days of logs to retain
     * @return int Number of deleted entries
     */
    public static function cleanup($daysToKeep = 365)
    {
        try {
            $stmt = Database::query(
                "DELETE FROM org_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("AuditLogService: Cleanup failed - " . $e->getMessage());
            return 0;
        }
    }
}
