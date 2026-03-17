<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * PollsController -- Community polls with voting support.
 */
class PollsController extends BaseApiController
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

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'index');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'show', func_get_args());
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'store');
    }

    public function vote(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'vote', func_get_args());
    }

    public function categories(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'categories');
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'destroy', func_get_args());
    }

    public function rank($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'rank', func_get_args());
    }

    public function rankedResults($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'rankedResults', func_get_args());
    }

    public function export($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\PollsApiController::class, 'export', func_get_args());
    }
}
