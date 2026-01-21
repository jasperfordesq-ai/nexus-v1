<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\MenuManager;
use Nexus\Models\Menu;
use Nexus\Models\MenuItem;
use Nexus\Models\PayPlan;
use Nexus\Services\PayPlanService;

class MenuController
{
    private function checkAdmin($jsonResponse = false)
    {
        if (!isset($_SESSION['user_id'])) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            if ($jsonResponse) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            header('HTTP/1.0 403 Forbidden');
            echo "Access Denied";
            exit;
        }
    }

    /**
     * List all menus (with optional pagination)
     */
    public function index()
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Check if pagination is requested
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 20;

            // Use pagination if page parameter exists, otherwise get all
            if (isset($_GET['page'])) {
                $result = Menu::paginate($tenantId, $page, $perPage);
                $menus = $result['data'];
                $pagination = $result['pagination'];
            } else {
                $menus = Menu::all($tenantId);
                $pagination = null;
            }

            $planStatus = PayPlanService::getPlanStatus($tenantId);
            $canCreateMore = PayPlanService::validateMenuCreation($tenantId);

            View::render('admin/menus/index', [
                'menus' => $menus,
                'pagination' => $pagination,
                'plan_status' => $planStatus,
                'can_create_more' => $canCreateMore
            ]);
        } catch (\PDOException $e) {
            // Tables don't exist - show migration notice
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Menu Manager - Setup Required</title>
    <style>
        body { font-family: system-ui; padding: 3rem; background: #1a1a1a; color: #fff; }
        .container { max-width: 800px; margin: 0 auto; }
        .alert { background: #3b82f6; padding: 2rem; border-radius: 0.5rem; margin-bottom: 2rem; }
        .error { background: #ef4444; }
        .button { display: inline-block; background: #22c55e; color: #fff; padding: 1rem 2rem; text-decoration: none; border-radius: 0.5rem; font-weight: 600; }
        pre { background: #000; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Menu Manager Setup Required</h1>
        <div class="alert">
            <strong>Database tables not found!</strong>
            <p>The Menu Manager module needs to be installed. Click the button below to run the migration.</p>
        </div>

        <a href="' . TenantContext::getBasePath() . '/run-menu-migration" class="button">
            ▶ Run Migration Now
        </a>

        <h2 style="margin-top: 3rem;">What This Will Do:</h2>
        <ul>
            <li>Create 5 database tables (menus, menu_items, pay_plans, etc.)</li>
            <li>Seed 4 default subscription plans</li>
            <li>Create sample menus for your tenant</li>
        </ul>

        <h2>Manual Installation (Alternative):</h2>
        <p>If you prefer, run this SQL file manually:</p>
        <pre>mysql -u user -p database < migrations/create_menu_and_pay_plans.sql</pre>

        <p style="margin-top: 2rem;">
            <a href="' . TenantContext::getBasePath() . '/admin">← Back to Admin Dashboard</a>
        </p>
    </div>
</body>
</html>';
            exit;
        }
    }

    /**
     * Show menu builder for a specific menu
     */
    public function builder($id)
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        $menu = Menu::getWithItems($id, $tenantId);

        if (!$menu) {
            header('HTTP/1.0 404 Not Found');
            echo "Menu not found";
            exit;
        }

        // Get available pages for linking
        $pages = Database::query(
            "SELECT id, title, slug FROM pages WHERE tenant_id = ? AND is_published = 1 ORDER BY title ASC",
            [$tenantId]
        )->fetchAll();

        $planStatus = PayPlanService::getPlanStatus($tenantId);
        $planLimits = PayPlan::getPlanLimits($tenantId);

        View::render('admin/menus/builder', [
            'menu' => $menu,
            'pages' => $pages,
            'plan_status' => $planStatus,
            'plan_limits' => $planLimits,
            'available_locations' => self::getAvailableLocations(),
            'available_layouts' => PayPlan::getAllowedLayouts($tenantId),
            'item_types' => self::getItemTypes()
        ]);
    }

    /**
     * Create a new menu
     */
    public function create()
    {
        $this->checkAdmin();
        $tenantId = TenantContext::getId();

        // Check if tenant can create more menus
        $validation = PayPlanService::validateMenuCreation($tenantId);

        if (!$validation['allowed']) {
            $_SESSION['error'] = $validation['reason'];
            header('Location: ' . TenantContext::getBasePath() . '/admin/menus');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: ' . TenantContext::getBasePath() . '/admin/menus');
                exit;
            }

            $slug = self::generateSlug($_POST['name'] ?? 'menu');

            $menuId = Menu::create([
                'tenant_id' => $tenantId,
                'name' => $_POST['name'] ?? 'New Menu',
                'slug' => $slug,
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? 'header-main',
                'layout' => !empty($_POST['layout']) ? $_POST['layout'] : null,
                'min_plan_tier' => (int)($_POST['min_plan_tier'] ?? 0),
                'is_active' => 1
            ]);

            MenuManager::clearCache($tenantId);

            $_SESSION['success'] = 'Menu created successfully';
            header('Location: ' . TenantContext::getBasePath() . '/admin/menus/builder/' . $menuId);
            exit;
        }

        // Show create form
        $planStatus = PayPlanService::getPlanStatus($tenantId);

        View::render('admin/menus/create', [
            'plan_status' => $planStatus,
            'available_locations' => self::getAvailableLocations(),
            'available_layouts' => PayPlan::getAllowedLayouts($tenantId)
        ]);
    }

    /**
     * Update menu settings
     */
    public function update($id)
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Validate menu settings
        $validation = self::validateMenuSettings($_POST);
        if (!$validation['valid']) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $validation['error'], 'field' => $validation['field'] ?? null]);
            exit;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'location' => $_POST['location'] ?? 'header-main',
            'layout' => !empty($_POST['layout']) ? $_POST['layout'] : null,
            'min_plan_tier' => (int)($_POST['min_plan_tier'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
        ];

        $success = Menu::update($id, $data, $tenantId);

        if ($success) {
            MenuManager::clearCache($tenantId);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu updated successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to update menu']);
        }
    }

    /**
     * Toggle menu active/inactive
     */
    public function toggleActive($id)
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $success = Menu::toggleActive($id, $tenantId);

        if ($success) {
            MenuManager::clearCache($tenantId);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu status toggled successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to toggle menu status']);
        }
    }

    /**
     * Delete a menu
     */
    public function delete($id)
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $success = Menu::delete($id, $tenantId);

        if ($success) {
            MenuManager::clearCache($tenantId);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu deleted successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to delete menu']);
        }
    }

    /**
     * Add menu item
     */
    public function addItem()
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Validate input data
        $validation = self::validateMenuItemData($_POST);
        if (!$validation['valid']) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $validation['error'], 'field' => $validation['field'] ?? null]);
            exit;
        }

        $menuId = (int)$_POST['menu_id'];

        // Verify menu belongs to tenant
        $menu = Menu::find($menuId, $tenantId);
        if (!$menu) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Menu not found']);
            exit;
        }

        // Check item limits
        $planLimits = PayPlan::getPlanLimits($tenantId);
        $currentCount = MenuItem::countByMenu($menuId);

        if ($currentCount >= $planLimits['max_menu_items']) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => "Maximum menu items ({$planLimits['max_menu_items']}) reached for your plan"
            ]);
            exit;
        }

        $data = [
            'menu_id' => $menuId,
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'type' => $validation['data']['type'],
            'label' => $validation['data']['label'],
            'url' => $validation['data']['url'],
            'route_name' => $_POST['route_name'] ?? null,
            'page_id' => !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null,
            'icon' => $validation['data']['icon'],
            'css_class' => $validation['data']['css_class'],
            'target' => $_POST['target'] ?? '_self',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'visibility_rules' => self::parseVisibilityRules($_POST),
            'is_active' => 1
        ];

        $itemId = MenuItem::create($data);

        if ($itemId) {
            MenuManager::clearCache($tenantId);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'item_id' => $itemId, 'message' => 'Menu item added']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to add menu item']);
        }
    }

    /**
     * Get a single menu item (for editing)
     */
    public function getItem($id)
    {
        $this->checkAdmin(true);

        $item = MenuItem::find($id);

        if ($item) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'item' => $item]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Menu item not found']);
        }
    }

    /**
     * Update menu item
     */
    public function updateItem($id)
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Validate input data
        $validation = self::validateMenuItemData($_POST);
        if (!$validation['valid']) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $validation['error'], 'field' => $validation['field'] ?? null]);
            exit;
        }

        $data = [
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'type' => $validation['data']['type'],
            'label' => $validation['data']['label'],
            'url' => $validation['data']['url'],
            'route_name' => $_POST['route_name'] ?? null,
            'page_id' => !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null,
            'icon' => $validation['data']['icon'],
            'css_class' => $validation['data']['css_class'],
            'target' => $_POST['target'] ?? '_self',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'visibility_rules' => self::parseVisibilityRules($_POST),
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
        ];

        $success = MenuItem::update($id, $data);

        if ($success) {
            MenuManager::clearCache(TenantContext::getId());
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu item updated']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to update menu item']);
        }
    }

    /**
     * Delete menu item
     */
    public function deleteItem($id)
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $success = MenuItem::delete($id);

        if ($success) {
            MenuManager::clearCache(TenantContext::getId());
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu item deleted']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to delete menu item']);
        }
    }

    /**
     * Update menu item order (drag and drop)
     */
    public function reorder()
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $items = json_decode($_POST['items'] ?? '[]', true);

        if (!is_array($items)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid items data']);
            exit;
        }

        $success = MenuItem::updateSortOrder($items);

        if ($success) {
            MenuManager::clearCache(TenantContext::getId());
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Menu order updated']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to update order']);
        }
    }

    /**
     * Helper: Parse visibility rules from POST data
     */
    private static function parseVisibilityRules($post)
    {
        $rules = [];

        if (isset($post['requires_auth'])) {
            $rules['requires_auth'] = (bool)$post['requires_auth'];
        }

        if (!empty($post['min_role'])) {
            $rules['min_role'] = $post['min_role'];
        }

        if (!empty($post['requires_feature'])) {
            $rules['requires_feature'] = $post['requires_feature'];
        }

        if (!empty($post['exclude_roles'])) {
            $rules['exclude_roles'] = is_array($post['exclude_roles'])
                ? $post['exclude_roles']
                : explode(',', $post['exclude_roles']);
        }

        return empty($rules) ? null : $rules;
    }

    /**
     * Helper: Generate unique slug
     */
    private static function generateSlug($name)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;

        while (Menu::findBySlug($slug, TenantContext::getId())) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Helper: Get available menu locations
     */
    private static function getAvailableLocations()
    {
        return [
            'header-main' => 'Header - Main Navigation',
            'header-secondary' => 'Header - Secondary Navigation',
            'footer' => 'Footer',
            'sidebar' => 'Sidebar',
            'mobile' => 'Mobile Menu'
        ];
    }

    /**
     * Helper: Get menu item types
     */
    private static function getItemTypes()
    {
        return [
            'link' => 'Link (URL)',
            'dropdown' => 'Dropdown (Parent)',
            'page' => 'CMS Page',
            'route' => 'Internal Route',
            'external' => 'External URL',
            'divider' => 'Divider'
        ];
    }

    /**
     * Helper: Validate menu item data (server-side validation)
     */
    private static function validateMenuItemData($data)
    {
        $errors = [];
        $sanitized = [];

        // Validate label (required, 1-100 chars)
        $label = trim($data['label'] ?? '');
        if (empty($label)) {
            return ['valid' => false, 'error' => 'Label is required', 'field' => 'label'];
        }
        if (strlen($label) > 100) {
            return ['valid' => false, 'error' => 'Label must be 100 characters or less', 'field' => 'label'];
        }
        $sanitized['label'] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        // Validate type
        $type = $data['type'] ?? 'link';
        $validTypes = ['link', 'dropdown', 'page', 'route', 'external', 'divider'];
        if (!in_array($type, $validTypes)) {
            return ['valid' => false, 'error' => 'Invalid menu item type', 'field' => 'type'];
        }
        $sanitized['type'] = $type;

        // Validate URL (if applicable)
        $url = trim($data['url'] ?? '');
        if (!empty($url) && $type !== 'page' && $type !== 'dropdown' && $type !== 'divider') {
            // Check for dangerous protocols
            $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:'];
            $lowerUrl = strtolower($url);
            foreach ($dangerousProtocols as $protocol) {
                if (strpos($lowerUrl, $protocol) === 0) {
                    return ['valid' => false, 'error' => 'Dangerous URL protocol detected', 'field' => 'url'];
                }
            }

            // Check for valid start
            if (!preg_match('/^(\/|#|https?:\/\/)/', $url)) {
                return ['valid' => false, 'error' => 'URL must start with /, #, http://, or https://', 'field' => 'url'];
            }

            // Check length
            if (strlen($url) > 500) {
                return ['valid' => false, 'error' => 'URL is too long (max 500 characters)', 'field' => 'url'];
            }

            $sanitized['url'] = filter_var($url, FILTER_SANITIZE_URL);
        } else {
            $sanitized['url'] = null;
        }

        // Validate icon (FontAwesome class format)
        $icon = trim($data['icon'] ?? '');
        if (!empty($icon)) {
            // Must match fa-{style} fa-{icon-name} format
            if (!preg_match('/^fa-(solid|regular|light|thin|duotone|brands)\s+fa-[a-z0-9-]+$/i', $icon)) {
                return ['valid' => false, 'error' => 'Icon must be a valid FontAwesome class (e.g., fa-solid fa-home)', 'field' => 'icon'];
            }
            if (strlen($icon) > 100) {
                return ['valid' => false, 'error' => 'Icon class is too long (max 100 characters)', 'field' => 'icon'];
            }
            $sanitized['icon'] = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        } else {
            $sanitized['icon'] = null;
        }

        // Validate CSS class (alphanumeric, hyphens, underscores, spaces only)
        $cssClass = trim($data['css_class'] ?? '');
        if (!empty($cssClass)) {
            if (!preg_match('/^[a-zA-Z0-9_\- ]+$/', $cssClass)) {
                return ['valid' => false, 'error' => 'CSS class contains invalid characters', 'field' => 'css_class'];
            }
            if (strlen($cssClass) > 200) {
                return ['valid' => false, 'error' => 'CSS class is too long (max 200 characters)', 'field' => 'css_class'];
            }
            $sanitized['css_class'] = htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8');
        } else {
            $sanitized['css_class'] = null;
        }

        return ['valid' => true, 'data' => $sanitized];
    }

    /**
     * Helper: Validate menu settings data
     */
    private static function validateMenuSettings($data)
    {
        // Validate name (required, 1-100 chars)
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Menu name is required', 'field' => 'name'];
        }
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Menu name must be 100 characters or less', 'field' => 'name'];
        }

        // Validate slug (required, lowercase alphanumeric and hyphens only)
        $slug = trim($data['slug'] ?? '');
        if (empty($slug)) {
            return ['valid' => false, 'error' => 'Slug is required', 'field' => 'slug'];
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['valid' => false, 'error' => 'Slug must contain only lowercase letters, numbers, and hyphens', 'field' => 'slug'];
        }
        if (strlen($slug) > 100) {
            return ['valid' => false, 'error' => 'Slug must be 100 characters or less', 'field' => 'slug'];
        }

        return ['valid' => true];
    }

    /**
     * Clear all menu cache for current tenant
     */
    public function clearCache()
    {
        $this->checkAdmin(true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $tenantId = TenantContext::getId();

        try {
            MenuManager::clearCache($tenantId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Menu cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to clear cache: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk operations on menus (activate, deactivate, delete)
     */
    public function bulk()
    {
        $this->checkAdmin(true);
        $tenantId = TenantContext::getId();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'] ?? '';
        $menuIds = json_decode($_POST['menu_ids'] ?? '[]', true);

        if (!in_array($action, ['activate', 'deactivate', 'delete'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }

        if (!is_array($menuIds) || empty($menuIds)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No menus selected']);
            exit;
        }

        $affected = 0;

        try {
            foreach ($menuIds as $menuId) {
                // Verify menu belongs to tenant
                $menu = Menu::find($menuId, $tenantId);
                if (!$menu) {
                    continue;
                }

                if ($action === 'activate' || $action === 'deactivate') {
                    if (Menu::toggleActive($menuId, $tenantId)) {
                        $affected++;
                    }
                } elseif ($action === 'delete') {
                    if (Menu::delete($menuId, $tenantId)) {
                        $affected++;
                    }
                }
            }

            MenuManager::clearCache($tenantId);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'affected' => $affected,
                'message' => "Successfully {$action}d {$affected} menu(s)"
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Bulk operation failed: ' . $e->getMessage()
            ]);
        }
    }
}
