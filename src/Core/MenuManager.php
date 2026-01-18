<?php

namespace Nexus\Core;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Menu;
use Nexus\Models\MenuItem;
use Nexus\Models\PayPlan;

/**
 * MenuManager - Tenant-aware, Pay Layout-aware Menu System
 *
 * Replaces/extends MenuGenerator with support for:
 * - Custom menu management
 * - Pay plan restrictions
 * - Layout-specific menus
 * - Advanced visibility rules
 * - Menu caching
 */
class MenuManager
{
    /**
     * Menu location constants (expanded from MenuGenerator)
     */
    const LOCATION_HEADER_MAIN = 'header-main';
    const LOCATION_HEADER_SECONDARY = 'header-secondary';
    const LOCATION_FOOTER = 'footer';
    const LOCATION_SIDEBAR = 'sidebar';
    const LOCATION_MOBILE = 'mobile';

    // Legacy support
    const MENU_ABOUT = 'about';
    const MENU_MAIN = 'main';
    const MENU_FOOTER = 'footer';

    /**
     * Get menu for a specific location and layout
     *
     * @param string $location Menu location
     * @param string|null $layout Current layout (modern, civicone, skeleton)
     * @param bool $useCache Whether to use cached menus
     * @return array Hierarchical menu structure
     */
    public static function getMenu($location, $layout = null, $useCache = true)
    {
        // MASTER KILL SWITCH: Check if menu manager is enabled
        if (!self::isEnabled()) {
            return self::getOriginalNavigation($location, $layout);
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return [];
        }

        // Get current user and plan info
        $user = self::getCurrentUser();
        $userRole = $user['role'] ?? $_SESSION['user_role'] ?? 'guest';

        // Check cache first
        if ($useCache) {
            $cached = self::getFromCache($tenantId, $location, $layout, $userRole);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Get tenant's features for visibility filtering
        // Use TenantContext::hasFeature() as the single source of truth
        $tenantFeatures = self::getTenantModuleFeatures($tenantId);

        // Get menus for this location/layout
        $menus = Menu::getByLocation($location, $layout, $tenantId);

        if (empty($menus)) {
            // Use default platform menu if available
            if (class_exists('\Nexus\Core\DefaultMenus')) {
                $defaultMenu = DefaultMenus::getDefaultMenu($location, TenantContext::getBasePath());
                if (!empty($defaultMenu)) {
                    return [$defaultMenu];
                }
            }

            // Fallback to legacy MenuGenerator for backwards compatibility
            return self::getLegacyMenu($location);
        }

        // Build menu structure
        $menuData = [];
        foreach ($menus as $menu) {
            // Check plan tier requirement
            $currentTier = PayPlan::getTierLevel($tenantId);
            if ($menu['min_plan_tier'] > $currentTier) {
                continue; // Skip menus requiring higher tier
            }

            // Get menu items
            $items = MenuItem::getByMenu($menu['id'], true);

            // Filter by visibility rules
            $filteredItems = MenuItem::filterVisible($items, $user, $tenantFeatures);

            // Resolve URLs
            $basePath = TenantContext::getBasePath();
            $filteredItems = self::resolveItemUrls($filteredItems, $basePath);

            $menuData[] = [
                'id' => $menu['id'],
                'name' => $menu['name'],
                'slug' => $menu['slug'],
                'location' => $menu['location'],
                'items' => $filteredItems
            ];
        }

        // Cache the result
        if ($useCache) {
            self::saveToCache($tenantId, $location, $layout, $userRole, $menuData);
        }

        return $menuData;
    }

    /**
     * Get a single menu by slug
     */
    public static function getMenuBySlug($slug, $layout = null, $useCache = true)
    {
        $tenantId = TenantContext::getId();
        $menu = Menu::findBySlug($slug, $tenantId);

        if (!$menu) {
            return null;
        }

        $user = self::getCurrentUser();
        // Use TenantContext::hasFeature() as the single source of truth
        $tenantFeatures = self::getTenantModuleFeatures($tenantId);

        // Get items
        $items = MenuItem::getByMenu($menu['id'], true);
        $filteredItems = MenuItem::filterVisible($items, $user, $tenantFeatures);

        $basePath = TenantContext::getBasePath();
        $filteredItems = self::resolveItemUrls($filteredItems, $basePath);

        return [
            'id' => $menu['id'],
            'name' => $menu['name'],
            'slug' => $menu['slug'],
            'location' => $menu['location'],
            'items' => $filteredItems
        ];
    }

    /**
     * Render menu as HTML
     *
     * @param string $location Menu location
     * @param string|null $layout Current layout
     * @param string $cssClass CSS class for the menu container
     * @return string HTML markup
     */
    public static function renderMenu($location, $layout = null, $cssClass = 'menu')
    {
        $menus = self::getMenu($location, $layout);

        if (empty($menus)) {
            return '';
        }

        $html = '';
        foreach ($menus as $menu) {
            $html .= '<nav class="' . htmlspecialchars($cssClass) . '" data-menu-id="' . $menu['id'] . '">';
            $html .= '<ul>';
            $html .= self::renderMenuItems($menu['items']);
            $html .= '</ul>';
            $html .= '</nav>';
        }

        return $html;
    }

    /**
     * Render menu items recursively
     */
    private static function renderMenuItems($items, $depth = 0)
    {
        $html = '';

        foreach ($items as $item) {
            $html .= '<li class="menu-item menu-item-' . $item['type'] . '">';

            if ($item['type'] === 'dropdown' && !empty($item['children'])) {
                // Dropdown menu
                $html .= '<details role="list">';
                $html .= '<summary aria-haspopup="listbox" role="link">';
                if ($item['icon']) {
                    $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
                }
                $html .= htmlspecialchars($item['label']);
                $html .= '</summary>';
                $html .= '<ul role="listbox">';
                $html .= self::renderMenuItems($item['children'], $depth + 1);
                $html .= '</ul>';
                $html .= '</details>';
            } elseif ($item['type'] === 'divider') {
                // Divider
                $html .= '<hr class="menu-divider">';
            } else {
                // Regular link
                $target = $item['target'] ?? '_self';
                $cssClass = $item['css_class'] ?? '';

                $html .= '<a href="' . htmlspecialchars($item['url']) . '" target="' . $target . '" class="' . htmlspecialchars($cssClass) . '">';
                if ($item['icon']) {
                    $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
                }
                $html .= htmlspecialchars($item['label']);
                $html .= '</a>';
            }

            $html .= '</li>';
        }

        return $html;
    }

    /**
     * Resolve URLs for menu items recursively
     */
    private static function resolveItemUrls($items, $basePath)
    {
        foreach ($items as &$item) {
            $item['url'] = MenuItem::resolveUrl($item, $basePath);

            if (!empty($item['children'])) {
                $item['children'] = self::resolveItemUrls($item['children'], $basePath);
            }
        }

        return $items;
    }

    /**
     * Get current user data
     */
    private static function getCurrentUser()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role'] ?? 'user',
            'is_super_admin' => $_SESSION['is_super_admin'] ?? false
        ];
    }

    /**
     * Cache management
     */
    private static function getFromCache($tenantId, $location, $layout, $userRole)
    {
        try {
            $cacheKey = self::generateCacheKey($tenantId, $location, $layout, $userRole);
            $db = Database::getConnection();

            $stmt = $db->prepare(
                "SELECT cached_data, expires_at FROM menu_cache
                WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())"
            );
            $stmt->execute([$cacheKey]);
            $cache = $stmt->fetch();

            if ($cache) {
                return json_decode($cache['cached_data'], true);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function saveToCache($tenantId, $location, $layout, $userRole, $menuData)
    {
        try {
            $cacheKey = self::generateCacheKey($tenantId, $location, $layout, $userRole);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db = Database::getConnection();

            // Delete old cache entry
            $stmt = $db->prepare("DELETE FROM menu_cache WHERE cache_key = ?");
            $stmt->execute([$cacheKey]);

            // Insert new cache
            $stmt = $db->prepare(
                "INSERT INTO menu_cache (tenant_id, layout, location, user_role, cache_key, cached_data, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $tenantId,
                $layout,
                $location,
                $userRole,
                $cacheKey,
                json_encode($menuData),
                $expiresAt
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function generateCacheKey($tenantId, $location, $layout, $userRole)
    {
        return md5("menu:{$tenantId}:{$location}:{$layout}:{$userRole}");
    }

    /**
     * Clear menu cache for a tenant
     */
    public static function clearCache($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM menu_cache WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Legacy support - fallback to old MenuGenerator for backwards compatibility
     */
    private static function getLegacyMenu($location)
    {
        // Map new locations to legacy locations
        $legacyMap = [
            'header-main' => 'main',
            'footer' => 'footer',
            'about' => 'about'
        ];

        $legacyLocation = $legacyMap[$location] ?? $location;

        try {
            $pages = \Nexus\Core\MenuGenerator::getMenuPages($legacyLocation);

            if (empty($pages)) {
                return [];
            }

            // Convert to new format
            return [[
                'id' => 'legacy-' . $legacyLocation,
                'name' => ucfirst($legacyLocation) . ' (Legacy)',
                'slug' => 'legacy-' . $legacyLocation,
                'location' => $location,
                'items' => array_map(function ($page) {
                    return [
                        'type' => 'link',
                        'label' => $page['title'],
                        'url' => $page['url'],
                        'target' => '_self',
                        'icon' => null,
                        'css_class' => ''
                    ];
                }, $pages)
            ]];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Legacy method support for backwards compatibility
     */
    public static function renderAboutMenu()
    {
        return \Nexus\Core\MenuGenerator::renderAboutMenu();
    }

    public static function getMainNavPages()
    {
        return \Nexus\Core\MenuGenerator::getMainNavPages();
    }

    public static function getFooterPages()
    {
        return \Nexus\Core\MenuGenerator::getFooterPages();
    }

    public static function hasMenuPages($location)
    {
        return \Nexus\Core\MenuGenerator::hasMenuPages($location);
    }

    /**
     * MASTER KILL SWITCH: Check if menu manager is enabled
     */
    public static function isEnabled()
    {
        static $config = null;

        if ($config === null) {
            $configFile = __DIR__ . '/../../config/menu-manager.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            } else {
                // Default to disabled if config doesn't exist
                $config = ['enabled' => false];
            }
        }

        // Check for admin override
        if (!empty($config['allow_admin_override'])) {
            if (isset($_GET['enable_menu_manager'])) {
                $_SESSION['menu_manager_override'] = (bool)$_GET['enable_menu_manager'];
            }
            if (isset($_SESSION['menu_manager_override'])) {
                return $_SESSION['menu_manager_override'];
            }
        }

        return $config['enabled'] ?? false;
    }

    /**
     * Get original navigation (fallback when menu manager is disabled)
     */
    private static function getOriginalNavigation($location, $layout = null)
    {
        $configFile = __DIR__ . '/../../config/menu-manager.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $navClass = $config['original_nav_config'] ?? 'Nexus\Config\Navigation';

        if (!class_exists($navClass)) {
            // Ultimate fallback - use DefaultMenus
            if (class_exists('\Nexus\Core\DefaultMenus')) {
                $defaultMenu = DefaultMenus::getDefaultMenu($location, TenantContext::getBasePath());
                if (!empty($defaultMenu)) {
                    return [$defaultMenu];
                }
            }
            return [];
        }

        // Map locations to Navigation class methods
        $basePath = TenantContext::getBasePath();
        $items = [];

        switch ($location) {
            case 'header-main':
                $mainItems = $navClass::getMainNavItems();
                $exploreItems = $navClass::getExploreItems();

                // Build main navigation items
                foreach ($mainItems as $key => $item) {
                    if ($navClass::shouldShow($item)) {
                        $items[] = [
                            'type' => 'link',
                            'label' => $item['label'],
                            'url' => $item['url'],
                            'icon' => $item['icon'] ?? null,
                            'is_active' => 1
                        ];
                    }
                }

                // Add Explore dropdown if items exist
                $exploreChildren = [];
                foreach ($exploreItems as $key => $item) {
                    if ($navClass::shouldShow($item)) {
                        $childItem = [
                            'type' => 'link',
                            'label' => $item['label'],
                            'url' => $item['url'],
                            'icon' => $item['icon'] ?? null,
                            'is_active' => 1
                        ];

                        // Preserve color and separator properties for menu rendering
                        if (isset($item['color'])) {
                            $childItem['color'] = $item['color'];
                        }
                        if (isset($item['separator_before'])) {
                            $childItem['separator_before'] = $item['separator_before'];
                        }
                        if (isset($item['highlight'])) {
                            $childItem['highlight'] = $item['highlight'];
                        }

                        $exploreChildren[] = $childItem;
                    }
                }

                if (!empty($exploreChildren)) {
                    $items[] = [
                        'type' => 'dropdown',
                        'label' => 'Explore',
                        'icon' => 'fa-solid fa-compass',
                        'is_active' => 1,
                        'children' => $exploreChildren
                    ];
                }

                break;

            case 'footer':
                // Footer links
                $items = [
                    ['type' => 'link', 'label' => 'Privacy Policy', 'url' => $basePath . '/privacy', 'is_active' => 1],
                    ['type' => 'link', 'label' => 'Terms of Service', 'url' => $basePath . '/terms', 'is_active' => 1],
                    ['type' => 'link', 'label' => 'Contact', 'url' => $basePath . '/contact', 'is_active' => 1],
                    ['type' => 'link', 'label' => 'Help', 'url' => $basePath . '/help', 'is_active' => 1]
                ];
                break;

            case 'mobile':
                $bottomNavItems = $navClass::getBottomNavItems();
                foreach ($bottomNavItems as $key => $item) {
                    $items[] = [
                        'type' => 'link',
                        'label' => $item['display_label'],
                        'url' => $item['url'],
                        'is_active' => 1
                    ];
                }
                break;
        }

        return [[
            'id' => 'original-nav-' . $location,
            'name' => 'Original Navigation',
            'slug' => 'original-nav',
            'location' => $location,
            'is_active' => 1,
            'items' => $items
        ]];
    }

    /**
     * Get menu manager configuration
     */
    public static function getConfig()
    {
        $configFile = __DIR__ . '/../../config/menu-manager.php';
        return file_exists($configFile) ? require $configFile : [];
    }

    /**
     * Get tenant module features from tenants.features column
     * This is the single source of truth for module visibility
     *
     * @param int|null $tenantId
     * @return array Feature flags keyed by module name
     */
    public static function getTenantModuleFeatures($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        if (!$tenantId) {
            return [];
        }

        // Get tenant record
        $tenant = TenantContext::get();
        if (!$tenant || (int)($tenant['id'] ?? 0) !== (int)$tenantId) {
            // Fetch directly if context doesn't match
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT features FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $featuresJson = $row['features'] ?? '{}';
        } else {
            $featuresJson = $tenant['features'] ?? '{}';
        }

        $features = is_string($featuresJson)
            ? json_decode($featuresJson, true)
            : $featuresJson;

        if (!is_array($features)) {
            $features = [];
        }

        // Define all module keys with defaults
        // Blog defaults to true for backwards compatibility
        $moduleDefaults = [
            'listings' => true,
            'groups' => true,
            'wallet' => true,
            'volunteering' => true,
            'events' => true,
            'resources' => true,
            'polls' => true,
            'goals' => true,
            'blog' => true,
            'help_center' => true,
        ];

        // Merge with defaults - explicit false overrides default true
        $result = [];
        foreach ($moduleDefaults as $key => $default) {
            if (array_key_exists($key, $features)) {
                $result[$key] = (bool)$features[$key];
            } else {
                $result[$key] = $default;
            }
        }

        return $result;
    }
}
