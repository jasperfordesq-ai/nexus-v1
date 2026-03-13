<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\ResourceCategoryService;
use Nexus\Services\ResourceOrderService;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;

/**
 * ResourceCategoriesApiController - V2 API for resource categories and ordering
 *
 * Endpoints:
 * - GET    /api/v2/resources/categories/tree  - Hierarchical category tree
 * - POST   /api/v2/resources/categories       - Create category (admin)
 * - PUT    /api/v2/resources/categories/{id}  - Update category (admin)
 * - DELETE /api/v2/resources/categories/{id}  - Delete category (admin)
 * - PUT    /api/v2/resources/reorder          - Reorder resources (admin)
 *
 * @package Nexus\Controllers\Api
 */
class ResourceCategoriesApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/resources/categories/tree
     *
     * Get hierarchical resource categories.
     *
     * Query Parameters:
     * - flat: bool (if true, return flat list instead of tree)
     *
     * Response: 200 OK with category tree or flat list
     */
    public function tree(): void
    {
        $this->getUserId();

        $flat = $this->queryBool('flat', false);
        $categories = ResourceCategoryService::getAll($flat);

        $this->respondWithData($categories);
    }

    /**
     * POST /api/v2/resources/categories
     *
     * Create a new resource category (admin only).
     *
     * Request Body (JSON):
     * {
     *   "name": "string (required)",
     *   "slug": "string (optional - auto-generated)",
     *   "parent_id": "int|null (optional)",
     *   "sort_order": "int (optional, default 0)",
     *   "icon": "string (optional)",
     *   "description": "string (optional)"
     * }
     *
     * Response: 201 Created with category data
     */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('resource_category_create', 10, 60);

        $data = $this->getAllInput();

        $categoryId = ResourceCategoryService::create($data);

        if ($categoryId === null) {
            $this->respondWithErrors(ResourceCategoryService::getErrors(), 422);
        }

        $category = ResourceCategoryService::getById($categoryId);

        $this->respondWithData($category, null, 201);
    }

    /**
     * PUT /api/v2/resources/categories/{id}
     *
     * Update a resource category (admin only).
     *
     * Response: 200 OK with updated category data
     */
    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('resource_category_update', 20, 60);

        $data = $this->getAllInput();

        $success = ResourceCategoryService::update($id, $data);

        if (!$success) {
            $errors = ResourceCategoryService::getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $category = ResourceCategoryService::getById($id);

        $this->respondWithData($category);
    }

    /**
     * DELETE /api/v2/resources/categories/{id}
     *
     * Delete a resource category (admin only).
     *
     * Response: 204 No Content
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('resource_category_delete', 10, 60);

        $success = ResourceCategoryService::delete($id);

        if (!$success) {
            $errors = ResourceCategoryService::getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) { $status = 409; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * PUT /api/v2/resources/reorder
     *
     * Reorder resources by providing an array of {id, sort_order}.
     * Admin only.
     *
     * Request Body (JSON):
     * {
     *   "items": [
     *     { "id": 1, "sort_order": 0 },
     *     { "id": 2, "sort_order": 1 },
     *     ...
     *   ]
     * }
     *
     * Response: 200 OK
     */
    public function reorder(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('resource_reorder', 10, 60);

        $items = $this->input('items');

        if (empty($items) || !is_array($items)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Items array is required',
                'items',
                400
            );
            return;
        }

        $success = ResourceOrderService::reorder($items);

        if (!$success) {
            $this->respondWithErrors(ResourceOrderService::getErrors(), 422);
        }

        $this->respondWithData(['message' => 'Resources reordered successfully']);
    }
}
