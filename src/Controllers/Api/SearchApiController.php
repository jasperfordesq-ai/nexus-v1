<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\UnifiedSearchService;

/**
 * SearchApiController - Unified search API
 *
 * Provides search endpoints with standardized response format.
 *
 * Endpoints:
 * - GET /api/v2/search              - Unified search across all content types
 * - GET /api/v2/search/suggestions  - Autocomplete suggestions
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

        $result = UnifiedSearchService::search($query, $userId, $filters);

        // Check for service errors
        $errors = UnifiedSearchService::getErrors();
        if (!empty($errors)) {
            $this->respondWithErrors($errors, 400);
            return;
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
