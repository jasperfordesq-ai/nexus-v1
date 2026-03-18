<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\MenuManager as LegacyMenuManager;

/**
 * App-namespace wrapper for Nexus\Core\MenuManager.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with a Laravel-native menu system.
 */
class MenuManager
{
    /** Menu location constants (inlined — safe when legacy is removed) */
    const LOCATION_HEADER_MAIN = 'header-main';
    const LOCATION_HEADER_SECONDARY = 'header-secondary';
    const LOCATION_FOOTER = 'footer';
    const LOCATION_SIDEBAR = 'sidebar';
    const LOCATION_MOBILE = 'mobile';

    /** Legacy support */
    const MENU_ABOUT = 'about';
    const MENU_MAIN = 'main';
    const MENU_FOOTER = 'footer';

    /**
     * Get menu for a specific location and layout.
     */
    public static function getMenu($location, $layout = null, $useCache = true)
    {
        if (!class_exists(LegacyMenuManager::class)) { return []; }
        return LegacyMenuManager::getMenu($location, $layout, $useCache);
    }

    /**
     * Get a single menu by slug.
     */
    public static function getMenuBySlug($slug, $layout = null, $useCache = true)
    {
        if (!class_exists(LegacyMenuManager::class)) { return null; }
        return LegacyMenuManager::getMenuBySlug($slug, $layout, $useCache);
    }

    /**
     * Render menu as HTML.
     */
    public static function renderMenu($location, $layout = null, $cssClass = 'menu')
    {
        if (!class_exists(LegacyMenuManager::class)) { return ''; }
        return LegacyMenuManager::renderMenu($location, $layout, $cssClass);
    }

    /**
     * Clear menu cache for a tenant.
     */
    public static function clearCache($tenantId = null)
    {
        if (!class_exists(LegacyMenuManager::class)) { return null; }
        return LegacyMenuManager::clearCache($tenantId);
    }

    /**
     * Legacy method: render about menu.
     */
    public static function renderAboutMenu()
    {
        if (!class_exists(LegacyMenuManager::class)) { return ''; }
        return LegacyMenuManager::renderAboutMenu();
    }

    /**
     * Legacy method: get main nav pages.
     */
    public static function getMainNavPages()
    {
        if (!class_exists(LegacyMenuManager::class)) { return []; }
        return LegacyMenuManager::getMainNavPages();
    }

    /**
     * Legacy method: get footer pages.
     */
    public static function getFooterPages()
    {
        if (!class_exists(LegacyMenuManager::class)) { return []; }
        return LegacyMenuManager::getFooterPages();
    }

    /**
     * Legacy method: check if menu pages exist for location.
     */
    public static function hasMenuPages($location)
    {
        if (!class_exists(LegacyMenuManager::class)) { return false; }
        return LegacyMenuManager::hasMenuPages($location);
    }

    /**
     * Check if menu manager is enabled.
     */
    public static function isEnabled()
    {
        if (!class_exists(LegacyMenuManager::class)) { return false; }
        return LegacyMenuManager::isEnabled();
    }

    /**
     * Get menu manager configuration.
     */
    public static function getConfig()
    {
        if (!class_exists(LegacyMenuManager::class)) { return []; }
        return LegacyMenuManager::getConfig();
    }

    /**
     * Get tenant module features.
     */
    public static function getTenantModuleFeatures($tenantId = null)
    {
        if (!class_exists(LegacyMenuManager::class)) { return []; }
        return LegacyMenuManager::getTenantModuleFeatures($tenantId);
    }
}
