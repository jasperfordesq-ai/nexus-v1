<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * AdminDashboardController -- Admin analytics dashboard endpoints.
 */
class AdminDashboardController extends BaseApiController
{
    protected bool $isV2Api = true;

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

    public function dashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDashboardApiController::class, 'dashboard');
    }

    public function userStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDashboardApiController::class, 'userStats');
    }

    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDashboardApiController::class, 'stats');
    }

    public function trends(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDashboardApiController::class, 'trends');
    }

    public function activity(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDashboardApiController::class, 'activity');
    }

    public function apiInsights(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\InsightsController::class, 'apiInsights');
    }
}
