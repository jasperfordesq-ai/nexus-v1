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
 *   GET  /api/v2/search              index()        — unified search
 *   GET  /api/v2/search/suggestions  suggestions()  — autocomplete
 *   GET  /api/v2/search/trending     trending()     — trending terms
 */
class SearchController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/search
    // -----------------------------------------------------------------

    /**
     * Unified search across listings, users, events, and groups.
     *
     * Query params: q (required, min 2), type (all|listings|users|events|groups),
     *               cursor, per_page (default 20, max 50), category_id,
     *               sort (relevance|newest|oldest), skills.
     */
    public function index(): JsonResponse
    {
        $this->rateLimit('search', 60, 60);

        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            return $this->respondWithError('VALIDATION_ERROR', 'Search query must be at least 2 characters', 'q', 400);
        }

        // Validate type param
        $type = $this->query('type');
        if ($type !== null) {
            $validTypes = ['all', 'listings', 'users', 'events', 'groups'];
            if (! in_array($type, $validTypes, true)) {
                return $this->respondWithError(
                    'VALIDATION_ERROR',
                    'Invalid type. Must be one of: ' . implode(', ', $validTypes),
                    'type',
                    400
                );
            }
        }

        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($type) {
            $filters['type'] = $type;
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }
        if ($this->query('sort')) {
            $validSorts = ['relevance', 'newest', 'oldest'];
            $sort = $this->query('sort');
            if (in_array($sort, $validSorts, true)) {
                $filters['sort'] = $sort;
            }
        }
        if ($this->query('skills')) {
            $filters['skills'] = $this->query('skills');
        }

        $result = $this->searchService->unifiedSearch($query, $userId, $filters);

        return $this->respondWithData($result['items'], [
            'pagination' => [
                'cursor'   => $result['cursor'],
                'per_page' => $filters['limit'],
                'has_more' => $result['has_more'],
            ],
            'search' => [
                'query' => $result['query'],
                'total' => $result['total'],
                'type'  => $filters['type'] ?? 'all',
            ],
        ]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/search/suggestions
    // -----------------------------------------------------------------

    /**
     * Get autocomplete suggestions for a partial query.
     *
     * Query params: q (required, min 2), limit (default 5, max 10).
     */
    public function suggestions(): JsonResponse
    {
        $this->rateLimit('search_suggestions', 120, 60);

        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            return $this->respondWithData([
                'listings' => [],
                'users'    => [],
                'events'   => [],
                'groups'   => [],
            ]);
        }

        $limit = $this->queryInt('limit', 5, 1, 10);

        $suggestions = $this->searchService->suggestions($query, $limit);

        return $this->respondWithData($suggestions);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/search/trending
    // -----------------------------------------------------------------

    /**
     * Get trending search terms for the current tenant.
     *
     * Query params: days (default 7, max 30), limit (default 10, max 50).
     */
    public function trending(): JsonResponse
    {
        $this->rateLimit('search_trending', 30, 60);

        $days = $this->queryInt('days', 7, 1, 30);
        $limit = $this->queryInt('limit', 10, 1, 50);

        $trending = $this->searchService->trending($days, $limit);

        return $this->respondWithData($trending);
    }
}
