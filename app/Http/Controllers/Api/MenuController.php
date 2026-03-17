<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\MenuManager;
use Nexus\Core\TenantContext;
use App\Models\PayPlan;

/**
 * MenuController — Navigation menu management.
 *
 * Native implementation using MenuManager (which itself handles DB queries,
 * caching, and feature-gated visibility). No legacy delegation.
 */
class MenuController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/menus
     *
     * Get all menus for current tenant and layout.
     * Auth is optional — menus are available to guests too.
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $layout = $this->query('layout');
        $location = $this->query('location');

        try {
            if ($location) {
                $menus = MenuManager::getMenu($location, $layout, true);
            } else {
                $locations = [
                    MenuManager::LOCATION_HEADER_MAIN,
                    MenuManager::LOCATION_HEADER_SECONDARY,
                    MenuManager::LOCATION_FOOTER,
                    MenuManager::LOCATION_MOBILE,
                ];

                $allMenus = [];
                foreach ($locations as $loc) {
                    $locMenus = MenuManager::getMenu($loc, $layout, true);
                    if (!empty($locMenus)) {
                        $allMenus[$loc] = $locMenus;
                    }
                }
                $menus = $allMenus;
            }

            return response()->json([
                'success'   => true,
                'data'      => $menus,
                'tenant_id' => $tenantId,
                'layout'    => $layout,
                'location'  => $location,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to load menus: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/menus/{slug}
     *
     * Get a specific menu by slug.
     * Auth is optional — menus are available to guests too.
     */
    public function show(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $layout = $this->query('layout');

        try {
            $menu = MenuManager::getMenuBySlug($slug, $layout, true);

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Menu not found',
                ], 404);
            }

            return response()->json([
                'success'   => true,
                'data'      => $menu,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to load menu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/menus/config
     *
     * Get menu configuration and plan info.
     * Auth is optional — config is available to guests too.
     */
    public function config(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            $plan = PayPlan::getCurrentPlanForTenant($tenantId);
            $allowedLayouts = PayPlan::getAllowedLayouts($tenantId);

            return response()->json([
                'success'             => true,
                'tenant_id'           => $tenantId,
                'allowed_layouts'     => $allowedLayouts,
                'current_plan'        => $plan ? [
                    'name'       => $plan['name'],
                    'slug'       => $plan['slug'],
                    'tier_level' => $plan['tier_level'],
                ] : null,
                'available_locations' => [
                    'header-main'      => 'Header - Main Navigation',
                    'header-secondary' => 'Header - Secondary Navigation',
                    'footer'           => 'Footer',
                    'sidebar'          => 'Sidebar',
                    'mobile'           => 'Mobile Menu',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to load config: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/menus/mobile
     *
     * Optimized endpoint for mobile apps — returns simplified menu structure.
     * Auth is optional — mobile menus are available to guests too.
     */
    public function mobile(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        try {
            // Get mobile-specific menu or fallback to header-main
            $mobileMenu = MenuManager::getMenu(MenuManager::LOCATION_MOBILE, null, true);

            if (empty($mobileMenu)) {
                $mobileMenu = MenuManager::getMenu(MenuManager::LOCATION_HEADER_MAIN, null, true);
            }

            // Simplify structure for mobile
            $simplified = [];
            foreach ($mobileMenu as $menu) {
                foreach ($menu['items'] as $item) {
                    $simplified[] = [
                        'id'       => $item['id'],
                        'label'    => $item['label'],
                        'icon'     => $item['icon'] ?? null,
                        'url'      => $item['url'] ?? '#',
                        'type'     => $item['type'],
                        'children' => $this->simplifyChildren($item['children'] ?? []),
                    ];
                }
            }

            return response()->json([
                'success'   => true,
                'data'      => $simplified,
                'tenant_id' => $tenantId,
                'cache_key' => md5(json_encode($simplified)),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to load mobile menu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/menus/clear-cache
     *
     * Clear menu cache (admin only).
     */
    public function clearCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            MenuManager::clearCache($tenantId);

            return response()->json([
                'success' => true,
                'message' => 'Menu cache cleared successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper: Simplify child items for mobile.
     */
    private function simplifyChildren(array $children): array
    {
        $simplified = [];
        foreach ($children as $child) {
            $simplified[] = [
                'id'    => $child['id'],
                'label' => $child['label'],
                'icon'  => $child['icon'] ?? null,
                'url'   => $child['url'] ?? '#',
                'type'  => $child['type'],
            ];
        }
        return $simplified;
    }
}
