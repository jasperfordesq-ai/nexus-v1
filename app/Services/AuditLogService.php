<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * AuditLogService — Laravel DI-based service for audit logging.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\AuditLogService.
 * Logs are stored in org_audit_log, scoped by tenant.
 */
class AuditLogService
{
    public const ACTION_WALLET_DEPOSIT = 'wallet_deposit';
    public const ACTION_WALLET_WITHDRAWAL = 'wallet_withdrawal';
    public const ACTION_TRANSFER_REQUEST = 'transfer_request';
    public const ACTION_TRANSFER_APPROVE = 'transfer_approve';
    public const ACTION_MEMBER_ADDED = 'member_added';
    public const ACTION_MEMBER_REMOVED = 'member_removed';
    public const ACTION_SETTINGS_CHANGED = 'settings_changed';
    public const ACTION_ADMIN_USER_UPDATED = 'admin_user_updated';
    public const ACTION_ADMIN_USER_SUSPENDED = 'admin_user_suspended';
    public const ACTION_ADMIN_USER_BANNED = 'admin_user_banned';

    /**
     * Log an auditable action.
     *
     * @param  int          $tenantId
     * @param  string       $action          Action type constant
     * @param  int|null     $userId          User performing the action
     * @param  array        $details         Additional context
     * @param  int|null     $organizationId
     * @param  int|null     $targetUserId    User being affected
     * @return int  Inserted log ID
     */
    public function log(
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
            'details'         => ! empty($details) ? json_encode($details) : null,
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
}
