<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminMatchingController -- Admin matching approval, configuration, and statistics.
 *
 * Delegates to legacy controller during migration.
 */
class AdminMatchingController extends BaseApiController
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

    /** GET /api/v2/admin/matching */
    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'index');
    }

    /** GET /api/v2/admin/matching/approval-stats */
    public function approvalStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'approvalStats');
    }

    /** GET /api/v2/admin/matching/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'show', [$id]);
    }

    /** POST /api/v2/admin/matching/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'approve', [$id]);
    }

    /** POST /api/v2/admin/matching/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'reject', [$id]);
    }

    /** GET /api/v2/admin/matching/config */
    public function getConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'getConfig');
    }

    /** PUT /api/v2/admin/matching/config */
    public function updateConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'updateConfig');
    }

    /** POST /api/v2/admin/matching/clear-cache */
    public function clearCache(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'clearCache');
    }

    /** GET /api/v2/admin/matching/stats */
    public function getStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminMatchingApiController::class, 'getStats');
    }
}
