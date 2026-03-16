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

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function apiCompareVersions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'apiCompareVersions');
    }


    public function apiGetVersion($versionId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'apiGetVersion', [$versionId]);
    }


    public function apiGetVersions($type): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'apiGetVersions', [$type]);
    }


    public function apiGetDocument($type): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'apiGetDocument', [$type]);
    }


    public function accept(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'accept');
    }


    public function status(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\LegalDocumentController::class, 'status');
    }

}
