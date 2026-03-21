<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TransactionCategoryService — native Eloquent/DB implementation.
 *
 * Manages transaction categories per tenant. Categories label transactions
 * (e.g. "Gardening", "Tutoring"). System categories (is_system=1) cannot
 * be deleted by admins.
 */
class TransactionCategoryService
{
    /**
     * Get all active categories for the current tenant, ordered by sort_order.
     *
     * @return array<int, array>
     */
    public function getAll(): array
    {
        $tenantId = TenantContext::getId();

        $rows = DB::select(
            "SELECT id, tenant_id, name, slug, description, icon, color, sort_order, is_system, is_active, created_at, updated_at
             FROM transaction_categories
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            [$tenantId]
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    /**
     * Get a single category by ID (tenant-scoped).
     *
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $row = DB::selectOne(
            "SELECT id, tenant_id, name, slug, description, icon, color, sort_order, is_system, is_active, created_at, updated_at
             FROM transaction_categories
             WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * Create a new transaction category.
     *
     * @param array $data Must contain 'name'; may contain 'description', 'icon', 'color', 'sort_order'.
     * @return int|null The new category ID, or null on failure.
     */
    public function create(array $data): ?int
    {
        $tenantId = TenantContext::getId();
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return null;
        }

        $slug = $this->generateUniqueSlug($tenantId, $name);

        try {
            $inserted = DB::insert(
                "INSERT INTO transaction_categories (tenant_id, name, slug, description, icon, color, sort_order, is_system, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, NOW(), NOW())",
                [
                    $tenantId,
                    $name,
                    $slug,
                    $data['description'] ?? null,
                    $data['icon'] ?? null,
                    $data['color'] ?? null,
                    (int) ($data['sort_order'] ?? 0),
                ]
            );

            if ($inserted) {
                return (int) DB::getPdo()->lastInsertId();
            }
        } catch (\Throwable $e) {
            Log::error('[TransactionCategoryService] create failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Update a category by ID (tenant-scoped).
     *
     * @param int   $id   Category ID.
     * @param array $data Fields to update (name, description, icon, color, sort_order, is_active).
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);

            // Regenerate slug when name changes
            $fields[] = 'slug = ?';
            $params[] = $this->generateUniqueSlug($tenantId, trim($data['name']), $id);
        }

        if (array_key_exists('description', $data)) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (array_key_exists('icon', $data)) {
            $fields[] = 'icon = ?';
            $params[] = $data['icon'];
        }

        if (array_key_exists('color', $data)) {
            $fields[] = 'color = ?';
            $params[] = $data['color'];
        }

        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $params[] = (int) $data['sort_order'];
        }

        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = (int) (bool) $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        $affected = DB::update(
            "UPDATE transaction_categories SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        return $affected > 0;
    }

    /**
     * Delete a category by ID (tenant-scoped). System categories cannot be deleted.
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        // Prevent deletion of system categories
        $category = DB::selectOne(
            "SELECT is_system FROM transaction_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$category || $category->is_system) {
            return false;
        }

        // Nullify category_id on transactions that reference this category
        DB::update(
            "UPDATE transactions SET category_id = NULL WHERE category_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $affected = DB::delete(
            "DELETE FROM transaction_categories WHERE id = ? AND tenant_id = ? AND is_system = 0",
            [$id, $tenantId]
        );

        return $affected > 0;
    }

    /**
     * Generate a unique slug for a category within a tenant.
     */
    private function generateUniqueSlug(int $tenantId, string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = "SELECT id FROM transaction_categories WHERE tenant_id = ? AND slug = ?";
            $params = [$tenantId, $slug];

            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }

            $existing = DB::selectOne($query, $params);

            if (!$existing) {
                break;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
