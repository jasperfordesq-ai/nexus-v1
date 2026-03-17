<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * SearchController - Unified search across content types.
 */
class SearchController extends BaseApiController
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
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'index');
    }

    public function suggestions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'suggestions');
    }

    public function savedSearches(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'savedSearches');
    }

    public function saveSearch(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'saveSearch');
    }

    public function deleteSavedSearch($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'deleteSavedSearch', func_get_args());
    }

    public function runSavedSearch($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'runSavedSearch', func_get_args());
    }

    public function trending(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'trending');
    }
}
