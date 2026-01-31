<?php
/**
 * NavigationConfig - Centralized Navigation Configuration
 *
 * This class serves as the single source of truth for all navigation items
 * across both Modern and CivicOne themes. It extracts and consolidates
 * navigation data from the Modern theme's premium-mega-menu.php and navbar.php.
 *
 * Usage:
 *   $primary = NavigationConfig::getPrimaryNav();
 *   $community = NavigationConfig::getCommunityNav();
 *   $explore = NavigationConfig::getExploreNav();
 */

namespace Nexus\Helpers;

use Nexus\Core\TenantContext;

class NavigationConfig
{
    /**
     * Get primary navigation items (top-level, always visible)
     * Based on Modern theme's navbar.php
     *
     * @return array Navigation items with label, url, icon, key, and optional feature flag
     */
    public static function getPrimaryNav(): array
    {
        $items = [
            [
                'label' => 'Feed',
                'url' => '/',
                'icon' => 'fa-house',
                'key' => 'home',
                'pattern' => '/',
            ],
            [
                'label' => 'Listings',
                'url' => '/listings',
                'icon' => 'fa-hand-holding-heart',
                'key' => 'listings',
                'pattern' => '/listings',
            ],
        ];

        // Conditional features
        if (TenantContext::hasFeature('volunteering')) {
            $items[] = [
                'label' => 'Volunteering',
                'url' => '/volunteering',
                'icon' => 'fa-handshake-angle',
                'key' => 'volunteering',
                'pattern' => '/volunteering',
                'feature' => 'volunteering',
            ];
        }

        return $items;
    }

    /**
     * Get Community menu items
     * Based on Modern theme's premium-mega-menu.php "Community" section
     *
     * @return array Navigation items with label, url, icon, desc
     */
    public static function getCommunityNav(): array
    {
        $items = [
            [
                'label' => 'Community Groups',
                'url' => '/community-groups',
                'icon' => 'fa-users',
                'desc' => 'Join interest-based communities',
                'pattern' => '/community-groups',
            ],
            [
                'label' => 'Local Hubs',
                'url' => '/groups',
                'icon' => 'fa-location-dot',
                'desc' => 'Connect with neighbors nearby',
                'pattern' => '/groups',
            ],
            [
                'label' => 'Members',
                'url' => '/members',
                'icon' => 'fa-user-group',
                'desc' => 'Browse all community members',
                'pattern' => '/members',
            ],
        ];

        if (TenantContext::hasFeature('events')) {
            $items[] = [
                'label' => 'Events',
                'url' => '/events',
                'icon' => 'fa-calendar-days',
                'desc' => 'Upcoming gatherings & activities',
                'pattern' => '/events',
                'feature' => 'events',
            ];
        }

        return $items;
    }

    /**
     * Get Explore menu items
     * Based on Modern theme's premium-mega-menu.php "Explore" section
     *
     * @return array Navigation items grouped by category
     */
    public static function getExploreNav(): array
    {
        $items = [];

        // Discovery section
        if (TenantContext::hasFeature('goals')) {
            $items[] = [
                'label' => 'Goals',
                'url' => '/goals',
                'icon' => 'fa-bullseye',
                'desc' => 'Set and track your goals',
                'pattern' => '/goals',
                'category' => 'discover',
                'feature' => 'goals',
            ];
        }

        if (TenantContext::hasFeature('polls')) {
            $items[] = [
                'label' => 'Polls',
                'url' => '/polls',
                'icon' => 'fa-square-poll-vertical',
                'desc' => 'Vote on community questions',
                'pattern' => '/polls',
                'category' => 'discover',
                'feature' => 'polls',
            ];
        }

        if (TenantContext::hasFeature('resources')) {
            $items[] = [
                'label' => 'Resources',
                'url' => '/resources',
                'icon' => 'fa-folder-open',
                'desc' => 'Helpful community resources',
                'pattern' => '/resources',
                'category' => 'discover',
                'feature' => 'resources',
            ];
        }

        // Gamification section
        $items[] = [
            'label' => 'Leaderboard',
            'url' => '/leaderboard',
            'icon' => 'fa-trophy',
            'desc' => 'See top community contributors',
            'pattern' => '/leaderboard',
            'category' => 'gamification',
        ];

        $items[] = [
            'label' => 'Achievements',
            'url' => '/achievements',
            'icon' => 'fa-medal',
            'desc' => 'Unlock badges & rewards',
            'pattern' => '/achievements',
            'category' => 'gamification',
        ];

        // Smart tools section
        $items[] = [
            'label' => 'Smart Matching',
            'url' => '/matches',
            'icon' => 'fa-wand-magic-sparkles',
            'desc' => 'AI-powered connections',
            'pattern' => '/matches',
            'category' => 'tools',
        ];

        return $items;
    }

    /**
     * Get About menu items (static pages, news, etc.)
     * Based on Modern theme's premium-mega-menu.php "About" section
     *
     * @return array Navigation items
     */
    public static function getAboutNav(): array
    {
        $items = [];

        if (TenantContext::hasFeature('blog')) {
            $items[] = [
                'label' => 'News',
                'url' => '/news',
                'icon' => 'fa-newspaper',
                'desc' => 'Community updates & stories',
                'pattern' => '/news',
                'feature' => 'blog',
            ];
        }

        return $items;
    }

    /**
     * Get Help & Support menu items
     * Based on Modern theme's premium-mega-menu.php "Help & Support" section
     *
     * @return array Navigation items
     */
    public static function getHelpNav(): array
    {
        return [
            [
                'label' => 'Help Center',
                'url' => '/help',
                'icon' => 'fa-circle-question',
                'desc' => 'Get support & answers',
                'pattern' => '/help',
            ],
            [
                'label' => 'Contact Us',
                'url' => '/contact',
                'icon' => 'fa-envelope',
                'desc' => 'Get in touch with us',
                'pattern' => '/contact',
            ],
            [
                'label' => 'Accessibility',
                'url' => '/accessibility',
                'icon' => 'fa-universal-access',
                'desc' => 'Our accessibility commitment',
                'pattern' => '/accessibility',
            ],
        ];
    }

    /**
     * Get all secondary navigation items (for "More" dropdown)
     * Combines Community, Explore, About, and Help items
     *
     * @return array Grouped navigation items
     */
    public static function getSecondaryNav(): array
    {
        return [
            'community' => [
                'title' => 'Community',
                'icon' => 'fa-users',
                'items' => self::getCommunityNav(),
            ],
            'explore' => [
                'title' => 'Explore',
                'icon' => 'fa-compass',
                'items' => self::getExploreNav(),
            ],
            'about' => [
                'title' => 'About',
                'icon' => 'fa-circle-info',
                'items' => self::getAboutNav(),
            ],
            'help' => [
                'title' => 'Help & Support',
                'icon' => 'fa-life-ring',
                'items' => self::getHelpNav(),
            ],
        ];
    }

    /**
     * Get flattened list of all navigation items for simple dropdowns
     * Useful for CivicOne's simple "More" dropdown
     *
     * @return array Flat list of all secondary nav items
     */
    public static function getFlatSecondaryNav(): array
    {
        $items = [];

        // Add all community items
        $items = array_merge($items, self::getCommunityNav());

        // Add all explore items
        $items = array_merge($items, self::getExploreNav());

        // Add all about items
        $items = array_merge($items, self::getAboutNav());

        // Add all help items
        $items = array_merge($items, self::getHelpNav());

        return $items;
    }

    /**
     * Get Explore menu items for CivicOne theme (excludes gamification)
     *
     * CivicOne theme follows GOV.UK Design System patterns where gamification
     * features are accessed via user dashboard/profile rather than primary navigation.
     *
     * @return array Navigation items without gamification category
     */
    public static function getExploreNavCivicOne(): array
    {
        return array_filter(self::getExploreNav(), function ($item) {
            return ($item['category'] ?? '') !== 'gamification';
        });
    }

    /**
     * Get secondary navigation for CivicOne theme (excludes gamification from explore)
     *
     * @return array Grouped navigation items
     */
    public static function getSecondaryNavCivicOne(): array
    {
        return [
            'community' => [
                'title' => 'Community',
                'icon' => 'fa-users',
                'items' => self::getCommunityNav(),
            ],
            'explore' => [
                'title' => 'Explore',
                'icon' => 'fa-compass',
                'items' => self::getExploreNavCivicOne(),
            ],
            'about' => [
                'title' => 'About',
                'icon' => 'fa-circle-info',
                'items' => self::getAboutNav(),
            ],
            'help' => [
                'title' => 'Help & Support',
                'icon' => 'fa-life-ring',
                'items' => self::getHelpNav(),
            ],
        ];
    }

    /**
     * Get gamification navigation items only
     * For use in dashboard/profile sections
     *
     * @return array Gamification nav items (Leaderboard, Achievements)
     */
    public static function getGamificationNav(): array
    {
        return array_filter(self::getExploreNav(), function ($item) {
            return ($item['category'] ?? '') === 'gamification';
        });
    }

    /**
     * Get custom file-based pages for a tenant
     * Wrapper around TenantContext::getCustomPages() for consistency
     *
     * @param string|null $layout Optional layout name
     * @return array List of pages
     */
    public static function getCustomPages(?string $layout = null): array
    {
        return TenantContext::getCustomPages($layout);
    }

    /**
     * Get database-driven pages from Page Builder
     * Wrapper around MenuGenerator for consistency
     *
     * @param string $location Menu location (main, about, footer)
     * @return array List of pages
     */
    public static function getMenuPages(string $location = 'main'): array
    {
        if (class_exists('\Nexus\Core\MenuGenerator')) {
            return \Nexus\Core\MenuGenerator::getMenuPages($location);
        }
        return [];
    }
}
