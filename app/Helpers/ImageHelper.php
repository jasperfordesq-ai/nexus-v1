<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\ImageHelper as LegacyImageHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\ImageHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's image handling.
 */
class ImageHelper
{
    public static function webp(
        string $imagePath,
        string $alt = '',
        string $class = '',
        array $attributes = []
    ): string {
        if (!class_exists(LegacyImageHelper::class)) {
            return sprintf('<img src="%s" alt="%s"%s>', htmlspecialchars($imagePath), htmlspecialchars($alt), $class ? ' class="' . htmlspecialchars($class) . '"' : '');
        }
        return LegacyImageHelper::webp($imagePath, $alt, $class, $attributes);
    }

    public static function responsive(
        string $imagePath,
        string $alt = '',
        array $sizes = [320, 640, 1024, 1920],
        string $class = ''
    ): string {
        if (!class_exists(LegacyImageHelper::class)) {
            return sprintf('<img src="%s" alt="%s"%s>', htmlspecialchars($imagePath), htmlspecialchars($alt), $class ? ' class="' . htmlspecialchars($class) . '"' : '');
        }
        return LegacyImageHelper::responsive($imagePath, $alt, $sizes, $class);
    }

    /**
     * @return array|false
     */
    public static function getDimensions(string $imagePath)
    {
        if (!class_exists(LegacyImageHelper::class)) { return false; }
        return LegacyImageHelper::getDimensions($imagePath);
    }

    public static function avatar(
        ?string $avatarPath,
        string $userName = 'User',
        int $size = 40,
        array $attributes = []
    ): string {
        if (!class_exists(LegacyImageHelper::class)) {
            $src = $avatarPath ?: '/assets/img/defaults/default_avatar.png';
            return sprintf('<img src="%s" alt="%s" width="%d" height="%d">', htmlspecialchars($src), htmlspecialchars($userName), $size, $size);
        }
        return LegacyImageHelper::avatar($avatarPath, $userName, $size, $attributes);
    }

    public static function browserSupportsWebP(): bool
    {
        if (!class_exists(LegacyImageHelper::class)) { return false; }
        return LegacyImageHelper::browserSupportsWebP();
    }
}
