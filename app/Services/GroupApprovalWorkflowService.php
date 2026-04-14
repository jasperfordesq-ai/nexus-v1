<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GroupApprovalWorkflowService — manages group creation approval workflow.
 *
 * Handles submission, approval, rejection, and change-request flows
 * for groups that require admin review before becoming active.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class GroupApprovalWorkflowService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    /**
     * Submit a group for approval.
     *
     * If a pending request already exists for this group (within the current tenant),
     * returns the existing request ID to prevent duplicates.
     *
     * @param int    $groupId The group to submit for approval
     * @param int    $userId  The user submitting the request
     * @param string $notes   Optional notes from the submitter
     * @return int The approval request ID
     */
    public static function submitForApproval(int $groupId, int $userId, string $notes = ''): int
    {
        $tenantId = TenantContext::getId();

        // Check for existing pending request to prevent duplicates
        $existing = DB::selectOne(
            "SELECT id FROM group_approval_requests
             WHERE tenant_id = ? AND group_id = ? AND status = ?",
            [$tenantId, $groupId, self::STATUS_PENDING]
        );

        if ($existing) {
            return (int) $existing->id;
        }

        // Insert new approval request
        DB::insert(
            "INSERT INTO group_approval_requests (tenant_id, group_id, submitted_by, status, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $groupId, $userId, self::STATUS_PENDING, $notes]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * Get an approval request by ID.
     *
     * @param int $requestId The approval request ID
     * @return array Associative array of request data, or empty array if not found
     */
    public static function getRequest(int $requestId): array
    {
        $tenantId = TenantContext::getId();

        $result = DB::selectOne(
            "SELECT * FROM group_approval_requests
             WHERE id = ? AND tenant_id = ?",
            [$requestId, $tenantId]
        );

        return $result ? (array) $result : [];
    }

    /**
     * Approve a pending group approval request.
     *
     * @param int    $requestId  The approval request ID
     * @param int    $approverId The admin/moderator approving the request
     * @param string $notes      Optional reviewer notes
     * @return bool True if approved, false if request not found or already processed
     */
    public static function approveGroup(int $requestId, int $approverId, string $notes = ''): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE group_approval_requests
             SET status = ?, reviewed_by = ?, reviewer_notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?",
            [self::STATUS_APPROVED, $approverId, $notes, $requestId, $tenantId, self::STATUS_PENDING]
        );

        return $affected > 0;
    }

    /**
     * Reject a pending group approval request.
     *
     * @param int    $requestId  The approval request ID
     * @param int    $rejecterId The admin/moderator rejecting the request
     * @param string $notes      Optional reviewer notes explaining the rejection
     * @return bool True if rejected, false if request not found or already processed
     */
    public static function rejectGroup(int $requestId, int $rejecterId, string $notes = ''): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE group_approval_requests
             SET status = ?, reviewed_by = ?, reviewer_notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?",
            [self::STATUS_REJECTED, $rejecterId, $notes, $requestId, $tenantId, self::STATUS_PENDING]
        );

        return $affected > 0;
    }

    /**
     * Get all pending approval requests for the current tenant.
     *
     * Joins with the groups table to include the group name in results.
     *
     * @return array List of pending approval requests with group names
     */
    public static function getPendingRequests(): array
    {
        $tenantId = TenantContext::getId();

        $rows = DB::select(
            "SELECT gar.*, g.name AS group_name
             FROM group_approval_requests gar
             LEFT JOIN `groups` g ON g.id = gar.group_id
             WHERE gar.tenant_id = ? AND gar.status = ?
             ORDER BY gar.created_at DESC",
            [$tenantId, self::STATUS_PENDING]
        );

        return array_map(fn($r) => (array) $r, $rows);
    }
}
