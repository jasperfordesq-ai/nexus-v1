<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminReportsController -- Admin user and content report handling.
 *
 * Delegates to legacy controller during migration.
 */
class AdminReportsController extends BaseApiController
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

    /** GET /api/v2/admin/reports */
    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminReportsApiController::class, 'index');
    }

    /** GET /api/v2/admin/reports/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminReportsApiController::class, 'show', [$id]);
    }

    /** POST /api/v2/admin/reports/{id}/resolve */
    public function resolve(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminReportsApiController::class, 'resolve', [$id]);
    }

    /** POST /api/v2/admin/reports/{id}/dismiss */
    public function dismiss(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminReportsApiController::class, 'dismiss', [$id]);
    }

    /** GET /api/v2/admin/reports/stats */
    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminReportsApiController::class, 'stats');
    }
}
