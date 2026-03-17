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
    /** Menu location constants */
    const LOCATION_HEADER_MAIN = LegacyMenuManager::LOCATION_HEADER_MAIN;
    const LOCATION_HEADER_SECONDARY = LegacyMenuManager::LOCATION_HEADER_SECONDARY;
    const LOCATION_FOOTER = LegacyMenuManager::LOCATION_FOOTER;
    const LOCATION_SIDEBAR = LegacyMenuManager::LOCATION_SIDEBAR;
    const LOCATION_MOBILE = LegacyMenuManager::LOCATION_MOBILE;

    /** Legacy support */
    const MENU_ABOUT = LegacyMenuManager::MENU_ABOUT;
    const MENU_MAIN = LegacyMenuManager::MENU_MAIN;
    const MENU_FOOTER = LegacyMenuManager::MENU_FOOTER;

    /**
     * Get menu for a specific location and layout.
     */
    public static function getMenu($location, $layout = null, $useCache = true)
    {
        return LegacyMenuManager::getMenu($location, $layout, $useCache);
    }

    /**
     * Get a single menu by slug.
     */
    public static function getMenuBySlug($slug, $layout = null, $useCache = true)
    {
        return LegacyMenuManager::getMenuBySlug($slug, $layout, $useCache);
    }

    /**
     * Render menu as HTML.
     */
    public static function renderMenu($location, $layout = null, $cssClass = 'menu')
    {
        return LegacyMenuManager::renderMenu($location, $layout, $cssClass);
    }

    /**
     * Clear menu cache for a tenant.
     */
    public static function clearCache($tenantId = null)
    {
        return LegacyMenuManager::clearCache($tenantId);
    }

    /**
     * Legacy method: render about menu.
     */
    public static function renderAboutMenu()
    {
        return LegacyMenuManager::renderAboutMenu();
    }

    /**
     * Legacy method: get main nav pages.
     */
    public static function getMainNavPages()
    {
        return LegacyMenuManager::getMainNavPages();
    }

    /**
     * Legacy method: get footer pages.
     */
    public static function getFooterPages()
    {
        return LegacyMenuManager::getFooterPages();
    }

    /**
     * Legacy method: check if menu pages exist for location.
     */
    public static function hasMenuPages($location)
    {
        return LegacyMenuManager::hasMenuPages($location);
    }

    /**
     * Check if menu manager is enabled.
     */
    public static function isEnabled()
    {
        return LegacyMenuManager::isEnabled();
    }

    /**
     * Get menu manager configuration.
     */
    public static function getConfig()
    {
        return LegacyMenuManager::getConfig();
    }

    /**
     * Get tenant module features.
     */
    public static function getTenantModuleFeatures($tenantId = null)
    {
        return LegacyMenuManager::getTenantModuleFeatures($tenantId);
    }
}
