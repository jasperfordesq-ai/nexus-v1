<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards every call to App\Core\MenuManager.
 *
 * @deprecated Use App\Core\MenuManager directly. Kept for backward compatibility.
 */
class MenuManager
{
    /** Menu location constants */
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
        return \App\Core\MenuManager::getMenu($location, $layout, $useCache);
    }

    /**
     * Get a single menu by slug.
     */
    public static function getMenuBySlug($slug, $layout = null, $useCache = true)
    {
        return \App\Core\MenuManager::getMenuBySlug($slug, $layout, $useCache);
    }

    /**
     * Render menu as HTML.
     */
    public static function renderMenu($location, $layout = null, $cssClass = 'menu')
    {
        return \App\Core\MenuManager::renderMenu($location, $layout, $cssClass);
    }

    /**
     * Clear menu cache for a tenant.
     */
    public static function clearCache($tenantId = null)
    {
        return \App\Core\MenuManager::clearCache($tenantId);
    }

    /**
     * Render the "About" dropdown menu HTML.
     */
    public static function renderAboutMenu()
    {
        return \App\Core\MenuManager::renderAboutMenu();
    }

    /**
     * Get pages for the main navigation bar.
     */
    public static function getMainNavPages()
    {
        return \App\Core\MenuManager::getMainNavPages();
    }

    /**
     * Get pages for the footer.
     */
    public static function getFooterPages()
    {
        return \App\Core\MenuManager::getFooterPages();
    }

    /**
     * Check if there are any pages for a menu location.
     */
    public static function hasMenuPages($location)
    {
        return \App\Core\MenuManager::hasMenuPages($location);
    }

    /**
     * Get pages from the pages table for a specific menu location.
     */
    public static function getMenuPages(string $location = 'about'): array
    {
        return \App\Core\MenuManager::getMenuPages($location);
    }

    /**
     * Check if menu manager is enabled.
     */
    public static function isEnabled()
    {
        return \App\Core\MenuManager::isEnabled();
    }

    /**
     * Get menu manager configuration.
     */
    public static function getConfig()
    {
        return \App\Core\MenuManager::getConfig();
    }

    /**
     * Get tenant module features.
     */
    public static function getTenantModuleFeatures($tenantId = null)
    {
        return \App\Core\MenuManager::getTenantModuleFeatures($tenantId);
    }

    /**
     * Get default platform menu for a specific location.
     */
    public static function getDefaultMenu($location, $basePath = '')
    {
        return \App\Core\MenuManager::getDefaultMenu($location, $basePath);
    }

    /**
     * Check if default menus should be used for a location.
     */
    public static function shouldUseDefault($location, $tenantId = null)
    {
        return \App\Core\MenuManager::shouldUseDefault($location, $tenantId);
    }
}
