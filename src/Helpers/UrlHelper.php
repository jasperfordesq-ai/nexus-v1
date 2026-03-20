<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\UrlHelper as AppUrlHelper;

/**
 * Legacy delegate — real implementation is now in App\Helpers\UrlHelper.
 *
 * @deprecated Use App\Helpers\UrlHelper directly.
 */
class UrlHelper
{
    public static function safeRedirect(?string $url, string $fallback = '/dashboard'): string
    {
        return AppUrlHelper::safeRedirect($url, $fallback);
    }

    public static function safeReferer(string $fallback = '/dashboard'): string
    {
        return AppUrlHelper::safeReferer($fallback);
    }

    public static function isAllowedHost(string $host): bool
    {
        return AppUrlHelper::isAllowedHost($host);
    }

    public static function addAllowedHost(string $host): void
    {
        AppUrlHelper::addAllowedHost($host);
    }

    public static function getBaseUrl(): string
    {
        return AppUrlHelper::getBaseUrl();
    }

    public static function absolute(?string $url): ?string
    {
        return AppUrlHelper::absolute($url);
    }

    public static function absoluteAvatar(?string $avatarUrl, string $default = '/assets/img/defaults/default_avatar.png'): string
    {
        return AppUrlHelper::absoluteAvatar($avatarUrl, $default);
    }

    public static function absoluteAll(array $urls): array
    {
        return AppUrlHelper::absoluteAll($urls);
    }
}
