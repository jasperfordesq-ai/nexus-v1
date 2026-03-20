<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\ImageHelper as AppImageHelper;

/**
 * Legacy delegate — real implementation is now in App\Helpers\ImageHelper.
 *
 * @deprecated Use App\Helpers\ImageHelper directly.
 */
class ImageHelper
{
    public static function webp(
        string $imagePath,
        string $alt = '',
        string $class = '',
        array $attributes = []
    ): string {
        return AppImageHelper::webp($imagePath, $alt, $class, $attributes);
    }

    public static function responsive(
        string $imagePath,
        string $alt = '',
        array $sizes = [320, 640, 1024, 1920],
        string $class = ''
    ): string {
        return AppImageHelper::responsive($imagePath, $alt, $sizes, $class);
    }

    /**
     * @return array|false
     */
    public static function getDimensions(string $imagePath)
    {
        return AppImageHelper::getDimensions($imagePath);
    }

    public static function avatar(
        ?string $avatarPath,
        string $userName = 'User',
        int $size = 40,
        array $attributes = []
    ): string {
        return AppImageHelper::avatar($avatarPath, $userName, $size, $attributes);
    }

    public static function browserSupportsWebP(): bool
    {
        return AppImageHelper::browserSupportsWebP();
    }
}
