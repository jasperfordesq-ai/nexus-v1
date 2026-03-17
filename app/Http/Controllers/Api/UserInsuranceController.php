<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\InsuranceCertificateService;

/**
 * UserInsuranceController -- User insurance certificate upload and listing.
 *
 * list() is native; upload() is kept as delegation because it handles file uploads.
 *
 * Endpoints:
 *   GET  /api/v2/users/me/insurance  list()
 *   POST /api/v2/users/me/insurance  upload()  (delegation — file upload)
 */
class UserInsuranceController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/insurance
     *
     * List the authenticated user's insurance certificates.
     */
    public function list(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $records = InsuranceCertificateService::getUserCertificates($userId);
            return $this->respondWithData($records);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/users/me/insurance
     *
     * Upload a new insurance certificate. Kept as delegation because it
     * handles multipart file uploads via $_FILES.
     */
    public function upload(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UserInsuranceApiController::class, 'upload');
    }

    /**
     * Delegate to legacy controller via output buffering.
     * Kept only for upload() which handles file uploads.
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
}
