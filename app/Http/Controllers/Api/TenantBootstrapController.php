<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * TenantBootstrapController -- Tenant configuration bootstrap for SPA init.
 * Uses delegation to legacy controller for guaranteed response compatibility.
 */
class TenantBootstrapController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/tenant/bootstrap */
    public function bootstrap(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\TenantBootstrapController::class, 'bootstrap');
    }

    /** GET /api/v2/tenants */
    public function list(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\TenantBootstrapController::class, 'list');
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


    public function platformStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\TenantBootstrapController::class, 'platformStats');
    }

}
