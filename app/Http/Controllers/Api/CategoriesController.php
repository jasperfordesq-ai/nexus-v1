<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\CategoryService;

/**
 * CategoriesController -- Tenant-scoped listing and service categories.
 *
 * Uses Eloquent via CategoryService with automatic tenant scoping from HasTenantScope.
 */
class CategoriesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * GET /api/v2/categories
     *
     * List categories, optionally filtered by type (listing, event, blog, resource, volunteering).
     *
     * Query Parameters:
     * - type: string (optional) — filter by category type
     */
    public function index(): JsonResponse
    {
        $type = $this->query('type');

        if ($type) {
            $categories = $this->categoryService->getByType($type);
        } else {
            $categories = $this->categoryService->getAll();
        }

        return $this->respondWithData($categories);
    }
}
