<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class Tenant
{
    public static function all()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM tenants ORDER BY path, id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Create a new tenant and seed default attributes.
     * @deprecated Use TenantHierarchyService::createTenant() for hierarchy-aware creation
     */
    public static function create($name, $slug, $domain = null)
    {
        $sql = "INSERT INTO tenants (name, slug, domain, path, depth) VALUES (?, ?, ?, '', 0)";
        Database::query($sql, [$name, $slug, $domain]);
        $tenantId = Database::lastInsertId();

        // Set path after we have the ID
        Database::query("UPDATE tenants SET path = ? WHERE id = ?", ['/' . $tenantId . '/', $tenantId]);

        // Hardwire Default Attributes
        \Nexus\Models\Attribute::seedDefaults($tenantId);

        // Hardwire Default Categories
        \Nexus\Models\Category::seedDefaults($tenantId);

        return $tenantId;
    }

    /**
     * Update tenant configuration
     */
    public static function updateConfig($id, $config)
    {
        $db = Database::getConnection();
        $sql = "UPDATE tenants SET configuration = ? WHERE id = ?";
        $statement = $db->prepare($sql);
        return $statement->execute([json_encode($config), $id]);
    }

    public static function find($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find tenant by slug
     */
    public static function findBySlug($slug)
    {
        return Database::query(
            "SELECT * FROM tenants WHERE slug = ?",
            [$slug]
        )->fetch();
    }

    /**
     * Find tenant by domain
     */
    public static function findByDomain($domain)
    {
        return Database::query(
            "SELECT * FROM tenants WHERE domain = ?",
            [$domain]
        )->fetch();
    }

    // =========================================================================
    // HIERARCHY METHODS
    // =========================================================================

    /**
     * Get all child tenants (direct children only)
     */
    public static function getChildren($tenantId): array
    {
        return Database::query(
            "SELECT * FROM tenants WHERE parent_id = ? ORDER BY name",
            [$tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public static function getDescendants($tenantId): array
    {
        $tenant = self::find($tenantId);
        if (!$tenant || empty($tenant['path'])) {
            return [];
        }

        return Database::query(
            "SELECT * FROM tenants WHERE path LIKE ? AND id != ? ORDER BY path",
            [$tenant['path'] . '%', $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public static function getAncestors($tenantId): array
    {
        $tenant = self::find($tenantId);
        if (!$tenant || empty($tenant['path'])) {
            return [];
        }

        // Extract IDs from path like /1/2/5/ -> [1, 2]
        $pathParts = array_filter(explode('/', trim($tenant['path'], '/')));
        array_pop($pathParts); // Remove own ID

        if (empty($pathParts)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pathParts), '?'));
        return Database::query(
            "SELECT * FROM tenants WHERE id IN ({$placeholders}) ORDER BY depth ASC",
            $pathParts
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the parent tenant
     */
    public static function getParent($tenantId): ?array
    {
        $tenant = self::find($tenantId);
        if (!$tenant || !$tenant['parent_id']) {
            return null;
        }

        return self::find($tenant['parent_id']) ?: null;
    }

    /**
     * Check if tenant is an ancestor of another tenant
     */
    public static function isAncestorOf($potentialAncestorId, $tenantId): bool
    {
        $tenant = self::find($tenantId);
        $ancestor = self::find($potentialAncestorId);

        if (!$tenant || !$ancestor) {
            return false;
        }

        // Ancestor's path should be a prefix of tenant's path
        return str_starts_with($tenant['path'], $ancestor['path'])
            && $tenant['id'] !== $ancestor['id'];
    }

    /**
     * Check if tenant is a descendant of another tenant
     */
    public static function isDescendantOf($potentialDescendantId, $tenantId): bool
    {
        return self::isAncestorOf($tenantId, $potentialDescendantId);
    }

    /**
     * Get tenant depth in hierarchy (0 = root/master)
     */
    public static function getDepth($tenantId): int
    {
        $tenant = self::find($tenantId);
        return $tenant ? (int)($tenant['depth'] ?? 0) : 0;
    }

    /**
     * Check if tenant allows sub-tenants
     */
    public static function allowsSubtenants($tenantId): bool
    {
        $tenant = self::find($tenantId);
        return $tenant && (bool)($tenant['allows_subtenants'] ?? false);
    }

    /**
     * Get the root/master tenant
     */
    public static function getMaster(): ?array
    {
        return self::find(1) ?: null;
    }

    /**
     * Get all root-level tenants (no parent)
     */
    public static function getRoots(): array
    {
        return Database::query(
            "SELECT * FROM tenants WHERE parent_id IS NULL ORDER BY id"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get hierarchy path as array of tenant names
     */
    public static function getBreadcrumb($tenantId): array
    {
        $ancestors = self::getAncestors($tenantId);
        $current = self::find($tenantId);

        $breadcrumb = [];
        foreach ($ancestors as $ancestor) {
            $breadcrumb[] = [
                'id' => $ancestor['id'],
                'name' => $ancestor['name'],
                'slug' => $ancestor['slug']
            ];
        }

        if ($current) {
            $breadcrumb[] = [
                'id' => $current['id'],
                'name' => $current['name'],
                'slug' => $current['slug']
            ];
        }

        return $breadcrumb;
    }
}
