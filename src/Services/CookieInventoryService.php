<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Cookie Inventory Service
 *
 * Manages the inventory of cookies used by the platform.
 * Provides information for cookie banners, policy pages, and documentation.
 */
class CookieInventoryService
{
    /**
     * Get all cookies for a specific category
     *
     * @param string $category Category (essential, functional, analytics, marketing)
     * @param int|null $tenantId Tenant ID (null = global cookies only)
     * @return array List of cookies
     */
    public static function getCookiesByCategory(string $category, ?int $tenantId = null): array
    {
        if ($tenantId === null) {
            // Global cookies only
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE category = ?
                 AND tenant_id IS NULL
                 AND is_active = 1
                 ORDER BY cookie_name",
                [$category]
            );
        } else {
            // Tenant-specific + global cookies
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE category = ?
                 AND (tenant_id IS NULL OR tenant_id = ?)
                 AND is_active = 1
                 ORDER BY cookie_name",
                [$category, $tenantId]
            );
        }

        return $stmt->fetchAll();
    }

    /**
     * Get all cookies (for policy page)
     *
     * @param int|null $tenantId Tenant ID (null = global only)
     * @return array Cookies grouped by category
     */
    public static function getAllCookies(?int $tenantId = null): array
    {
        if ($tenantId === null) {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE tenant_id IS NULL
                 AND is_active = 1
                 ORDER BY category, cookie_name"
            );
        } else {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE (tenant_id IS NULL OR tenant_id = ?)
                 AND is_active = 1
                 ORDER BY category, cookie_name",
                [$tenantId]
            );
        }

        $cookies = $stmt->fetchAll();

        // Group by category
        $grouped = [
            'essential' => [],
            'functional' => [],
            'analytics' => [],
            'marketing' => []
        ];

        foreach ($cookies as $cookie) {
            $grouped[$cookie['category']][] = $cookie;
        }

        return $grouped;
    }

    /**
     * Get cookies formatted for banner display
     *
     * @param int|null $tenantId Tenant ID
     * @return array Cookies grouped by category with counts
     */
    public static function getBannerCookieList(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return self::getAllCookies($tenantId);
    }

    /**
     * Add new cookie to inventory (admin only)
     *
     * @param array $data Cookie data
     * @return int Cookie ID
     */
    public static function addCookie(array $data): int
    {
        $requiredFields = ['cookie_name', 'category', 'purpose', 'duration'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate category
        $validCategories = ['essential', 'functional', 'analytics', 'marketing'];
        if (!in_array($data['category'], $validCategories)) {
            throw new \InvalidArgumentException("Invalid category: {$data['category']}");
        }

        Database::query(
            "INSERT INTO cookie_inventory
             (cookie_name, category, purpose, duration, third_party, tenant_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['cookie_name'],
                $data['category'],
                $data['purpose'],
                $data['duration'],
                $data['third_party'] ?? 'First-party',
                $data['tenant_id'] ?? null,
                $data['is_active'] ?? true
            ]
        );

        return (int) Database::lastInsertId();
    }

    /**
     * Update cookie details
     *
     * @param int $id Cookie ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public static function updateCookie(int $id, array $data): bool
    {
        $allowedFields = [
            'cookie_name',
            'category',
            'purpose',
            'duration',
            'third_party',
            'is_active'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;

        Database::query(
            "UPDATE cookie_inventory
             SET " . implode(', ', $updates) . ", updated_at = NOW()
             WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete (deactivate) cookie from inventory
     *
     * @param int $id Cookie ID
     * @return bool Success status
     */
    public static function deleteCookie(int $id): bool
    {
        // Soft delete (set is_active = 0)
        Database::query(
            "UPDATE cookie_inventory SET is_active = 0, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        return true;
    }

    /**
     * Get single cookie by ID
     *
     * @param int $id Cookie ID
     * @return array|null Cookie data
     */
    public static function getCookie(int $id): ?array
    {
        $stmt = Database::query(
            "SELECT * FROM cookie_inventory WHERE id = ?",
            [$id]
        );

        $cookie = $stmt->fetch();
        return $cookie ?: null;
    }

    /**
     * Get cookie by name
     *
     * @param string $name Cookie name
     * @param int|null $tenantId Tenant ID
     * @return array|null Cookie data
     */
    public static function getCookieByName(string $name, ?int $tenantId = null): ?array
    {
        if ($tenantId === null) {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE cookie_name = ? AND tenant_id IS NULL
                 LIMIT 1",
                [$name]
            );
        } else {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE cookie_name = ? AND (tenant_id IS NULL OR tenant_id = ?)
                 ORDER BY tenant_id DESC
                 LIMIT 1",
                [$name, $tenantId]
            );
        }

        $cookie = $stmt->fetch();
        return $cookie ?: null;
    }

    /**
     * Get cookie count by category
     *
     * @param int|null $tenantId Tenant ID
     * @return array Category counts
     */
    public static function getCookieCounts(?int $tenantId = null): array
    {
        if ($tenantId === null) {
            $stmt = Database::query(
                "SELECT category, COUNT(*) AS count
                 FROM cookie_inventory
                 WHERE tenant_id IS NULL AND is_active = 1
                 GROUP BY category"
            );
        } else {
            $stmt = Database::query(
                "SELECT category, COUNT(*) AS count
                 FROM cookie_inventory
                 WHERE (tenant_id IS NULL OR tenant_id = ?) AND is_active = 1
                 GROUP BY category",
                [$tenantId]
            );
        }

        $counts = [
            'essential' => 0,
            'functional' => 0,
            'analytics' => 0,
            'marketing' => 0
        ];

        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['category']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Search cookies by name or purpose
     *
     * @param string $query Search query
     * @param int|null $tenantId Tenant ID
     * @return array Matching cookies
     */
    public static function searchCookies(string $query, ?int $tenantId = null): array
    {
        $searchTerm = "%{$query}%";

        if ($tenantId === null) {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE (cookie_name LIKE ? OR purpose LIKE ?)
                 AND tenant_id IS NULL
                 AND is_active = 1
                 ORDER BY cookie_name",
                [$searchTerm, $searchTerm]
            );
        } else {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE (cookie_name LIKE ? OR purpose LIKE ?)
                 AND (tenant_id IS NULL OR tenant_id = ?)
                 AND is_active = 1
                 ORDER BY cookie_name",
                [$searchTerm, $searchTerm, $tenantId]
            );
        }

        return $stmt->fetchAll();
    }

    /**
     * Get all cookies for admin management
     *
     * @param int|null $tenantId Tenant ID (null = all tenants)
     * @return array All cookies including inactive
     */
    public static function getAllCookiesAdmin(?int $tenantId = null): array
    {
        if ($tenantId === null) {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory ORDER BY tenant_id, category, cookie_name"
            );
        } else {
            $stmt = Database::query(
                "SELECT * FROM cookie_inventory
                 WHERE (tenant_id IS NULL OR tenant_id = ?)
                 ORDER BY category, cookie_name",
                [$tenantId]
            );
        }

        return $stmt->fetchAll();
    }
}
