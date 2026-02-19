<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;

class MenuItem
{
    /**
     * Get all items for a menu
     * @param int $menuId
     * @param bool $hierarchical If true, returns nested structure
     */
    public static function getByMenu($menuId, $hierarchical = false)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM menu_items
            WHERE menu_id = ?
            AND is_active = 1
            ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$menuId]);
        $items = $stmt->fetchAll();

        if ($hierarchical) {
            return self::buildHierarchy($items);
        }

        return $items;
    }

    /**
     * Build hierarchical tree structure from flat items
     */
    private static function buildHierarchy($items, $parentId = null)
    {
        $branch = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = self::buildHierarchy($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $branch[] = $item;
            }
        }

        return $branch;
    }

    /**
     * Find a menu item by ID
     */
    public static function find($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create a new menu item
     */
    public static function create($data)
    {
        $sql = "INSERT INTO menu_items
                (menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $visibilityRules = isset($data['visibility_rules']) && is_array($data['visibility_rules'])
            ? json_encode($data['visibility_rules'])
            : ($data['visibility_rules'] ?? null);

        $params = [
            $data['menu_id'],
            $data['parent_id'] ?? null,
            $data['type'] ?? 'link',
            $data['label'],
            $data['url'] ?? null,
            $data['route_name'] ?? null,
            $data['page_id'] ?? null,
            $data['icon'] ?? null,
            $data['css_class'] ?? null,
            $data['target'] ?? '_self',
            $data['sort_order'] ?? 0,
            $visibilityRules,
            $data['is_active'] ?? 1
        ];

        Database::query($sql, $params);
        return Database::lastInsertId();
    }

    /**
     * Update a menu item
     */
    public static function update($id, $data)
    {
        $db = Database::getConnection();

        $visibilityRules = isset($data['visibility_rules']) && is_array($data['visibility_rules'])
            ? json_encode($data['visibility_rules'])
            : ($data['visibility_rules'] ?? null);

        $sql = "UPDATE menu_items
                SET parent_id = ?,
                    type = ?,
                    label = ?,
                    url = ?,
                    route_name = ?,
                    page_id = ?,
                    icon = ?,
                    css_class = ?,
                    target = ?,
                    sort_order = ?,
                    visibility_rules = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?";

        $params = [
            $data['parent_id'] ?? null,
            $data['type'] ?? 'link',
            $data['label'],
            $data['url'] ?? null,
            $data['route_name'] ?? null,
            $data['page_id'] ?? null,
            $data['icon'] ?? null,
            $data['css_class'] ?? null,
            $data['target'] ?? '_self',
            $data['sort_order'] ?? 0,
            $visibilityRules,
            $data['is_active'] ?? 1,
            $id
        ];

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a menu item
     */
    public static function delete($id)
    {
        $db = Database::getConnection();
        // Children will be cascade deleted by foreign key constraint
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Update sort order for multiple items at once
     */
    public static function updateSortOrder($items)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE menu_items SET sort_order = ? WHERE id = ?");

        foreach ($items as $item) {
            $stmt->execute([$item['sort_order'], $item['id']]);
        }

        return true;
    }

    /**
     * Count items in a menu
     */
    public static function countByMenu($menuId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM menu_items WHERE menu_id = ?");
        $stmt->execute([$menuId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    /**
     * Check if user can see this menu item based on visibility rules
     */
    public static function isVisible($item, $user = null, $tenantFeatures = [])
    {
        // If no visibility rules, item is visible
        if (empty($item['visibility_rules'])) {
            return true;
        }

        $rules = is_string($item['visibility_rules'])
            ? json_decode($item['visibility_rules'], true)
            : $item['visibility_rules'];

        if (!is_array($rules)) {
            return true;
        }

        // Check authentication requirement
        if (isset($rules['requires_auth']) && $rules['requires_auth']) {
            if (!$user || !isset($_SESSION['user_id'])) {
                return false;
            }
        }

        // Check minimum role requirement
        if (isset($rules['min_role']) && $user) {
            $roleHierarchy = [
                'user' => 0,
                'admin' => 1,
                'tenant_admin' => 2,
                'super_admin' => 3
            ];

            $userRole = $user['role'] ?? $_SESSION['user_role'] ?? 'user';
            $requiredRole = $rules['min_role'];

            $userLevel = $roleHierarchy[$userRole] ?? 0;
            $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;

            if ($userLevel < $requiredLevel) {
                return false;
            }
        }

        // Check role exclusions
        if (isset($rules['exclude_roles']) && is_array($rules['exclude_roles'])) {
            $userRole = $user['role'] ?? $_SESSION['user_role'] ?? 'guest';
            if (in_array($userRole, $rules['exclude_roles'])) {
                return false;
            }
        }

        // Check feature requirement
        if (isset($rules['requires_feature'])) {
            $requiredFeature = $rules['requires_feature'];
            if (!isset($tenantFeatures[$requiredFeature]) || !$tenantFeatures[$requiredFeature]) {
                return false;
            }
        }

        // Check custom condition (if provided as callable)
        if (isset($rules['custom_condition']) && is_callable($rules['custom_condition'])) {
            return $rules['custom_condition']($item, $user, $tenantFeatures);
        }

        return true;
    }

    /**
     * Filter menu items based on visibility rules
     */
    public static function filterVisible($items, $user = null, $tenantFeatures = [])
    {
        $filtered = [];
        foreach ($items as $item) {
            $visible = self::isVisible($item, $user, $tenantFeatures);

            // Recursively filter children if item has children
            if ($visible && isset($item['children']) && is_array($item['children'])) {
                $item['children'] = self::filterVisible($item['children'], $user, $tenantFeatures);
            }

            if ($visible) {
                $filtered[] = $item;
            }
        }
        return $filtered;
    }

    /**
     * Resolve menu item URL based on type
     */
    public static function resolveUrl($item, $basePath = '')
    {
        switch ($item['type']) {
            case 'link':
            case 'external':
                return $item['url'];

            case 'page':
                if ($item['page_id']) {
                    // Fetch page slug and build URL
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT slug FROM pages WHERE id = ?");
                    $stmt->execute([$item['page_id']]);
                    $page = $stmt->fetch();
                    if ($page) {
                        return $basePath . '/page/' . $page['slug'];
                    }
                }
                return '#';

            case 'route':
                if ($item['route_name']) {
                    // In a full router system, this would resolve route name to URL
                    // For now, return a placeholder or the route name
                    return $basePath . '/' . $item['route_name'];
                }
                return '#';

            case 'dropdown':
            case 'divider':
                return '#';

            default:
                return $item['url'] ?? '#';
        }
    }
}
