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
 */
class CategoriesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /** GET /api/v2/categories */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $type = $this->query('type');
        
        $categories = $this->categoryService->getAll($tenantId, $type);
        
        return $this->respondWithData($categories);
    }

}
