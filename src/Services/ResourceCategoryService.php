<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * ResourceCategoryService - Hierarchical categories for resources
 *
 * Manages a tree of resource categories with parent-child relationships,
 * sort ordering, and icons. Used by both the Resources module and the
 * Knowledge Base.
 *
 * @package Nexus\Services
 */
class ResourceCategoryService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Get all resource categories for the tenant (hierarchical)
     *
     * @param bool $flat If true, return flat list; if false, return nested tree
     * @return array
     */
    public static function getAll(bool $flat = false): array
    {
        $tenantId = TenantContext::getId();

        $categories = Database::query(
            "SELECT rc.*, COUNT(r.id) as resource_count
             FROM resource_categories rc
             LEFT JOIN resources r ON r.category_id = rc.id AND r.tenant_id = rc.tenant_id
             WHERE rc.tenant_id = ?
             GROUP BY rc.id
             ORDER BY rc.sort_order ASC, rc.name ASC",
            [$tenantId]
        )->fetchAll();

        $items = array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'parent_id' => $c['parent_id'] ? (int)$c['parent_id'] : null,
                'sort_order' => (int)$c['sort_order'],
                'icon' => $c['icon'],
                'description' => $c['description'],
                'resource_count' => (int)$c['resource_count'],
            ];
        }, $categories);

        if ($flat) {
            return $items;
        }

        // Build tree structure
        return self::buildTree($items);
    }

    /**
     * Build a tree from flat category list
     *
     * @param array $items
     * @param int|null $parentId
     * @return array
     */
    private static function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $item['children'] = self::buildTree($items, $item['id']);
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Get a single category by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $category = Database::query(
            "SELECT rc.*, COUNT(r.id) as resource_count
             FROM resource_categories rc
             LEFT JOIN resources r ON r.category_id = rc.id AND r.tenant_id = rc.tenant_id
             WHERE rc.id = ? AND rc.tenant_id = ?
             GROUP BY rc.id",
            [$id, $tenantId]
        )->fetch();

        if (!$category) {
            return null;
        }

        return [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'parent_id' => $category['parent_id'] ? (int)$category['parent_id'] : null,
            'sort_order' => (int)$category['sort_order'],
            'icon' => $category['icon'],
            'description' => $category['description'],
            'resource_count' => (int)$category['resource_count'],
        ];
    }

    /**
     * Create a new resource category (admin only)
     *
     * @param array $data Keys: name, slug, parent_id, sort_order, icon, description
     * @return int|null Category ID on success
     */
    public static function create(array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $name = trim($data['name'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $icon = trim($data['icon'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Category name is required', 'name');
            return null;
        }

        // Auto-generate slug if not provided
        if (empty($slug)) {
            $slug = self::generateSlug($name);
        }

        // Check for duplicate slug in this tenant
        $existing = Database::query(
            "SELECT id FROM resource_categories WHERE slug = ? AND tenant_id = ?",
            [$slug, $tenantId]
        )->fetch();

        if ($existing) {
            $slug = $slug . '-' . time();
        }

        // Validate parent exists
        if ($parentId !== null) {
            $parent = Database::query(
                "SELECT id FROM resource_categories WHERE id = ? AND tenant_id = ?",
                [$parentId, $tenantId]
            )->fetch();

            if (!$parent) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Parent category not found', 'parent_id');
                return null;
            }
        }

        try {
            Database::query(
                "INSERT INTO resource_categories (tenant_id, name, slug, parent_id, sort_order, icon, description, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $name, $slug, $parentId, $sortOrder, $icon ?: null, $description ?: null]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Resource category creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create category');
            return null;
        }
    }

    /**
     * Update a resource category
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        $category = self::getById($id);
        if (!$category) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Category not found');
            return false;
        }

        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Name cannot be empty', 'name');
                return false;
            }
            $updates[] = "name = ?";
            $params[] = $name;
        }

        if (isset($data['slug'])) {
            $updates[] = "slug = ?";
            $params[] = trim($data['slug']);
        }

        if (array_key_exists('parent_id', $data)) {
            $updates[] = "parent_id = ?";
            $params[] = $data['parent_id'] !== null ? (int)$data['parent_id'] : null;
        }

        if (isset($data['sort_order'])) {
            $updates[] = "sort_order = ?";
            $params[] = (int)$data['sort_order'];
        }

        if (array_key_exists('icon', $data)) {
            $updates[] = "icon = ?";
            $params[] = $data['icon'] ?: null;
        }

        if (array_key_exists('description', $data)) {
            $updates[] = "description = ?";
            $params[] = trim($data['description']) ?: null;
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE resource_categories SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Resource category update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update category');
            return false;
        }
    }

    /**
     * Delete a resource category
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Check for child categories
        $children = Database::query(
            "SELECT COUNT(*) as count FROM resource_categories WHERE parent_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if ((int)$children['count'] > 0) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot delete category with child categories');
            return false;
        }

        try {
            // Unset category on associated resources (set to null)
            Database::query(
                "UPDATE resources SET category_id = NULL WHERE category_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            Database::query(
                "DELETE FROM resource_categories WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("Resource category deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete category');
            return false;
        }
    }

    /**
     * Generate a URL-friendly slug from a name
     *
     * @param string $name
     * @return string
     */
    private static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'category';
    }
}
