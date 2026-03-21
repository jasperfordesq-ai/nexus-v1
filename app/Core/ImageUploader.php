<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use App\Admin\WebPConverter;

/**
 * Image upload handler with resizing, cropping, and WebP conversion.
 * Direct implementation replacing Nexus\Core\ImageUploader delegation.
 */
class ImageUploader
{
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private static $maxSize = 8 * 1024 * 1024; // 8MB
    private static $autoConvertWebP = true;
    private static $maxDimension = 1920;

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
        if (empty($file['name'])) return null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Upload Error Code: " . $file['error']);
        }

        // Validation - Extension whitelist
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = \strtolower(\pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!\in_array($extension, $allowedExtensions)) {
            throw new \Exception("Invalid file extension. Only JPG, PNG, GIF, WEBP allowed.");
        }

        // Validation - MIME type check using file content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        if (!in_array($detectedMime, self::$allowedTypes)) {
            throw new \Exception("Invalid file type. File content does not match allowed image types.");
        }

        // Validation - Verify it's actually an image
        $imageInfo = @\getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new \Exception("File is not a valid image.");
        }
        if ($file['size'] > self::$maxSize) {
            throw new \Exception("File too large. Max 8MB.");
        }

        // Generate secure filename
        $filename = \bin2hex(\random_bytes(16)) . '.' . $extension;

        // Tenant Scoping
        $tenant = TenantContext::get();
        $slug = $tenant['slug'] ?? 'default';
        if ($tenant['id'] == 1 && empty($tenant['slug'])) {
            $slug = 'master';
        }

        // Tenant-scoped directory
        $tenantDir = 'tenants/' . $slug . '/' . $directory;

        // Physical Path
        $targetDir = __DIR__ . '/../../httpdocs/uploads/' . $tenantDir;

        if (!\is_dir($targetDir)) {
            \mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . $tenantDir . '/' . $filename;

        // Move file
        if (!\move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception("Failed to save file.");
        }

        // Auto-resize oversized images (unless explicit dimensions provided)
        if (empty($options['width']) && empty($options['crop'])) {
            self::autoResizeIfNeeded($targetPath);
        }

        // Process Image (Resize/Crop) - explicit options from caller
        if (!empty($options['crop']) || !empty($options['width'])) {
            self::processImage($targetPath, $options);
        }

        // Auto-convert to WebP for performance (JPG/PNG only)
        if (self::$autoConvertWebP && in_array($extension, ['jpg', 'jpeg', 'png'])) {
            self::convertToWebP($targetPath);
        }

        return $publicPath;
    }

    /**
     * Enable or disable automatic WebP conversion.
     */
    public static function setAutoConvertWebP(bool $enabled): void
    {
        self::$autoConvertWebP = $enabled;
    }

    /**
     * Set maximum dimension for auto-resize.
     *
     * @param int $maxDimension Maximum width or height in pixels
     */
    public static function setMaxDimension(int $maxDimension): void
    {
        self::$maxDimension = $maxDimension;
    }

    /**
     * Convert uploaded image to WebP format.
     */
    private static function convertToWebP(string $imagePath): ?array
    {
        try {
            if (!class_exists(WebPConverter::class)) { return null; }
            $converter = new WebPConverter();

            if (!$converter->isCwebpAvailable()) {
                return null;
            }

            $result = $converter->convertOnUpload($imagePath);

            if ($result['success']) {
                \error_log("WebP auto-converted: {$imagePath} (saved {$result['savings']}%)");
            }

            return $result;
        } catch (\Exception $e) {
            \error_log("WebP conversion failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Auto-resize image if it exceeds maximum dimensions.
     */
    private static function autoResizeIfNeeded(string $path): void
    {
        if (!\function_exists('imagecreatefromjpeg')) {
            return;
        }

        $info = @\getimagesize($path);
        if (!$info) {
            return;
        }

        $srcWidth = $info[0];
        $srcHeight = $info[1];
        $maxDim = self::$maxDimension;

        if ($srcWidth <= $maxDim && $srcHeight <= $maxDim) {
            return;
        }

        if ($srcWidth > $srcHeight) {
            $newWidth = $maxDim;
            $newHeight = (int)($srcHeight * ($maxDim / $srcWidth));
        } else {
            $newHeight = $maxDim;
            $newWidth = (int)($srcWidth * ($maxDim / $srcHeight));
        }

        self::processImage($path, [
            'width' => $newWidth,
            'height' => $newHeight
        ]);

        \error_log("Auto-resized image: {$srcWidth}x{$srcHeight} -> {$newWidth}x{$newHeight}");
    }

    private static function processImage($path, $options)
    {
        if (!\function_exists('imagecreatefromjpeg')) {
            \error_log('Warning: GD library not available. Image uploaded without resizing/cropping.');
            return;
        }

        $info = \getimagesize($path);
        if (!$info) return;

        $mime = $info['mime'];
        $srcWidth = $info[0];
        $srcHeight = $info[1];

        switch ($mime) {
            case 'image/jpeg':
                $image = \imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $image = \imagecreatefrompng($path);
                break;
            case 'image/webp':
                $image = \imagecreatefromwebp($path);
                break;
            case 'image/gif':
                $image = \imagecreatefromgif($path);
                break;
            default:
                return;
        }

        $targetWidth = $options['width'] ?? $srcWidth;
        $targetHeight = $options['height'] ?? ($options['crop'] ? $targetWidth : $srcHeight);

        if (!empty($options['crop'])) {
            $thumbRatio = $targetWidth / $targetHeight;
            $srcRatio = $srcWidth / $srcHeight;

            if ($srcRatio > $thumbRatio) {
                $newHeight = $srcHeight;
                $newWidth = (int)($srcHeight * $thumbRatio);
            } else {
                $newWidth = $srcWidth;
                $newHeight = (int)($srcWidth / $thumbRatio);
            }

            $xOffset = ($srcWidth - $newWidth) / 2;
            $yOffset = ($srcHeight - $newHeight) / 2;

            $thumb = \imagecreatetruecolor($targetWidth, $targetHeight);

            if ($mime == 'image/png' || $mime == 'image/webp') {
                \imagealphablending($thumb, false);
                \imagesavealpha($thumb, true);
            }

            \imagecopyresampled($thumb, $image, 0, 0, $xOffset, $yOffset, $targetWidth, $targetHeight, $newWidth, $newHeight);
            $image = $thumb;
        } elseif (isset($options['width'])) {
            $ratio = $srcWidth / $srcHeight;
            if ($targetWidth / $targetHeight > $ratio) {
                $targetWidth = $targetHeight * $ratio;
            } else {
                $targetHeight = $targetWidth / $ratio;
            }

            $thumb = \imagecreatetruecolor($targetWidth, $targetHeight);
            if ($mime == 'image/png' || $mime == 'image/webp') {
                \imagealphablending($thumb, false);
                \imagesavealpha($thumb, true);
            }
            \imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
            $image = $thumb;
        }

        switch ($mime) {
            case 'image/jpeg':
                \imagejpeg($image, $path, 90);
                break;
            case 'image/png':
                \imagepng($image, $path);
                break;
            case 'image/webp':
                \imagewebp($image, $path);
                break;
            case 'image/gif':
                \imagegif($image, $path);
                break;
        }

        \imagedestroy($image);
    }
}
