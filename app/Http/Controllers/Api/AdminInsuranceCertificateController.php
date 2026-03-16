<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminInsuranceCertificateController -- Admin insurance certificate management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminInsuranceCertificateController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

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

    /** GET /api/v2/admin/insurance-certificates */
    public function list(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'list');
    }

    /** GET /api/v2/admin/insurance-certificates/stats */
    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'stats');
    }

    /** GET /api/v2/admin/insurance-certificates/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'show', [$id]);
    }

    /** POST /api/v2/admin/insurance-certificates */
    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'store');
    }

    /** PUT /api/v2/admin/insurance-certificates/{id} */
    public function update(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'update', [$id]);
    }

    /** POST /api/v2/admin/insurance-certificates/{id}/verify */
    public function verify(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'verify', [$id]);
    }

    /** POST /api/v2/admin/insurance-certificates/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'reject', [$id]);
    }

    /** DELETE /api/v2/admin/insurance-certificates/{id} */
    public function destroy(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'destroy', [$id]);
    }

    /** GET /api/v2/admin/insurance/user/{userId} */
    public function getUserCertificates(int $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminInsuranceCertificateApiController::class, 'getUserCertificates', [$userId]);
    }
}
