<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringInviteCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Member-facing API for the Caring Community module.
 *
 * Exposes read-only views of data the authenticated member has a stake in,
 * scoped entirely to the current tenant.
 */
class CaringCommunityApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CaringInviteCodeService $inviteCodeService,
    ) {
    }

    /**
     * GET /api/v2/caring-community/invite/{code}  (PUBLIC — no auth)
     *
     * Look up a single invite code's status so the member-facing join page can
     * show the appropriate state (valid / expired / already_used / invalid).
     *
     * Intentionally always returns 200 (never 404) to prevent code enumeration.
     */
    public function lookupInvite(string $code): JsonResponse
    {
        $tenantId = TenantContext::getId();
        $result   = $this->inviteCodeService->lookup($tenantId, strtoupper(trim($code)));

        return $this->respondWithData($result);
    }

    /**
     * POST /api/v2/caring-community/request-help
     *
     * Submit a low-friction help request. Stored in caring_help_requests so
     * coordinators can see and act on it via the workflow console.
     */
    public function requestHelp(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();

        $what = trim((string) ($input['what'] ?? ''));
        $whenNeeded = trim((string) ($input['when'] ?? ''));
        $contactPref = (string) ($input['contact_preference'] ?? 'either');

        $errors = [];
        if ($what === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_required'), 'field' => 'what'];
        } elseif (mb_strlen($what) > 500) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_too_long'), 'field' => 'what'];
        }
        if ($whenNeeded === '') {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_required'), 'field' => 'when'];
        } elseif (mb_strlen($whenNeeded) > 200) {
            $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api.field_too_long'), 'field' => 'when'];
        }
        if (!in_array($contactPref, ['phone', 'message', 'either'], true)) {
            $contactPref = 'either';
        }

        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        try {
            DB::table('caring_help_requests')->insert([
                'tenant_id'          => $tenantId,
                'user_id'            => $userId,
                'what'               => $what,
                'when_needed'        => $whenNeeded,
                'contact_preference' => $contactPref,
                'status'             => 'pending',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CaringCommunity] requestHelp insert failed', [
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData([
            'success' => true,
            'message' => 'caring_community.requests.submitted',
        ], null, 201);
    }

    /**
     * GET /api/v2/caring-community/my-relationships
     *
     * Returns the authenticated member's support relationships (as supporter
     * OR recipient), including partner info and the last 3 vol_logs per
     * relationship.  Limit 50, ordered by status priority then next check-in.
     */
    public function myRelationships(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!Schema::hasTable('caring_support_relationships')) {
            return $this->respondWithData([]);
        }

        $rows = DB::select(
            "SELECT
                csr.id,
                csr.title,
                csr.description,
                csr.frequency,
                csr.expected_hours,
                csr.status,
                csr.start_date,
                csr.end_date,
                csr.last_logged_at,
                csr.next_check_in_at,
                csr.supporter_id,
                csr.recipient_id,
                supporter.name            AS supporter_name,
                supporter.first_name      AS supporter_first_name,
                supporter.last_name       AS supporter_last_name,
                supporter.profile_photo   AS supporter_avatar,
                recipient.name            AS recipient_name,
                recipient.first_name      AS recipient_first_name,
                recipient.last_name       AS recipient_last_name,
                recipient.profile_photo   AS recipient_avatar
             FROM caring_support_relationships csr
             LEFT JOIN users supporter
                    ON supporter.id = csr.supporter_id
                   AND supporter.tenant_id = csr.tenant_id
             LEFT JOIN users recipient
                    ON recipient.id = csr.recipient_id
                   AND recipient.tenant_id = csr.tenant_id
             WHERE csr.tenant_id = ?
               AND (csr.supporter_id = ? OR csr.recipient_id = ?)
               AND csr.status IN ('active', 'paused')
             ORDER BY
                CASE csr.status WHEN 'active' THEN 0 ELSE 1 END,
                COALESCE(csr.next_check_in_at, csr.created_at) ASC,
                csr.id DESC
             LIMIT 50",
            [$tenantId, $userId, $userId]
        );

        if (empty($rows)) {
            return $this->respondWithData([]);
        }

        // Bulk-fetch the last 3 logs per relationship in one query.
        $relationshipIds = array_map(fn (object $r): int => (int) $r->id, $rows);
        $logsData = $this->fetchRecentLogs($tenantId, $relationshipIds);

        $items = array_map(
            fn (object $row): array => $this->formatRelationship($row, $userId, $logsData[(int) $row->id] ?? []),
            $rows
        );

        return $this->respondWithData($items);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the last 3 vol_logs for each of the given relationship IDs,
     * grouped by caring_support_relationship_id.
     *
     * Returns: [ relationship_id => [ log, log, log ], ... ]
     *
     * @param  int[]  $relationshipIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchRecentLogs(int $tenantId, array $relationshipIds): array
    {
        if (
            !Schema::hasTable('vol_logs')
            || !Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
        ) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($relationshipIds), '?'));

        // Rank logs per relationship and take the top 3.
        $logRows = DB::select(
            "SELECT caring_support_relationship_id, date_logged, hours, status
             FROM (
                 SELECT
                     caring_support_relationship_id,
                     date_logged,
                     hours,
                     status,
                     ROW_NUMBER() OVER (
                         PARTITION BY caring_support_relationship_id
                         ORDER BY date_logged DESC, id DESC
                     ) AS rn
                 FROM vol_logs
                 WHERE tenant_id = ?
                   AND caring_support_relationship_id IN ({$placeholders})
             ) ranked
             WHERE rn <= 3",
            array_merge([$tenantId], $relationshipIds)
        );

        $grouped = [];
        foreach ($logRows as $log) {
            $rid = (int) $log->caring_support_relationship_id;
            $grouped[$rid][] = [
                'date'   => (string) $log->date_logged,
                'hours'  => round((float) $log->hours, 2),
                'status' => (string) $log->status,
            ];
        }

        return $grouped;
    }

    /**
     * Format a single relationship row for the member-facing API.
     *
     * @param array<int, array<string, mixed>> $recentLogs
     * @return array<string, mixed>
     */
    private function formatRelationship(object $row, int $authUserId, array $recentLogs): array
    {
        $supporterId = (int) $row->supporter_id;
        $role        = $supporterId === $authUserId ? 'supporter' : 'recipient';
        $partnerId   = $role === 'supporter' ? (int) $row->recipient_id : $supporterId;

        if ($role === 'supporter') {
            $partnerName   = $this->displayName($row, 'recipient');
            $partnerAvatar = $row->recipient_avatar ?? null;
        } else {
            $partnerName   = $this->displayName($row, 'supporter');
            $partnerAvatar = $row->supporter_avatar ?? null;
        }

        return [
            'id'              => (int) $row->id,
            'title'           => (string) $row->title,
            'description'     => (string) ($row->description ?? ''),
            'frequency'       => (string) $row->frequency,
            'expected_hours'  => round((float) $row->expected_hours, 2),
            'status'          => (string) $row->status,
            'start_date'      => (string) $row->start_date,
            'end_date'        => $row->end_date ? (string) $row->end_date : null,
            'last_logged_at'  => $row->last_logged_at ? (string) $row->last_logged_at : null,
            'next_check_in_at' => $row->next_check_in_at ? (string) $row->next_check_in_at : null,
            'role'            => $role,
            'partner'         => [
                'id'         => $partnerId,
                'name'       => $partnerName,
                'avatar_url' => $partnerAvatar ? (string) $partnerAvatar : null,
            ],
            'recent_logs'     => $recentLogs,
        ];
    }

    private function displayName(object $row, string $prefix): string
    {
        $full = trim(
            (string) ($row->{$prefix . '_first_name'} ?? '')
            . ' '
            . (string) ($row->{$prefix . '_last_name'} ?? '')
        );
        return $full !== '' ? $full : (string) ($row->{$prefix . '_name'} ?? '');
    }
}
