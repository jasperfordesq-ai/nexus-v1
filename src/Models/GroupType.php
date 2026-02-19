<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GroupType
{
    /**
     * Create a new group type
     */
    public static function create($data)
    {
        $tenantId = TenantContext::getId();

        // Generate slug from name if not provided
        if (empty($data['slug'])) {
            $data['slug'] = self::generateSlug($data['name']);
        }

        $sql = "INSERT INTO group_types (tenant_id, name, slug, description, icon, color, sort_order, is_active, is_hub)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        Database::query($sql, [
            $tenantId,
            $data['name'],
            $data['slug'],
            $data['description'] ?? '',
            $data['icon'] ?? 'fa-layer-group',
            $data['color'] ?? '#6366f1',
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $data['is_hub'] ?? 0
        ]);

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Update an existing group type
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();
        $allowed = ['name', 'slug', 'description', 'icon', 'color', 'image_url', 'sort_order', 'is_active', 'is_hub'];
        $set = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed)) {
                continue;
            }

            // Don't overwrite image_url with empty values
            if ($key === 'image_url' && ($value === '' || $value === null)) {
                continue;
            }

            $set[] = "`$key` = ?";
            $params[] = $value;
        }

        if (empty($set)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE group_types SET " . implode(', ', $set) . " WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, $params);
    }

    /**
     * Delete a group type
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();

        // Note: Foreign key is set to ON DELETE SET NULL
        // So deleting a type will set groups.type_id to NULL, not delete the groups
        $sql = "DELETE FROM group_types WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Get a single group type by ID
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM group_types WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$id, $tenantId])->fetch();
    }

    /**
     * Get a group type by slug
     */
    public static function findBySlug($slug)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM group_types WHERE slug = ? AND tenant_id = ?";
        return Database::query($sql, [$slug, $tenantId])->fetch();
    }

    /**
     * Get all group types for current tenant
     */
    public static function all($activeOnly = false)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT gt.*,
                COUNT(DISTINCT g.id) as group_count
                FROM group_types gt
                LEFT JOIN `groups` g ON gt.id = g.type_id AND g.tenant_id = gt.tenant_id
                WHERE gt.tenant_id = ?";

        $params = [$tenantId];

        if ($activeOnly) {
            $sql .= " AND gt.is_active = 1";
        }

        $sql .= " GROUP BY gt.id ORDER BY gt.sort_order ASC, gt.name ASC";

        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Get active group types only
     */
    public static function getActive()
    {
        return self::all(true);
    }

    /**
     * Get groups by type
     */
    public static function getGroups($typeId, $limit = null)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT g.*,
                COUNT(gm.id) as member_count
                FROM `groups` g
                LEFT JOIN group_members gm ON g.id = gm.group_id
                WHERE g.tenant_id = ? AND g.type_id = ?
                GROUP BY g.id
                ORDER BY member_count DESC, g.created_at DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        return Database::query($sql, [$tenantId, $typeId])->fetchAll();
    }

    /**
     * Get statistics for a group type
     */
    public static function getStats($typeId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT
                COUNT(DISTINCT g.id) as total_groups,
                COUNT(DISTINCT gm.user_id) as total_members,
                COUNT(DISTINCT CASE WHEN g.visibility = 'public' THEN g.id END) as public_groups,
                COUNT(DISTINCT CASE WHEN g.visibility = 'private' THEN g.id END) as private_groups
                FROM `groups` g
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                WHERE g.tenant_id = ? AND g.type_id = ?";

        return Database::query($sql, [$tenantId, $typeId])->fetch();
    }

    /**
     * Reorder group types
     */
    public static function reorder($orderedIds)
    {
        $tenantId = TenantContext::getId();

        foreach ($orderedIds as $index => $id) {
            $sortOrder = ($index + 1) * 10;
            Database::query(
                "UPDATE group_types SET sort_order = ? WHERE id = ? AND tenant_id = ?",
                [$sortOrder, $id, $tenantId]
            );
        }

        return true;
    }

    /**
     * Check if slug is unique
     */
    public static function isSlugUnique($slug, $excludeId = null)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT id FROM group_types WHERE slug = ? AND tenant_id = ?";
        $params = [$slug, $tenantId];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = Database::query($sql, $params)->fetch();
        return !$result;
    }

    /**
     * Generate a URL-friendly slug from name
     */
    public static function generateSlug($name)
    {
        // Convert to lowercase
        $slug = strtolower($name);

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while (!self::isSlugUnique($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Toggle active status
     */
    public static function toggleActive($id)
    {
        $tenantId = TenantContext::getId();

        $sql = "UPDATE group_types
                SET is_active = NOT is_active
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Get overview statistics for all types
     */
    public static function getOverviewStats()
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT
                COUNT(*) as total_types,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types,
                (SELECT COUNT(DISTINCT id) FROM `groups` WHERE tenant_id = ? AND type_id IS NOT NULL) as categorized_groups,
                (SELECT COUNT(DISTINCT id) FROM `groups` WHERE tenant_id = ? AND type_id IS NULL) as uncategorized_groups
                FROM group_types
                WHERE tenant_id = ?";

        return Database::query($sql, [$tenantId, $tenantId, $tenantId])->fetch();
    }

    /**
     * Get the hub type for current tenant
     * Returns the special "Local Hub" type marked with is_hub = 1
     */
    public static function getHubType()
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT * FROM group_types WHERE tenant_id = ? AND is_hub = 1 LIMIT 1";
        return Database::query($sql, [$tenantId])->fetch();
    }

    /**
     * Get all regular (non-hub) types
     */
    public static function getRegularTypes($activeOnly = true)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT gt.*,
                COUNT(DISTINCT g.id) as group_count
                FROM group_types gt
                LEFT JOIN `groups` g ON gt.id = g.type_id AND g.tenant_id = gt.tenant_id
                WHERE gt.tenant_id = ? AND gt.is_hub = 0";

        $params = [$tenantId];

        if ($activeOnly) {
            $sql .= " AND gt.is_active = 1";
        }

        $sql .= " GROUP BY gt.id ORDER BY gt.sort_order ASC, gt.name ASC";

        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Check if a type is a hub type
     */
    public static function isHubType($typeId)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT is_hub FROM group_types WHERE id = ? AND tenant_id = ?";
        $result = Database::query($sql, [$typeId, $tenantId])->fetch();
        return $result ? (bool)$result['is_hub'] : false;
    }
}
