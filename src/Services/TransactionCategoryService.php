<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * TransactionCategoryService (W8)
 *
 * Manages transaction categories for labelling exchanges and transfers.
 * Each tenant can have custom categories plus system defaults.
 */
class TransactionCategoryService
{
    /**
     * Default system categories seeded per tenant
     */
    private const DEFAULTS = [
        ['slug' => 'general', 'name' => 'General', 'icon' => 'circle', 'color' => '#6B7280'],
        ['slug' => 'gardening', 'name' => 'Gardening', 'icon' => 'flower-2', 'color' => '#22C55E'],
        ['slug' => 'cooking', 'name' => 'Cooking', 'icon' => 'chef-hat', 'color' => '#F97316'],
        ['slug' => 'transport', 'name' => 'Transport', 'icon' => 'car', 'color' => '#3B82F6'],
        ['slug' => 'childcare', 'name' => 'Childcare', 'icon' => 'baby', 'color' => '#EC4899'],
        ['slug' => 'tech-help', 'name' => 'Tech Help', 'icon' => 'laptop', 'color' => '#8B5CF6'],
        ['slug' => 'teaching', 'name' => 'Teaching', 'icon' => 'graduation-cap', 'color' => '#0EA5E9'],
        ['slug' => 'diy', 'name' => 'DIY & Repairs', 'icon' => 'wrench', 'color' => '#EAB308'],
        ['slug' => 'admin-tasks', 'name' => 'Admin & Paperwork', 'icon' => 'file-text', 'color' => '#64748B'],
        ['slug' => 'companionship', 'name' => 'Companionship', 'icon' => 'heart', 'color' => '#EF4444'],
        ['slug' => 'creative', 'name' => 'Creative Arts', 'icon' => 'palette', 'color' => '#A855F7'],
        ['slug' => 'other', 'name' => 'Other', 'icon' => 'more-horizontal', 'color' => '#9CA3AF'],
    ];

    /**
     * Ensure default categories exist for current tenant
     */
    public static function ensureDefaults(): void
    {
        $tenantId = TenantContext::getId();

        foreach (self::DEFAULTS as $i => $cat) {
            try {
                Database::query(
                    "INSERT IGNORE INTO transaction_categories
                     (tenant_id, slug, name, icon, color, sort_order, is_system, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1, 1)",
                    [$tenantId, $cat['slug'], $cat['name'], $cat['icon'], $cat['color'], $i * 10]
                );
            } catch (\Exception $e) {
                // Duplicate — ignore
            }
        }
    }

    /**
     * Get all active categories for the current tenant
     *
     * @return array Categories
     */
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT id, slug, name, description, icon, color, sort_order, is_system
             FROM transaction_categories
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            [$tenantId]
        );

        $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // If none exist, seed defaults
        if (empty($categories)) {
            self::ensureDefaults();

            $stmt = Database::query(
                "SELECT id, slug, name, description, icon, color, sort_order, is_system
                 FROM transaction_categories
                 WHERE tenant_id = ? AND is_active = 1
                 ORDER BY sort_order ASC, name ASC",
                [$tenantId]
            );
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return array_map(function ($cat) {
            return [
                'id' => (int) $cat['id'],
                'slug' => $cat['slug'],
                'name' => $cat['name'],
                'description' => $cat['description'],
                'icon' => $cat['icon'],
                'color' => $cat['color'],
                'sort_order' => (int) $cat['sort_order'],
                'is_system' => (bool) $cat['is_system'],
            ];
        }, $categories);
    }

    /**
     * Get a single category by ID
     *
     * @param int $id Category ID
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM transaction_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $cat = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $cat ?: null;
    }

    /**
     * Create a custom category (admin only)
     *
     * @param array $data ['name', 'slug', 'description', 'icon', 'color']
     * @return int|null Category ID
     */
    public static function create(array $data): ?int
    {
        $tenantId = TenantContext::getId();
        $slug = $data['slug'] ?? self::slugify($data['name'] ?? '');

        try {
            Database::query(
                "INSERT INTO transaction_categories
                 (tenant_id, name, slug, description, icon, color, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $tenantId,
                    $data['name'],
                    $slug,
                    $data['description'] ?? null,
                    $data['icon'] ?? 'circle',
                    $data['color'] ?? '#6B7280',
                    (int) ($data['sort_order'] ?? 100),
                ]
            );

            return Database::lastInsertId();
        } catch (\Exception $e) {
            error_log("TransactionCategoryService::create error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a category (admin only, non-system categories)
     *
     * @param int $id Category ID
     * @param array $data Fields to update
     * @return bool Success
     */
    public static function update(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $sets = [];
        $params = [];

        $allowed = ['name', 'description', 'icon', 'color', 'sort_order', 'is_active'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $setStr = implode(', ', $sets);
        Database::query(
            "UPDATE transaction_categories SET {$setStr} WHERE id = ? AND tenant_id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete a non-system category (soft delete via is_active)
     *
     * @param int $id Category ID
     * @return bool Success
     */
    public static function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        // Cannot delete system categories
        $stmt = Database::query(
            "SELECT is_system FROM transaction_categories WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $cat = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cat || $cat['is_system']) {
            return false;
        }

        Database::query(
            "UPDATE transaction_categories SET is_active = 0 WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return true;
    }

    /**
     * Generate a URL-safe slug from a name
     */
    private static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}
