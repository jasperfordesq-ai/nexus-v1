<?php
/**
 * Menu Manager Configuration
 *
 * MASTER KILL SWITCH for Menu Manager System
 * Set to false to disable menu manager and use original hardcoded menus
 */

return [
    /**
     * Enable/Disable Menu Manager
     *
     * TRUE  = Use custom menu manager (EXPERIMENTAL - UNDER DEVELOPMENT)
     * FALSE = Use original hardcoded navigation (STABLE)
     *
     * IMPORTANT: Menu Manager is currently UNSTABLE and under active development.
     * For production sites, keep this set to FALSE until stable release.
     */
    'enabled' => false,

    /**
     * Show development warning banner in admin
     * Displays warning when menu manager is enabled
     */
    'show_warning' => true,

    /**
     * Allow admin override
     * If true, admins can toggle menu manager via URL parameter
     * Access: /admin/menus?enable_menu_manager=1
     */
    'allow_admin_override' => true,

    /**
     * Original navigation configuration
     * Falls back to this when menu manager is disabled
     */
    'original_nav_config' => 'Nexus\Config\Navigation',

    /**
     * Cache settings for menus
     */
    'cache_enabled' => true,
    'cache_ttl' => 3600, // 1 hour

    /**
     * Feature status
     */
    'status' => 'UNDER_DEVELOPMENT',
    'version' => '0.1.0-alpha',
    'stable_release' => false,

    /**
     * Known issues
     */
    'known_issues' => [
        'Menu editing may not persist correctly',
        'Visibility rules need testing',
        'Performance not optimized',
        'Missing bulk operations',
        'No menu import/export'
    ]
];
