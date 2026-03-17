<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\ImageUploader as LegacyImageUploader;

/**
 * App-namespace wrapper for Nexus\Core\ImageUploader.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's Storage facade / Intervention Image.
 */
class ImageUploader
{
    /**
     * Upload an image with optional resizing/cropping.
     *
     * @param array  $file      $_FILES['input'] array
     * @param string $directory Subfolder under uploads (e.g. 'listings', 'profiles')
     * @param array  $options   ['crop' => true, 'width' => 200, 'height' => 200]
     * @return string|null Public path to the uploaded file, or null on empty input
     * @throws \Exception On upload error or validation failure
     */
    public static function upload($file, $directory = 'listings', $options = []): ?string
    {
        return LegacyImageUploader::upload($file, $directory, $options);
    }

    /**
     * Enable or disable automatic WebP conversion.
     */
    public static function setAutoConvertWebP(bool $enabled): void
    {
        LegacyImageUploader::setAutoConvertWebP($enabled);
    }

    /**
     * Set maximum dimension for auto-resize.
     *
     * @param int $maxDimension Maximum width or height in pixels
     */
    public static function setMaxDimension(int $maxDimension): void
    {
        LegacyImageUploader::setMaxDimension($maxDimension);
    }
}
