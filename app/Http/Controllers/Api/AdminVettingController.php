<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminVettingController -- Admin member vetting and background check management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminVettingController extends BaseApiController
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

    /** GET /api/v2/admin/vetting */
    public function list(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'list');
    }

    /** GET /api/v2/admin/vetting/stats */
    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'stats');
    }

    /** GET /api/v2/admin/vetting/{id} */
    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'show', [$id]);
    }

    /** POST /api/v2/admin/vetting */
    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'store');
    }

    /** PUT /api/v2/admin/vetting/{id} */
    public function update(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'update', [$id]);
    }

    /** POST /api/v2/admin/vetting/{id}/verify */
    public function verify(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'verify', [$id]);
    }

    /** POST /api/v2/admin/vetting/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'reject', [$id]);
    }

    /** DELETE /api/v2/admin/vetting/{id} */
    public function destroy(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'destroy', [$id]);
    }

    /** POST /api/v2/admin/vetting/bulk */
    public function bulk(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'bulk');
    }

    /** GET /api/v2/admin/vetting/user/{userId} */
    public function getUserRecords(int $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'getUserRecords', [$userId]);
    }

    /** POST /api/v2/admin/vetting/{id}/document */
    public function uploadDocument(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVettingApiController::class, 'uploadDocument', [$id]);
    }
}
