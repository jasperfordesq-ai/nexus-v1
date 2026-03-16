<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminBrokerController -- Admin time-broker exchange monitoring and risk management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminBrokerController extends BaseApiController
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

    /** GET /api/v2/admin/broker/dashboard */
    public function dashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'dashboard');
    }

    /** GET /api/v2/admin/broker/exchanges */
    public function exchanges(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'exchanges');
    }

    /** GET /api/v2/admin/broker/exchanges/{id} */
    public function showExchange(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'showExchange', [$id]);
    }

    /** POST /api/v2/admin/broker/exchanges/{id}/approve */
    public function approveExchange(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'approveExchange', [$id]);
    }

    /** POST /api/v2/admin/broker/exchanges/{id}/reject */
    public function rejectExchange(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'rejectExchange', [$id]);
    }

    /** GET /api/v2/admin/broker/risk-tags */
    public function riskTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'riskTags');
    }

    /** GET /api/v2/admin/broker/messages */
    public function messages(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'messages');
    }

    /** GET /api/v2/admin/broker/messages/{id} */
    public function showMessage(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'showMessage', [$id]);
    }

    /** POST /api/v2/admin/broker/messages/{id}/review */
    public function reviewMessage(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'reviewMessage', [$id]);
    }

    /** POST /api/v2/admin/broker/messages/{id}/approve */
    public function approveMessage(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'approveMessage', [$id]);
    }

    /** POST /api/v2/admin/broker/messages/{id}/flag */
    public function flagMessage(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'flagMessage', [$id]);
    }

    /** GET /api/v2/admin/broker/monitoring */
    public function monitoring(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'monitoring');
    }

    /** GET /api/v2/admin/broker/archives */
    public function archives(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'archives');
    }

    /** GET /api/v2/admin/broker/archives/{id} */
    public function showArchive(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'showArchive', [$id]);
    }

    /** POST /api/v2/admin/broker/users/{userId}/monitoring */
    public function setMonitoring(int $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'setMonitoring', [$userId]);
    }

    /** POST /api/v2/admin/broker/listings/{lid}/risk-tag */
    public function saveRiskTag(int $lid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'saveRiskTag', [$lid]);
    }

    /** DELETE /api/v2/admin/broker/listings/{lid}/risk-tag */
    public function removeRiskTag(int $lid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'removeRiskTag', [$lid]);
    }

    /** GET /api/v2/admin/broker/configuration */
    public function getConfiguration(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'getConfiguration');
    }

    /** PUT /api/v2/admin/broker/configuration */
    public function saveConfiguration(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'saveConfiguration');
    }

    /** GET /api/v2/admin/broker/unreviewed-count */
    public function unreviewedCount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminBrokerApiController::class, 'unreviewedCount');
    }
}
