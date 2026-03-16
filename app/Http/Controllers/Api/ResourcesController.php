<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\ResourceService;

/**
 * ResourcesController -- Community shared resources (files, links, documents).
 */
class ResourcesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ResourceService $resourceService,
    ) {}

    /** GET /api/v2/resources */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $categoryId = $this->queryInt('category_id');
        
        $result = $this->resourceService->getAll($tenantId, $page, $perPage, $categoryId);
        
        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** POST /api/v2/resources */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('resource_create', 10, 60);
        
        $data = $this->getAllInput();
        $resource = $this->resourceService->create($userId, $this->getTenantId(), $data);
        
        return $this->respondWithData($resource, null, 201);
    }

    /** GET /api/v2/resources/{id}/download */
    public function download(int $id): JsonResponse
    {
        $resource = $this->resourceService->getById($id, $this->getTenantId());
        
        if ($resource === null) {
            return $this->respondWithError('NOT_FOUND', 'Resource not found', null, 404);
        }
        
        $this->resourceService->incrementDownloads($id, $this->getTenantId());
        
        return $this->respondWithData($resource);
    }

    /** DELETE /api/v2/resources/{id} */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $deleted = $this->resourceService->delete($id, $userId, $this->getTenantId());
        
        if (!$deleted) {
            return $this->respondWithError('NOT_FOUND', 'Resource not found', null, 404);
        }
        
        return $this->noContent();
    }

}
