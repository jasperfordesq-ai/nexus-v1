<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminDeliverabilityController -- Admin deliverability dashboard and deliverable CRUD.
 *
 * Delegates to legacy controller during migration.
 */
class AdminDeliverabilityController extends BaseApiController
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

    /** GET /api/v2/admin/deliverability/dashboard */
    public function getDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getDashboard');
    }

    /** GET /api/v2/admin/deliverability/analytics */
    public function getAnalytics(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getAnalytics');
    }

    /** GET /api/v2/admin/deliverability */
    public function getDeliverables(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getDeliverables');
    }

    /** GET /api/v2/admin/deliverability/{id} */
    public function getDeliverable(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getDeliverable', [$id]);
    }

    /** POST /api/v2/admin/deliverability */
    public function createDeliverable(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'createDeliverable');
    }

    /** PUT /api/v2/admin/deliverability/{id} */
    public function updateDeliverable(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'updateDeliverable', [$id]);
    }

    /** DELETE /api/v2/admin/deliverability/{id} */
    public function deleteDeliverable(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'deleteDeliverable', [$id]);
    }

    /** POST /api/v2/admin/deliverability/{id}/comments */
    public function addComment(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'addComment', [$id]);
    }
}
