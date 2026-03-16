<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;

/**
 * KnowledgeBaseController — Community knowledge base articles.
 */
class KnowledgeBaseController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly KnowledgeBaseService $kbService,
    ) {}

    /** GET /api/v2/knowledge-base */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $categoryId = $this->queryInt('category_id');

        $result = $this->kbService->getAll($tenantId, $page, $perPage, $categoryId);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/knowledge-base/{id} */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $article = $this->kbService->getById($id, $tenantId);

        if ($article === null) {
            return $this->respondWithError('NOT_FOUND', 'Article not found', null, 404);
        }

        return $this->respondWithData($article);
    }

    /** GET /api/v2/knowledge-base/search?q= */
    public function search(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 20, 1, 50);

        $results = $this->kbService->search($q, $tenantId, $limit);

        return $this->respondWithData($results);
    }

    /** POST /api/v2/knowledge-base */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('kb_create', 5, 60);

        $data = $this->getAllInput();

        $article = $this->kbService->create($userId, $tenantId, $data);

        return $this->respondWithData($article, null, 201);
    }
}
