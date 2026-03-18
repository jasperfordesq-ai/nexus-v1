<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\UrlHelper as LegacyUrlHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\UrlHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's URL helpers.
 */
class UrlHelper
{
    public static function safeRedirect(?string $url, string $fallback = '/dashboard'): string
    {
        if (!class_exists(LegacyUrlHelper::class)) { return $fallback; }
        return LegacyUrlHelper::safeRedirect($url, $fallback);
    }

    public static function safeReferer(string $fallback = '/dashboard'): string
    {
        if (!class_exists(LegacyUrlHelper::class)) { return $fallback; }
        return LegacyUrlHelper::safeReferer($fallback);
    }

    public static function isAllowedHost(string $host): bool
    {
        if (!class_exists(LegacyUrlHelper::class)) { return false; }
        return LegacyUrlHelper::isAllowedHost($host);
    }

    public static function addAllowedHost(string $host): void
    {
        if (!class_exists(LegacyUrlHelper::class)) { return; }
        LegacyUrlHelper::addAllowedHost($host);
    }

    public static function getBaseUrl(): string
    {
        if (!class_exists(LegacyUrlHelper::class)) { return ''; }
        return LegacyUrlHelper::getBaseUrl();
    }

    public static function absolute(?string $url): ?string
    {
        if (!class_exists(LegacyUrlHelper::class)) { return $url; }
        return LegacyUrlHelper::absolute($url);
    }

    public static function absoluteAvatar(?string $avatarUrl, string $default = '/assets/img/defaults/default_avatar.png'): string
    {
        if (!class_exists(LegacyUrlHelper::class)) { return $avatarUrl ?? $default; }
        return LegacyUrlHelper::absoluteAvatar($avatarUrl, $default);
    }

    public static function absoluteAll(array $urls): array
    {
        if (!class_exists(LegacyUrlHelper::class)) { return $urls; }
        return LegacyUrlHelper::absoluteAll($urls);
    }
}
