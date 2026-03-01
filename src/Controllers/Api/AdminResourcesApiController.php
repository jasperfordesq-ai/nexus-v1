<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\KnowledgeBaseService;

/**
 * Admin Resources / Knowledge Base API Controller
 *
 * GET    /api/v2/admin/resources              - List all articles
 * GET    /api/v2/admin/resources/{id}         - Article detail
 * DELETE /api/v2/admin/resources/{id}         - Delete article
 */
class AdminResourcesApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
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
            $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        } else {
            $this->respondWithPaginatedCollection($items, $total, $page, $limit);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();

        $article = KnowledgeBaseService::getById($id);

        if (!$article) {
            $this->respondWithError('NOT_FOUND', 'Article not found', null, 404);
            return;
        }

        $this->respondWithData($article);
    }

    public function destroy(int $id): void
    {
        $this->requireAdmin();

        $article = KnowledgeBaseService::getById($id);
        if (!$article) {
            $this->respondWithError('NOT_FOUND', 'Article not found', null, 404);
            return;
        }

        $deleted = KnowledgeBaseService::delete($id);

        if ($deleted) {
            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } else {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete article', null, 400);
        }
    }
}
