<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\KnowledgeBaseService;

/**
 * AdminResourcesController -- Admin resource / knowledge base management.
 *
 * All endpoints require admin authentication.
 */
class AdminResourcesController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/resources
     *
     * Query params: search, status, page, limit
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $filters = [
            'search' => $this->query('search'),
            'status' => $this->query('status'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = KnowledgeBaseService::getAll($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            return $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        }

        return $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/resources/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();

        $article = KnowledgeBaseService::getById($id);

        if (!$article) {
            return $this->respondWithError('NOT_FOUND', 'Article not found', null, 404);
        }

        return $this->respondWithData($article);
    }

    /**
     * DELETE /api/v2/admin/resources/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();

        $article = KnowledgeBaseService::getById($id);
        if (!$article) {
            return $this->respondWithError('NOT_FOUND', 'Article not found', null, 404);
        }

        $deleted = KnowledgeBaseService::delete($id);

        if ($deleted) {
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        }

        return $this->respondWithError('DELETE_FAILED', 'Failed to delete article', null, 400);
    }
}
