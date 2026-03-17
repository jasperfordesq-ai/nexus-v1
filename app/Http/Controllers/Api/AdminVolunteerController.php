<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * AdminVolunteerController -- Admin volunteer management.
 */
class AdminVolunteerController extends BaseApiController
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

    public function opportunities(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'opportunities');
    }

    public function applications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'applications');
    }

    public function verifyHours(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'verifyHours', func_get_args());
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'index');
    }

    public function approvals(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'approvals');
    }

    public function organizations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'organizations');
    }

    public function approveApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'approveApplication', func_get_args());
    }

    public function declineApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'declineApplication', func_get_args());
    }

    public function sendShiftReminders(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'sendShiftReminders');
    }
}
