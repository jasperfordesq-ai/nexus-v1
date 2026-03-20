<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\NavigationConfig as AppNavigationConfig;

/**
 * Legacy delegate — real implementation is now in App\Helpers\NavigationConfig.
 *
 * @deprecated Use App\Helpers\NavigationConfig directly.
 */
class NavigationConfig
{
    public static function getPrimaryNav(): array
    {
        return AppNavigationConfig::getPrimaryNav();
    }

    public static function getCommunityNav(): array
    {
        return AppNavigationConfig::getCommunityNav();
    }

    public static function getExploreNav(): array
    {
        return AppNavigationConfig::getExploreNav();
    }

    public static function getAboutNav(): array
    {
        return AppNavigationConfig::getAboutNav();
    }

    public static function getHelpNav(): array
    {
        return AppNavigationConfig::getHelpNav();
    }

    public static function getSecondaryNav(): array
    {
        return AppNavigationConfig::getSecondaryNav();
    }

    public static function getFlatSecondaryNav(): array
    {
        return AppNavigationConfig::getFlatSecondaryNav();
    }

    public static function getGamificationNav(): array
    {
        return AppNavigationConfig::getGamificationNav();
    }

    public static function getCustomPages(?string $layout = null): array
    {
        return AppNavigationConfig::getCustomPages($layout);
    }

    public static function getMenuPages(string $location = 'main'): array
    {
        return AppNavigationConfig::getMenuPages($location);
    }
}
