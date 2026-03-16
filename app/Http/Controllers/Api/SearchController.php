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
}
