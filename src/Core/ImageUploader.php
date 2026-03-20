<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards every call to App\Core\ImageUploader.
 *
 * @deprecated Use App\Core\ImageUploader directly. Kept for backward compatibility.
 */
class ImageUploader
{
    /**
     * Upload an image with optional resizing/cropping.
     *
     * @param array  $file      $_FILES['input']
     * @param string $directory Subfolder
     * @param array  $options   ['crop' => true, 'width' => 200, 'height' => 200]
     * @return string|null
     */
    public static function upload($file, $directory = 'listings', $options = [])
    {
        return \App\Core\ImageUploader::upload($file, $directory, $options);
    }

    /**
     * Enable or disable automatic WebP conversion.
     */
    public static function setAutoConvertWebP(bool $enabled): void
    {
        \App\Core\ImageUploader::setAutoConvertWebP($enabled);
    }

    /**
     * Set maximum dimension for auto-resize.
     */
    public static function setMaxDimension(int $maxDimension): void
    {
        \App\Core\ImageUploader::setMaxDimension($maxDimension);
    }
}
