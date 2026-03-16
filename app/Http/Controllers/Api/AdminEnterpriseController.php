<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminEnterpriseController -- Admin enterprise: roles, GDPR, monitoring, health, logs, secrets.
 *
 * Delegates to legacy controller during migration.
 */
class AdminEnterpriseController extends BaseApiController
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

    /** GET /api/v2/admin/enterprise/dashboard */
    public function dashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'dashboard');
    }

    /** GET /api/v2/admin/enterprise/roles */
    public function roles(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'roles');
    }

    /** GET /api/v2/admin/enterprise/roles/{id} */
    public function showRole(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'showRole', [$id]);
    }

    /** POST /api/v2/admin/enterprise/roles */
    public function createRole(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'createRole');
    }

    /** PUT /api/v2/admin/enterprise/roles/{id} */
    public function updateRole(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'updateRole', [$id]);
    }

    /** DELETE /api/v2/admin/enterprise/roles/{id} */
    public function deleteRole(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'deleteRole', [$id]);
    }

    /** GET /api/v2/admin/enterprise/permissions */
    public function permissions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'permissions');
    }

    /** GET /api/v2/admin/enterprise/gdpr */
    public function gdprDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'gdprDashboard');
    }

    /** GET /api/v2/admin/enterprise/gdpr/requests */
    public function gdprRequests(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'gdprRequests');
    }

    /** PUT /api/v2/admin/enterprise/gdpr/requests/{id} */
    public function updateGdprRequest(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'updateGdprRequest', [$id]);
    }

    /** GET /api/v2/admin/enterprise/gdpr/consents */
    public function gdprConsents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'gdprConsents');
    }

    /** GET /api/v2/admin/enterprise/gdpr/breaches */
    public function gdprBreaches(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'gdprBreaches');
    }

    /** POST /api/v2/admin/enterprise/gdpr/breaches */
    public function createBreach(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'createBreach');
    }

    /** GET /api/v2/admin/enterprise/gdpr/audit */
    public function gdprAudit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'gdprAudit');
    }

    /** GET /api/v2/admin/enterprise/monitoring */
    public function monitoring(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'monitoring');
    }

    /** GET /api/v2/admin/enterprise/health */
    public function healthCheck(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'healthCheck');
    }

    /** GET /api/v2/admin/enterprise/logs */
    public function logs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'logs');
    }

    /** GET /api/v2/admin/enterprise/config */
    public function config(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'config');
    }

    /** PUT /api/v2/admin/enterprise/config */
    public function updateConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'updateConfig');
    }

    /** GET /api/v2/admin/enterprise/secrets */
    public function secrets(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminEnterpriseApiController::class, 'secrets');
    }
}
