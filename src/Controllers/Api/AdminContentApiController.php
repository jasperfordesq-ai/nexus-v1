<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * AdminContentApiController - V2 API for Pages, Menus, and Plans admin management
 *
 * Provides endpoints for managing CMS pages, navigation menus with nested items,
 * and subscription plans with tenant assignments.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 *
 * Pages:
 * - GET    /api/v2/admin/pages              - List all pages
 * - GET    /api/v2/admin/pages/{id}         - Get single page
 * - POST   /api/v2/admin/pages              - Create page
 * - PUT    /api/v2/admin/pages/{id}         - Update page
 * - DELETE /api/v2/admin/pages/{id}         - Delete page
 *
 * Menus:
 * - GET    /api/v2/admin/menus              - List all menus with item counts
 * - GET    /api/v2/admin/menus/{id}         - Get menu with nested items
 * - POST   /api/v2/admin/menus              - Create menu
 * - PUT    /api/v2/admin/menus/{id}         - Update menu
 * - DELETE /api/v2/admin/menus/{id}         - Delete menu (cascades to items)
 * - GET    /api/v2/admin/menus/{id}/items   - Get nested menu items
 * - POST   /api/v2/admin/menus/{id}/items   - Create menu item
 * - PUT    /api/v2/admin/menu-items/{id}    - Update menu item
 * - DELETE /api/v2/admin/menu-items/{id}    - Delete menu item
 * - POST   /api/v2/admin/menus/{id}/items/reorder - Reorder menu items
 *
 * Plans & Subscriptions:
 * - GET    /api/v2/admin/plans              - List all plans
 * - GET    /api/v2/admin/plans/{id}         - Get single plan
 * - POST   /api/v2/admin/plans              - Create plan
 * - PUT    /api/v2/admin/plans/{id}         - Update plan
 * - DELETE /api/v2/admin/plans/{id}         - Delete plan (if no active assignments)
 * - GET    /api/v2/admin/subscriptions      - List all tenant plan assignments
 */
class AdminContentApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/pages
     *
     * List all pages for the current tenant, ordered by sort_order.
     */
    public function getPages(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT id, tenant_id, title, slug, content, is_published, sort_order, show_in_menu, menu_location, publish_at, created_at, updated_at
             FROM pages
             WHERE tenant_id = ?
             ORDER BY sort_order ASC, created_at DESC",
            [$tenantId]
        )->fetchAll();

        // Map is_published to status for frontend consistency
        $pages = array_map(function ($row) {
            $row['status'] = $row['is_published'] ? 'published' : 'draft';
            unset($row['is_published']);
            return $row;
        }, $rows);

        $this->respondWithData($pages);
    }

    /**
     * GET /api/v2/admin/pages/{id}
     *
     * Get a single page by ID.
     */
    public function getPage(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/pages/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid page ID', 'id', 400);
            return;
        }

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, is_published, sort_order, show_in_menu, menu_location, publish_at, created_at, updated_at
             FROM pages
             WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$row) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Page not found', 'id', 404);
            return;
        }

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        $this->respondWithData($row);
    }

    /**
     * POST /api/v2/admin/pages
     *
     * Create a new page. Required: title. Auto-generates slug from title.
     * Default status is 'draft'.
     *
     * Body: { "title": "About Us", "content": "...", "status": "draft", "sort_order": 0 }
     */
    public function createPage(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $title = trim($input['title'] ?? '');

        if (empty($title)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Title is required', 'title', 422);
            return;
        }

        $slug = $this->generateSlug($title);
        $content = $input['content'] ?? '';
        $status = $input['status'] ?? 'draft';
        $sortOrder = (int)($input['sort_order'] ?? 0);
        $showInMenu = (int)($input['show_in_menu'] ?? 0);
        $menuLocation = $input['menu_location'] ?? 'about';

        // Validate status
        if (!in_array($status, ['draft', 'published'], true)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Status must be draft or published', 'status', 422);
            return;
        }

        $isPublished = ($status === 'published') ? 1 : 0;

        // Ensure slug is unique within tenant
        $slug = $this->ensureUniqueSlug('pages', $slug, $tenantId);

        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, is_published, sort_order, show_in_menu, menu_location, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $title, $slug, $content, $isPublished, $sortOrder, $showInMenu, $menuLocation]
        );

        $newId = Database::lastInsertId();

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, is_published, sort_order, show_in_menu, menu_location, publish_at, created_at, updated_at
             FROM pages WHERE id = ?",
            [$newId]
        )->fetch();

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        $this->respondWithData($row, null, 201);
    }

    /**
     * PUT /api/v2/admin/pages/{id}
     *
     * Update a page by ID.
     *
     * Body: { "title": "...", "slug": "...", "content": "...", "status": "draft|published", "sort_order": 0, "show_in_menu": 0, "menu_location": "about" }
     */
    public function updatePage(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/pages/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid page ID', 'id', 400);
            return;
        }

        // Verify page exists and belongs to tenant
        $existing = Database::query(
            "SELECT id FROM pages WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Page not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['title'])) {
            $updates[] = 'title = ?';
            $params[] = trim($input['title']);
        }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            // Check uniqueness excluding current page
            $conflict = Database::query(
                "SELECT id FROM pages WHERE slug = ? AND tenant_id = ? AND id != ?",
                [$slug, $tenantId, $id]
            )->fetch();
            if ($conflict) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Slug already in use', 'slug', 422);
                return;
            }
            $updates[] = 'slug = ?';
            $params[] = $slug;
        }
        if (array_key_exists('content', $input)) {
            $updates[] = 'content = ?';
            $params[] = $input['content'];
        }
        if (isset($input['status'])) {
            if (!in_array($input['status'], ['draft', 'published'], true)) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Status must be draft or published', 'status', 422);
                return;
            }
            $updates[] = 'is_published = ?';
            $params[] = ($input['status'] === 'published') ? 1 : 0;
        }
        if (isset($input['sort_order'])) {
            $updates[] = 'sort_order = ?';
            $params[] = (int)$input['sort_order'];
        }
        if (isset($input['show_in_menu'])) {
            $updates[] = 'show_in_menu = ?';
            $params[] = (int)$input['show_in_menu'];
        }
        if (isset($input['menu_location'])) {
            $updates[] = 'menu_location = ?';
            $params[] = $input['menu_location'];
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 422);
            return;
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE pages SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        $row = Database::query(
            "SELECT id, tenant_id, title, slug, content, is_published, sort_order, show_in_menu, menu_location, publish_at, created_at, updated_at
             FROM pages WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        $row['status'] = $row['is_published'] ? 'published' : 'draft';
        unset($row['is_published']);

        $this->respondWithData($row);
    }

    /**
     * DELETE /api/v2/admin/pages/{id}
     *
     * Delete a page by ID. Verifies tenant ownership.
     */
    public function deletePage(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/pages/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid page ID', 'id', 400);
            return;
        }

        $existing = Database::query(
            "SELECT id FROM pages WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Page not found', 'id', 404);
            return;
        }

        Database::query(
            "DELETE FROM pages WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menus
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/menus
     *
     * List all menus for the current tenant with item counts.
     */
    public function getMenus(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $menus = Database::query(
            "SELECT m.id, m.tenant_id, m.name, m.slug, m.description, m.location, m.layout,
                    m.min_plan_tier, m.is_active, m.created_at, m.updated_at,
                    COUNT(mi.id) AS item_count
             FROM menus m
             LEFT JOIN menu_items mi ON mi.menu_id = m.id
             WHERE m.tenant_id = ?
             GROUP BY m.id
             ORDER BY m.name ASC",
            [$tenantId]
        )->fetchAll();

        $this->respondWithData($menus);
    }

    /**
     * GET /api/v2/admin/menus/{id}
     *
     * Get a single menu by ID with its items nested by parent_id.
     */
    public function getMenu(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)(?:/|$)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        $menu = Database::query(
            "SELECT id, tenant_id, name, slug, description, location, layout,
                    min_plan_tier, is_active, created_at, updated_at
             FROM menus
             WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$menu) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        // Fetch items and build nested tree
        $menu['items'] = $this->buildMenuItemTree($id);

        $this->respondWithData($menu);
    }

    /**
     * POST /api/v2/admin/menus
     *
     * Create a new menu. Required: name, location. Auto-generates slug from name.
     *
     * Body: { "name": "Main Nav", "location": "header", "description": "...", "layout": "...", "min_plan_tier": 0, "is_active": 1 }
     */
    public function createMenu(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();
        $name = trim($input['name'] ?? '');
        $location = trim($input['location'] ?? '');

        if (empty($name)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Name is required', 'name', 422);
            return;
        }
        if (empty($location)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Location is required', 'location', 422);
            return;
        }

        $slug = $this->generateSlug($name);
        $slug = $this->ensureUniqueSlug('menus', $slug, $tenantId);
        $description = $input['description'] ?? '';
        $layout = $input['layout'] ?? null;
        $minPlanTier = (int)($input['min_plan_tier'] ?? 0);
        $isActive = (int)($input['is_active'] ?? 1);

        Database::query(
            "INSERT INTO menus (tenant_id, name, slug, description, location, layout, min_plan_tier, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $name, $slug, $description, $location, $layout, $minPlanTier, $isActive]
        );

        $newId = Database::lastInsertId();

        $menu = Database::query(
            "SELECT id, tenant_id, name, slug, description, location, layout,
                    min_plan_tier, is_active, created_at, updated_at
             FROM menus WHERE id = ?",
            [$newId]
        )->fetch();

        $this->respondWithData($menu, null, 201);
    }

    /**
     * PUT /api/v2/admin/menus/{id}
     *
     * Update a menu by ID.
     *
     * Body: { "name": "...", "slug": "...", "description": "...", "location": "...", "layout": "...", "min_plan_tier": 0, "is_active": 1 }
     */
    public function updateMenu(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        $existing = Database::query(
            "SELECT id FROM menus WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($input['name']);
        }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            $conflict = Database::query(
                "SELECT id FROM menus WHERE slug = ? AND tenant_id = ? AND id != ?",
                [$slug, $tenantId, $id]
            )->fetch();
            if ($conflict) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Slug already in use', 'slug', 422);
                return;
            }
            $updates[] = 'slug = ?';
            $params[] = $slug;
        }
        if (array_key_exists('description', $input)) {
            $updates[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['location'])) {
            $updates[] = 'location = ?';
            $params[] = trim($input['location']);
        }
        if (array_key_exists('layout', $input)) {
            $updates[] = 'layout = ?';
            $params[] = $input['layout'];
        }
        if (isset($input['min_plan_tier'])) {
            $updates[] = 'min_plan_tier = ?';
            $params[] = (int)$input['min_plan_tier'];
        }
        if (isset($input['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = (int)$input['is_active'];
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 422);
            return;
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE menus SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        $menu = Database::query(
            "SELECT id, tenant_id, name, slug, description, location, layout,
                    min_plan_tier, is_active, created_at, updated_at
             FROM menus WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        $this->respondWithData($menu);
    }

    /**
     * DELETE /api/v2/admin/menus/{id}
     *
     * Delete a menu and cascade to its items.
     */
    public function deleteMenu(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        $existing = Database::query(
            "SELECT id FROM menus WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        // Delete items first, then the menu
        Database::query("DELETE FROM menu_items WHERE menu_id = ?", [$id]);
        Database::query("DELETE FROM menus WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menu Items
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/menus/{id}/items
     *
     * Get items for a menu, returned as a nested tree structure.
     */
    public function getMenuItems(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)/items#', $uri, $matches);
        $menuId = (int)($matches[1] ?? 0);

        if ($menuId < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        // Verify menu belongs to tenant
        $menu = Database::query(
            "SELECT id FROM menus WHERE id = ? AND tenant_id = ?",
            [$menuId, $tenantId]
        )->fetch();

        if (!$menu) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        $tree = $this->buildMenuItemTree($menuId);
        $this->respondWithData($tree);
    }

    /**
     * POST /api/v2/admin/menus/{id}/items
     *
     * Add a new item to a menu.
     *
     * Body: { "label": "Home", "type": "link", "url": "/", "parent_id": null, "icon": "home",
     *         "css_class": "", "target": "_self", "sort_order": 0, "route_name": null,
     *         "page_id": null, "visibility_rules": null, "is_active": 1 }
     */
    public function createMenuItem(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)/items#', $uri, $matches);
        $menuId = (int)($matches[1] ?? 0);

        if ($menuId < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        // Verify menu belongs to tenant
        $menu = Database::query(
            "SELECT id FROM menus WHERE id = ? AND tenant_id = ?",
            [$menuId, $tenantId]
        )->fetch();

        if (!$menu) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $label = trim($input['label'] ?? '');

        if (empty($label)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Label is required', 'label', 422);
            return;
        }

        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        $type = $input['type'] ?? 'link';
        $url = $input['url'] ?? null;
        $routeName = $input['route_name'] ?? null;
        $pageId = isset($input['page_id']) ? (int)$input['page_id'] : null;
        $icon = $input['icon'] ?? null;
        $cssClass = $input['css_class'] ?? null;
        $target = $input['target'] ?? '_self';
        $sortOrder = (int)($input['sort_order'] ?? 0);
        $visibilityRules = isset($input['visibility_rules']) ? json_encode($input['visibility_rules']) : null;
        $isActive = (int)($input['is_active'] ?? 1);

        Database::query(
            "INSERT INTO menu_items (menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$menuId, $parentId, $type, $label, $url, $routeName, $pageId, $icon, $cssClass, $target, $sortOrder, $visibilityRules, $isActive]
        );

        $newId = Database::lastInsertId();

        $item = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items WHERE id = ?",
            [$newId]
        )->fetch();

        if ($item && $item['visibility_rules']) {
            $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
        }

        $this->respondWithData($item, null, 201);
    }

    /**
     * PUT /api/v2/admin/menu-items/{id}
     *
     * Update a menu item by ID.
     *
     * Body: { "label": "...", "type": "...", "url": "...", "parent_id": null, ... }
     */
    public function updateMenuItem(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menu-items/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu item ID', 'id', 400);
            return;
        }

        // Verify item belongs to a menu owned by the tenant
        $existing = Database::query(
            "SELECT mi.id, mi.menu_id
             FROM menu_items mi
             JOIN menus m ON m.id = mi.menu_id
             WHERE mi.id = ? AND m.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu item not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['label'])) {
            $updates[] = 'label = ?';
            $params[] = trim($input['label']);
        }
        if (isset($input['type'])) {
            $updates[] = 'type = ?';
            $params[] = $input['type'];
        }
        if (array_key_exists('url', $input)) {
            $updates[] = 'url = ?';
            $params[] = $input['url'];
        }
        if (array_key_exists('route_name', $input)) {
            $updates[] = 'route_name = ?';
            $params[] = $input['route_name'];
        }
        if (array_key_exists('page_id', $input)) {
            $updates[] = 'page_id = ?';
            $params[] = isset($input['page_id']) ? (int)$input['page_id'] : null;
        }
        if (array_key_exists('parent_id', $input)) {
            $updates[] = 'parent_id = ?';
            $params[] = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        }
        if (array_key_exists('icon', $input)) {
            $updates[] = 'icon = ?';
            $params[] = $input['icon'];
        }
        if (array_key_exists('css_class', $input)) {
            $updates[] = 'css_class = ?';
            $params[] = $input['css_class'];
        }
        if (isset($input['target'])) {
            $updates[] = 'target = ?';
            $params[] = $input['target'];
        }
        if (isset($input['sort_order'])) {
            $updates[] = 'sort_order = ?';
            $params[] = (int)$input['sort_order'];
        }
        if (array_key_exists('visibility_rules', $input)) {
            $updates[] = 'visibility_rules = ?';
            $params[] = isset($input['visibility_rules']) ? json_encode($input['visibility_rules']) : null;
        }
        if (isset($input['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = (int)$input['is_active'];
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 422);
            return;
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query(
            "UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );

        $item = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items WHERE id = ?",
            [$id]
        )->fetch();

        if ($item && $item['visibility_rules']) {
            $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
        }

        $this->respondWithData($item);
    }

    /**
     * DELETE /api/v2/admin/menu-items/{id}
     *
     * Remove a menu item by ID.
     */
    public function deleteMenuItem(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menu-items/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu item ID', 'id', 400);
            return;
        }

        // Verify item belongs to a menu owned by the tenant
        $existing = Database::query(
            "SELECT mi.id
             FROM menu_items mi
             JOIN menus m ON m.id = mi.menu_id
             WHERE mi.id = ? AND m.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu item not found', 'id', 404);
            return;
        }

        // Also delete child items
        Database::query("DELETE FROM menu_items WHERE parent_id = ?", [$id]);
        Database::query("DELETE FROM menu_items WHERE id = ?", [$id]);

        $this->respondWithData(['deleted' => true]);
    }

    /**
     * POST /api/v2/admin/menus/{id}/items/reorder
     *
     * Reorder menu items. Accepts an array of { id, sort_order, parent_id }.
     *
     * Body: { "items": [ { "id": 1, "sort_order": 0, "parent_id": null }, { "id": 2, "sort_order": 1, "parent_id": null } ] }
     */
    public function reorderMenuItems(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/menus/(\d+)/items/reorder#', $uri, $matches);
        $menuId = (int)($matches[1] ?? 0);

        if ($menuId < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid menu ID', 'id', 400);
            return;
        }

        // Verify menu belongs to tenant
        $menu = Database::query(
            "SELECT id FROM menus WHERE id = ? AND tenant_id = ?",
            [$menuId, $tenantId]
        )->fetch();

        if (!$menu) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Menu not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $items = $input['items'] ?? [];

        if (!is_array($items) || empty($items)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Items array is required', 'items', 422);
            return;
        }

        foreach ($items as $item) {
            $itemId = (int)($item['id'] ?? 0);
            $sortOrder = (int)($item['sort_order'] ?? 0);
            $parentId = isset($item['parent_id']) ? (int)$item['parent_id'] : null;

            if ($itemId < 1) {
                continue;
            }

            Database::query(
                "UPDATE menu_items SET sort_order = ?, parent_id = ?, updated_at = NOW()
                 WHERE id = ? AND menu_id = ?",
                [$sortOrder, $parentId, $itemId, $menuId]
            );
        }

        // Return updated tree
        $tree = $this->buildMenuItemTree($menuId);
        $this->respondWithData($tree);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plans
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/plans
     *
     * List all pay plans.
     */
    public function getPlans(): void
    {
        $this->requireAdmin();

        $plans = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans
             ORDER BY tier_level ASC, name ASC"
        )->fetchAll();

        // Decode JSON fields
        foreach ($plans as &$plan) {
            if (isset($plan['features'])) {
                $plan['features'] = json_decode($plan['features'], true) ?: [];
            }
            if (isset($plan['allowed_layouts'])) {
                $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: [];
            }
        }
        unset($plan);

        $this->respondWithData($plans);
    }

    /**
     * GET /api/v2/admin/plans/{id}
     *
     * Get a single plan by ID.
     */
    public function getPlan(): void
    {
        $this->requireAdmin();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/plans/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid plan ID', 'id', 400);
            return;
        }

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?",
            [$id]
        )->fetch();

        if (!$plan) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Plan not found', 'id', 404);
            return;
        }

        if (isset($plan['features'])) {
            $plan['features'] = json_decode($plan['features'], true) ?: [];
        }
        if (isset($plan['allowed_layouts'])) {
            $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: [];
        }

        $this->respondWithData($plan);
    }

    /**
     * POST /api/v2/admin/plans
     *
     * Create a new plan. Required: name. Auto-generates slug.
     *
     * Body: { "name": "Pro", "description": "...", "tier_level": 2, "features": [...],
     *         "allowed_layouts": [...], "max_menus": 10, "max_menu_items": 50,
     *         "price_monthly": 29.99, "price_yearly": 299.99, "is_active": 1 }
     */
    public function createPlan(): void
    {
        $this->requireAdmin();

        $input = $this->getAllInput();
        $name = trim($input['name'] ?? '');

        if (empty($name)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Name is required', 'name', 422);
            return;
        }

        $slug = $this->generateSlug($name);

        // Ensure slug is unique (plans are global, not tenant-scoped)
        $counter = 0;
        $originalSlug = $slug;
        while (true) {
            $conflict = Database::query(
                "SELECT id FROM pay_plans WHERE slug = ?",
                [$slug]
            )->fetch();
            if (!$conflict) break;
            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }

        $description = $input['description'] ?? '';
        $tierLevel = (int)($input['tier_level'] ?? 0);
        $features = isset($input['features']) ? json_encode($input['features']) : '[]';
        $allowedLayouts = isset($input['allowed_layouts']) ? json_encode($input['allowed_layouts']) : '[]';
        $maxMenus = isset($input['max_menus']) ? (int)$input['max_menus'] : null;
        $maxMenuItems = isset($input['max_menu_items']) ? (int)$input['max_menu_items'] : null;
        $priceMonthly = isset($input['price_monthly']) ? (float)$input['price_monthly'] : null;
        $priceYearly = isset($input['price_yearly']) ? (float)$input['price_yearly'] : null;
        $isActive = (int)($input['is_active'] ?? 1);

        Database::query(
            "INSERT INTO pay_plans (name, slug, description, tier_level, features, allowed_layouts, max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$name, $slug, $description, $tierLevel, $features, $allowedLayouts, $maxMenus, $maxMenuItems, $priceMonthly, $priceYearly, $isActive]
        );

        $newId = Database::lastInsertId();

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?",
            [$newId]
        )->fetch();

        if ($plan) {
            if (isset($plan['features'])) {
                $plan['features'] = json_decode($plan['features'], true) ?: [];
            }
            if (isset($plan['allowed_layouts'])) {
                $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: [];
            }
        }

        $this->respondWithData($plan, null, 201);
    }

    /**
     * PUT /api/v2/admin/plans/{id}
     *
     * Update a plan by ID.
     *
     * Body: { "name": "...", "slug": "...", "description": "...", "tier_level": 2, ... }
     */
    public function updatePlan(): void
    {
        $this->requireAdmin();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/plans/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid plan ID', 'id', 400);
            return;
        }

        $existing = Database::query(
            "SELECT id FROM pay_plans WHERE id = ?",
            [$id]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Plan not found', 'id', 404);
            return;
        }

        $input = $this->getAllInput();
        $updates = [];
        $params = [];

        if (isset($input['name'])) {
            $updates[] = 'name = ?';
            $params[] = trim($input['name']);
        }
        if (isset($input['slug'])) {
            $slug = $this->generateSlug($input['slug']);
            $conflict = Database::query(
                "SELECT id FROM pay_plans WHERE slug = ? AND id != ?",
                [$slug, $id]
            )->fetch();
            if ($conflict) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Slug already in use', 'slug', 422);
                return;
            }
            $updates[] = 'slug = ?';
            $params[] = $slug;
        }
        if (array_key_exists('description', $input)) {
            $updates[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['tier_level'])) {
            $updates[] = 'tier_level = ?';
            $params[] = (int)$input['tier_level'];
        }
        if (array_key_exists('features', $input)) {
            $updates[] = 'features = ?';
            $params[] = json_encode($input['features'] ?? []);
        }
        if (array_key_exists('allowed_layouts', $input)) {
            $updates[] = 'allowed_layouts = ?';
            $params[] = json_encode($input['allowed_layouts'] ?? []);
        }
        if (isset($input['max_menus'])) {
            $updates[] = 'max_menus = ?';
            $params[] = (int)$input['max_menus'];
        }
        if (isset($input['max_menu_items'])) {
            $updates[] = 'max_menu_items = ?';
            $params[] = (int)$input['max_menu_items'];
        }
        if (array_key_exists('price_monthly', $input)) {
            $updates[] = 'price_monthly = ?';
            $params[] = isset($input['price_monthly']) ? (float)$input['price_monthly'] : null;
        }
        if (array_key_exists('price_yearly', $input)) {
            $updates[] = 'price_yearly = ?';
            $params[] = isset($input['price_yearly']) ? (float)$input['price_yearly'] : null;
        }
        if (isset($input['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = (int)$input['is_active'];
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No fields to update', null, 422);
            return;
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query(
            "UPDATE pay_plans SET " . implode(', ', $updates) . " WHERE id = ?",
            $params
        );

        $plan = Database::query(
            "SELECT id, name, slug, description, tier_level, features, allowed_layouts,
                    max_menus, max_menu_items, price_monthly, price_yearly, is_active, created_at, updated_at
             FROM pay_plans WHERE id = ?",
            [$id]
        )->fetch();

        if ($plan) {
            if (isset($plan['features'])) {
                $plan['features'] = json_decode($plan['features'], true) ?: [];
            }
            if (isset($plan['allowed_layouts'])) {
                $plan['allowed_layouts'] = json_decode($plan['allowed_layouts'], true) ?: [];
            }
        }

        $this->respondWithData($plan);
    }

    /**
     * DELETE /api/v2/admin/plans/{id}
     *
     * Delete a plan. Checks for active tenant assignments first.
     */
    public function deletePlan(): void
    {
        $this->requireAdmin();

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        preg_match('#/api/v2/admin/plans/(\d+)#', $uri, $matches);
        $id = (int)($matches[1] ?? 0);

        if ($id < 1) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid plan ID', 'id', 400);
            return;
        }

        $existing = Database::query(
            "SELECT id FROM pay_plans WHERE id = ?",
            [$id]
        )->fetch();

        if (!$existing) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Plan not found', 'id', 404);
            return;
        }

        // Check for active assignments
        $activeAssignments = Database::query(
            "SELECT COUNT(*) AS cnt FROM tenant_plan_assignments WHERE pay_plan_id = ? AND status = 'active'",
            [$id]
        )->fetch();

        if ($activeAssignments && (int)$activeAssignments['cnt'] > 0) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Cannot delete plan with active tenant assignments (' . $activeAssignments['cnt'] . ' active)',
                'id',
                422
            );
            return;
        }

        Database::query("DELETE FROM pay_plans WHERE id = ?", [$id]);

        $this->respondWithData(['deleted' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Subscriptions (Tenant Plan Assignments)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/subscriptions
     *
     * List all tenant plan assignments with plan names joined.
     */
    public function getSubscriptions(): void
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

        $this->respondWithData($subscriptions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a URL-safe slug from a string.
     */
    private function generateSlug(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    }

    /**
     * Ensure a slug is unique within a tenant-scoped table.
     * Appends -1, -2, etc. if needed.
     */
    private function ensureUniqueSlug(string $table, string $slug, int $tenantId): string
    {
        $counter = 0;
        $originalSlug = $slug;

        while (true) {
            $conflict = Database::query(
                "SELECT id FROM {$table} WHERE slug = ? AND tenant_id = ?",
                [$slug, $tenantId]
            )->fetch();

            if (!$conflict) {
                break;
            }

            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }

        return $slug;
    }

    /**
     * Build a nested tree of menu items for a given menu ID.
     *
     * Returns items as a hierarchical array with 'children' on each node.
     */
    private function buildMenuItemTree(int $menuId): array
    {
        $items = Database::query(
            "SELECT id, menu_id, parent_id, type, label, url, route_name, page_id, icon, css_class, target, sort_order, visibility_rules, is_active, created_at, updated_at
             FROM menu_items
             WHERE menu_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$menuId]
        )->fetchAll();

        // Decode JSON fields
        foreach ($items as &$item) {
            if (isset($item['visibility_rules']) && $item['visibility_rules']) {
                $item['visibility_rules'] = json_decode($item['visibility_rules'], true);
            }
        }
        unset($item);

        // Build tree from flat list
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
