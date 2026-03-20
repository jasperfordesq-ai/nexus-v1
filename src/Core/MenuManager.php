<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Menu;
use Nexus\Models\MenuItem;
use Nexus\Models\PayPlan;

/**
 * MenuManager - Tenant-aware, Pay Layout-aware Menu System
 *
 * Provides:
 * - Custom menu management (DB-driven)
 * - Default fallback menus (built-in)
 * - Page-builder menu pages (from pages table)
 * - Pay plan restrictions
 * - Layout-specific menus
 * - Advanced visibility rules
 * - Menu caching
 */
/**
 *  Use AppCoreMenuManager instead. This class is maintained for backward compatibility only.
 */
/**
 * @deprecated Use AppCoreMenuManager instead. Maintained for backward compatibility.
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
     * @param string|null $layout Current layout (modern)
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
            $defaultMenu = self::getDefaultMenu($location, TenantContext::getBasePath());
            if (!empty($defaultMenu)) {
                return [$defaultMenu];
            }

            // Fallback to pages-table menu for backwards compatibility
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
            'is_super_admin' => $_SESSION['is_super_admin'] ?? false,
            'is_tenant_super_admin' => $_SESSION['is_tenant_super_admin'] ?? false
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
     * Legacy support - get menu pages from the pages table for a location
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
            $pages = self::getMenuPages($legacyLocation);

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
     * Get pages from the pages table for a specific menu location.
     * Inlined from the former MenuGenerator class.
     *
     * @param string $location Menu location (about, main, footer)
     * @return array List of pages with title, slug, url
     */
    public static function getMenuPages(string $location = 'about'): array
    {
        try {
            $tenantId = TenantContext::getId();
            if (!$tenantId) {
                return [];
            }

            // Check if page builder module is enabled (gracefully handle missing column)
            try {
                $tenant = Database::query("SELECT module_page_builder_enabled FROM tenants WHERE id = ?", [$tenantId])->fetch();
                if ($tenant && empty($tenant['module_page_builder_enabled'])) {
                    return []; // Module explicitly disabled
                }
            } catch (\Exception $e) {
                // Column doesn't exist - assume page builder is enabled by default
            }

            $basePath = TenantContext::getBasePath();

            // Fetch published pages for the specified menu location
            try {
                $pages = Database::query(
                    "SELECT title, slug FROM pages
                     WHERE tenant_id = ?
                     AND is_published = 1
                     AND show_in_menu = 1
                     AND menu_location = ?
                     ORDER BY sort_order ASC, title ASC",
                    [$tenantId, $location]
                )->fetchAll();
            } catch (\Exception $e) {
                // Fallback: columns don't exist yet, show all published pages in About
                if ($location === 'about') {
                    $pages = Database::query(
                        "SELECT title, slug FROM pages
                         WHERE tenant_id = ? AND is_published = 1
                         ORDER BY sort_order ASC, title ASC",
                        [$tenantId]
                    )->fetchAll();
                } else {
                    $pages = [];
                }
            }

            // Add full URL to each page
            foreach ($pages as &$page) {
                $page['url'] = $basePath . '/page/' . $page['slug'];
            }

            return $pages;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Render the "About" dropdown menu HTML for the current tenant.
     * Inlined from the former MenuGenerator class.
     *
     * @return string HTML markup
     */
    public static function renderAboutMenu()
    {
        try {
            $tenantId = TenantContext::getId();
            if (!$tenantId) {
                return '';
            }

            $pages = self::getMenuPages('about');
            if (empty($pages)) {
                return '';
            }

            $basePath = TenantContext::getBasePath();
            $html = '<li>';
            $html .= '<details role="list" dir="rtl">';
            $html .= '<summary aria-haspopup="listbox" role="link">About</summary>';
            $html .= '<ul role="listbox">';

            foreach ($pages as $page) {
                $slug = htmlspecialchars($page['slug']);
                $title = htmlspecialchars($page['title']);
                $html .= "<li><a href=\"{$basePath}/page/{$slug}\">{$title}</a></li>";
            }

            $html .= '</ul>';
            $html .= '</details>';
            $html .= '</li>';

            return $html;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get pages for the main navigation bar.
     *
     * @return array List of pages
     */
    public static function getMainNavPages()
    {
        return self::getMenuPages('main');
    }

    /**
     * Get pages for the footer.
     *
     * @return array List of pages
     */
    public static function getFooterPages()
    {
        return self::getMenuPages('footer');
    }

    /**
     * Check if there are any pages for a menu location.
     *
     * @param string $location Menu location
     * @return bool
     */
    public static function hasMenuPages($location)
    {
        return !empty(self::getMenuPages($location));
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
            // Ultimate fallback - use built-in default menus
            $defaultMenu = self::getDefaultMenu($location, TenantContext::getBasePath());
            if (!empty($defaultMenu)) {
                return [$defaultMenu];
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

    // ---------------------------------------------------------------
    // Default Menu Builders (inlined from the former DefaultMenus class)
    // ---------------------------------------------------------------

    /**
     * Get default platform menu for a specific location.
     *
     * @param string $location Menu location (header-main, footer, mobile)
     * @param string $basePath Tenant base path
     * @return array Menu structure compatible with MenuManager, or empty array
     */
    public static function getDefaultMenu($location, $basePath = '')
    {
        $basePath = $basePath ?: TenantContext::getBasePath();

        switch ($location) {
            case 'header-main':
                return self::getDefaultMainMenu($basePath);
            case 'footer':
                return self::getDefaultFooterMenu($basePath);
            case 'mobile':
                return self::getDefaultMobileMenu($basePath);
            default:
                return [];
        }
    }

    /**
     * Get default main navigation menu.
     */
    private static function getDefaultMainMenu($basePath)
    {
        $items = [];

        $items[] = [
            'type' => 'link',
            'label' => 'News Feed',
            'url' => $basePath . '/',
            'icon' => 'fa-solid fa-newspaper',
            'sort_order' => 10,
            'is_active' => 1
        ];

        $items[] = [
            'type' => 'link',
            'label' => 'Listings',
            'url' => $basePath . '/listings',
            'icon' => 'fa-solid fa-list',
            'sort_order' => 20,
            'is_active' => 1
        ];

        if (TenantContext::hasFeature('volunteering')) {
            $items[] = [
                'type' => 'link',
                'label' => 'Volunteering',
                'url' => $basePath . '/volunteering',
                'icon' => 'fa-solid fa-hands-helping',
                'sort_order' => 25,
                'is_active' => 1
            ];
        }

        $items[] = [
            'type' => 'link',
            'label' => 'Groups',
            'url' => $basePath . '/community-groups',
            'icon' => 'fa-solid fa-users',
            'sort_order' => 30,
            'is_active' => 1
        ];

        $items[] = [
            'type' => 'link',
            'label' => 'Local Hubs',
            'url' => $basePath . '/groups',
            'icon' => 'fa-solid fa-map-marker-alt',
            'sort_order' => 40,
            'is_active' => 1
        ];

        $items[] = [
            'type' => 'link',
            'label' => 'Members',
            'url' => $basePath . '/members',
            'icon' => 'fa-solid fa-user-group',
            'sort_order' => 50,
            'is_active' => 1
        ];

        $exploreItems = self::getDefaultExploreMenuItems($basePath);
        if (!empty($exploreItems)) {
            $items[] = [
                'type' => 'dropdown',
                'label' => 'Explore',
                'icon' => 'fa-solid fa-compass',
                'sort_order' => 60,
                'is_active' => 1,
                'children' => $exploreItems
            ];
        }

        return [
            'id' => 'default-main',
            'name' => 'Default Main Navigation',
            'slug' => 'default-main-nav',
            'location' => 'header-main',
            'is_active' => 1,
            'items' => $items
        ];
    }

    /**
     * Get Explore submenu items based on enabled features.
     */
    private static function getDefaultExploreMenuItems($basePath)
    {
        $items = [];
        $order = 10;

        $exploreConfig = [
            'events' => [
                'label' => 'Events',
                'url' => '/events',
                'icon' => 'fa-solid fa-calendar-days',
                'feature' => 'events'
            ],
            'polls' => [
                'label' => 'Polls',
                'url' => '/polls',
                'icon' => 'fa-solid fa-square-poll-vertical',
                'feature' => 'polls'
            ],
            'goals' => [
                'label' => 'Goals',
                'url' => '/goals',
                'icon' => 'fa-solid fa-bullseye',
                'feature' => 'goals'
            ],
            'resources' => [
                'label' => 'Resources',
                'url' => '/resources',
                'icon' => 'fa-solid fa-folder-open',
                'feature' => 'resources'
            ],
            'smart_matching' => [
                'label' => 'Smart Matching',
                'url' => '/matches',
                'icon' => 'fa-solid fa-wand-magic-sparkles',
                'color' => '#ec4899',
                'separator_before' => true
            ],
            'leaderboard' => [
                'label' => 'Leaderboards',
                'url' => '/leaderboard',
                'icon' => 'fa-solid fa-trophy',
                'separator_before' => true
            ],
            'achievements' => [
                'label' => 'Achievements',
                'url' => '/achievements',
                'icon' => 'fa-solid fa-medal'
            ],
            'ai' => [
                'label' => 'AI Assistant',
                'url' => '/ai',
                'icon' => 'fa-solid fa-robot',
                'color' => '#6366f1',
                'separator_before' => true
            ],
            'get_app' => [
                'label' => 'Get App',
                'url' => '/mobile-download',
                'icon' => 'fa-solid fa-mobile-screen-button',
                'separator_before' => true
            ]
        ];

        foreach ($exploreConfig as $key => $config) {
            if (!isset($config['feature']) || TenantContext::hasFeature($config['feature'])) {
                $item = [
                    'type' => 'link',
                    'label' => $config['label'],
                    'url' => $basePath . $config['url'],
                    'icon' => $config['icon'],
                    'sort_order' => $order,
                    'is_active' => 1
                ];

                if (isset($config['color'])) {
                    $item['color'] = $config['color'];
                }
                if (isset($config['separator_before'])) {
                    $item['separator_before'] = $config['separator_before'];
                }
                if (isset($config['highlight'])) {
                    $item['highlight'] = $config['highlight'];
                }

                $items[] = $item;
                $order += 10;
            }
        }

        return $items;
    }

    /**
     * Get default footer menu.
     */
    private static function getDefaultFooterMenu($basePath)
    {
        $items = [
            [
                'type' => 'link',
                'label' => 'Privacy Policy',
                'url' => $basePath . '/privacy',
                'sort_order' => 10,
                'is_active' => 1
            ],
            [
                'type' => 'link',
                'label' => 'Terms of Service',
                'url' => $basePath . '/terms',
                'sort_order' => 20,
                'is_active' => 1
            ],
            [
                'type' => 'link',
                'label' => 'Contact Us',
                'url' => $basePath . '/contact',
                'sort_order' => 30,
                'is_active' => 1
            ],
            [
                'type' => 'link',
                'label' => 'Help Center',
                'url' => $basePath . '/help',
                'sort_order' => 40,
                'is_active' => 1
            ]
        ];

        return [
            'id' => 'default-footer',
            'name' => 'Default Footer Navigation',
            'slug' => 'default-footer-nav',
            'location' => 'footer',
            'is_active' => 1,
            'items' => $items
        ];
    }

    /**
     * Get default mobile menu.
     */
    private static function getDefaultMobileMenu($basePath)
    {
        $mainMenu = self::getDefaultMainMenu($basePath);

        // For mobile, flatten by excluding dropdown items
        $items = array_filter($mainMenu['items'], function ($item) {
            return $item['type'] !== 'dropdown';
        });

        return [
            'id' => 'default-mobile',
            'name' => 'Default Mobile Navigation',
            'slug' => 'default-mobile-nav',
            'location' => 'mobile',
            'is_active' => 1,
            'items' => array_values($items)
        ];
    }

    /**
     * Check if default menus should be used for a location.
     * Returns true if no active custom menus exist.
     *
     * @param string $location
     * @param int|null $tenantId
     * @return bool
     */
    public static function shouldUseDefault($location, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $menus = Menu::getByLocation($location, 'modern', $tenantId);
            return empty($menus);
        } catch (\Exception $e) {
            return true;
        }
    }
}
