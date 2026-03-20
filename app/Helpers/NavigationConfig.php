<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\NavigationConfig as LegacyNavigationConfig;

/**
 * App-namespace wrapper for Nexus\Helpers\NavigationConfig.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with a Laravel-native navigation config.
 */
class NavigationConfig
{
    public static function getPrimaryNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getPrimaryNav();
    }

    public static function getCommunityNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getCommunityNav();
    }

    public static function getExploreNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getExploreNav();
    }

    public static function getAboutNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getAboutNav();
    }

    public static function getHelpNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getHelpNav();
    }

    public static function getSecondaryNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getSecondaryNav();
    }

    public static function getFlatSecondaryNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getFlatSecondaryNav();
    }

    public static function getGamificationNav(): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getGamificationNav();
    }

    public static function getCustomPages(?string $layout = null): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getCustomPages($layout);
    }

    public static function getMenuPages(string $location = 'main'): array
    {
        if (!class_exists(LegacyNavigationConfig::class)) { return []; }
        return LegacyNavigationConfig::getMenuPages($location);
    }
}
