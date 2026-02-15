<?php
/**
 * Unified Navigation Configuration
 * Single source of truth for all navigation menus
 *
 * SOURCE OF TRUTH: Modern Desktop Header (views/layouts/modern/header.php)
 * All menus synchronize to this structure
 */

namespace Nexus\Config;

class Navigation {

    /**
     * Get main navigation items (PRIMARY - in display order)
     * Source: Modern desktop header main nav (lines 2013-2020)
     */
    public static function getMainNavItems() {
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        return [
            'news_feed' => [
                'label' => 'News Feed',
                'short_label' => 'Home',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/'),
                'icon' => 'fa-solid fa-house',
                'dashicon' => 'dashicons-admin-home',
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
                'data_nav_match' => '/',
                'order' => 1
            ],
            'listings' => [
                'label' => 'Listings',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/listings'),
                'icon' => 'fa-solid fa-hand-holding-heart',
                'dashicon' => 'dashicons-list-view',
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h18V4H4c-1.1 0-2 .9-2 2v11H0v3h14v-3H4V6zm19 2h-6c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V9c0-.55-.45-1-1-1zm-1 9h-4v-7h4v7z"/></svg>',
                'data_nav_match' => 'listings',
                'requires_feature' => 'listings',
                'order' => 2
            ],
            'volunteering' => [
                'label' => 'Volunteering',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/volunteering'),
                'icon' => 'fa-solid fa-hands-helping',
                'dashicon' => 'dashicons-heart',
                'data_nav_match' => 'volunteering',
                'requires_feature' => 'volunteering',
                'order' => 3
            ],
            'groups' => [
                'label' => 'Groups',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/community-groups'),
                'icon' => 'fa-solid fa-users',
                'dashicon' => 'dashicons-groups',
                'data_nav_match' => 'community-groups',
                'requires_feature' => 'groups',
                'order' => 4
            ],
            'local_hubs' => [
                'label' => 'Local Hubs',
                'short_label' => 'Hubs',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/groups'),
                'icon' => 'fa-solid fa-people-group',
                'dashicon' => 'dashicons-groups',
                'data_nav_match' => 'groups',
                'requires_feature' => 'groups',
                'order' => 5
            ],
            'members' => [
                'label' => 'Members',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/members'),
                'icon' => 'fa-solid fa-user-group',
                'dashicon' => 'dashicons-admin-users',
                'data_nav_match' => 'members',
                'order' => 6
            ]
        ];
    }

    /**
     * Get "Explore" dropdown items
     * Source: Modern desktop header Explore dropdown (lines 2024-2044)
     */
    public static function getExploreItems() {
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        return [
            'events' => [
                'label' => 'Events',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/events'),
                'icon' => 'fa-solid fa-calendar-days',
                'color' => '#8b5cf6',
                'requires_feature' => 'events',
                'order' => 1
            ],
            'polls' => [
                'label' => 'Polls',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/polls'),
                'icon' => 'fa-solid fa-square-poll-vertical',
                'color' => '#06b6d4',
                'requires_feature' => 'polls',
                'order' => 2
            ],
            'goals' => [
                'label' => 'Goals',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/goals'),
                'icon' => 'fa-solid fa-bullseye',
                'color' => '#f59e0b',
                'requires_feature' => 'goals',
                'order' => 3
            ],
            'resources' => [
                'label' => 'Resources',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/resources'),
                'icon' => 'fa-solid fa-folder-open',
                'color' => '#10b981',
                'requires_feature' => 'resources',
                'order' => 4
            ],
            'smart_matching' => [
                'label' => 'Smart Matching',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/matches'),
                'icon' => 'fa-solid fa-wand-magic-sparkles',
                'color' => '#ec4899',
                'order' => 5,
                'separator_before' => true
            ],
            'leaderboard' => [
                'label' => 'Leaderboards',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/leaderboard'),
                'icon' => 'fa-solid fa-trophy',
                'color' => '#f59e0b',
                'order' => 6
            ],
            'achievements' => [
                'label' => 'Achievements',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/achievements'),
                'icon' => 'fa-solid fa-medal',
                'color' => '#a855f7',
                'order' => 5
            ],
            'ai' => [
                'label' => 'AI Assistant',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/ai'),
                'icon' => 'fa-solid fa-robot',
                'color' => '#6366f1',
                'highlight' => true,
                'order' => 7,
                'separator_before' => true
            ],
        ];
    }

    /**
     * Get utility bar items (SECONDARY - in display order)
     * Source: Modern desktop header utility bar (lines 1987-1990)
     * Logged-in users only
     */
    public static function getUtilityItems() {
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        $userId = $_SESSION['user_id'] ?? null;

        return [
            'messages' => [
                'label' => 'Messages',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/messages'),
                'icon' => 'fa-solid fa-envelope',
                'dashicon' => 'dashicons-email',
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
                'is_icon_button' => true,
                'has_badge' => true,
                'requires_login' => true,
                'order' => 1
            ],
            'notifications' => [
                'label' => 'Notifications',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/notifications'),
                'icon' => 'fa-solid fa-bell',
                'dashicon' => 'dashicons-bell',
                'is_icon_button' => true,
                'has_badge' => true,
                'requires_login' => true,
                'order' => 2
            ],
            'wallet' => [
                'label' => 'Wallet',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/wallet'),
                'icon' => 'fa-solid fa-wallet',
                'dashicon' => 'dashicons-money-alt',
                'desktop_only' => true,
                'requires_login' => true,
                'requires_feature' => 'wallet',
                'order' => 3
            ],
            'my_profile' => [
                'label' => 'My Profile',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/profile/' . $userId),
                'icon' => 'fa-solid fa-user',
                'dashicon' => 'dashicons-admin-users',
                'desktop_only' => true,
                'requires_login' => true,
                'order' => 4
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/dashboard'),
                'icon' => 'fa-solid fa-gauge',
                'dashicon' => 'dashicons-dashboard',
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
                'requires_login' => true,
                'order' => 5
            ],
            'admin' => [
                'label' => 'Admin',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/admin-legacy'),
                'icon' => 'fa-solid fa-shield-halved',
                'dashicon' => 'dashicons-shield',
                'color' => '#ea580c',
                'requires_admin' => true,
                'order' => 6
            ],
            'sign_out' => [
                'label' => 'Sign Out',
                'url' => $basePath . '/logout',
                'icon' => 'fa-solid fa-arrow-right-from-bracket',
                'dashicon' => 'dashicons-exit',
                'color' => '#ef4444',
                'requires_login' => true,
                'order' => 7
            ]
        ];
    }

    /**
     * Get items for mobile bottom nav (5-item bar)
     * CivicOne layout only
     */
    public static function getBottomNavItems() {
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        $userId = $_SESSION['user_id'] ?? null;

        $items = [
            'home' => [
                'display_label' => 'Home',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
                'description' => 'Activity Feed',
                'order' => 1
            ],
            'listings' => [
                'display_label' => 'Listings',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/listings'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h18V4H4c-1.1 0-2 .9-2 2v11H0v3h14v-3H4V6zm19 2h-6c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V9c0-.55-.45-1-1-1zm-1 9h-4v-7h4v7z"/></svg>',
                'description' => 'Browse offers and requests',
                'order' => 2
            ],
            'create' => [
                'display_label' => 'Create',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . ($userId ? '/listings/create?type=offer' : '/login')),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>',
                'description' => 'Create new listing',
                'elevated' => true,
                'order' => 3
            ],
            'messages' => [
                'display_label' => $userId ? 'Messages' : 'Sign In',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . ($userId ? '/messages' : '/login')),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
                'description' => 'View your messages',
                'has_badge' => $userId ? true : false,
                'order' => 4
            ],
            'dashboard' => [
                'display_label' => $userId ? 'Dashboard' : 'Join',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . ($userId ? '/dashboard' : '/register')),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
                'description' => 'Your overview',
                'order' => 5
            ]
        ];

        return $items;
    }

    /**
     * BACKWARD COMPATIBILITY METHODS
     * CivicOne header still uses these old method names
     */
    public static function getPrimaryItems() {
        return self::getMainNavItems();
    }

    public static function getCreateItems() {
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        $items = [
            'offer' => [
                'label' => 'New Offer',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/listings/create?type=offer'),
                'icon' => 'dashicons-plus-alt',
                'color' => '#059669'
            ],
            'request' => [
                'label' => 'New Request',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/listings/create?type=request'),
                'icon' => 'dashicons-sos',
                'color' => '#d97706'
            ]
        ];

        if (\Nexus\Core\TenantContext::hasFeature('events')) {
            $items['event'] = [
                'label' => 'New Event',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/events/create'),
                'icon' => 'dashicons-calendar-alt',
                'color' => '#4f46e5'
            ];
        }

        if (\Nexus\Core\TenantContext::hasFeature('volunteering') &&
            (($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_org_admin']))) {
            $items['volunteering'] = [
                'label' => 'Volunteer Opp',
                'url' => \Nexus\Services\LayoutHelper::preserveLayoutInUrl($basePath . '/volunteering/opportunities/create'),
                'icon' => 'dashicons-heart',
                'color' => '#be185d'
            ];
        }

        return $items;
    }

    /**
     * Check if a navigation item should be displayed
     */
    public static function shouldShow($item) {
        // Check feature requirements
        if (isset($item['requires_feature'])) {
            if (!\Nexus\Core\TenantContext::hasFeature($item['requires_feature'])) {
                return false;
            }
        }

        // Check login requirements
        if (isset($item['requires_login']) && $item['requires_login']) {
            if (!isset($_SESSION['user_id'])) {
                return false;
            }
        }

        // Check admin requirements
        if (isset($item['requires_admin']) && $item['requires_admin']) {
            $isAdmin = (!empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin');
            if (!$isAdmin) {
                return false;
            }
        }

        // Check tenant requirements
        if (isset($item['requires_tenant'])) {
            if (\Nexus\Core\TenantContext::getId() != $item['requires_tenant']) {
                return false;
            }
        }

        return true;
    }
}
