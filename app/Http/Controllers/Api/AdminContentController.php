<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\RedisCache;

/**
 * AdminContentController -- Content moderation, pages, menus, plans, subscriptions.
 *
 * Converted from legacy delegation to direct DB calls.
 */
class AdminContentController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Content Reports (already converted)
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/admin/content/reports */
    public function reports(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM content_reports WHERE tenant_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, 'pending', $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM content_reports WHERE tenant_id = ? AND status = ?',
            [$tenantId, 'pending']
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** POST /api/v2/admin/content/{id}/approve */
    public function approveContent(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE content_reports SET status = ?, resolved_at = NOW() WHERE id = ? AND tenant_id = ?',
            ['approved', $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'approved']);
    }

    /** POST /api/v2/admin/content/{id}/reject */
    public function rejectContent(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $reason = $this->input('reason', '');

        $affected = DB::update(
            'UPDATE content_reports SET status = ?, rejection_reason = ?, resolved_at = NOW() WHERE id = ? AND tenant_id = ?',
            ['rejected', $reason, $id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'rejected']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────────

    public function getPages(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT id, tenant_id, title, slug, content, meta_description, is_published, sort_order, show_in_menu, menu_location, menu_order, publish_at, created_at, updated_at
             FROM pages WHERE tenant_id = ? ORDER BY sort_order ASC, created_at DESC",
            [$tenantId]
        )->fetchAll();

        $pages = array_map(function ($row) {
            $row['status'] = $row['is_published'] ? 'published' : 'draft';
            unset($row['is_published']);
            return $row;
        }, $rows);

        return $this->respondWithData($pages);
    }

    public function getPage($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid page ID', 'id', 400);
        }

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, meta_description, is_published, sort_order, show_in_menu, menu_location, menu_order, publish_at, created_at, updated_at
             FROM pages WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$row) {
            return $this->respondWithError('NOT_FOUND', 'Page not found', 'id', 404);
        }

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        return $this->respondWithData($row);
    }

    public function createPage(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $title = trim($input['title'] ?? '');

        if (empty($title)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Title is required', 'title', 422);
        }

        $slug = $this->generateSlug($title);
        $content = $input['content'] ?? '';
        $metaDescription = $input['meta_description'] ?? '';
        $status = $input['status'] ?? 'draft';
        $sortOrder = (int)($input['sort_order'] ?? 0);
        $showInMenu = (int)($input['show_in_menu'] ?? 0);
        $menuLocation = $input['menu_location'] ?? 'about';
        $menuOrder = (int)($input['menu_order'] ?? 0);

        if (!in_array($status, ['draft', 'published'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Status must be draft or published', 'status', 422);
        }

        if ($this->isReservedPageSlug($slug)) {
            return $this->respondWithError('VALIDATION_ERROR', "The slug \"{$slug}\" is reserved and cannot be used for a page.", 'slug', 422);
        }

        $isPublished = ($status === 'published') ? 1 : 0;
        $slug = $this->ensureUniqueSlug('pages', $slug, $tenantId);

        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, meta_description, is_published, sort_order, show_in_menu, menu_location, menu_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $title, $slug, $content, $metaDescription, $isPublished, $sortOrder, $showInMenu, $menuLocation, $menuOrder]
        );

        $newId = Database::lastInsertId();

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, meta_description, is_published, sort_order, show_in_menu, menu_location, menu_order, publish_at, created_at, updated_at
             FROM pages WHERE id = ?", [$newId]
        )->fetch();

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        try { RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}

        return $this->respondWithData($row, null, 201);
    }

    public function updatePage($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid page ID', 'id', 400);
        }

        $existing = Database::query("SELECT id FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$existing) {
            return $this->respondWithError('NOT_FOUND', 'Page not found', 'id', 404);
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['title'])) { $updates[] = 'title = ?'; $params[] = trim($input['title']); }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            if ($this->isReservedPageSlug($slug)) {
                return $this->respondWithError('VALIDATION_ERROR', "The slug \"{$slug}\" is reserved.", 'slug', 422);
            }
            $conflict = Database::query("SELECT id FROM pages WHERE slug = ? AND tenant_id = ? AND id != ?", [$slug, $tenantId, $id])->fetch();
            if ($conflict) {
                return $this->respondWithError('VALIDATION_ERROR', 'Slug already in use', 'slug', 422);
            }
            $updates[] = 'slug = ?'; $params[] = $slug;
        }
        if (array_key_exists('content', $input)) { $updates[] = 'content = ?'; $params[] = $input['content']; }
        if (isset($input['status'])) {
            if (!in_array($input['status'], ['draft', 'published'], true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Status must be draft or published', 'status', 422);
            }
            $updates[] = 'is_published = ?'; $params[] = ($input['status'] === 'published') ? 1 : 0;
        }
        if (isset($input['sort_order'])) { $updates[] = 'sort_order = ?'; $params[] = (int)$input['sort_order']; }
        if (isset($input['show_in_menu'])) { $updates[] = 'show_in_menu = ?'; $params[] = (int)$input['show_in_menu']; }
        if (isset($input['menu_location'])) { $updates[] = 'menu_location = ?'; $params[] = $input['menu_location']; }
        if (array_key_exists('meta_description', $input)) { $updates[] = 'meta_description = ?'; $params[] = $input['meta_description']; }
        if (isset($input['menu_order'])) { $updates[] = 'menu_order = ?'; $params[] = (int)$input['menu_order']; }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422);
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        Database::query("UPDATE pages SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, meta_description, is_published, sort_order, show_in_menu, menu_location, menu_order, publish_at, created_at, updated_at
             FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId]
        )->fetch();

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        try { RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}

        return $this->respondWithData($row);
    }

    public function deletePage($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid page ID', 'id', 400);
        }

        $existing = Database::query("SELECT id FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$existing) {
            return $this->respondWithError('NOT_FOUND', 'Page not found', 'id', 404);
        }

        Database::query("DELETE FROM menu_items WHERE page_id = ? AND page_id IN (SELECT id FROM pages WHERE tenant_id = ?)", [$id, $tenantId]);
        Database::query("DELETE FROM pages WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        try { RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}

        return $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menus
    // ─────────────────────────────────────────────────────────────────────────

    public function getMenus(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $menus = Database::query(
            "SELECT m.id, m.tenant_id, m.name, m.slug, m.description, m.location, m.layout,
                    m.min_plan_tier, m.is_active, m.created_at, m.updated_at, COUNT(mi.id) AS item_count
             FROM menus m LEFT JOIN menu_items mi ON mi.menu_id = m.id
             WHERE m.tenant_id = ? GROUP BY m.id ORDER BY m.name ASC",
            [$tenantId]
        )->fetchAll();

        return $this->respondWithData($menus);
    }

    public function getMenu($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400);
        }

        $menu = Database::query(
            "SELECT id, tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at, updated_at
             FROM menus WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$menu) {
            return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404);
        }

        $menu['items'] = $this->buildMenuItemTree($id);

        return $this->respondWithData($menu);
    }

    public function createMenu(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $name = trim($input['name'] ?? '');
        $location = trim($input['location'] ?? '');

        if (empty($name)) { return $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name', 422); }
        if (empty($location)) { return $this->respondWithError('VALIDATION_ERROR', 'Location is required', 'location', 422); }

        $slug = $this->generateSlug($name);
        $slug = $this->ensureUniqueSlug('menus', $slug, $tenantId);

        Database::query(
            "INSERT INTO menus (tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $name, $slug, $input['description'] ?? '', $location, $input['layout'] ?? null, (int)($input['min_plan_tier'] ?? 0), (int)($input['is_active'] ?? 1)]
        );

        $menu = Database::query("SELECT id, tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at, updated_at FROM menus WHERE id = ?", [Database::lastInsertId()])->fetch();

        return $this->respondWithData($menu, null, 201);
    }

    public function updateMenu($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400); }

        $existing = Database::query("SELECT id FROM menus WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404); }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['name'])) { $updates[] = 'name = ?'; $params[] = trim($input['name']); }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            $conflict = Database::query("SELECT id FROM menus WHERE slug = ? AND tenant_id = ? AND id != ?", [$slug, $tenantId, $id])->fetch();
            if ($conflict) { return $this->respondWithError('VALIDATION_ERROR', 'Slug already in use', 'slug', 422); }
            $updates[] = 'slug = ?'; $params[] = $slug;
        }
        if (array_key_exists('description', $input)) { $updates[] = 'description = ?'; $params[] = $input['description']; }
        if (isset($input['location'])) { $updates[] = 'location = ?'; $params[] = trim($input['location']); }
        if (array_key_exists('layout', $input)) { $updates[] = 'layout = ?'; $params[] = $input['layout']; }
        if (isset($input['min_plan_tier'])) { $updates[] = 'min_plan_tier = ?'; $params[] = (int)$input['min_plan_tier']; }
        if (isset($input['is_active'])) { $updates[] = 'is_active = ?'; $params[] = (int)$input['is_active']; }

        if (empty($updates)) { return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422); }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        Database::query("UPDATE menus SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);

        $menu = Database::query("SELECT id, tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at, updated_at FROM menus WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();

        return $this->respondWithData($menu);
    }

    public function deleteMenu($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400); }

        $existing = Database::query("SELECT id FROM menus WHERE id = ? AND tenant_id = ?", [$id, $tenantId])->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404); }

        Database::query("DELETE FROM menu_items WHERE menu_id = ? AND menu_id IN (SELECT id FROM menus WHERE tenant_id = ?)", [$id, $tenantId]);
        Database::query("DELETE FROM menus WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        return $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menu Items
    // ─────────────────────────────────────────────────────────────────────────

    public function getMenuItems($menuId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $menuId = (int) $menuId;

        if ($menuId < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400); }

        $menu = Database::query("SELECT id FROM menus WHERE id = ? AND tenant_id = ?", [$menuId, $tenantId])->fetch();
        if (!$menu) { return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404); }

        return $this->respondWithData($this->buildMenuItemTree($menuId));
    }

    public function createMenuItem($menuId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $menuId = (int) $menuId;

        if ($menuId < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400); }

        $menu = Database::query("SELECT id FROM menus WHERE id = ? AND tenant_id = ?", [$menuId, $tenantId])->fetch();
        if (!$menu) { return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404); }

        $input = $this->getAllInput();
        $label = trim($input['label'] ?? '');
        if (empty($label)) { return $this->respondWithError('VALIDATION_ERROR', 'Label is required', 'label', 422); }

        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        $visibilityRules = isset($input['visibility_rules']) ? json_encode($input['visibility_rules']) : null;

        Database::query(
            "INSERT INTO menu_items (menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$menuId, $parentId, $input['type'] ?? 'link', $label, $input['url'] ?? null, $input['route_name'] ?? null,
             isset($input['page_id']) ? (int)$input['page_id'] : null, $input['icon'] ?? null, $input['css_class'] ?? null,
             $input['target'] ?? '_self', (int)($input['sort_order'] ?? 0), $visibilityRules, (int)($input['is_active'] ?? 1)]
        );

        $item = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items WHERE id = ?", [Database::lastInsertId()]
        )->fetch();

        if ($item && $item['visibility_rules']) {
            $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
        }

        return $this->respondWithData($item, null, 201);
    }

    public function reorderMenuItems($menuId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $menuId = (int) $menuId;

        if ($menuId < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu ID', 'id', 400); }

        $menu = Database::query("SELECT id FROM menus WHERE id = ? AND tenant_id = ?", [$menuId, $tenantId])->fetch();
        if (!$menu) { return $this->respondWithError('NOT_FOUND', 'Menu not found', 'id', 404); }

        $input = $this->getAllInput();
        $items = $input['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Items array is required', 'items', 422);
        }

        foreach ($items as $item) {
            $itemId = (int)($item['id'] ?? 0);
            if ($itemId < 1) continue;
            $parentId = isset($item['parent_id']) ? (int)$item['parent_id'] : null;
            Database::query("UPDATE menu_items SET sort_order = ?, parent_id = ?, updated_at = NOW() WHERE id = ? AND menu_id = ?",
                [(int)($item['sort_order'] ?? 0), $parentId, $itemId, $menuId]);
        }

        return $this->respondWithData($this->buildMenuItemTree($menuId));
    }

    public function updateMenuItem($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu item ID', 'id', 400); }

        $existing = Database::query(
            "SELECT mi.id, mi.menu_id FROM menu_items mi JOIN menus m ON m.id = mi.menu_id WHERE mi.id = ? AND m.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Menu item not found', 'id', 404); }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['label'])) { $updates[] = 'label = ?'; $params[] = trim($input['label']); }
        if (isset($input['type'])) { $updates[] = 'type = ?'; $params[] = $input['type']; }
        if (array_key_exists('url', $input)) { $updates[] = 'url = ?'; $params[] = $input['url']; }
        if (array_key_exists('route_name', $input)) { $updates[] = 'route_name = ?'; $params[] = $input['route_name']; }
        if (array_key_exists('page_id', $input)) { $updates[] = 'page_id = ?'; $params[] = isset($input['page_id']) ? (int)$input['page_id'] : null; }
        if (array_key_exists('parent_id', $input)) { $updates[] = 'parent_id = ?'; $params[] = isset($input['parent_id']) ? (int)$input['parent_id'] : null; }
        if (array_key_exists('icon', $input)) { $updates[] = 'icon = ?'; $params[] = $input['icon']; }
        if (array_key_exists('css_class', $input)) { $updates[] = 'css_class = ?'; $params[] = $input['css_class']; }
        if (isset($input['target'])) { $updates[] = 'target = ?'; $params[] = $input['target']; }
        if (isset($input['sort_order'])) { $updates[] = 'sort_order = ?'; $params[] = (int)$input['sort_order']; }
        if (array_key_exists('visibility_rules', $input)) {
            $updates[] = 'visibility_rules = ?';
            $params[] = isset($input['visibility_rules']) ? json_encode($input['visibility_rules']) : null;
        }
        if (isset($input['is_active'])) { $updates[] = 'is_active = ?'; $params[] = (int)$input['is_active']; }

        if (empty($updates)) { return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422); }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        Database::query("UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = ? AND menu_id IN (SELECT id FROM menus WHERE tenant_id = ?)", $params);

        $item = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items WHERE id = ?", [$id]
        )->fetch();

        if ($item && $item['visibility_rules']) {
            $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
        }

        return $this->respondWithData($item);
    }

    public function deleteMenuItem($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid menu item ID', 'id', 400); }

        $existing = Database::query(
            "SELECT mi.id FROM menu_items mi JOIN menus m ON m.id = mi.menu_id WHERE mi.id = ? AND m.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Menu item not found', 'id', 404); }

        Database::query("DELETE FROM menu_items WHERE parent_id = ? AND menu_id IN (SELECT id FROM menus WHERE tenant_id = ?)", [$id, $tenantId]);
        Database::query("DELETE FROM menu_items WHERE id = ? AND menu_id IN (SELECT id FROM menus WHERE tenant_id = ?)", [$id, $tenantId]);

        return $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plans
    // ─────────────────────────────────────────────────────────────────────────

    public function getPlans(): JsonResponse
    {
        $this->requireAdmin();

        $plans = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans ORDER BY tier_level ASC, name ASC"
        )->fetchAll();

        foreach ($plans as &$plan) {
            if (isset($plan['features'])) { $plan['features'] = json_decode($plan['features'], true) ?: []; }
            if (isset($plan['allowed_layouts'])) { $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: []; }
        }
        unset($plan);

        return $this->respondWithData($plans);
    }

    public function getPlan($id): JsonResponse
    {
        $this->requireAdmin();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid plan ID', 'id', 400); }

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?", [$id]
        )->fetch();

        if (!$plan) { return $this->respondWithError('NOT_FOUND', 'Plan not found', 'id', 404); }

        if (isset($plan['features'])) { $plan['features'] = json_decode($plan['features'], true) ?: []; }
        if (isset($plan['allowed_layouts'])) { $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: []; }

        return $this->respondWithData($plan);
    }

    public function createPlan(): JsonResponse
    {
        $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $name = trim($input['name'] ?? '');
        if (empty($name)) { return $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name', 422); }

        $slug = $this->generateSlug($name);
        $counter = 0;
        $originalSlug = $slug;
        while (Database::query("SELECT id FROM pay_plans WHERE slug = ?", [$slug])->fetch()) {
            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }

        Database::query(
            "INSERT INTO pay_plans (name, slug, description, tier_level, features, allowed_layouts, max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$name, $slug, $input['description'] ?? '', (int)($input['tier_level'] ?? 0),
             isset($input['features']) ? json_encode($input['features']) : '[]',
             isset($input['allowed_layouts']) ? json_encode($input['allowed_layouts']) : '[]',
             isset($input['max_menus']) ? (int)$input['max_menus'] : null,
             isset($input['max_menu_items']) ? (int)$input['max_menu_items'] : null,
             isset($input['price_monthly']) ? (float)$input['price_monthly'] : null,
             isset($input['price_yearly']) ? (float)$input['price_yearly'] : null,
             (int)($input['is_active'] ?? 1)]
        );

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts, max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?", [Database::lastInsertId()]
        )->fetch();

        if ($plan) {
            if (isset($plan['features'])) { $plan['features'] = json_decode($plan['features'], true) ?: []; }
            if (isset($plan['allowed_layouts'])) { $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: []; }
        }

        return $this->respondWithData($plan, null, 201);
    }

    public function updatePlan($id): JsonResponse
    {
        $this->requireSuperAdmin();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid plan ID', 'id', 400); }

        $existing = Database::query("SELECT id FROM pay_plans WHERE id = ?", [$id])->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Plan not found', 'id', 404); }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['name'])) { $updates[] = 'name = ?'; $params[] = trim($input['name']); }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            $conflict = Database::query("SELECT id FROM pay_plans WHERE slug = ? AND id != ?", [$slug, $id])->fetch();
            if ($conflict) { return $this->respondWithError('VALIDATION_ERROR', 'Slug already in use', 'slug', 422); }
            $updates[] = 'slug = ?'; $params[] = $slug;
        }
        if (array_key_exists('description', $input)) { $updates[] = 'description = ?'; $params[] = $input['description']; }
        if (isset($input['tier_level'])) { $updates[] = 'tier_level = ?'; $params[] = (int)$input['tier_level']; }
        if (array_key_exists('features', $input)) { $updates[] = 'features = ?'; $params[] = json_encode($input['features'] ?? []); }
        if (array_key_exists('allowed_layouts', $input)) { $updates[] = 'allowed_layouts = ?'; $params[] = json_encode($input['allowed_layouts'] ?? []); }
        if (isset($input['max_menus'])) { $updates[] = 'max_menus = ?'; $params[] = (int)$input['max_menus']; }
        if (isset($input['max_menu_items'])) { $updates[] = 'max_menu_items = ?'; $params[] = (int)$input['max_menu_items']; }
        if (array_key_exists('price_monthly', $input)) { $updates[] = 'price_monthly = ?'; $params[] = isset($input['price_monthly']) ? (float)$input['price_monthly'] : null; }
        if (array_key_exists('price_yearly', $input)) { $updates[] = 'price_yearly = ?'; $params[] = isset($input['price_yearly']) ? (float)$input['price_yearly'] : null; }
        if (isset($input['is_active'])) { $updates[] = 'is_active = ?'; $params[] = (int)$input['is_active']; }

        if (empty($updates)) { return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422); }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query("UPDATE pay_plans SET " . implode(', ', $updates) . " WHERE id = ?", $params);

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts, max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?", [$id]
        )->fetch();

        if ($plan) {
            if (isset($plan['features'])) { $plan['features'] = json_decode($plan['features'], true) ?: []; }
            if (isset($plan['allowed_layouts'])) { $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: []; }
        }

        return $this->respondWithData($plan);
    }

    public function deletePlan($id): JsonResponse
    {
        $this->requireSuperAdmin();
        $id = (int) $id;

        if ($id < 1) { return $this->respondWithError('VALIDATION_ERROR', 'Invalid plan ID', 'id', 400); }

        $existing = Database::query("SELECT id FROM pay_plans WHERE id = ?", [$id])->fetch();
        if (!$existing) { return $this->respondWithError('NOT_FOUND', 'Plan not found', 'id', 404); }

        $activeAssignments = Database::query("SELECT COUNT(*) AS cnt FROM tenant_plan_assignments WHERE pay_plan_id = ? AND status = 'active'", [$id])->fetch();
        if ($activeAssignments && (int)$activeAssignments['cnt'] > 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Cannot delete plan with active tenant assignments (' . $activeAssignments['cnt'] . ' active)', 'id', 422);
        }

        Database::query("DELETE FROM pay_plans WHERE id = ?", [$id]);

        return $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Subscriptions
    // ─────────────────────────────────────────────────────────────────────────

    public function getSubscriptions(): JsonResponse
    {
        $this->requireAdmin();

        $subscriptions = Database::query(
            "SELECT tpa.id, tpa.tenant_id, tpa.pay_plan_id, tpa.status, tpa.starts_at, tpa.expires_at,
                    tpa.trial_ends_at, tpa.created_at, tpa.updated_at,
                    pp.name AS plan_name, pp.slug AS plan_slug, pp.tier_level AS plan_tier_level,
                    t.name AS tenant_name
             FROM tenant_plan_assignments tpa
             JOIN pay_plans pp ON pp.id = tpa.pay_plan_id
             LEFT JOIN tenants t ON t.id = tpa.tenant_id
             ORDER BY tpa.created_at DESC"
        )->fetchAll();

        return $this->respondWithData($subscriptions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function generateSlug(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    }

    private const RESERVED_PAGE_SLUGS = [
        'login', 'register', 'password', 'logout', 'dashboard', 'listings',
        'events', 'groups', 'messages', 'notifications', 'wallet', 'feed',
        'search', 'members', 'profile', 'settings', 'exchanges', 'achievements',
        'leaderboard', 'goals', 'volunteering', 'blog', 'resources',
        'organisations', 'federation', 'onboarding', 'group-exchanges', 'matches', 'newsletter',
        'help', 'contact', 'about', 'faq', 'legal', 'terms',
        'privacy', 'accessibility', 'cookies', 'development-status',
        'timebanking-guide', 'partner', 'social-prescribing', 'impact-summary', 'impact-report', 'strategic-plan',
        'admin', 'admin-legacy', 'super-admin', 'api', 'assets',
        'uploads', 'classic', 'health', 'page',
    ];

    private function isReservedPageSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_PAGE_SLUGS, true);
    }

    private function ensureUniqueSlug(string $table, string $slug, int $tenantId): string
    {
        $counter = 0;
        $originalSlug = $slug;
        while (Database::query("SELECT id FROM {$table} WHERE slug = ? AND tenant_id = ?", [$slug, $tenantId])->fetch()) {
            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }
        return $slug;
    }

    private function buildMenuItemTree(int $menuId): array
    {
        $items = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC",
            [$menuId]
        )->fetchAll();

        foreach ($items as &$item) {
            if (isset($item['visibility_rules']) && $item['visibility_rules']) {
                $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
            }
        }
        unset($item);

        $indexed = [];
        foreach ($items as &$item) {
            $item['children'] = [];
            $indexed[$item['id']] = &$item;
        }
        unset($item);

        $tree = [];
        foreach ($indexed as &$item) {
            if (!empty($item['parent_id']) && isset($indexed[$item['parent_id']])) {
                $indexed[$item['parent_id']]['children'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
