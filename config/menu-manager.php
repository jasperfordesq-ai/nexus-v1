<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
     * TRUE  = Use custom menu manager with React frontend integration
     * FALSE = Use original hardcoded navigation (fallback)
     *
     * IMPORTANT: Only enable after custom menus have been created via the
     * admin panel. DefaultMenus fallback uses Font Awesome icons which are
     * incompatible with the React frontend's Lucide icon renderer.
     * The React frontend has its own hardcoded fallback when this is OFF.
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
    'original_nav_config' => 'App\Config\Navigation',

    /**
     * Cache settings for menus
     */
    'cache_enabled' => true,
    'cache_ttl' => 3600, // 1 hour

    /**
     * Feature status
     */
    'status' => 'STABLE',
    'version' => '1.0.0',
    'stable_release' => true,

    /**
     * Known issues
     */
    'known_issues' => [
        'Visibility rules need production testing',
        'Performance not fully optimized for large menus',
        'No menu import/export functionality',
        'No automated test coverage'
    ],

    /**
     * Recent improvements (v0.7.0-beta)
     */
    'recent_improvements' => [
        'Added bulk operations (activate, deactivate, delete)',
        'Added drag-and-drop reordering with visual feedback',
        'Implemented comprehensive client & server validation',
        'Added URL and CSS class input sanitization',
        'Implemented cache management UI with animations',
        'Fixed menu editing persistence issues',
        'Enhanced security with input validation',
        'Added pagination support for menu lists',
        'Improved error handling and user feedback',
        'Multi-select checkboxes for batch operations'
    ]
];
