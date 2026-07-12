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
use App\Enums\GroupStatus;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

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
        $tenantId = (int) TenantContext::getId();

        return DB::transaction(function () use ($groupId, $userId, $notes, $tenantId): int {
            $group = DB::table('groups')
                ->where('id', $groupId)
                ->where('tenant_id', $tenantId)
                ->where('owner_id', $userId)
                ->where('status', GroupStatus::PendingReview->value)
                ->lockForUpdate()
                ->first();

            if ($group === null) {
                throw new InvalidArgumentException('Only a pending-review group owner can submit approval.');
            }

            $existing = DB::table('group_approval_requests')
                ->where('tenant_id', $tenantId)
                ->where('group_id', $groupId)
                ->where('status', self::STATUS_PENDING)
                ->value('id');

            if ($existing !== null) {
                return (int) $existing;
            }

            return (int) DB::table('group_approval_requests')->insertGetId([
                'tenant_id' => $tenantId,
                'group_id' => $groupId,
                'submitted_by' => $userId,
                'status' => self::STATUS_PENDING,
                'submission_notes' => $notes,
                'created_at' => now(),
            ]);
        });
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

        $request = self::reviewRequest(
            $requestId,
            $approverId,
            $notes,
            self::STATUS_APPROVED,
            GroupStatus::Active,
        );

        if ($request) {
            try {
                $user = DB::table('users')->where('id', $request->submitted_by)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                if ($user && !empty($user->email)) {
                    // Render the approval notification in the submitter's locale.
                    LocaleContext::withLocale($user, function () use ($user, $request, $requestId, $tenantId) {
                        $firstName = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
                        $groupName = htmlspecialchars($request->group_name ?? '', ENT_QUOTES, 'UTF-8');
                        $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/groups/' . $request->group_id;
                        $html = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.group_approval.approved_title'))
                            ->greeting($firstName)
                            ->paragraph(__('emails_misc.group_approval.approved_body', ['group' => $groupName]))
                            ->button(__('emails_misc.group_approval.approved_cta'), $fullUrl)
                            ->render();
                        if (!\App\Services\EmailDispatchService::sendRaw($user->email, __('emails_misc.group_approval.approved_subject', ['group' => $groupName]), $html, null, null, null, 'group_approval', ['tenant_id' => $tenantId])) {
                            Log::warning('[GroupApprovalWorkflowService] approveGroup email failed', ['request_id' => $requestId]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('[GroupApprovalWorkflowService] approveGroup email error: ' . $e->getMessage());
            }
        }

        return $request !== null;
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

        $request = self::reviewRequest(
            $requestId,
            $rejecterId,
            $notes,
            self::STATUS_REJECTED,
            GroupStatus::Rejected,
        );

        if ($request) {
            try {
                $user = DB::table('users')->where('id', $request->submitted_by)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name', 'preferred_language'])->first();
                if ($user && !empty($user->email)) {
                    // Render the rejection notice in the submitter's locale.
                    LocaleContext::withLocale($user, function () use ($user, $request, $notes, $requestId, $tenantId) {
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
                        if (!\App\Services\EmailDispatchService::sendRaw($user->email, __('emails_misc.group_approval.rejected_subject', ['group' => $groupName]), $html, null, null, null, 'group_approval', ['tenant_id' => $tenantId])) {
                            Log::warning('[GroupApprovalWorkflowService] rejectGroup email failed', ['request_id' => $requestId]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('[GroupApprovalWorkflowService] rejectGroup email error: ' . $e->getMessage());
            }
        }

        return $request !== null;
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
             LEFT JOIN `groups` g ON g.id = gar.group_id AND g.tenant_id = gar.tenant_id
             WHERE gar.tenant_id = ? AND gar.status = ?
             ORDER BY gar.created_at DESC",
            [$tenantId, self::STATUS_PENDING]
        );

        return array_map(fn($r) => (array) $r, $rows);
    }

    private static function reviewRequest(
        int $requestId,
        int $reviewerId,
        string $notes,
        string $requestStatus,
        GroupStatus $groupStatus,
    ): object|null {
        $tenantId = (int) TenantContext::getId();

        return DB::transaction(function () use (
            $requestId,
            $reviewerId,
            $notes,
            $requestStatus,
            $groupStatus,
            $tenantId,
        ): object|null {
            $request = DB::table('group_approval_requests as gar')
                ->join('groups as g', function ($join): void {
                    $join->on('g.id', '=', 'gar.group_id')
                        ->on('g.tenant_id', '=', 'gar.tenant_id');
                })
                ->where('gar.id', $requestId)
                ->where('gar.tenant_id', $tenantId)
                ->where('gar.status', self::STATUS_PENDING)
                ->select([
                    'gar.submitted_by',
                    'gar.group_id',
                    'g.name as group_name',
                ])
                ->lockForUpdate()
                ->first();

            if ($request === null || ! GroupLifecycleService::transition(
                (int) $request->group_id,
                $groupStatus->value,
                $reviewerId,
                $notes,
            )) {
                return null;
            }

            $affected = DB::table('group_approval_requests')
                ->where('id', $requestId)
                ->where('tenant_id', $tenantId)
                ->where('status', self::STATUS_PENDING)
                ->update([
                    'status' => $requestStatus,
                    'reviewed_by' => $reviewerId,
                    'review_notes' => $notes,
                    'reviewed_at' => now(),
                ]);

            if ($affected !== 1) {
                throw new RuntimeException('Concurrent group approval review prevented an atomic transition.');
            }

            return $request;
        });
    }
}
