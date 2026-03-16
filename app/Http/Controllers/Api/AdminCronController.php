<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminCronController -- Admin cron job logs, settings, and health metrics.
 *
 * Delegates to legacy controller during migration.
 */
class AdminCronController extends BaseApiController
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

    /** GET /api/v2/admin/cron/logs */
    public function getLogs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'getLogs');
    }

    /** GET /api/v2/admin/cron/logs/{logId} */
    public function getLogDetail(int $logId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'getLogDetail', [$logId]);
    }

    /** DELETE /api/v2/admin/cron/logs */
    public function clearLogs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'clearLogs');
    }

    /** GET /api/v2/admin/cron/jobs/{jobId}/settings */
    public function getJobSettings(int $jobId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'getJobSettings', [$jobId]);
    }

    /** PUT /api/v2/admin/cron/jobs/{jobId}/settings */
    public function updateJobSettings(int $jobId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'updateJobSettings', [$jobId]);
    }

    /** GET /api/v2/admin/cron/settings */
    public function getGlobalSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'getGlobalSettings');
    }

    /** PUT /api/v2/admin/cron/settings */
    public function updateGlobalSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'updateGlobalSettings');
    }

    /** GET /api/v2/admin/cron/health */
    public function getHealthMetrics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCronApiController::class, 'getHealthMetrics');
    }
}
