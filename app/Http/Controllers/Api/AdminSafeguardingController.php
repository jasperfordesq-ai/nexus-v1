<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminSafeguardingController -- Admin safeguarding module endpoints.
 *
 * Provides dashboard stats, flagged message management, guardian assignment
 * CRUD, and member safeguarding preference review for the admin panel.
 *
 * Frontend contract: react-frontend/src/admin/modules/safeguarding/SafeguardingDashboard.tsx
 */
class AdminSafeguardingController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /v2/admin/safeguarding/dashboard
     *
     * Returns aggregate stats matching the SafeguardingDashboard frontend:
     * { active_assignments, unreviewed_flags, consented_wards, total_flags_this_month, critical_flags }
     */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $activeAssignments = 0;
        $unreviewedFlags = 0;
        $consentedWards = 0;
        $totalFlagsThisMonth = 0;
        $criticalFlags = 0;

        // Active safeguarding assignments (not revoked, consent given)
        try {
            $activeAssignments = (int) DB::table('safeguarding_assignments')
                ->where('tenant_id', $tenantId)
                ->whereNull('revoked_at')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$this->isTableNotFound($e)) {
                throw $e;
            }
        }

        // Unreviewed broker message copies (pending review)
        try {
            $unreviewedFlags = (int) DB::table('broker_message_copies')
                ->where('tenant_id', $tenantId)
                ->whereNull('reviewed_at')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$this->isTableNotFound($e)) {
                throw $e;
            }
        }

        // Consented wards (assignments where the ward has given consent)
        try {
            $consentedWards = (int) DB::table('safeguarding_assignments')
                ->where('tenant_id', $tenantId)
                ->whereNull('revoked_at')
                ->whereNotNull('consent_given_at')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$this->isTableNotFound($e)) {
                throw $e;
            }
        }

        // Total flags this month (broker message copies created this month)
        try {
            $totalFlagsThisMonth = (int) DB::table('broker_message_copies')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$this->isTableNotFound($e)) {
                throw $e;
            }
        }

        // Critical: flagged copies + unreviewed safeguarding incidents
        try {
            $criticalFlags = (int) DB::table('broker_message_copies')
                ->where('tenant_id', $tenantId)
                ->where('flagged', true)
                ->whereNull('reviewed_at')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            if (!$this->isTableNotFound($e)) {
                throw $e;
            }
        }

        // Also count unreviewed safeguarding incidents as critical
        try {
            $criticalFlags += (int) DB::table('vol_safeguarding_incidents')
                ->where('tenant_id', $tenantId)
                ->whereIn('severity', ['high', 'critical'])
                ->where('status', 'open')
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            // Table may not exist yet
        }

        return $this->respondWithData([
            'active_assignments' => $activeAssignments,
            'unreviewed_flags' => $unreviewedFlags,
            'consented_wards' => $consentedWards,
            'total_flags_this_month' => $totalFlagsThisMonth,
            'critical_flags' => $criticalFlags,
        ]);
    }

    /**
     * GET /v2/admin/safeguarding/flagged-messages
     *
     * Returns broker message copies as flagged messages in the shape the frontend expects:
     * { id, message_content, sender: { id, name, avatar_url }, recipient: { id, name, avatar_url },
     *   severity, flag_reason, is_reviewed, reviewed_by, review_notes, reviewed_at, created_at }
     */
    public function flaggedMessages(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 50, 1, 200);
        $offset = ($page - 1) * $limit;

        try {
            $baseQuery = DB::table('broker_message_copies as bmc')
                ->where('bmc.tenant_id', $tenantId);

            $total = (int) (clone $baseQuery)->count();

            $copies = $baseQuery
                ->leftJoin('users as sender', 'bmc.sender_id', '=', 'sender.id')
                ->leftJoin('users as receiver', 'bmc.receiver_id', '=', 'receiver.id')
                ->leftJoin('users as reviewer', 'bmc.reviewed_by', '=', 'reviewer.id')
                ->select([
                    'bmc.id',
                    'bmc.original_message_id as message_id',
                    'bmc.message_body as message_content',
                    'bmc.sender_id',
                    DB::raw("COALESCE(sender.name, CONCAT(COALESCE(sender.first_name, ''), ' ', COALESCE(sender.last_name, ''))) as sender_name"),
                    'sender.avatar_url as sender_avatar',
                    'bmc.receiver_id',
                    DB::raw("COALESCE(receiver.name, CONCAT(COALESCE(receiver.first_name, ''), ' ', COALESCE(receiver.last_name, ''))) as receiver_name"),
                    'receiver.avatar_url as receiver_avatar',
                    'bmc.copy_reason',
                    'bmc.flagged',
                    'bmc.reviewed_by as reviewed_by_id',
                    DB::raw("COALESCE(reviewer.name, CONCAT(COALESCE(reviewer.first_name, ''), ' ', COALESCE(reviewer.last_name, ''))) as reviewed_by_name"),
                    'bmc.reviewed_at',
                    'bmc.review_notes',
                    'bmc.created_at',
                ])
                ->orderByDesc('bmc.flagged')
                ->orderByDesc('bmc.created_at')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($row) {
                    $severity = 'low';
                    if ($row->flagged) {
                        $severity = 'high';
                    } elseif ($row->copy_reason === 'flagged_user') {
                        $severity = 'medium';
                    }

                    return [
                        'id' => (int) $row->id,
                        'message_id' => (int) $row->message_id,
                        'message_content' => $row->message_content ?? '',
                        'sender' => [
                            'id' => (int) $row->sender_id,
                            'name' => trim($row->sender_name ?? ''),
                            'avatar_url' => $row->sender_avatar,
                        ],
                        'recipient' => [
                            'id' => (int) $row->receiver_id,
                            'name' => trim($row->receiver_name ?? ''),
                            'avatar_url' => $row->receiver_avatar,
                        ],
                        'severity' => $severity,
                        'flag_reason' => $row->copy_reason ?? 'unknown',
                        'is_reviewed' => $row->reviewed_at !== null,
                        'reviewed_by' => $row->reviewed_by_name ? trim($row->reviewed_by_name) : null,
                        'review_notes' => $row->review_notes ?? null,
                        'reviewed_at' => $row->reviewed_at,
                        'created_at' => $row->created_at,
                    ];
                })
                ->toArray();

            return $this->respondWithPaginatedCollection($copies, $total, $page, $limit);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithPaginatedCollection([], 0, 1, $limit);
            }
            throw $e;
        }
    }

    /**
     * GET /v2/admin/safeguarding/assignments
     *
     * Returns guardian assignments in the shape the frontend expects:
     * { id, ward: { id, name, avatar_url }, guardian: { id, name, avatar_url },
     *   status, consent_given, created_at, expires_at }
     */
    public function assignments(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $assignments = DB::table('safeguarding_assignments as sa')
                ->join('users as ward', 'sa.ward_user_id', '=', 'ward.id')
                ->join('users as guardian', 'sa.guardian_user_id', '=', 'guardian.id')
                ->where('sa.tenant_id', $tenantId)
                ->select([
                    'sa.id',
                    'sa.ward_user_id',
                    DB::raw("COALESCE(ward.name, CONCAT(COALESCE(ward.first_name, ''), ' ', COALESCE(ward.last_name, ''))) as ward_name"),
                    'ward.avatar_url as ward_avatar',
                    'sa.guardian_user_id',
                    DB::raw("COALESCE(guardian.name, CONCAT(COALESCE(guardian.first_name, ''), ' ', COALESCE(guardian.last_name, ''))) as guardian_name"),
                    'guardian.avatar_url as guardian_avatar',
                    'sa.consent_given_at',
                    'sa.revoked_at',
                    'sa.assigned_at',
                    'sa.notes',
                ])
                ->orderByDesc('sa.assigned_at')
                ->get()
                ->map(function ($row) {
                    $status = 'pending';
                    if ($row->revoked_at !== null) {
                        $status = 'revoked';
                    } elseif ($row->consent_given_at !== null) {
                        $status = 'active';
                    }

                    return [
                        'id' => (int) $row->id,
                        'ward' => [
                            'id' => (int) $row->ward_user_id,
                            'name' => trim($row->ward_name ?? ''),
                            'avatar_url' => $row->ward_avatar,
                        ],
                        'guardian' => [
                            'id' => (int) $row->guardian_user_id,
                            'name' => trim($row->guardian_name ?? ''),
                            'avatar_url' => $row->guardian_avatar,
                        ],
                        'status' => $status,
                        'consent_given' => $row->consent_given_at !== null,
                        'created_at' => $row->assigned_at,
                        'expires_at' => null,
                    ];
                })
                ->toArray();

            return $this->respondWithData($assignments);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e) || str_contains($e->getMessage(), 'Column not found')) {
                return $this->respondWithData([]);
            }
            throw $e;
        }
    }

    /**
     * POST /v2/admin/safeguarding/flagged-messages/{id}/review
     *
     * Reviews a flagged message (broker_message_copies). Frontend sends { notes }.
     * Marks the copy as reviewed by the current admin.
     */
    public function reviewMessage(Request $request, int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $notes = $request->input('notes', '');

        try {
            $updateData = [
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ];
            if (!empty($notes)) {
                $updateData['action_notes'] = $notes;
                $updateData['action_taken'] = 'reviewed';
            }

            $affected = DB::table('broker_message_copies')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->whereNull('reviewed_at')
                ->update(array_merge($updateData, [
                    'review_notes' => !empty($notes) ? $notes : null,
                ]));

            if ($affected === 0) {
                return $this->respondWithError(
                    'NOT_FOUND',
                    __('api.safeguarding_message_not_found_or_reviewed'),
                    null,
                    404
                );
            }

            // Audit log the review
            DB::table('activity_log')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $adminId,
                'action' => 'safeguarding_message_reviewed',
                'action_type' => 'safeguarding',
                'entity_type' => 'broker_message_copy',
                'entity_id' => $id,
                'details' => json_encode(['notes' => $notes]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $this->respondWithData(['reviewed' => true]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    __('api.safeguarding_tables_not_created'),
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
     * Creates a new safeguarding (guardian/ward) assignment.
     * Accepts either user IDs or email addresses:
     *   { user_id, assignee_id } OR { ward_email, guardian_email }
     */
    public function createAssignment(Request $request): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $wardId = null;
        $guardianId = null;

        // Support both ID-based and email-based input from the frontend
        if ($request->has('ward_email') || $request->has('guardian_email')) {
            $wardEmail = trim($request->input('ward_email', ''));
            $guardianEmail = trim($request->input('guardian_email', ''));

            if (empty($wardEmail) || empty($guardianEmail)) {
                return $this->respondWithError('VALIDATION_ERROR', __('api.ward_guardian_emails_required'), null, 422);
            }

            $ward = \App\Models\User::where('email', $wardEmail)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            $guardian = \App\Models\User::where('email', $guardianEmail)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if (!$ward) {
                return $this->respondWithError('INVALID_USER', __('api.no_active_member_found', ['email' => $wardEmail]), 'ward_email', 404);
            }
            if (!$guardian) {
                return $this->respondWithError('INVALID_USER', __('api.no_active_member_found', ['email' => $guardianEmail]), 'guardian_email', 404);
            }

            $wardId = $ward->id;
            $guardianId = $guardian->id;
        } else {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'assignee_id' => 'required|integer',
            ]);

            $wardId = $validated['user_id'];
            $guardianId = $validated['assignee_id'];

            // Validate both users belong to current tenant
            if (!\App\Models\User::where('id', $wardId)->where('tenant_id', $tenantId)->exists()) {
                return $this->respondWithError('INVALID_USER', __('api.ward_not_found_in_tenant'), 'user_id', 404);
            }
            if (!\App\Models\User::where('id', $guardianId)->where('tenant_id', $tenantId)->exists()) {
                return $this->respondWithError('INVALID_USER', __('api.guardian_not_found_in_tenant'), 'assignee_id', 404);
            }
        }

        if ($wardId === $guardianId) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.ward_guardian_same_person'), null, 422);
        }

        $notes = $request->input('notes', '');

        try {
            $id = DB::table('safeguarding_assignments')->insertGetId([
                'tenant_id' => $tenantId,
                'ward_user_id' => $wardId,
                'guardian_user_id' => $guardianId,
                'assigned_by' => $adminId,
                'assigned_at' => now(),
                'notes' => $notes ?: null,
            ]);

            // Audit log
            DB::table('activity_log')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $adminId,
                'action' => 'safeguarding_assignment_created',
                'action_type' => 'safeguarding',
                'entity_type' => 'safeguarding_assignment',
                'entity_id' => $id,
                'details' => json_encode([
                    'ward_user_id' => $wardId,
                    'guardian_user_id' => $guardianId,
                ]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            // Notify both the ward and guardian
            try {
                $wardUser = \App\Models\User::find($wardId);
                $guardianUser = \App\Models\User::find($guardianId);

                // Notify guardian
                \App\Models\Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $guardianId,
                    'type' => 'safeguarding_assignment',
                    'message' => 'You have been assigned as a safeguarding guardian for ' . ($wardUser->name ?? 'a member'),
                    'link' => '/admin/safeguarding',
                    'is_read' => false,
                ]);

                // Notify ward — they must know a guardian has been assigned
                \App\Models\Notification::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $wardId,
                    'type' => 'safeguarding_assignment',
                    'message' => ($guardianUser->name ?? 'A community coordinator') . ' has been assigned as your safeguarding support contact. You can give or revoke consent in your settings.',
                    'link' => '/settings/safeguarding',
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to notify ward/guardian of assignment', ['error' => $e->getMessage()]);
            }

            return $this->respondWithData(['id' => $id, 'created' => true]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    __('api.safeguarding_tables_not_created'),
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
     * Soft-deletes (revokes) a safeguarding assignment to preserve the audit trail.
     */
    public function deleteAssignment(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $affected = DB::table('safeguarding_assignments')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);

            if ($affected === 0) {
                return $this->respondWithError(
                    'NOT_FOUND',
                    __('api.safeguarding_assignment_not_found_or_revoked'),
                    null,
                    404
                );
            }

            // Audit log the revocation
            DB::table('activity_log')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $adminId,
                'action' => 'safeguarding_assignment_revoked',
                'action_type' => 'safeguarding',
                'entity_type' => 'safeguarding_assignment',
                'entity_id' => $id,
                'details' => json_encode(['revoked_by_admin' => $adminId]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);

            return $this->respondWithData(['revoked' => true]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->isTableNotFound($e)) {
                return $this->respondWithError(
                    'TABLE_NOT_FOUND',
                    __('api.safeguarding_tables_not_created'),
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
        $tenantId = TenantContext::getId();

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
                'tenant_id' => $tenantId,
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
