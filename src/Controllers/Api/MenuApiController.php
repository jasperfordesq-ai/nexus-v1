<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;
use Nexus\Core\MenuManager;
use Nexus\Models\PayPlan;

/**
 * MenuApiController - API endpoints for mobile app menu access
 *
 * Provides JSON menu structures for mobile apps and SPAs
 */
class MenuApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * GET /api/menus
     * Get all menus for current tenant and layout
     */
    public function index()
    {
        // Auth is optional - menus might be public
        $userId = null;
        try {
            $userId = $this->requireAuth();
        } catch (\Exception $e) {
            // Guest access allowed
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            $this->jsonResponse(['error' => 'Tenant not found'], 404);
        }

        // Get query parameters
        $layout = $_GET['layout'] ?? null;
        $location = $_GET['location'] ?? null;

        try {
            if ($location) {
                // Get menus for specific location
                $menus = MenuManager::getMenu($location, $layout, true);
            } else {
                // Get all menus for tenant
                $locations = [
                    MenuManager::LOCATION_HEADER_MAIN,
                    MenuManager::LOCATION_HEADER_SECONDARY,
                    MenuManager::LOCATION_FOOTER,
                    MenuManager::LOCATION_MOBILE
                ];

                $allMenus = [];
                foreach ($locations as $loc) {
                    $menus = MenuManager::getMenu($loc, $layout, true);
                    if (!empty($menus)) {
                        $allMenus[$loc] = $menus;
                    }
                }
                $menus = $allMenus;
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $menus,
                'tenant_id' => $tenantId,
                'layout' => $layout,
                'location' => $location
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to load menus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/menus/:slug
     * Get a specific menu by slug
     */
    public function show($slug)
    {
        // Auth is optional
        $userId = null;
        try {
            $userId = $this->requireAuth();
        } catch (\Exception $e) {
            // Guest access allowed
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            $this->jsonResponse(['error' => 'Tenant not found'], 404);
        }

        $layout = $_GET['layout'] ?? null;

        try {
            $menu = MenuManager::getMenuBySlug($slug, $layout, true);

            if (!$menu) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Menu not found'
                ], 404);
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $menu,
                'tenant_id' => $tenantId
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to load menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/menus/config
     * Get menu configuration and plan info
     */
    public function config()
    {
        // Auth is optional
        $userId = null;
        try {
            $userId = $this->requireAuth();
        } catch (\Exception $e) {
            // Guest access allowed
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            $this->jsonResponse(['error' => 'Tenant not found'], 404);
        }

        try {
            $plan = PayPlan::getCurrentPlanForTenant($tenantId);
            $allowedLayouts = PayPlan::getAllowedLayouts($tenantId);

            $config = [
                'success' => true,
                'tenant_id' => $tenantId,
                'allowed_layouts' => $allowedLayouts,
                'current_plan' => $plan ? [
                    'name' => $plan['name'],
                    'slug' => $plan['slug'],
                    'tier_level' => $plan['tier_level']
                ] : null,
                'available_locations' => [
                    'header-main' => 'Header - Main Navigation',
                    'header-secondary' => 'Header - Secondary Navigation',
                    'footer' => 'Footer',
                    'sidebar' => 'Sidebar',
                    'mobile' => 'Mobile Menu'
                ]
            ];

            $this->jsonResponse($config);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to load config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/menus/mobile
     * Optimized endpoint for mobile apps - returns simplified menu structure
     */
    public function mobile()
    {
        // Auth is optional
        $userId = null;
        try {
            $userId = $this->requireAuth();
        } catch (\Exception $e) {
            // Guest access allowed
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            $this->jsonResponse(['error' => 'Tenant not found'], 404);
        }

        try {
            // Get mobile-specific menu or fallback to header-main
            $mobileMenu = MenuManager::getMenu(MenuManager::LOCATION_MOBILE, null, true);

            if (empty($mobileMenu)) {
                // Fallback to main header menu
                $mobileMenu = MenuManager::getMenu(MenuManager::LOCATION_HEADER_MAIN, null, true);
            }

            // Simplify structure for mobile
            $simplified = [];
            foreach ($mobileMenu as $menu) {
                foreach ($menu['items'] as $item) {
                    $simplified[] = [
                        'id' => $item['id'],
                        'label' => $item['label'],
                        'icon' => $item['icon'] ?? null,
                        'url' => $item['url'] ?? '#',
                        'type' => $item['type'],
                        'children' => $this->simplifyChildren($item['children'] ?? [])
                    ];
                }
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $simplified,
                'tenant_id' => $tenantId,
                'cache_key' => md5(json_encode($simplified))
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to load mobile menu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Simplify child items for mobile
     */
    private function simplifyChildren($children)
    {
        $simplified = [];
        foreach ($children as $child) {
            $simplified[] = [
                'id' => $child['id'],
                'label' => $child['label'],
                'icon' => $child['icon'] ?? null,
                'url' => $child['url'] ?? '#',
                'type' => $child['type']
            ];
        }
        return $simplified;
    }

    /**
     * POST /api/menus/clear-cache
     * Clear menu cache (authenticated users only)
     */
    public function clearCache()
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!$userId) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
        }

        try {
            MenuManager::clearCache($tenantId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Menu cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
}
