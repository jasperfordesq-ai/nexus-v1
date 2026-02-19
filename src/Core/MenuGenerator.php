<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class MenuGenerator
{
    /**
     * Menu location constants
     */
    const MENU_ABOUT = 'about';
    const MENU_MAIN = 'main';
    const MENU_FOOTER = 'footer';
    const MENU_NONE = 'none';

    /**
     * Get pages for a specific menu location.
     *
     * @param string $location Menu location (about, main, footer)
     * @return array List of pages with title, slug, url
     */
    public static function getMenuPages(string $location = self::MENU_ABOUT): array
    {
        try {
            $tenantId = TenantContext::getId();
            if (!$tenantId) {
                return [];
            }

            // Check if page builder module is enabled (gracefully handle missing column)
            try {
                $tenant = Database::query("SELECT module_page_builder_enabled FROM tenants WHERE id = ?", [$tenantId])->fetch();
                if ($tenant && empty($tenant['module_page_builder_enabled'])) {
                    return []; // Module explicitly disabled
                }
            } catch (\Exception $e) {
                // Column doesn't exist - assume page builder is enabled by default
            }

            $basePath = TenantContext::getBasePath();

            // Fetch published pages for the specified menu location
            // Gracefully handle missing columns (show_in_menu, menu_location) for backwards compatibility
            try {
                $pages = Database::query(
                    "SELECT title, slug FROM pages
                     WHERE tenant_id = ?
                     AND is_published = 1
                     AND show_in_menu = 1
                     AND menu_location = ?
                     ORDER BY sort_order ASC, title ASC",
                    [$tenantId, $location]
                )->fetchAll();
            } catch (\Exception $e) {
                // Fallback: columns don't exist yet, show all published pages in About
                if ($location === self::MENU_ABOUT) {
                    $pages = Database::query(
                        "SELECT title, slug FROM pages
                         WHERE tenant_id = ? AND is_published = 1
                         ORDER BY sort_order ASC, title ASC",
                        [$tenantId]
                    )->fetchAll();
                } else {
                    $pages = [];
                }
            }

            // Add full URL to each page
            foreach ($pages as &$page) {
                $page['url'] = $basePath . '/page/' . $page['slug'];
            }

            return $pages;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Renders the "About" dropdown menu for the current tenant.
     * Returns empty string if module disabled or no pages found.
     */
    public static function renderAboutMenu()
    {
        try {
            // 1. Get Current Context
            $tenantId = TenantContext::getId();
            if (!$tenantId) {
                return ''; // Master Dashboard doesn't use this logic
            }

            // 2. Fetch Pages for About menu (getMenuPages handles module check internally)
            $pages = self::getMenuPages(self::MENU_ABOUT);

            if (empty($pages)) {
                return '';
            }

            // 4. Render HTML
            $basePath = TenantContext::getBasePath();
            $html = '<li>';
            $html .= '<details role="list" dir="rtl">';
            $html .= '<summary aria-haspopup="listbox" role="link">About</summary>';
            $html .= '<ul role="listbox">';

            foreach ($pages as $page) {
                $slug = htmlspecialchars($page['slug']);
                $title = htmlspecialchars($page['title']);
                $html .= "<li><a href=\"{$basePath}/page/{$slug}\">{$title}</a></li>";
            }

            $html .= '</ul>';
            $html .= '</details>';
            $html .= '</li>';

            return $html;
        } catch (\Exception $e) {
            // Graceful failure (table doesn't exist yet, etc.)
            return '';
        }
    }

    /**
     * Get pages for the main navigation bar.
     *
     * @return array List of pages
     */
    public static function getMainNavPages(): array
    {
        return self::getMenuPages(self::MENU_MAIN);
    }

    /**
     * Get pages for the footer.
     *
     * @return array List of pages
     */
    public static function getFooterPages(): array
    {
        return self::getMenuPages(self::MENU_FOOTER);
    }

    /**
     * Check if there are any pages for a menu location.
     *
     * @param string $location Menu location
     * @return bool
     */
    public static function hasMenuPages(string $location = self::MENU_ABOUT): bool
    {
        return !empty(self::getMenuPages($location));
    }
}
