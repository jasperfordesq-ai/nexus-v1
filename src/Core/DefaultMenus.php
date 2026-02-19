<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Default Platform Menu System
 * Provides fallback menus when custom menus are not available or disabled
 */
class DefaultMenus
{
    /**
     * Get default platform menu for a specific location
     *
     * @param string $location Menu location (header-main, footer, etc.)
     * @param string $basePath Tenant base path
     * @return array Menu structure compatible with MenuManager
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
     * Get default main navigation menu
     */
    private static function getDefaultMainMenu($basePath)
    {
        $items = [];

        // News Feed
        $items[] = [
            'type' => 'link',
            'label' => 'News Feed',
            'url' => $basePath . '/',
            'icon' => 'fa-solid fa-newspaper',
            'sort_order' => 10,
            'is_active' => 1
        ];

        // Listings
        $items[] = [
            'type' => 'link',
            'label' => 'Listings',
            'url' => $basePath . '/listings',
            'icon' => 'fa-solid fa-list',
            'sort_order' => 20,
            'is_active' => 1
        ];

        // Volunteering (if enabled)
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

        // Groups
        $items[] = [
            'type' => 'link',
            'label' => 'Groups',
            'url' => $basePath . '/community-groups',
            'icon' => 'fa-solid fa-users',
            'sort_order' => 30,
            'is_active' => 1
        ];

        // Local Hubs
        $items[] = [
            'type' => 'link',
            'label' => 'Local Hubs',
            'url' => $basePath . '/groups',
            'icon' => 'fa-solid fa-map-marker-alt',
            'sort_order' => 40,
            'is_active' => 1
        ];

        // Members
        $items[] = [
            'type' => 'link',
            'label' => 'Members',
            'url' => $basePath . '/members',
            'icon' => 'fa-solid fa-user-group',
            'sort_order' => 50,
            'is_active' => 1
        ];

        // Explore Dropdown
        $exploreItems = self::getExploreMenuItems($basePath);
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

        // NOTE: About dropdown removed - header.php has database-driven About dropdown
        // that should be used instead to avoid duplicates

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
     * Get Explore submenu items based on enabled features
     */
    private static function getExploreMenuItems($basePath)
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
                // No feature requirement - always show
            ],
            'leaderboard' => [
                'label' => 'Leaderboards',
                'url' => '/leaderboard',
                'icon' => 'fa-solid fa-trophy',
                'separator_before' => true
                // No feature requirement - always show
            ],
            'achievements' => [
                'label' => 'Achievements',
                'url' => '/achievements',
                'icon' => 'fa-solid fa-medal'
                // No feature requirement - always show
            ],
            'ai' => [
                'label' => 'AI Assistant',
                'url' => '/ai',
                'icon' => 'fa-solid fa-robot',
                'color' => '#6366f1',
                'separator_before' => true
                // No feature requirement - always show
            ],
            'get_app' => [
                'label' => 'Get App',
                'url' => '/mobile-download',
                'icon' => 'fa-solid fa-mobile-screen-button',
                'separator_before' => true
                // No feature requirement - always show
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

                // Add optional properties if present
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
     * Get About submenu items (pulls from database pages if available)
     */
    private static function getAboutMenuItems($basePath)
    {
        $items = [];

        try {
            // Try to get "About" pages from database
            if (class_exists('\Nexus\Core\MenuGenerator')) {
                $dbPages = \Nexus\Core\MenuGenerator::getMenuPages('about');
                $order = 10;
                foreach ($dbPages as $page) {
                    $items[] = [
                        'type' => 'page',
                        'label' => $page['title'],
                        'url' => $basePath . '/page/' . $page['slug'],
                        'page_id' => $page['id'],
                        'sort_order' => $order,
                        'is_active' => 1
                    ];
                    $order += 10;
                }
            }
        } catch (\Exception $e) {
            // Fallback to hardcoded about items
        }

        // If no database pages, add default About items
        if (empty($items)) {
            $items[] = [
                'type' => 'link',
                'label' => 'About Us',
                'url' => $basePath . '/about',
                'icon' => 'fa-solid fa-info-circle',
                'sort_order' => 10,
                'is_active' => 1
            ];

            $items[] = [
                'type' => 'link',
                'label' => 'Contact',
                'url' => $basePath . '/contact',
                'icon' => 'fa-solid fa-envelope',
                'sort_order' => 20,
                'is_active' => 1
            ];
        }

        return $items;
    }

    /**
     * Get default footer menu
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
     * Get default mobile menu
     */
    private static function getDefaultMobileMenu($basePath)
    {
        // Mobile menu combines main + most important items
        $mainMenu = self::getDefaultMainMenu($basePath);

        // For mobile, we flatten the structure a bit
        $items = array_filter($mainMenu['items'], function($item) {
            // Include all non-dropdown items
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
     * Check if we should use default menus for a location
     * Returns true if no active custom menus exist
     *
     * @param string $location
     * @param int|null $tenantId
     * @return bool
     */
    public static function shouldUseDefault($location, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            if (!class_exists('\Nexus\Models\Menu')) {
                return true;
            }

            $menus = \Nexus\Models\Menu::getByLocation($location, 'modern', $tenantId);
            return empty($menus);
        } catch (\Exception $e) {
            // If there's any error (table doesn't exist, etc.), use defaults
            return true;
        }
    }
}
