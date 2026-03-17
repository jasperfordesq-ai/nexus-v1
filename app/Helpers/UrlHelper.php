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
    /**
     * Validate and sanitize a redirect URL to prevent open redirect attacks.
     */
    public static function safeRedirect(?string $url, string $fallback = '/dashboard'): string
    {
        return LegacyUrlHelper::safeRedirect($url, $fallback);
    }

    /**
     * Get safe redirect URL from HTTP_REFERER.
     */
    public static function safeReferer(string $fallback = '/dashboard'): string
    {
        return LegacyUrlHelper::safeReferer($fallback);
    }

    /**
     * Check if a host is in the allowed list.
     */
    public static function isAllowedHost(string $host): bool
    {
        return LegacyUrlHelper::isAllowedHost($host);
    }

    /**
     * Add a host to the allowed list dynamically.
     */
    public static function addAllowedHost(string $host): void
    {
        LegacyUrlHelper::addAllowedHost($host);
    }

    /**
     * Get the base URL for the current tenant/request.
     */
    public static function getBaseUrl(): string
    {
        return LegacyUrlHelper::getBaseUrl();
    }

    /**
     * Convert a relative URL to an absolute URL.
     */
    public static function absolute(?string $url): ?string
    {
        return LegacyUrlHelper::absolute($url);
    }

    /**
     * Convert avatar URL to absolute, with fallback to default avatar.
     */
    public static function absoluteAvatar(?string $avatarUrl, string $default = '/assets/img/defaults/default_avatar.png'): string
    {
        return LegacyUrlHelper::absoluteAvatar($avatarUrl, $default);
    }

    /**
     * Convert an array of URLs to absolute URLs.
     */
    public static function absoluteAll(array $urls): array
    {
        return LegacyUrlHelper::absoluteAll($urls);
    }
}
