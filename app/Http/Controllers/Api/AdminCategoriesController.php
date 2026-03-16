<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminCategoriesController -- Admin category and attribute management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminCategoriesController extends BaseApiController
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

    /** GET /api/v2/admin/categories */
    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'index');
    }

    /** POST /api/v2/admin/categories */
    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'store');
    }

    /** PUT /api/v2/admin/categories/{id} */
    public function update(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'update', [$id]);
    }

    /** DELETE /api/v2/admin/categories/{id} */
    public function destroy(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'destroy', [$id]);
    }

    /** GET /api/v2/admin/categories/attributes */
    public function listAttributes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'listAttributes');
    }

    /** POST /api/v2/admin/categories/attributes */
    public function storeAttribute(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'storeAttribute');
    }

    /** PUT /api/v2/admin/categories/attributes/{id} */
    public function updateAttribute(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'updateAttribute', [$id]);
    }

    /** DELETE /api/v2/admin/categories/attributes/{id} */
    public function destroyAttribute(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCategoriesApiController::class, 'destroyAttribute', [$id]);
    }
}
