<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\LegalDocumentService;

/**
 * LegalAcceptanceApiController - V2 API for user legal document acceptance
 *
 * Provides Bearer-token-compatible endpoints for the React frontend to:
 * - Check if the current user has pending legal documents to accept
 * - Accept all pending documents in one call
 *
 * Uses BaseApiController (with ApiAuth trait) so both Bearer tokens
 * and PHP sessions are supported.
 *
 * @package Nexus\Controllers\Api
 */
class LegalAcceptanceApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/legal/acceptance/status
     *
     * Returns the current user's legal acceptance status.
     *
     * Response:
     * {
     *   "data": {
     *     "has_pending": bool,
     *     "documents": [
     *       {
     *         "document_id": int,
     *         "document_type": string,        // "terms", "privacy", etc.
     *         "title": string,
     *         "current_version_id": int|null,
     *         "current_version": string|null, // "1.2", etc.
     *         "acceptance_status": "current"|"outdated"|"not_accepted",
     *         "accepted_at": string|null
     *       }
     *     ]
     *   }
     * }
     */
    public function getStatus(): void
    {
        $userId = $this->requireAuth();

        $documents = LegalDocumentService::getUserAcceptanceStatus($userId);
        $hasPending = LegalDocumentService::hasPendingAcceptances($userId);

        $this->respondWithData([
            'has_pending' => $hasPending,
            'documents'   => array_values($documents),
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
    public function acceptAll(): void
    {
        $userId = $this->requireAuth();

        $pending = LegalDocumentService::getDocumentsRequiringAcceptance($userId);

        if (empty($pending)) {
            $this->respondWithData([
                'accepted' => [],
                'message'  => 'No documents require acceptance',
            ]);
            return;
        }

        $accepted = [];
        $errors   = [];

        foreach ($pending as $doc) {
            if (empty($doc['current_version_id'])) {
                continue;
            }

            try {
                LegalDocumentService::recordAcceptanceFromRequest(
                    $userId,
                    (int) $doc['document_id'],
                    (int) $doc['current_version_id'],
                    LegalDocumentService::ACCEPTANCE_LOGIN_PROMPT
                );
                $accepted[] = $doc['document_type'];
            } catch (\Throwable $e) {
                $errors[] = $doc['document_type'];
            }
        }

        if (!empty($errors)) {
            $this->respondWithError(
                'LEGAL_ACCEPT_FAILED',
                'Failed to record acceptance for some documents: ' . implode(', ', $errors),
                null,
                500
            );
            return;
        }

        $this->respondWithData([
            'accepted' => $accepted,
            'message'  => 'All documents accepted',
        ]);
    }
}
