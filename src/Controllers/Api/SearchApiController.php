<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\UnifiedSearchService;
use Nexus\Services\SavedSearchService;
use Nexus\Services\SearchLogService;

/**
 * SearchApiController - Unified search API
 *
 * Provides search endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/search                  - Unified search across all content types
 * - GET    /api/v2/search/suggestions      - Autocomplete suggestions
 * - GET    /api/v2/search/saved            - List saved searches
 * - POST   /api/v2/search/saved            - Save a search
 * - DELETE /api/v2/search/saved/{id}       - Delete a saved search
 * - POST   /api/v2/search/saved/{id}/run   - Re-run a saved search
 * - GET    /api/v2/search/trending         - Get trending search terms
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class SearchApiController extends BaseApiController
{
    /**
     * GET /api/v2/search
     *
     * Unified search across listings, users, events, and groups.
     *
     * Query Parameters:
     * - q: string (required - search query, min 2 chars)
     * - type: string ('all', 'listings', 'users', 'events', 'groups') (default: 'all')
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 50)
     * - category_id: int (filter by category — listings only)
     * - date_from: string (ISO date — filter results created after)
     * - date_to: string (ISO date — filter results created before)
     * - sort: string ('relevance', 'newest', 'oldest') (default: 'relevance')
     * - skills: string (comma-separated skill tags — listings only)
     *
     * Response: 200 OK with search results and pagination meta
     */
    public function index(): void
    {
        $userId = $this->getUserIdOptional();
        $this->rateLimit('search', 60, 60);

        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            $this->respondWithError('VALIDATION_ERROR', 'Search query must be at least 2 characters', 'q', 400);
            return;
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('type')) {
            $validTypes = ['all', 'listings', 'users', 'events', 'groups'];
            $type = $this->query('type');

            if (!in_array($type, $validTypes)) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid type. Must be one of: ' . implode(', ', $validTypes), 'type', 400);
                return;
            }

            $filters['type'] = $type;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        // Advanced filters
        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }
        if ($this->query('date_from')) {
            $filters['date_from'] = $this->query('date_from');
        }
        if ($this->query('date_to')) {
            $filters['date_to'] = $this->query('date_to');
        }
        if ($this->query('sort')) {
            $validSorts = ['relevance', 'newest', 'oldest'];
            $sort = $this->query('sort');
            if (in_array($sort, $validSorts)) {
                $filters['sort'] = $sort;
            }
        }
        if ($this->query('skills')) {
            $filters['skills'] = $this->query('skills');
        }
        if ($this->query('location')) {
            $filters['location'] = $this->query('location');
        }

        $result = UnifiedSearchService::search($query, $userId, $filters);

        // Check for service errors
        $errors = UnifiedSearchService::getErrors();
        if (!empty($errors)) {
            $this->respondWithErrors($errors, 400);
            return;
        }

        // Log the search for analytics
        try {
            SearchLogService::log(
                $query,
                $filters['type'] ?? 'all',
                $result['total'] ?? count($result['items']),
                $userId,
                array_filter([
                    'category_id' => $filters['category_id'] ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to' => $filters['date_to'] ?? null,
                    'sort' => $filters['sort'] ?? null,
                    'skills' => $filters['skills'] ?? null,
                ])
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        // Format response with search-specific meta
        $this->respondWithSearchResults(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more'],
            [
                'query' => $result['query'],
                'total' => $result['total'],
                'type' => $filters['type'] ?? 'all',
            ]
        );
    }

    /**
     * GET /api/v2/search/suggestions
     *
     * Get autocomplete suggestions for a partial query.
     *
     * Query Parameters:
     * - q: string (required - partial search query, min 2 chars)
     * - limit: int (default 5, max 10)
     *
     * Response: 200 OK with suggestions by type
     */
    public function suggestions(): void
    {
        $this->getUserIdOptional();
        $this->rateLimit('search_suggestions', 120, 60);

        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            $this->respondWithData([
                'listings' => [],
                'users' => [],
                'events' => [],
                'groups' => [],
            ]);
            return;
        }

        $limit = $this->queryInt('limit', 5, 1, 10);
        $tenantId = TenantContext::getId();

        $suggestions = UnifiedSearchService::getSuggestions($query, $tenantId, $limit);

        $this->respondWithData($suggestions);
    }

    /**
     * GET /api/v2/search/saved
     *
     * List all saved searches for the authenticated user.
     *
     * Response: 200 OK with saved searches array
     */
    public function savedSearches(): void
    {
        $userId = $this->getUserId();

        $searches = SavedSearchService::getAll($userId);

        $this->respondWithData($searches);
    }

    /**
     * POST /api/v2/search/saved
     *
     * Save a search.
     *
     * Request Body (JSON):
     * {
     *   "name": "string (required)",
     *   "query_params": { "q": "...", "type": "...", "skills": "..." },
     *   "notify_on_new": false
     * }
     *
     * Response: 201 Created with saved search data
     */
    public function saveSearch(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('save_search', 10, 60);

        $data = $this->getAllInput();
        $name = $data['name'] ?? '';
        $queryParams = $data['query_params'] ?? [];
        $notifyOnNew = !empty($data['notify_on_new']);

        $id = SavedSearchService::save($userId, $name, $queryParams, $notifyOnNew);

        if ($id === null) {
            $errors = SavedSearchService::getErrors();
            $this->respondWithErrors($errors, 422);
            return;
        }

        $saved = SavedSearchService::getById($id, $userId);
        $this->respondWithData($saved, null, 201);
    }

    /**
     * DELETE /api/v2/search/saved/{id}
     *
     * Delete a saved search.
     *
     * Response: 200 OK with { deleted: true }
     */
    public function deleteSavedSearch(int $id): void
    {
        $userId = $this->getUserId();

        $deleted = SavedSearchService::delete($id, $userId);

        if (!$deleted) {
            $this->respondWithError('NOT_FOUND', 'Saved search not found', null, 404);
            return;
        }

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/search/saved/{id}/run
     *
     * Re-run a saved search and update its last_run metadata.
     *
     * Response: 200 OK with search results
     */
    public function runSavedSearch(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('search', 60, 60);

        $saved = SavedSearchService::getById($id, $userId);
        if (!$saved) {
            $this->respondWithError('NOT_FOUND', 'Saved search not found', null, 404);
            return;
        }

        $queryParams = $saved['query_params'];
        $query = $queryParams['q'] ?? '';

        if (strlen(trim($query)) < 2) {
            $this->respondWithError('VALIDATION_ERROR', 'Saved search query is too short', 'q', 400);
            return;
        }

        $filters = array_merge($queryParams, [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ]);

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = UnifiedSearchService::search($query, $userId, $filters);

        // Update run metadata
        SavedSearchService::markRun($id, $userId, $result['total'] ?? count($result['items']));

        $this->respondWithSearchResults(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more'],
            [
                'query' => $result['query'] ?? $query,
                'total' => $result['total'],
                'saved_search_id' => $id,
                'saved_search_name' => $saved['name'],
            ]
        );
    }

    /**
     * GET /api/v2/search/trending
     *
     * Get trending search terms for the current tenant.
     *
     * Query Parameters:
     * - days: int (default 7, max 30)
     * - limit: int (default 10, max 50)
     *
     * Response: 200 OK with trending searches
     */
    public function trending(): void
    {
        $this->getUserIdOptional();
        $this->rateLimit('search_trending', 30, 60);

        $days = $this->queryInt('days', 7, 1, 30);
        $limit = $this->queryInt('limit', 10, 1, 50);

        $trending = SearchLogService::getTrendingSearches($days, $limit);

        $this->respondWithData($trending);
    }

    /**
     * Get user ID optionally (search can work for anonymous users)
     */
    private function getUserIdOptional(): ?int
    {
        return $this->getOptionalUserId();
    }

    /**
     * Respond with search results including search-specific metadata
     */
    private function respondWithSearchResults(array $items, ?string $cursor, int $perPage, bool $hasMore, array $searchMeta = []): void
    {
        $meta = [
            'pagination' => [
                'cursor' => $cursor,
                'per_page' => $perPage,
                'has_more' => $hasMore,
            ],
        ];

        // Add search-specific meta
        if (!empty($searchMeta)) {
            $meta['search'] = $searchMeta;
        }

        $this->respondWithData($items, $meta);
    }
}
