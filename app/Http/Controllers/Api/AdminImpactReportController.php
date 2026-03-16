<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminImpactReportController -- Admin impact report configuration.
 *
 * Delegates to legacy controller during migration.
 */
class AdminImpactReportController extends BaseApiController
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

    /** GET /api/v2/admin/impact-report */
    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminImpactReportApiController::class, 'index');
    }

    /** PUT /api/v2/admin/impact-report/config */
    public function updateConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminImpactReportApiController::class, 'updateConfig');
    }
}
