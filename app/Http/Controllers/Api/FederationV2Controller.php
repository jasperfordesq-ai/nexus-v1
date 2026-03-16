<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * FederationV2Controller -- Federation v2: cross-tenant discovery, messaging, connections.
 *
 * Delegates to legacy controller during migration.
 */
class FederationV2Controller extends BaseApiController
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

    /** GET /api/v2/federation/status */
    public function status(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'status');
    }

    /** POST /api/v2/federation/opt-in */
    public function optIn(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'optIn');
    }

    /** POST /api/v2/federation/setup */
    public function setup(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'setup');
    }

    /** POST /api/v2/federation/opt-out */
    public function optOut(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'optOut');
    }

    /** GET /api/v2/federation/partners */
    public function partners(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'partners');
    }

    /** GET /api/v2/federation/activity */
    public function activity(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'activity');
    }

    /** GET /api/v2/federation/events */
    public function events(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'events');
    }

    /** GET /api/v2/federation/listings */
    public function listings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'listings');
    }

    /** GET /api/v2/federation/members */
    public function members(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'members');
    }

    /** GET /api/v2/federation/members/{id} */
    public function member(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'member', [$id]);
    }

    /** GET /api/v2/federation/messages */
    public function messages(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'messages');
    }

    /** POST /api/v2/federation/messages */
    public function sendMessage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'sendMessage');
    }

    /** POST /api/v2/federation/messages/{id}/read */
    public function markMessageRead(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'markMessageRead', [$id]);
    }

    /** GET /api/v2/federation/settings */
    public function getSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'getSettings');
    }

    /** PUT /api/v2/federation/settings */
    public function updateSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'updateSettings');
    }

    /** GET /api/v2/federation/connections */
    public function connections(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'connections');
    }

    /** POST /api/v2/federation/connections */
    public function sendConnectionRequest(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'sendConnectionRequest');
    }

    /** POST /api/v2/federation/connections/{id}/accept */
    public function acceptConnection(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'acceptConnection', [$id]);
    }

    /** POST /api/v2/federation/connections/{id}/reject */
    public function rejectConnection(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'rejectConnection', [$id]);
    }

    /** DELETE /api/v2/federation/connections/{id} */
    public function removeConnection(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'removeConnection', [$id]);
    }

    /** GET /api/v2/federation/connections/status/{userId}/{tenantId} */
    public function connectionStatus($userId, $tenantId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\FederationV2ApiController::class, 'connectionStatus', [$userId, $tenantId]);
    }
}
