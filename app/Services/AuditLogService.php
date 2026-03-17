<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Nexus\Services\AuditLogService as LegacyAuditLogService;

/**
 * AuditLogService — Laravel DI-based service for audit logging.
 *
 * Wraps the legacy static \Nexus\Services\AuditLogService and provides
 * all the specialized logging helpers used by AdminUsersController,
 * AdminBrokerController, and other admin controllers.
 *
 * Static helper methods delegate directly to the legacy service so that
 * controllers can call AuditLogService::logUserSuspended(...) etc.
 */
class AuditLogService
{
    // ─── Action type constants (mirrored from legacy) ───────────────
    public const ACTION_WALLET_DEPOSIT = 'wallet_deposit';
    public const ACTION_WALLET_WITHDRAWAL = 'wallet_withdrawal';
    public const ACTION_TRANSFER_REQUEST = 'transfer_request';
    public const ACTION_TRANSFER_APPROVE = 'transfer_approve';
    public const ACTION_TRANSFER_REJECT = 'transfer_reject';
    public const ACTION_TRANSFER_CANCEL = 'transfer_cancel';
    public const ACTION_MEMBER_ADDED = 'member_added';
    public const ACTION_MEMBER_REMOVED = 'member_removed';
    public const ACTION_MEMBER_ROLE_CHANGED = 'member_role_changed';
    public const ACTION_OWNERSHIP_TRANSFERRED = 'ownership_transferred';
    public const ACTION_SETTINGS_CHANGED = 'settings_changed';
    public const ACTION_LIMITS_CHANGED = 'limits_changed';
    public const ACTION_BULK_APPROVE = 'bulk_approve';
    public const ACTION_BULK_REJECT = 'bulk_reject';

    // Admin user management actions
    public const ACTION_ADMIN_USER_CREATED = 'admin_user_created';
    public const ACTION_ADMIN_USER_UPDATED = 'admin_user_updated';
    public const ACTION_ADMIN_USER_DELETED = 'admin_user_deleted';
    public const ACTION_ADMIN_USER_SUSPENDED = 'admin_user_suspended';
    public const ACTION_ADMIN_USER_BANNED = 'admin_user_banned';
    public const ACTION_ADMIN_USER_REACTIVATED = 'admin_user_reactivated';
    public const ACTION_ADMIN_USER_APPROVED = 'admin_user_approved';
    public const ACTION_ADMIN_ROLE_CHANGED = 'admin_role_changed';
    public const ACTION_ADMIN_2FA_RESET = 'admin_2fa_reset';
    public const ACTION_ADMIN_USER_IMPERSONATED = 'admin_user_impersonated';
    public const ACTION_ADMIN_SUPER_ADMIN_REVOKED = 'admin_super_admin_revoked';
    public const ACTION_ADMIN_BULK_IMPORT = 'admin_bulk_import';

    // ─── Instance method (DI-friendly) ──────────────────────────────

    /**
     * Log an auditable action (instance method for DI usage).
     *
     * @param  int          $tenantId
     * @param  string       $action          Action type constant
     * @param  int|null     $userId          User performing the action
     * @param  array        $details         Additional context
     * @param  int|null     $organizationId
     * @param  int|null     $targetUserId    User being affected
     * @return int  Inserted log ID
     */
    public function logAction(
        int $tenantId,
        string $action,
        ?int $userId = null,
        array $details = [],
        ?int $organizationId = null,
        ?int $targetUserId = null,
    ): int {
        return DB::table('org_audit_log')->insertGetId([
            'tenant_id'       => $tenantId,
            'organization_id' => $organizationId,
            'user_id'         => $userId,
            'target_user_id'  => $targetUserId,
            'action'          => $action,
            'details'         => !empty($details) ? json_encode($details) : null,
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
            'created_at'      => now(),
        ]);
    }

    /**
     * Get recent audit log entries for a tenant.
     */
    public function getRecent(int $tenantId, int $limit = 50, ?int $organizationId = null): array
    {
        $query = DB::table('org_audit_log as a')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
            ->where('a.tenant_id', $tenantId)
            ->select([
                'a.id', 'a.action', 'a.details', 'a.ip_address',
                'a.created_at', 'a.user_id', 'a.target_user_id',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) as actor_name"),
            ])
            ->orderByDesc('a.created_at')
            ->limit($limit);

        if ($organizationId !== null) {
            $query->where('a.organization_id', $organizationId);
        }

        return $query->get()->map(function ($row) {
            $row->details = $row->details ? json_decode($row->details, true) : null;
            return (array) $row;
        })->all();
    }

    // ─── Static delegation helpers (called by controllers) ──────────
    // These delegate to the legacy static service so controllers can
    // `use App\Services\AuditLogService` and call the same static API.

    /**
     * Log an action (static — delegates to legacy).
     */
    public static function log($action, $organizationId = null, $userId = null, $details = [], $targetUserId = null)
    {
        return LegacyAuditLogService::log($action, $organizationId, $userId, $details, $targetUserId);
    }

    /**
     * Log an admin user management action (no organization context).
     */
    public static function logAdminAction($action, $adminUserId, $targetUserId = null, $details = [])
    {
        return LegacyAuditLogService::logAdminAction($action, $adminUserId, $targetUserId, $details);
    }

    /**
     * Log user profile update by admin.
     */
    public static function logUserUpdated($adminUserId, $targetUserId, $changedFields = [])
    {
        return LegacyAuditLogService::logUserUpdated($adminUserId, $targetUserId, $changedFields);
    }

    /**
     * Log role change by admin.
     */
    public static function logAdminRoleChanged($adminUserId, $targetUserId, $oldRole, $newRole)
    {
        return LegacyAuditLogService::logAdminRoleChanged($adminUserId, $targetUserId, $oldRole, $newRole);
    }

    /**
     * Log user creation by admin.
     */
    public static function logUserCreated($adminUserId, $targetUserId, $targetEmail = '')
    {
        return LegacyAuditLogService::logUserCreated($adminUserId, $targetUserId, $targetEmail);
    }

    /**
     * Log user approval by admin.
     */
    public static function logUserApproved($adminUserId, $targetUserId, $targetEmail = '')
    {
        return LegacyAuditLogService::logUserApproved($adminUserId, $targetUserId, $targetEmail);
    }

    /**
     * Log user suspension by admin.
     */
    public static function logUserSuspended($adminUserId, $targetUserId, $reason = '')
    {
        return LegacyAuditLogService::logUserSuspended($adminUserId, $targetUserId, $reason);
    }

    /**
     * Log user ban by admin.
     */
    public static function logUserBanned($adminUserId, $targetUserId, $reason = '')
    {
        return LegacyAuditLogService::logUserBanned($adminUserId, $targetUserId, $reason);
    }

    /**
     * Log user reactivation by admin.
     */
    public static function logUserReactivated($adminUserId, $targetUserId, $previousStatus = '')
    {
        return LegacyAuditLogService::logUserReactivated($adminUserId, $targetUserId, $previousStatus);
    }

    /**
     * Log user deletion by admin.
     */
    public static function logUserDeleted($adminUserId, $targetUserId, $targetEmail = '')
    {
        return LegacyAuditLogService::logUserDeleted($adminUserId, $targetUserId, $targetEmail);
    }

    /**
     * Log 2FA reset by admin.
     */
    public static function log2faReset($adminUserId, $targetUserId, $reason = '')
    {
        return LegacyAuditLogService::log2faReset($adminUserId, $targetUserId, $reason);
    }

    /**
     * Log user impersonation by admin.
     */
    public static function logUserImpersonated($adminUserId, $targetUserId, $targetEmail = '')
    {
        return LegacyAuditLogService::logUserImpersonated($adminUserId, $targetUserId, $targetEmail);
    }

    /**
     * Log super admin revocation.
     */
    public static function logSuperAdminRevoked($adminUserId, $targetUserId, $targetEmail = '')
    {
        return LegacyAuditLogService::logSuperAdminRevoked($adminUserId, $targetUserId, $targetEmail);
    }

    /**
     * Log bulk user import by admin.
     */
    public static function logBulkImport($adminUserId, $importedCount, $skippedCount, $totalRows)
    {
        return LegacyAuditLogService::logBulkImport($adminUserId, $importedCount, $skippedCount, $totalRows);
    }

    // ─── Organization-level audit helpers ────────────────────────────

    /**
     * Log a wallet transaction.
     */
    public static function logTransaction($organizationId, $userId, $type, $amount, $recipientId = null, $description = '')
    {
        return LegacyAuditLogService::logTransaction($organizationId, $userId, $type, $amount, $recipientId, $description);
    }

    /**
     * Log a transfer request action.
     */
    public static function logTransferRequest($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        return LegacyAuditLogService::logTransferRequest($organizationId, $requesterId, $recipientId, $amount, $description);
    }

    /**
     * Log transfer approval.
     */
    public static function logTransferApproval($organizationId, $approverId, $requestId, $recipientId, $amount)
    {
        return LegacyAuditLogService::logTransferApproval($organizationId, $approverId, $requestId, $recipientId, $amount);
    }

    /**
     * Log transfer rejection.
     */
    public static function logTransferRejection($organizationId, $approverId, $requestId, $recipientId, $reason = '')
    {
        return LegacyAuditLogService::logTransferRejection($organizationId, $approverId, $requestId, $recipientId, $reason);
    }

    /**
     * Log member addition.
     */
    public static function logMemberAdded($organizationId, $addedBy, $memberId, $role)
    {
        return LegacyAuditLogService::logMemberAdded($organizationId, $addedBy, $memberId, $role);
    }

    /**
     * Log member removal.
     */
    public static function logMemberRemoved($organizationId, $removedBy, $memberId, $reason = '')
    {
        return LegacyAuditLogService::logMemberRemoved($organizationId, $removedBy, $memberId, $reason);
    }

    /**
     * Log role change within an organization.
     */
    public static function logRoleChanged($organizationId, $changedBy, $memberId, $oldRole, $newRole)
    {
        return LegacyAuditLogService::logRoleChanged($organizationId, $changedBy, $memberId, $oldRole, $newRole);
    }

    /**
     * Log ownership transfer.
     */
    public static function logOwnershipTransfer($organizationId, $oldOwnerId, $newOwnerId)
    {
        return LegacyAuditLogService::logOwnershipTransfer($organizationId, $oldOwnerId, $newOwnerId);
    }

    /**
     * Log settings change.
     */
    public static function logSettingsChanged($organizationId, $userId, $changes)
    {
        return LegacyAuditLogService::logSettingsChanged($organizationId, $userId, $changes);
    }

    /**
     * Log limits change.
     */
    public static function logLimitsChanged($organizationId, $userId, $oldLimits, $newLimits)
    {
        return LegacyAuditLogService::logLimitsChanged($organizationId, $userId, $oldLimits, $newLimits);
    }

    /**
     * Log bulk approval.
     */
    public static function logBulkApproval($organizationId, $approverId, $requestIds, $successCount, $failCount)
    {
        return LegacyAuditLogService::logBulkApproval($organizationId, $approverId, $requestIds, $successCount, $failCount);
    }

    /**
     * Log bulk rejection.
     */
    public static function logBulkRejection($organizationId, $approverId, $requestIds, $successCount, $failCount, $reason = '')
    {
        return LegacyAuditLogService::logBulkRejection($organizationId, $approverId, $requestIds, $successCount, $failCount, $reason);
    }

    // ─── Query helpers (static delegation) ───────────────────────────

    /**
     * Get audit log for an organization.
     */
    public static function getLog($organizationId, $filters = [], $limit = 50, $offset = 0)
    {
        return LegacyAuditLogService::getLog($organizationId, $filters, $limit, $offset);
    }

    /**
     * Get audit log count for pagination.
     */
    public static function getLogCount($organizationId, $filters = [])
    {
        return LegacyAuditLogService::getLogCount($organizationId, $filters);
    }

    /**
     * Get action summary for dashboard.
     */
    public static function getActionSummary($organizationId, $days = 30)
    {
        return LegacyAuditLogService::getActionSummary($organizationId, $days);
    }

    /**
     * Get recent activity for a user.
     */
    public static function getUserActivity($userId, $limit = 20)
    {
        return LegacyAuditLogService::getUserActivity($userId, $limit);
    }

    /**
     * Get human-readable action label.
     */
    public static function getActionLabel($action)
    {
        return LegacyAuditLogService::getActionLabel($action);
    }

    /**
     * Export audit log to CSV.
     */
    public static function exportToCSV($organizationId, $filters = [])
    {
        return LegacyAuditLogService::exportToCSV($organizationId, $filters);
    }

    /**
     * Clean up old audit logs.
     *
     * @return int  Number of deleted entries
     */
    public static function cleanup($daysToKeep = 365)
    {
        return LegacyAuditLogService::cleanup($daysToKeep);
    }
}
