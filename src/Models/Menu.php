<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Menu
{
    /**
     * Get all menus for current tenant
     */
    public static function all($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM menus WHERE tenant_id = ? ORDER BY location ASC, id ASC");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    /**
     * Get paginated menus
     */
    public static function paginate($tenantId = null, $page = 1, $perPage = 20)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM menus WHERE tenant_id = ?");
        $countStmt->execute([$tenantId]);
        $total = $countStmt->fetch()['total'];

        // Calculate pagination
        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        // Get paginated results
        $stmt = $db->prepare("
            SELECT * FROM menus
            WHERE tenant_id = ?
            ORDER BY location ASC, id ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $perPage, $offset]);
        $menus = $stmt->fetchAll();

        return [
            'data' => $menus,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ];
    }

    /**
     * Find a menu by ID
     */
    public static function find($id, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM menus WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Find a menu by slug
     */
    public static function findBySlug($slug, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM menus WHERE slug = ? AND tenant_id = ?");
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch();
    }

    /**
     * Get menus by location
     * Prefers layout-specific menus over generic ones
     */
    public static function getByLocation($location, $layout = null, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        if ($layout) {
            // First, try to get layout-specific menus
            $stmt = $db->prepare(
                "SELECT * FROM menus
                WHERE tenant_id = ?
                AND location = ?
                AND layout = ?
                AND is_active = 1
                ORDER BY id ASC"
            );
            $stmt->execute([$tenantId, $location, $layout]);
            $layoutSpecificMenus = $stmt->fetchAll();

            // If layout-specific menus exist, use only those
            if (!empty($layoutSpecificMenus)) {
                return $layoutSpecificMenus;
            }

            // Otherwise, fall back to layout-agnostic menus
            $stmt = $db->prepare(
                "SELECT * FROM menus
                WHERE tenant_id = ?
                AND location = ?
                AND (layout IS NULL OR layout = '')
                AND is_active = 1
                ORDER BY id ASC"
            );
            $stmt->execute([$tenantId, $location]);
            return $stmt->fetchAll();
        } else {
            // Get all menus for location regardless of layout
            $stmt = $db->prepare(
                "SELECT * FROM menus
                WHERE tenant_id = ?
                AND location = ?
                AND is_active = 1
                ORDER BY id ASC"
            );
            $stmt->execute([$tenantId, $location]);
            return $stmt->fetchAll();
        }
    }

    /**
     * Create a new menu
     */
    public static function create($data)
    {
        $tenantId = $data['tenant_id'] ?? TenantContext::getId();

        $sql = "INSERT INTO menus
                (tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $tenantId,
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['location'],
            $data['layout'] ?? null,
            $data['min_plan_tier'] ?? 0,
            $data['is_active'] ?? 1
        ];

        Database::query($sql, $params);
        return Database::lastInsertId();
    }

    /**
     * Update a menu
     */
    public static function update($id, $data, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        $sql = "UPDATE menus
                SET name = ?,
                    slug = ?,
                    description = ?,
                    location = ?,
                    layout = ?,
                    min_plan_tier = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?";

        $params = [
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['location'],
            $data['layout'] ?? null,
            $data['min_plan_tier'] ?? 0,
            $data['is_active'] ?? 1,
            $id,
            $tenantId
        ];

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a menu and all its items
     */
    public static function delete($id, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        // Items will be cascade deleted by foreign key constraint
        $stmt = $db->prepare("DELETE FROM menus WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tenantId]);
    }

    /**
     * Get menu with all its items (hierarchical structure)
     */
    public static function getWithItems($id, $tenantId = null)
    {
        $menu = self::find($id, $tenantId);
        if (!$menu) {
            return null;
        }

        $menu['items'] = MenuItem::getByMenu($id, true);
        return $menu;
    }

    /**
     * Count menus for a tenant
     */
    public static function count($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM menus WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    /**
     * Check if tenant can create more menus based on plan limits
     */
    public static function canCreateMore($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Get tenant's plan limits
        $plan = PayPlan::getCurrentPlanForTenant($tenantId);
        if (!$plan) {
            return false;
        }

        $maxMenus = $plan['max_menus'] ?? 1;
        $currentCount = self::count($tenantId);

        return $currentCount < $maxMenus;
    }

    /**
     * Toggle menu active/inactive status
     */
    public static function toggleActive($id, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $db = Database::getConnection();

        $sql = "UPDATE menus
                SET is_active = NOT is_active,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?";

        $stmt = $db->prepare($sql);
        return $stmt->execute([$id, $tenantId]);
    }

    /**
     * Seed default menus for a new tenant
     */
    public static function seedDefaults($tenantId)
    {
        // Create main navigation menu
        $mainMenuId = self::create([
            'tenant_id' => $tenantId,
            'name' => 'Main Navigation',
            'slug' => 'main-nav',
            'description' => 'Primary navigation menu',
            'location' => 'header-main',
            'layout' => null,
            'min_plan_tier' => 0,
            'is_active' => 1
        ]);

        // Create default menu items
        MenuItem::create([
            'menu_id' => $mainMenuId,
            'type' => 'link',
            'label' => 'Home',
            'url' => '/',
            'sort_order' => 10,
            'is_active' => 1
        ]);

        MenuItem::create([
            'menu_id' => $mainMenuId,
            'type' => 'link',
            'label' => 'Explore',
            'url' => '/listings',
            'sort_order' => 20,
            'is_active' => 1
        ]);

        MenuItem::create([
            'menu_id' => $mainMenuId,
            'type' => 'link',
            'label' => 'About',
            'url' => '/about',
            'sort_order' => 30,
            'is_active' => 1
        ]);

        // Create footer menu
        $footerMenuId = self::create([
            'tenant_id' => $tenantId,
            'name' => 'Footer Navigation',
            'slug' => 'footer-nav',
            'description' => 'Footer links',
            'location' => 'footer',
            'layout' => null,
            'min_plan_tier' => 0,
            'is_active' => 1
        ]);

        MenuItem::create([
            'menu_id' => $footerMenuId,
            'type' => 'link',
            'label' => 'Privacy Policy',
            'url' => '/privacy',
            'sort_order' => 10,
            'is_active' => 1
        ]);

        MenuItem::create([
            'menu_id' => $footerMenuId,
            'type' => 'link',
            'label' => 'Terms of Service',
            'url' => '/terms',
            'sort_order' => 20,
            'is_active' => 1
        ]);

        return [$mainMenuId, $footerMenuId];
    }
}
