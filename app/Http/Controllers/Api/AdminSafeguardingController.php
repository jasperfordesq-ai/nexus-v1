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
            'action' => 'required|string|max:50',
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

        try {
            $id = DB::table('safeguarding_assignments')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $validated['user_id'],
                'assignee_id' => $validated['assignee_id'],
                'type' => $validated['type'],
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
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
