<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\BlogService;

/**
 * BlogController -- Public blog posts and categories.
 */
class BlogController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly BlogService $blogService,
    ) {}

    /** GET /api/v2/blog */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $categoryId = $this->queryInt('category_id');
        
        $result = $this->blogService->getPosts($tenantId, $page, $perPage, $categoryId);
        
        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** GET /api/v2/blog/{slug} */
    public function show(string $slug): JsonResponse
    {
        $post = $this->blogService->getBySlug($slug, $this->getTenantId());
        
        if ($post === null) {
            return $this->respondWithError('NOT_FOUND', 'Blog post not found', null, 404);
        }
        
        return $this->respondWithData($post);
    }

    /** GET /api/v2/blog/categories */
    public function categories(): JsonResponse
    {
        $categories = $this->blogService->getCategories($this->getTenantId());
        
        return $this->respondWithData($categories);
    }

}
