<?php

namespace Nexus\Core;

use Nexus\Admin\WebPConverter;

class ImageUploader
{
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private static $maxSize = 8 * 1024 * 1024; // 8MB
    private static $autoConvertWebP = true; // Auto-convert uploads to WebP
    private static $maxDimension = 1920; // Auto-resize images larger than this (width or height)

    /**
     * Upload an image with optional resizing/cropping
     * @param array $file $_FILES['input']
     * @param string $directory Subfolder
     * @param array $options ['crop' => true, 'width' => 200, 'height' => 200]
     */
    public static function upload($file, $directory = 'listings', $options = [])
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

        // Validation - MIME type check using file content (not user-provided header)
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

        // Generate secure filename using cryptographically secure random bytes
        $filename = \bin2hex(\random_bytes(16)) . '.' . $extension;

        // Tenant Scoping
        $tenant = \Nexus\Core\TenantContext::get();
        // If we are in a tenant context (and not Master ID 1, unless Master also uses tenants folder?), 
        // normally Master ID 1 might use root uploads, but for consistency let's check.
        // If separate folders per tenant are required:
        $slug = $tenant['slug'] ?? 'default';
        if ($tenant['id'] == 1 && empty($tenant['slug'])) {
            $slug = 'master'; // or keep default structure? Let's use 'tenants/master' or just root? 
            // The directory listing showed 'tenants', 'listings'.
            // If I move to tenants/slug/listings, it's cleaner.
        }

        // Override directory to include tenant path
        // Old: uploads/listings
        // New: uploads/tenants/{slug}/listings
        $tenantDir = 'tenants/' . $slug . '/' . $directory;

        // Physical Path (Relative to this file -> httpdocs/uploads)
        // Note: __DIR__ is src/Core. We need to go up to httpdocs.
        // src/Core/../../httpdocs/uploads is correct.
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
     * Convert uploaded image to WebP format
     * Creates a .webp version alongside the original
     *
     * @param string $imagePath Full path to the image
     * @return array|null Conversion result or null if unavailable
     */
    private static function convertToWebP(string $imagePath): ?array
    {
        try {
            $converter = new WebPConverter();

            if (!$converter->isCwebpAvailable()) {
                return null; // cwebp not installed, skip silently
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
     * Enable or disable automatic WebP conversion
     *
     * @param bool $enabled
     */
    public static function setAutoConvertWebP(bool $enabled): void
    {
        self::$autoConvertWebP = $enabled;
    }

    /**
     * Set maximum dimension for auto-resize
     *
     * @param int $maxDimension Maximum width or height in pixels
     */
    public static function setMaxDimension(int $maxDimension): void
    {
        self::$maxDimension = $maxDimension;
    }

    /**
     * Auto-resize image if it exceeds maximum dimensions
     * Maintains aspect ratio, resizes based on largest dimension
     *
     * @param string $path Full path to image file
     */
    private static function autoResizeIfNeeded(string $path): void
    {
        if (!\function_exists('imagecreatefromjpeg')) {
            return; // GD not available
        }

        $info = @\getimagesize($path);
        if (!$info) {
            return;
        }

        $srcWidth = $info[0];
        $srcHeight = $info[1];
        $maxDim = self::$maxDimension;

        // Check if resize is needed
        if ($srcWidth <= $maxDim && $srcHeight <= $maxDim) {
            return; // Image is within limits
        }

        // Calculate new dimensions maintaining aspect ratio
        if ($srcWidth > $srcHeight) {
            // Landscape: constrain by width
            $newWidth = $maxDim;
            $newHeight = (int)($srcHeight * ($maxDim / $srcWidth));
        } else {
            // Portrait or square: constrain by height
            $newHeight = $maxDim;
            $newWidth = (int)($srcWidth * ($maxDim / $srcHeight));
        }

        // Process the resize
        self::processImage($path, [
            'width' => $newWidth,
            'height' => $newHeight
        ]);

        \error_log("Auto-resized image: {$srcWidth}x{$srcHeight} -> {$newWidth}x{$newHeight}");
    }

    private static function processImage($path, $options)
    {
        // Check if GD library is available
        if (!\function_exists('imagecreatefromjpeg')) {
            // GD library not installed - skip image processing but allow upload
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

        // Intelligent Crop (Center) or Resize
        if (!empty($options['crop'])) {
            // Find center crop coordinates
            $thumbRatio = $targetWidth / $targetHeight;
            $srcRatio = $srcWidth / $srcHeight;

            if ($srcRatio > $thumbRatio) {
                // Source is wider
                $newHeight = $srcHeight;
                $newWidth = (int)($srcHeight * $thumbRatio);
            } else {
                // Source is taller
                $newWidth = $srcWidth;
                $newHeight = (int)($srcWidth / $thumbRatio);
            }

            $xOffset = ($srcWidth - $newWidth) / 2;
            $yOffset = ($srcHeight - $newHeight) / 2;

            $thumb = \imagecreatetruecolor($targetWidth, $targetHeight);

            // Transparency Support
            if ($mime == 'image/png' || $mime == 'image/webp') {
                \imagealphablending($thumb, false);
                \imagesavealpha($thumb, true);
            }

            // Crop and Resize
            \imagecopyresampled($thumb, $image, 0, 0, $xOffset, $yOffset, $targetWidth, $targetHeight, $newWidth, $newHeight);
            $image = $thumb;
        } elseif (isset($options['width'])) {
            // Simple Resize (Maintain Aspect Ratio)
            $ratio = $srcWidth / $srcHeight;
            if ($targetWidth / $targetHeight > $ratio) {
                $targetWidth = $targetHeight * $ratio;
            } else {
                $targetHeight = $targetWidth / $ratio;
            }

            $thumb = \imagecreatetruecolor($targetWidth, $targetHeight);
            // Transparency Support
            if ($mime == 'image/png' || $mime == 'image/webp') {
                \imagealphablending($thumb, false);
                \imagesavealpha($thumb, true);
            }
            \imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
            $image = $thumb;
        }

        // Save back
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
