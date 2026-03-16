<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminLegalDocController -- Admin legal document version management and compliance.
 *
 * Delegates to legacy controller during migration.
 */
class AdminLegalDocController extends BaseApiController
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

    /** GET /api/v2/admin/legal-docs/{docId}/versions */
    public function getVersions(int $docId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'getVersions', [$docId]);
    }

    /** GET /api/v2/admin/legal-docs/{docId}/compare */
    public function compareVersions(int $docId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'compareVersions', [$docId]);
    }

    /** POST /api/v2/admin/legal-docs/{docId}/versions */
    public function createVersion(int $docId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'createVersion', [$docId]);
    }

    /** POST /api/v2/admin/legal-docs/versions/{vid}/publish */
    public function publishVersion(int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'publishVersion', [$vid]);
    }

    /** GET /api/v2/admin/legal-docs/compliance-stats */
    public function getComplianceStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'getComplianceStats');
    }

    /** GET /api/v2/admin/legal-docs/versions/{vid}/acceptances */
    public function getAcceptances(int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'getAcceptances', [$vid]);
    }

    /** GET /api/v2/admin/legal-docs/{docId}/export */
    public function exportAcceptances(int $docId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'exportAcceptances', [$docId]);
    }

    /** POST /api/v2/admin/legal-docs/{docId}/versions/{vid}/notify */
    public function notifyUsers(int $docId, int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'notifyUsers', [$docId, $vid]);
    }

    /** GET /api/v2/admin/legal-docs/{docId}/versions/{vid}/pending */
    public function getUsersPendingCount(int $docId, int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'getUsersPendingCount', [$docId, $vid]);
    }

    /** PUT /api/v2/admin/legal-docs/{docId}/versions/{vid} */
    public function updateVersion(int $docId, int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'updateVersion', [$docId, $vid]);
    }

    /** DELETE /api/v2/admin/legal-docs/{docId}/versions/{vid} */
    public function deleteVersion(int $docId, int $vid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminLegalDocController::class, 'deleteVersion', [$docId, $vid]);
    }
}
