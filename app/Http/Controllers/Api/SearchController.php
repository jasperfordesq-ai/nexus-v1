<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

/**
 * SearchController - Unified search across content types.
 *
 * Endpoints (v2):
 *   GET /api/v2/search  index()
 */
class SearchController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    /**
     * Search across listings, events, groups, members, etc.
     *
     * Query params: q (required), type (optional filter: listings, events,
     * groups, members), per_page, cursor.
     */
    public function index(): JsonResponse
    {
        $this->rateLimit('search', 30, 60);

        $q = $this->query('q', '');

        if (trim($q) === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'Search query is required', 'q', 400);
        }

        $userId = $this->getOptionalUserId();

        $filters = [
            'query'  => $q,
            'limit'  => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        $result = $this->searchService->search($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
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
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'deleteSavedSearch', [$id]);
    }


    public function runSavedSearch($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'runSavedSearch', [$id]);
    }


    public function trending(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SearchApiController::class, 'trending');
    }

}
