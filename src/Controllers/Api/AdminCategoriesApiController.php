<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;

/**
 * AdminCategoriesApiController - V2 API for React admin category management
 *
 * Provides full CRUD for categories in the admin panel.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/categories              - List all categories for tenant
 * - POST   /api/v2/admin/categories              - Create a new category
 * - PUT    /api/v2/admin/categories/{id}          - Update an existing category
 * - DELETE /api/v2/admin/categories/{id}          - Delete a category
 */
class AdminCategoriesApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/categories
     *
     * Lists all categories for the current tenant, ordered by type then name.
     * Includes listing count for each category.
     *
     * Query params: type (filter by category type)
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $typeFilter = $_GET['type'] ?? null;

        $conditions = ['c.tenant_id = ?'];
        $params = [$tenantId];

        if ($typeFilter && $typeFilter !== 'all') {
            $conditions[] = 'c.type = ?';
            $params[] = $typeFilter;
        }

        $where = implode(' AND ', $conditions);

        $items = Database::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id) as listing_count
             FROM categories c
             WHERE {$where}
             ORDER BY c.type ASC, c.name ASC",
            $params
        )->fetchAll();

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'] ?? '',
                'slug' => $row['slug'] ?? '',
                'color' => $row['color'] ?? 'blue',
                'type' => $row['type'] ?? 'listing',
                'listing_count' => (int) ($row['listing_count'] ?? 0),
                'created_at' => $row['created_at'],
            ];
        }, $items);

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/categories
     *
     * Create a new category.
     * Required: name
     * Optional: color, type
     */
    public function store(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();

        // Validate required fields
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Category name is required',
                'name',
                422
            );
            return;
        }

        // Generate slug
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
        $slug = trim($slug, '-');

        $color = trim($data['color'] ?? 'blue');
        $type = trim($data['type'] ?? 'listing');

        // Validate type
        $allowedTypes = ['listing', 'event', 'blog', 'vol_opportunity'];
        if (!in_array($type, $allowedTypes)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'Invalid category type. Allowed: ' . implode(', ', $allowedTypes),
                'type',
                422
            );
            return;
        }

        // Check name uniqueness within tenant
        $existing = Database::query(
            "SELECT id FROM categories WHERE name = ? AND tenant_id = ?",
            [$name, $tenantId]
        )->fetch();

        if ($existing) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_DUPLICATE,
                'A category with this name already exists',
                'name',
                409
            );
            return;
        }

        // Insert
        Database::query(
            "INSERT INTO categories (tenant_id, name, slug, color, type) VALUES (?, ?, ?, ?, ?)",
            [$tenantId, $name, $slug, $color, $type]
        );
        $newId = (int) Database::lastInsertId();

        ActivityLog::log($adminId, 'admin_create_category', "Created category #{$newId}: {$name} (type: {$type})");

        $this->respondWithData([
            'id' => $newId,
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'type' => $type,
            'listing_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], null, 201);
    }

    /**
     * PUT /api/v2/admin/categories/{id}
     *
     * Update an existing category.
     * Optional: name, color, type
     */
    public function update(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();

        // Verify category exists and belongs to tenant
        $category = Database::query(
            "SELECT * FROM categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$category) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Category not found',
                null,
                404
            );
            return;
        }

        $name = isset($data['name']) && trim($data['name']) !== '' ? trim($data['name']) : $category['name'];
        $color = isset($data['color']) && trim($data['color']) !== '' ? trim($data['color']) : $category['color'];
        $type = isset($data['type']) && trim($data['type']) !== '' ? trim($data['type']) : $category['type'];

        // Validate type if changed
        $allowedTypes = ['listing', 'event', 'blog', 'vol_opportunity'];
        if (!in_array($type, $allowedTypes)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                'Invalid category type. Allowed: ' . implode(', ', $allowedTypes),
                'type',
                422
            );
            return;
        }

        // Check name uniqueness if name changed
        if ($name !== $category['name']) {
            $existing = Database::query(
                "SELECT id FROM categories WHERE name = ? AND tenant_id = ? AND id != ?",
                [$name, $tenantId, $id]
            )->fetch();

            if ($existing) {
                $this->respondWithError(
                    ApiErrorCodes::VALIDATION_DUPLICATE,
                    'A category with this name already exists',
                    'name',
                    409
                );
                return;
            }
        }

        // Regenerate slug if name changed
        $slug = $category['slug'];
        if ($name !== $category['name']) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)));
            $slug = trim($slug, '-');
        }

        Database::query(
            "UPDATE categories SET name = ?, slug = ?, color = ?, type = ? WHERE id = ? AND tenant_id = ?",
            [$name, $slug, $color, $type, $id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_update_category', "Updated category #{$id}: {$name}");

        // Fetch updated record with listing count
        $updated = Database::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id) as listing_count
             FROM categories c
             WHERE c.id = ? AND c.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        $this->respondWithData([
            'id' => (int) $updated['id'],
            'name' => $updated['name'],
            'slug' => $updated['slug'],
            'color' => $updated['color'],
            'type' => $updated['type'],
            'listing_count' => (int) ($updated['listing_count'] ?? 0),
            'created_at' => $updated['created_at'],
        ]);
    }

    // ========================================
    // Attributes CRUD
    // ========================================

    /**
     * GET /api/v2/admin/attributes
     *
     * Lists all attributes for the current tenant.
     */
    public function listAttributes(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $items = Database::query(
            "SELECT a.*, c.name as category_name
             FROM attributes a
             LEFT JOIN categories c ON a.category_id = c.id
             WHERE a.tenant_id = ?
             ORDER BY a.category_id ASC, a.name ASC",
            [$tenantId]
        )->fetchAll();

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'] ?? '',
                'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($row['name'] ?? ''))),
                'type' => $row['input_type'] ?? 'checkbox',
                'options' => null,
                'category_id' => $row['category_id'] ? (int) $row['category_id'] : null,
                'category_name' => $row['category_name'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'target_type' => $row['target_type'] ?? 'any',
            ];
        }, $items);

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/attributes
     *
     * Create a new attribute.
     */
    public function storeAttribute(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Attribute name is required',
                'name',
                422
            );
            return;
        }

        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $inputType = trim($data['type'] ?? $data['input_type'] ?? 'checkbox');

        $id = \Nexus\Models\Attribute::create($name, $categoryId, $inputType);

        ActivityLog::log($adminId, 'admin_create_attribute', "Created attribute #{$id}: {$name}");

        $this->respondWithData([
            'id' => (int) $id,
            'name' => $name,
            'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)),
            'type' => $inputType,
            'options' => null,
            'category_id' => $categoryId,
            'is_active' => true,
        ], null, 201);
    }

    /**
     * PUT /api/v2/admin/attributes/{id}
     *
     * Update an existing attribute.
     */
    public function updateAttribute(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $data = $this->getAllInput();

        $attribute = \Nexus\Models\Attribute::find($id);
        if (!$attribute) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Attribute not found', null, 404);
            return;
        }

        $name = isset($data['name']) && trim($data['name']) !== '' ? trim($data['name']) : $attribute['name'];
        $categoryId = array_key_exists('category_id', $data) ? ($data['category_id'] ?: null) : ($attribute['category_id'] ?: null);
        $inputType = isset($data['type']) ? trim($data['type']) : (isset($data['input_type']) ? trim($data['input_type']) : $attribute['input_type']);
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : ($attribute['is_active'] ?? 1);

        \Nexus\Models\Attribute::update($id, [
            'name' => $name,
            'category_id' => $categoryId,
            'input_type' => $inputType,
            'is_active' => $isActive,
        ]);

        ActivityLog::log($adminId, 'admin_update_attribute', "Updated attribute #{$id}: {$name}");

        $this->respondWithData([
            'id' => (int) $id,
            'name' => $name,
            'slug' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)),
            'type' => $inputType,
            'is_active' => (bool) $isActive,
        ]);
    }

    /**
     * DELETE /api/v2/admin/attributes/{id}
     *
     * Delete an attribute.
     */
    public function destroyAttribute(int $id): void
    {
        $adminId = $this->requireAdmin();

        $attribute = \Nexus\Models\Attribute::find($id);
        if (!$attribute) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Attribute not found', null, 404);
            return;
        }

        \Nexus\Models\Attribute::delete($id);

        ActivityLog::log($adminId, 'admin_delete_attribute', "Deleted attribute #{$id}: {$attribute['name']}");

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * DELETE /api/v2/admin/categories/{id}
     *
     * Delete a category. Warns about assigned listings.
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $category = Database::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id) as listing_count
             FROM categories c
             WHERE c.id = ? AND c.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$category) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Category not found',
                null,
                404
            );
            return;
        }

        $listingCount = (int) ($category['listing_count'] ?? 0);

        // Nullify category_id on affected listings so they are not orphaned with a bad FK
        if ($listingCount > 0) {
            Database::query(
                "UPDATE listings SET category_id = NULL WHERE category_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
        }

        Database::query(
            "DELETE FROM categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log(
            $adminId,
            'admin_delete_category',
            "Deleted category #{$id}: {$category['name']}" . ($listingCount > 0 ? " ({$listingCount} listings unassigned)" : '')
        );

        $this->respondWithData([
            'deleted' => true,
            'id' => $id,
            'listings_unassigned' => $listingCount,
        ]);
    }
}
