<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;

/**
 * ConnectionsController - Member connections (friend requests).
 *
 * Endpoints (v2):
 *   GET    /api/v2/connections              index()
 *   POST   /api/v2/connections              request()
 *   PUT    /api/v2/connections/{id}/accept   accept()
 *   DELETE /api/v2/connections/{id}         destroy()
 */
class ConnectionsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ConnectionService $connectionService,
    ) {}

    /**
     * List connections for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->connectionService->getAll($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Send a connection request. Requires authentication.
     */
    public function request(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('connection_request', 20, 60);

        $result = $this->connectionService->sendRequest($userId, $this->getAllInput());

        return $this->respondWithData($result, null, 201);
    }

    /**
     * Accept a pending connection request.
     */
    public function accept(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $result = $this->connectionService->accept($id, $userId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Connection request not found', null, 404);
        }

        return $this->respondWithData($result);
    }

    /**
     * Remove a connection or cancel a pending request.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $existing = $this->connectionService->getById($id, $userId);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Connection not found', null, 404);
        }

        $this->connectionService->delete($id, $userId);

        return $this->noContent();
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


    public function pendingCounts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ConnectionsApiController::class, 'pendingCounts');
    }


    public function status($userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ConnectionsApiController::class, 'status', [$userId]);
    }

}
