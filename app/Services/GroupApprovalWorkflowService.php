<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $request = DB::selectOne(
            "SELECT gar.submitted_by, g.name AS group_name, g.id AS group_id
             FROM group_approval_requests gar
             LEFT JOIN `groups` g ON g.id = gar.group_id
             WHERE gar.id = ? AND gar.tenant_id = ? AND gar.status = ?",
            [$requestId, $tenantId, self::STATUS_PENDING]
        );

        $affected = DB::update(
            "UPDATE group_approval_requests
             SET status = ?, reviewed_by = ?, reviewer_notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?",
            [self::STATUS_APPROVED, $approverId, $notes, $requestId, $tenantId, self::STATUS_PENDING]
        );

        if ($affected > 0 && $request) {
            try {
                $user = DB::table('users')->where('id', $request->submitted_by)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                if ($user && !empty($user->email)) {
                    // Render the approval notification in the submitter's locale.
                    LocaleContext::withLocale($user, function () use ($user, $request, $requestId) {
                        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                        $groupName = htmlspecialchars($request->group_name ?? '', ENT_QUOTES, 'UTF-8');
                        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups/' . $request->group_id;
                        $html = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.group_approval.approved_title'))
                            ->greeting($firstName)
                            ->paragraph(__('emails_misc.group_approval.approved_body', ['group' => $groupName]))
                            ->button(__('emails_misc.group_approval.approved_cta'), $fullUrl)
                            ->render();
                        if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.group_approval.approved_subject', ['group' => $groupName]), $html)) {
                            Log::warning('[GroupApprovalWorkflowService] approveGroup email failed', ['request_id' => $requestId]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('[GroupApprovalWorkflowService] approveGroup email error: ' . $e->getMessage());
            }
        }

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

        $request = DB::selectOne(
            "SELECT gar.submitted_by, g.name AS group_name
             FROM group_approval_requests gar
             LEFT JOIN `groups` g ON g.id = gar.group_id
             WHERE gar.id = ? AND gar.tenant_id = ? AND gar.status = ?",
            [$requestId, $tenantId, self::STATUS_PENDING]
        );

        $affected = DB::update(
            "UPDATE group_approval_requests
             SET status = ?, reviewed_by = ?, reviewer_notes = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?",
            [self::STATUS_REJECTED, $rejecterId, $notes, $requestId, $tenantId, self::STATUS_PENDING]
        );

        if ($affected > 0 && $request) {
            try {
                $user = DB::table('users')->where('id', $request->submitted_by)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                if ($user && !empty($user->email)) {
                    // Render the rejection notice in the submitter's locale.
                    LocaleContext::withLocale($user, function () use ($user, $request, $notes, $requestId) {
                        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                        $groupName = htmlspecialchars($request->group_name ?? '', ENT_QUOTES, 'UTF-8');
                        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups';
                        $builder   = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.group_approval.rejected_title'))
                            ->greeting($firstName)
                            ->paragraph(__('emails_misc.group_approval.rejected_body', ['group' => $groupName]));
                        if (!empty($notes)) {
                            $builder->paragraph('<strong>' . __('emails_misc.group_approval.rejected_notes_label') . ':</strong> ' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'));
                        }
                        $html = $builder->button(__('emails_misc.group_approval.rejected_cta'), $fullUrl)->render();
                        if (!Mailer::forCurrentTenant()->send($user->email, __('emails_misc.group_approval.rejected_subject', ['group' => $groupName]), $html)) {
                            Log::warning('[GroupApprovalWorkflowService] rejectGroup email failed', ['request_id' => $requestId]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('[GroupApprovalWorkflowService] rejectGroup email error: ' . $e->getMessage());
            }
        }

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
