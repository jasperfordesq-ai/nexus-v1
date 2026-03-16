<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminJobsController -- Admin job vacancy management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminJobsController extends BaseApiController
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

    /** GET /api/v2/admin/jobs */
    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'index');
    }

    /** GET /api/v2/admin/jobs/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'show', [$id]);
    }

    /** DELETE /api/v2/admin/jobs/{id} */
    public function destroy(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'destroy', [$id]);
    }

    /** POST /api/v2/admin/jobs/{id}/feature */
    public function feature(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'feature', [$id]);
    }

    /** POST /api/v2/admin/jobs/{id}/unfeature */
    public function unfeature(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'unfeature', [$id]);
    }

    /** GET /api/v2/admin/jobs/{id}/applications */
    public function getApplications(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'getApplications', [$id]);
    }

    /** PUT /api/v2/admin/jobs/{id}/app-status */
    public function updateApplicationStatus(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminJobsApiController::class, 'updateApplicationStatus', [$id]);
    }
}
