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
 * ChallengeCategoryService - CRUD for challenge categories
 *
 * Categories are a per-tenant taxonomy used to classify ideation challenges.
 * Each category has a name, slug, optional icon/color, and sort order.
 *
 * @package Nexus\Services
 */
class ChallengeCategoryService
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
     * List all categories for the current tenant
     *
     * @return array
     */
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT * FROM challenge_categories WHERE tenant_id = ? ORDER BY sort_order ASC, name ASC",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Get a single category by ID
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT * FROM challenge_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Create a new category
     *
     * @return int|null Category ID on success
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage categories');
            return null;
        }

        $tenantId = TenantContext::getId();
        $name = trim($data['name'] ?? '');

        if (empty($name)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Name is required', 'name');
            return null;
        }

        $slug = self::generateSlug($name);
        $icon = !empty($data['icon']) ? trim($data['icon']) : null;
        $color = !empty($data['color']) ? trim($data['color']) : null;
        $sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;

        // Check for duplicate slug in this tenant
        $existing = Database::query(
            "SELECT id FROM challenge_categories WHERE tenant_id = ? AND slug = ?",
            [$tenantId, $slug]
        )->fetch();

        if ($existing) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'A category with this name already exists', 'name');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO challenge_categories (tenant_id, name, slug, icon, color, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [$tenantId, $name, $slug, $icon, $color, $sortOrder]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Category creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create category');
            return null;
        }
    }

    /**
     * Update a category
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage categories');
            return false;
        }

        $category = self::getById($id);
        if (!$category) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Category not found');
            return false;
        }

        $tenantId = TenantContext::getId();
        $updates = [];
        $params = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Name cannot be empty', 'name');
                return false;
            }
            $slug = self::generateSlug($name);

            // Check duplicate slug (excluding current)
            $existing = Database::query(
                "SELECT id FROM challenge_categories WHERE tenant_id = ? AND slug = ? AND id != ?",
                [$tenantId, $slug, $id]
            )->fetch();

            if ($existing) {
                self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'A category with this name already exists', 'name');
                return false;
            }

            $updates[] = "name = ?";
            $params[] = $name;
            $updates[] = "slug = ?";
            $params[] = $slug;
        }

        if (array_key_exists('icon', $data)) {
            $updates[] = "icon = ?";
            $params[] = !empty($data['icon']) ? trim($data['icon']) : null;
        }

        if (array_key_exists('color', $data)) {
            $updates[] = "color = ?";
            $params[] = !empty($data['color']) ? trim($data['color']) : null;
        }

        if (isset($data['sort_order'])) {
            $updates[] = "sort_order = ?";
            $params[] = (int)$data['sort_order'];
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE challenge_categories SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Category update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update category');
            return false;
        }
    }

    /**
     * Delete a category
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        if (!self::isAdmin($userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can manage categories');
            return false;
        }

        $category = self::getById($id);
        if (!$category) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Category not found');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // Null out the category_id on challenges that use this category
            Database::query(
                "UPDATE ideation_challenges SET category_id = NULL WHERE category_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            Database::query(
                "DELETE FROM challenge_categories WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("Category deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete category');
            return false;
        }
    }

    /**
     * Generate a URL-safe slug from a name
     */
    private static function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Check if user has admin role
     */
    private static function isAdmin(int $userId): bool
    {
        $user = Database::query(
            "SELECT role FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
