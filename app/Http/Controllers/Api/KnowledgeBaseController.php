<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * KnowledgeBaseController — Community knowledge base articles.
 */
class KnowledgeBaseController extends BaseApiController
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
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'index');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'show', func_get_args());
    }

    public function search(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'search');
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'store');
    }

    public function showBySlug($slug): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'showBySlug', func_get_args());
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'destroy', func_get_args());
    }

    public function feedback($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\KnowledgeBaseApiController::class, 'feedback', func_get_args());
    }
}
