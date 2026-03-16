<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\LegalDocumentService;
use Illuminate\Http\JsonResponse;

/**
 * LegalController — Legal documents (terms, privacy policy, etc.).
 */
class LegalController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LegalDocumentService $legalService,
    ) {}

    /**
     * GET /api/v2/legal/{slug}
     *
     * Get the current version of a legal document by slug.
     * Slugs: terms-of-service, privacy-policy, acceptable-use, cookie-policy.
     */
    public function getDocument(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $document = $this->legalService->getCurrentDocument($slug, $tenantId);

        if ($document === null) {
            return $this->respondWithError('NOT_FOUND', 'Document not found', null, 404);
        }

        return $this->respondWithData($document);
    }

    /**
     * GET /api/v2/legal/{slug}/versions
     *
     * Get all published versions of a legal document.
     */
    public function getVersions(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $versions = $this->legalService->getVersions($slug, $tenantId);

        return $this->respondWithData($versions);
    }

    /**
     * POST /api/v2/legal/accept-all
     *
     * Accept all current legal documents for the authenticated user.
     */
    public function acceptAll(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $this->legalService->acceptAll($userId, $tenantId);

        return $this->respondWithData(['message' => 'All legal documents accepted']);
    }
}
