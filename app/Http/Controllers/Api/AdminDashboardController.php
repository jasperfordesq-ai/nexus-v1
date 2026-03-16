<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\AdminAnalyticsService;

/**
 * AdminDashboardController -- Admin analytics dashboard endpoints.
 *
 * All endpoints require admin authentication.
 */
class AdminDashboardController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly AdminAnalyticsService $analyticsService,
    ) {}

    /** GET /api/v2/admin/dashboard */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period = $this->query('period', '30d');
        
        $stats = $this->analyticsService->getDashboard($tenantId, $period);
        
        return $this->respondWithData($stats);
    }

    /** GET /api/v2/admin/user-stats */
    public function userStats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period = $this->query('period', '30d');
        $groupBy = $this->query('group_by', 'day');
        
        $stats = $this->analyticsService->getUserStats($tenantId, $period, $groupBy);
        
        return $this->respondWithData($stats);
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
