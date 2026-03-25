<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminSafeguardingController -- Admin safeguarding module endpoints.
 *
 * Provides dashboard stats, flagged message management, and safeguarding
 * assignment CRUD for the admin safeguarding module.
 */
class AdminSafeguardingController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /v2/admin/safeguarding/dashboard
     *
     * Returns aggregate stats for the safeguarding dashboard.
     */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $totalFlagged = DB::table('flagged_messages')
                ->where('tenant_id', $tenantId)
                ->count();

            $pendingReview = DB::table('flagged_messages')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();

            $activeAssignments = DB::table('safeguarding_assignments')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();

            $resolved = DB::table('flagged_messages')
                ->where('tenant_id', $tenantId)
                ->where('status', 'resolved')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithData([
                    'total_flagged' => 0,
                    'pending_review' => 0,
                    'active_assignments' => 0,
                    'resolved' => 0,
                ]);
            }
            throw $e;
        }

        return $this->respondWithData([
            'total_flagged' => $totalFlagged,
            'pending_review' => $pendingReview,
            'active_assignments' => $activeAssignments,
            'resolved' => $resolved,
        ]);
    }

    /**
     * GET /v2/admin/safeguarding/flagged-messages
     *
     * Returns list of flagged messages with user info.
     */
    public function flaggedMessages(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $messages = DB::table('flagged_messages as fm')
                ->join('users as u', 'fm.user_id', '=', 'u.id')
                ->where('fm.tenant_id', $tenantId)
                ->select([
                    'fm.id',
                    'fm.user_id',
                    'u.first_name',
                    'u.last_name',
                    'u.email',
                    'fm.message_id',
                    'fm.reason',
                    'fm.status',
                    'fm.review_notes',
                    'fm.reviewed_by',
                    'fm.reviewed_at',
                    'fm.created_at',
                    'fm.updated_at',
                ])
                ->orderByDesc('fm.created_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithCollection([]);
            }
            throw $e;
        }

        return $this->respondWithCollection($messages);
    }

    /**
     * GET /v2/admin/safeguarding/assignments
     *
     * Returns list of safeguarding assignments with user info.
     */
    public function assignments(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $assignments = DB::table('safeguarding_assignments as sa')
                ->join('users as u', 'sa.ward_user_id', '=', 'u.id')
                ->where('sa.tenant_id', $tenantId)
                ->select([
                    'sa.id',
                    'sa.ward_user_id as user_id',
                    'u.first_name',
                    'u.last_name',
                    'u.email',
                    'sa.guardian_user_id as assignee_id',
                    DB::raw("'safeguarding' as type"),
                    DB::raw("CASE WHEN sa.revoked_at IS NOT NULL THEN 'revoked' WHEN sa.consent_given_at IS NOT NULL THEN 'active' ELSE 'pending' END as status"),
                    'sa.notes',
                    'sa.assigned_at as created_at',
                    'sa.assigned_at as updated_at',
                ])
                ->orderByDesc('sa.assigned_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e) || str_contains($e->getMessage(), 'Column not found')) {
                return $this->respondWithCollection([]);
            }
            throw $e;
        }

        return $this->respondWithCollection($assignments);
    }

    /**
     * POST /v2/admin/safeguarding/flagged-messages/{id}/review
     *
     * Reviews a flagged message by updating its status and adding review notes.
     */
    public function reviewMessage(Request $request, int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'action' => 'required|in:pending,approved,rejected,resolved',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $affected = DB::table('flagged_messages')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => $validated['action'],
                    'reviewed_by' => $adminId,
                    'review_notes' => $validated['notes'] ?? null,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return $this->respondWithError(
                    'NOT_FOUND',
                    'Flagged message not found.',
                    null,
                    404
                );
            }

            $message = DB::table('flagged_messages')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            return $this->respondWithData((array) $message);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    'Safeguarding tables have not been created yet.',
                    null,
                    404
                );
            }
            throw $e;
        }
    }

    /**
     * POST /v2/admin/safeguarding/assignments
     *
     * Creates a new safeguarding assignment.
     */
    public function createAssignment(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'assignee_id' => 'required|integer',
            'type' => 'required|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Validate both users belong to current tenant
        $userExists = \App\Models\User::where('id', $validated['user_id'])
            ->where('tenant_id', $tenantId)
            ->exists();
        $assigneeExists = \App\Models\User::where('id', $validated['assignee_id'])
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$userExists) {
            return $this->respondWithError('INVALID_USER', 'User not found in this tenant', 'user_id', 404);
        }
        if (!$assigneeExists) {
            return $this->respondWithError('INVALID_USER', 'Assignee not found in this tenant', 'assignee_id', 404);
        }

        try {
            $id = DB::table('safeguarding_assignments')->insertGetId([
                'tenant_id' => $tenantId,
                'ward_user_id' => $validated['user_id'],
                'guardian_user_id' => $validated['assignee_id'],
                'assigned_by' => $this->requireAdmin(),
                'assigned_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $assignment = DB::table('safeguarding_assignments')
                ->where('id', $id)
                ->first();

            return $this->respondWithData((array) $assignment);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    'Safeguarding tables have not been created yet.',
                    null,
                    404
                );
            }
            throw $e;
        }
    }

    /**
     * DELETE /v2/admin/safeguarding/assignments/{id}
     *
     * Deletes a safeguarding assignment.
     */
    public function deleteAssignment(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $affected = DB::table('safeguarding_assignments')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->delete();

            if ($affected === 0) {
                return $this->respondWithError(
                    'NOT_FOUND',
                    'Assignment not found.',
                    null,
                    404
                );
            }

            return $this->respondWithData(['deleted' => true]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    'Safeguarding tables have not been created yet.',
                    null,
                    404
                );
            }
            throw $e;
        }
    }

    // ============================================
    // MEMBER SAFEGUARDING PREFERENCES
    // ============================================

    /**
     * GET /v2/admin/safeguarding/member-preferences
     *
     * Returns all members who have selected safeguarding options during onboarding.
     * Grouped by user, with their selected options and trigger status.
     * Access is audit-logged.
     */
    public function memberPreferences(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = \App\Core\TenantContext::getId();

        try {
            $rows = DB::select(
                "SELECT
                    u.id as user_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.avatar_url as user_avatar,
                    usp.consent_given_at,
                    tso.option_key,
                    tso.label as option_label,
                    tso.triggers
                 FROM user_safeguarding_preferences usp
                 JOIN users u ON u.id = usp.user_id
                 JOIN tenant_safeguarding_options tso ON tso.id = usp.option_id
                 WHERE usp.tenant_id = ? AND usp.revoked_at IS NULL AND tso.is_active = 1
                 ORDER BY usp.consent_given_at DESC, u.id, tso.sort_order",
                [$tenantId]
            );

            // Group by user
            $grouped = [];
            foreach ($rows as $row) {
                $uid = (int) $row->user_id;
                if (!isset($grouped[$uid])) {
                    $grouped[$uid] = [
                        'user_id' => $uid,
                        'user_name' => trim($row->user_name),
                        'user_avatar' => $row->user_avatar,
                        'consent_given_at' => $row->consent_given_at,
                        'options' => [],
                        'has_triggers' => false,
                    ];
                }
                $triggers = json_decode($row->triggers ?? '{}', true) ?: [];
                $hasTriggers = !empty(array_filter($triggers, fn ($v) => $v === true));
                $grouped[$uid]['options'][] = [
                    'option_key' => $row->option_key,
                    'label' => $row->option_label,
                ];
                if ($hasTriggers) {
                    $grouped[$uid]['has_triggers'] = true;
                }
            }

            // Audit log this access
            DB::table('activity_log')->insert([
                'user_id' => $adminId,
                'action' => 'safeguarding_preferences_list_viewed',
                'action_type' => 'safeguarding',
                'entity_type' => 'tenant',
                'entity_id' => $tenantId,
                'details' => json_encode(['members_count' => count($grouped)]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $this->respondWithData(array_values($grouped));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithData([]);
            }
            throw $e;
        }
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * Check if a QueryException indicates a missing table (SQLSTATE 42S02).
     */
    private function isTableNotFound(\Illuminate\Database\QueryException $e): bool
    {
        return str_contains($e->getCode(), '42S02')
            || str_contains($e->getMessage(), "doesn't exist")
            || str_contains($e->getMessage(), 'Base table or view not found');
    }
}
