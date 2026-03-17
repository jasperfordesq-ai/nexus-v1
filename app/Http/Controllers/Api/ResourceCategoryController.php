<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

/**
 * ResourceCategoryController — Resource category tree management.
 *
 * Native DB facade implementation — no legacy delegation.
 * Mirrors the exact logic from ResourceCategoriesApiController +
 * ResourceCategoryService + ResourceOrderService.
 */
class ResourceCategoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ----------------------------------------------------------------
    // GET  /api/v2/resources/categories/tree
    // ----------------------------------------------------------------

    /**
     * Get hierarchical resource categories.
     *
     * Query Parameters:
     * - flat: bool (if true, return flat list instead of tree)
     */
    public function tree(): JsonResponse
    {
        $this->getUserId();
        $tenantId = $this->getTenantId();
        $flat = $this->queryBool('flat', false);

        $rows = DB::table('resource_categories as rc')
            ->leftJoin('resources as r', function ($join) {
                $join->on('r.category_id', '=', 'rc.id')
                     ->whereColumn('r.tenant_id', 'rc.tenant_id');
            })
            ->where('rc.tenant_id', $tenantId)
            ->select(
                'rc.id', 'rc.name', 'rc.slug', 'rc.parent_id',
                'rc.sort_order', 'rc.icon', 'rc.description',
                DB::raw('COUNT(r.id) as resource_count')
            )
            ->groupBy('rc.id', 'rc.name', 'rc.slug', 'rc.parent_id', 'rc.sort_order', 'rc.icon', 'rc.description')
            ->orderBy('rc.sort_order')
            ->orderBy('rc.name')
            ->get();

        $items = $rows->map(function ($c) {
            return [
                'id'             => (int) $c->id,
                'name'           => $c->name,
                'slug'           => $c->slug,
                'parent_id'      => $c->parent_id ? (int) $c->parent_id : null,
                'sort_order'     => (int) $c->sort_order,
                'icon'           => $c->icon,
                'description'    => $c->description,
                'resource_count' => (int) $c->resource_count,
            ];
        })->all();

        $data = $flat ? $items : $this->buildTree($items);

        return $this->respondWithData($data);
    }

    // ----------------------------------------------------------------
    // POST /api/v2/resources/categories
    // ----------------------------------------------------------------

    /**
     * Create a new resource category (admin only).
     *
     * Request Body (JSON):
     * {
     *   "name": "string (required)",
     *   "slug": "string (optional — auto-generated)",
     *   "parent_id": "int|null (optional)",
     *   "sort_order": "int (optional, default 0)",
     *   "icon": "string (optional)",
     *   "description": "string (optional)"
     * }
     */
    public function store(): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('resource_category_create', 10, 60);

        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Category name is required', 'name', 422);
        }

        $slug = trim($data['slug'] ?? '');
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        // Deduplicate slug within tenant
        $existing = DB::table('resource_categories')
            ->where('slug', $slug)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            $slug = $slug . '-' . time();
        }

        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $icon = trim($data['icon'] ?? '') ?: null;
        $description = trim($data['description'] ?? '') ?: null;

        // Validate parent exists
        if ($parentId !== null) {
            $parent = DB::table('resource_categories')
                ->where('id', $parentId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$parent) {
                return $this->respondWithError('VALIDATION_INVALID_VALUE', 'Parent category not found', 'parent_id', 422);
            }
        }

        $categoryId = DB::table('resource_categories')->insertGetId([
            'tenant_id'   => $tenantId,
            'name'        => $name,
            'slug'        => $slug,
            'parent_id'   => $parentId,
            'sort_order'  => $sortOrder,
            'icon'        => $icon,
            'description' => $description,
            'created_at'  => now(),
        ]);

        $category = $this->getCategoryById($categoryId, $tenantId);

        return $this->respondWithData($category, null, 201);
    }

    // ----------------------------------------------------------------
    // PUT /api/v2/resources/categories/{id}
    // ----------------------------------------------------------------

    /**
     * Update a resource category (admin only).
     */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('resource_category_update', 20, 60);

        $tenantId = $this->getTenantId();
        $category = $this->getCategoryById($id, $tenantId);

        if (!$category) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Category not found', null, 404);
        }

        $data = $this->getAllInput();
        $updates = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Name cannot be empty', 'name', 422);
            }
            $updates['name'] = $name;
        }

        if (isset($data['slug'])) {
            $updates['slug'] = trim($data['slug']);
        }

        if (array_key_exists('parent_id', $data)) {
            $updates['parent_id'] = $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
        }

        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        if (array_key_exists('icon', $data)) {
            $updates['icon'] = $data['icon'] ?: null;
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = trim($data['description']) ?: null;
        }

        if (!empty($updates)) {
            DB::table('resource_categories')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update($updates);
        }

        $category = $this->getCategoryById($id, $tenantId);

        return $this->respondWithData($category);
    }

    // ----------------------------------------------------------------
    // DELETE /api/v2/resources/categories/{id}
    // ----------------------------------------------------------------

    /**
     * Delete a resource category (admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('resource_category_delete', 10, 60);

        $tenantId = $this->getTenantId();

        $exists = DB::table('resource_categories')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$exists) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Category not found', null, 404);
        }

        // Block deletion if children exist
        $childCount = DB::table('resource_categories')
            ->where('parent_id', $id)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($childCount > 0) {
            return $this->respondWithError('RESOURCE_CONFLICT', 'Cannot delete category with child categories', null, 409);
        }

        // Unset category on associated resources (set to null)
        DB::table('resources')
            ->where('category_id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['category_id' => null]);

        DB::table('resource_categories')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        return $this->noContent();
    }

    // ----------------------------------------------------------------
    // PUT /api/v2/resources/reorder
    // ----------------------------------------------------------------

    /**
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
     */
    public function reorder(): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('resource_reorder', 10, 60);

        $tenantId = $this->getTenantId();
        $items = $this->input('items');

        if (empty($items) || !is_array($items)) {
            return $this->respondWithError(
                'VALIDATION_REQUIRED_FIELD',
                'Items array is required',
                'items',
                400
            );
        }

        DB::beginTransaction();
        try {
            foreach ($items as $i => $item) {
                $id = (int) ($item['id'] ?? 0);
                $sortOrder = (int) ($item['sort_order'] ?? 0);

                if ($id <= 0) {
                    DB::rollBack();
                    return $this->respondWithError(
                        'VALIDATION_INVALID_VALUE',
                        "Invalid resource ID at index {$i}",
                        "items.{$i}.id",
                        422
                    );
                }

                DB::table('resources')
                    ->where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->update(['sort_order' => $sortOrder]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Failed to reorder resources', null, 500);
        }

        return $this->respondWithData(['message' => 'Resources reordered successfully']);
    }

    // ----------------------------------------------------------------
    // PRIVATE HELPERS
    // ----------------------------------------------------------------

    /**
     * Fetch a single resource category by ID with resource count.
     */
    private function getCategoryById(int $id, int $tenantId): ?array
    {
        $row = DB::table('resource_categories as rc')
            ->leftJoin('resources as r', function ($join) {
                $join->on('r.category_id', '=', 'rc.id')
                     ->whereColumn('r.tenant_id', 'rc.tenant_id');
            })
            ->where('rc.id', $id)
            ->where('rc.tenant_id', $tenantId)
            ->select(
                'rc.id', 'rc.name', 'rc.slug', 'rc.parent_id',
                'rc.sort_order', 'rc.icon', 'rc.description',
                DB::raw('COUNT(r.id) as resource_count')
            )
            ->groupBy('rc.id', 'rc.name', 'rc.slug', 'rc.parent_id', 'rc.sort_order', 'rc.icon', 'rc.description')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'id'             => (int) $row->id,
            'name'           => $row->name,
            'slug'           => $row->slug,
            'parent_id'      => $row->parent_id ? (int) $row->parent_id : null,
            'sort_order'     => (int) $row->sort_order,
            'icon'           => $row->icon,
            'description'    => $row->description,
            'resource_count' => (int) $row->resource_count,
        ];
    }

    /**
     * Build a tree from flat category list.
     */
    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $item['children'] = $this->buildTree($items, $item['id']);
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Generate a URL-friendly slug from a name.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'category';
    }
}
