<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * CommentsController -- Threaded comments.
 */
class CommentsController extends BaseApiController
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
        return $this->delegate(\Nexus\Controllers\Api\CommentsV2ApiController::class, 'index');
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CommentsV2ApiController::class, 'store');
    }

    public function update(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CommentsV2ApiController::class, 'update', func_get_args());
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CommentsV2ApiController::class, 'destroy', func_get_args());
    }

    public function reactions($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\CommentsV2ApiController::class, 'reactions', func_get_args());
    }
}
