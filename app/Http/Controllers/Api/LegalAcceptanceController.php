<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

/**
 * LegalAcceptanceController -- Legal document acceptance status and bulk accept.
 *
 * Native Eloquent implementation — no delegation to legacy controller.
 */
class LegalAcceptanceController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/legal/acceptance/status
     *
     * Returns the current user's legal acceptance status for all required documents.
     *
     * Response:
     * {
     *   "data": {
     *     "has_pending": bool,
     *     "documents": [
     *       {
     *         "document_id": int,
     *         "document_type": string,
     *         "title": string,
     *         "current_version_id": int|null,
     *         "current_version": string|null,
     *         "acceptance_status": "current"|"outdated"|"not_accepted",
     *         "accepted_at": string|null
     *       }
     *     ]
     *   }
     * }
     */
    public function getStatus(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $documents = $this->getUserAcceptanceStatus($userId, $tenantId);
        $hasPending = collect($documents)->contains(fn ($doc) => $doc->acceptance_status !== 'current');

        return $this->respondWithData([
            'has_pending' => $hasPending,
            'documents'   => collect($documents)->map(fn ($doc) => [
                'document_id'        => (int) $doc->document_id,
                'document_type'      => $doc->document_type,
                'title'              => $doc->title,
                'current_version_id' => $doc->current_version_id ? (int) $doc->current_version_id : null,
                'current_version'    => $doc->current_version,
                'acceptance_status'  => $doc->acceptance_status,
                'accepted_at'        => $doc->accepted_at,
            ])->values()->all(),
        ]);
    }

    /**
     * POST /api/v2/legal/acceptance/accept-all
     *
     * Accepts all documents the current user has not yet accepted (or that
     * have been updated since their last acceptance).
     *
     * Response:
     * {
     *   "data": {
     *     "accepted": ["terms", "privacy"],
     *     "message": "All documents accepted"
     *   }
     * }
     */
    public function acceptAll(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $documents = $this->getUserAcceptanceStatus($userId, $tenantId);
        $pending = collect($documents)->filter(fn ($doc) => $doc->acceptance_status !== 'current');

        if ($pending->isEmpty()) {
            return $this->respondWithData([
                'accepted' => [],
                'message'  => 'No documents require acceptance',
            ]);
        }

        $accepted = [];
        $errors   = [];

        foreach ($pending as $doc) {
            if (empty($doc->current_version_id)) {
                continue;
            }

            try {
                DB::table('user_legal_acceptances')->insert([
                    'user_id'        => $userId,
                    'document_id'    => (int) $doc->document_id,
                    'version_id'     => (int) $doc->current_version_id,
                    'version_number' => $doc->current_version,
                    'method'         => 'login_prompt',
                    'ip_address'     => request()->ip(),
                    'user_agent'     => request()->userAgent(),
                    'accepted_at'    => now(),
                    'created_at'     => now(),
                ]);
                $accepted[] = $doc->document_type;
            } catch (\Throwable $e) {
                $errors[] = $doc->document_type;
            }
        }

        if (! empty($errors)) {
            return $this->respondWithError(
                'LEGAL_ACCEPT_FAILED',
                'Failed to record acceptance for some documents: ' . implode(', ', $errors),
                null,
                500
            );
        }

        return $this->respondWithData([
            'accepted' => $accepted,
            'message'  => 'All documents accepted',
        ]);
    }

    // ================================================================
    // Private helpers
    // ================================================================

    /**
     * Get user's acceptance status for all required documents.
     *
     * Replicates the exact SQL from LegalDocumentService::getUserAcceptanceStatus()
     * using Laravel's query builder.
     *
     * @return array<object>
     */
    private function getUserAcceptanceStatus(int $userId, int $tenantId): array
    {
        return DB::select("
            SELECT
                ld.id AS document_id,
                ld.document_type,
                ld.title,
                ld.requires_acceptance,
                ld.current_version_id,
                ldv.version_number AS current_version,
                ldv.effective_date,
                ula.id AS acceptance_id,
                ula.version_id AS accepted_version_id,
                ula.version_number AS accepted_version,
                ula.accepted_at,
                CASE
                    WHEN ula.version_id IS NULL THEN 'not_accepted'
                    WHEN ula.version_id = ld.current_version_id THEN 'current'
                    ELSE 'outdated'
                END AS acceptance_status
            FROM legal_documents ld
            LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
            LEFT JOIN user_legal_acceptances ula ON ula.user_id = ?
                AND ula.document_id = ld.id
                AND ula.version_id = (
                    SELECT MAX(ula2.version_id)
                    FROM user_legal_acceptances ula2
                    WHERE ula2.user_id = ? AND ula2.document_id = ld.id
                )
            WHERE ld.tenant_id = ?
            AND ld.is_active = 1
            AND ld.requires_acceptance = 1
            AND ld.current_version_id IS NOT NULL
        ", [$userId, $userId, $tenantId]);
    }
}
