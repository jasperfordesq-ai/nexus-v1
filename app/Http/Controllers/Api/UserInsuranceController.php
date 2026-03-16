<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * UserInsuranceController -- User insurance certificate upload and listing.
 *
 * Delegates to legacy controller during migration.
 */
class UserInsuranceController extends BaseApiController
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

    /** GET /api/v2/insurance */
    public function list(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UserInsuranceApiController::class, 'list');
    }

    /** POST /api/v2/insurance/upload */
    public function upload(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UserInsuranceApiController::class, 'upload');
    }
}
