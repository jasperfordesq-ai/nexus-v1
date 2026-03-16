<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\ExchangeService;

/**
 * ExchangesController -- Time credit exchange lifecycle (create, accept, decline).
 */
class ExchangesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ExchangeService $exchangeService,
    ) {}

    /** GET /api/v2/exchanges */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();
        $status = $this->query('status');
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        
        $result = $this->exchangeService->getForUser(
            $userId, $this->getTenantId(), $status, $page, $perPage
        );
        
        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** GET /api/v2/exchanges/{id} */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $exchange = $this->exchangeService->getById($id, $userId, $this->getTenantId());
        
        if ($exchange === null) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }
        
        return $this->respondWithData($exchange);
    }

    /** POST /api/v2/exchanges */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('exchange_create', 10, 60);
        
        $data = $this->getAllInput();
        $exchange = $this->exchangeService->create($userId, $this->getTenantId(), $data);
        
        return $this->respondWithData($exchange, null, 201);
    }

    /** POST /api/v2/exchanges/{id}/accept */
    public function accept(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $result = $this->exchangeService->accept($id, $userId, $this->getTenantId());
        
        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }
        
        return $this->respondWithData($result);
    }

    /** POST /api/v2/exchanges/{id}/decline */
    public function decline(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $reason = $this->input('reason');
        $result = $this->exchangeService->decline($id, $userId, $this->getTenantId(), $reason);
        
        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Exchange not found', null, 404);
        }
        
        return $this->respondWithData($result);
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


    public function config(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'config');
    }


    public function check(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'check');
    }


    public function start($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'start', [$id]);
    }


    public function complete($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'complete', [$id]);
    }


    public function confirm($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'confirm', [$id]);
    }


    public function cancel($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ExchangesApiController::class, 'cancel', [$id]);
    }

}
