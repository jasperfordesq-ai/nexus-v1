<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminTimebankingController -- Admin timebanking stats, alerts, balance adjustments, org wallets.
 *
 * Delegates to legacy controller during migration.
 */
class AdminTimebankingController extends BaseApiController
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

    /** GET /api/v2/admin/timebanking/stats */
    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'stats');
    }

    /** GET /api/v2/admin/timebanking/alerts */
    public function alerts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'alerts');
    }

    /** PUT /api/v2/admin/timebanking/alerts/{id} */
    public function updateAlert(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'updateAlert', [$id]);
    }

    /** POST /api/v2/admin/timebanking/adjust-balance */
    public function adjustBalance(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'adjustBalance');
    }

    /** GET /api/v2/admin/timebanking/org-wallets */
    public function orgWallets(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'orgWallets');
    }

    /** GET /api/v2/admin/timebanking/user-report */
    public function userReport(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'userReport');
    }

    /** GET /api/v2/admin/timebanking/user-statement */
    public function userStatement(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'userStatement');
    }

    public function userSearchApi(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Admin\TimebankingController::class, 'userSearchApi');
    }

}
