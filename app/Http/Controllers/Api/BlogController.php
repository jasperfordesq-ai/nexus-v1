<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\BlogService;

/**
 * BlogController — Eloquent-powered public blog posts and categories.
 *
 * Fully migrated from legacy delegation to Eloquent via BlogService.
 */
class BlogController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly BlogService $blogService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/blog
    // -----------------------------------------------------------------

    public function index(): JsonResponse
    {
        $filters = [
            'limit' => $this->queryInt('per_page', 12, 1, 50),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }
        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        $result = $this->blogService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/blog/{slug}
    // -----------------------------------------------------------------

    public function show(string $slug): JsonResponse
    {
        $post = $this->blogService->getBySlug($slug, $this->getTenantId());

        if ($post === null) {
            return $this->respondWithError('NOT_FOUND', 'Blog post not found', null, 404);
        }

        return $this->respondWithData($post);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/blog/categories
    // -----------------------------------------------------------------

    public function categories(): JsonResponse
    {
        $categories = $this->blogService->getCategories($this->getTenantId());

        return $this->respondWithData($categories);
    }
}
