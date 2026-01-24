<?php

namespace Nexus\Admin;

/**
 * WebP Converter for Admin Panel
 *
 * Provides automatic WebP conversion functionality in the admin panel
 */
class WebPConverter
{
    private string $baseDir;
    private array $searchPaths;
    private int $quality = 85;

    public function __construct()
    {
        $this->baseDir = dirname(__DIR__, 2);
        $this->searchPaths = [
            $this->baseDir . '/httpdocs/assets/img',
            $this->baseDir . '/httpdocs/uploads'
        ];
    }

    /**
     * Check if cwebp command is available
     *
     * @return bool
     */
    public function isCwebpAvailable(): bool
    {
        // Try to execute cwebp -version
        $output = [];
        $returnCode = 0;
        @exec('cwebp -version 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get installation instructions for cwebp
     *
     * @return string
     */
    public function getInstallInstructions(): string
    {
        return "To enable WebP conversion, install cwebp:\n\n" .
               "Ubuntu/Debian: sudo apt-get install webp\n" .
               "CentOS/RHEL:   sudo yum install libwebp-tools\n" .
               "macOS:         brew install webp\n" .
               "Windows:       choco install webp";
    }

    /**
     * Convert a single image to WebP
     *
     * @param string $imagePath Full path to image file
     * @param bool $overwrite Overwrite existing WebP file
     * @return array Result with 'success', 'message', 'original_size', 'webp_size', 'savings'
     */
    public function convertImage(string $imagePath, bool $overwrite = false): array
    {
        // Check if file exists
        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'message' => 'File not found',
                'file' => $imagePath
            ];
        }

        // Check if it's a valid image type
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            return [
                'success' => false,
                'message' => 'Invalid file type (must be JPG or PNG)',
                'file' => $imagePath
            ];
        }

        // Generate WebP path
        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imagePath);

        // Check if WebP already exists
        if (!$overwrite && file_exists($webpPath) && filemtime($webpPath) > filemtime($imagePath)) {
            return [
                'success' => false,
                'message' => 'WebP already exists and is up to date',
                'file' => $imagePath,
                'skipped' => true
            ];
        }

        // Get original size
        $originalSize = filesize($imagePath);

        // Execute cwebp with properly escaped paths to prevent command injection
        $command = sprintf(
            'cwebp -q %d %s -o %s 2>&1',
            $this->quality,
            escapeshellarg($imagePath),
            escapeshellarg($webpPath)
        );

        $output = [];
        $returnCode = 0;
        @exec($command, $output, $returnCode);

        // Check if conversion succeeded
        if ($returnCode !== 0 || !file_exists($webpPath)) {
            return [
                'success' => false,
                'message' => 'Conversion failed: ' . implode("\n", $output),
                'file' => $imagePath
            ];
        }

        // Get WebP size
        $webpSize = filesize($webpPath);
        $savings = round((($originalSize - $webpSize) / $originalSize) * 100, 1);

        return [
            'success' => true,
            'message' => 'Converted successfully',
            'file' => $imagePath,
            'webp_file' => $webpPath,
            'original_size' => $originalSize,
            'webp_size' => $webpSize,
            'savings' => $savings,
            'savings_bytes' => $originalSize - $webpSize
        ];
    }

    /**
     * Convert all images in configured directories
     *
     * @param callable|null $progressCallback Called with progress updates
     * @return array Summary with 'converted', 'skipped', 'failed', 'total_savings', etc.
     */
    public function convertAll(?callable $progressCallback = null): array
    {
        $results = [
            'converted' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_original_size' => 0,
            'total_webp_size' => 0,
            'total_savings' => 0,
            'files' => []
        ];

        foreach ($this->searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            // Find all images
            $images = $this->findImages($searchPath);

            foreach ($images as $imagePath) {
                $result = $this->convertImage($imagePath);

                if ($result['success']) {
                    $results['converted']++;
                    $results['total_original_size'] += $result['original_size'];
                    $results['total_webp_size'] += $result['webp_size'];
                    $results['total_savings'] += $result['savings_bytes'];
                } elseif (isset($result['skipped'])) {
                    $results['skipped']++;
                } else {
                    $results['failed']++;
                }

                $results['files'][] = $result;

                // Call progress callback
                if ($progressCallback) {
                    $progressCallback($result);
                }
            }
        }

        return $results;
    }

    /**
     * Find all images in a directory recursively
     *
     * @param string $directory
     * @return array Array of full file paths
     */
    private function findImages(string $directory): array
    {
        $images = [];
        $extensions = ['jpg', 'jpeg', 'png'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $extensions)) {
                    $images[] = $file->getPathname();
                }
            }
        }

        return $images;
    }

    /**
     * Get statistics about WebP conversion coverage
     *
     * @return array Stats with counts of images, webp files, coverage percentage
     */
    public function getStats(): array
    {
        $stats = [
            'total_images' => 0,
            'total_webp' => 0,
            'missing_webp' => 0,
            'coverage_percent' => 0,
            'potential_savings' => 0
        ];

        foreach ($this->searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $images = $this->findImages($searchPath);
            $stats['total_images'] += count($images);

            foreach ($images as $imagePath) {
                $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imagePath);

                if (file_exists($webpPath)) {
                    $stats['total_webp']++;

                    // Calculate actual savings
                    $originalSize = filesize($imagePath);
                    $webpSize = filesize($webpPath);
                    $stats['potential_savings'] += ($originalSize - $webpSize);
                } else {
                    $stats['missing_webp']++;

                    // Estimate potential savings (assume 60% reduction)
                    $stats['potential_savings'] += filesize($imagePath) * 0.6;
                }
            }
        }

        if ($stats['total_images'] > 0) {
            $stats['coverage_percent'] = round(($stats['total_webp'] / $stats['total_images']) * 100, 1);
        }

        return $stats;
    }

    /**
     * Convert image on upload (hook this into your upload handler)
     *
     * @param string $uploadedFilePath Path to newly uploaded file
     * @return array Result of conversion
     */
    public function convertOnUpload(string $uploadedFilePath): array
    {
        // Only convert if it's an image (use finfo instead of deprecated mime_content_type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($uploadedFilePath);
        if (!str_starts_with($mimeType, 'image/')) {
            return ['success' => false, 'message' => 'Not an image'];
        }

        // Only convert JPG/PNG
        $ext = strtolower(pathinfo($uploadedFilePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            return ['success' => false, 'message' => 'Not JPG/PNG'];
        }

        return $this->convertImage($uploadedFilePath, true);
    }

    /**
     * Set quality for conversions
     *
     * @param int $quality Quality 0-100 (85 recommended)
     */
    public function setQuality(int $quality): void
    {
        $this->quality = max(0, min(100, $quality));
    }

    /**
     * Get list of images that need WebP conversion
     *
     * @return array Array of full file paths for images missing WebP versions
     */
    public function getPendingImages(): array
    {
        $pending = [];

        foreach ($this->searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $images = $this->findImages($searchPath);

            foreach ($images as $imagePath) {
                $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imagePath);

                // Include if WebP doesn't exist or is older than the original
                if (!file_exists($webpPath) || filemtime($webpPath) < filemtime($imagePath)) {
                    $pending[] = $imagePath;
                }
            }
        }

        return $pending;
    }

    /**
     * Get list of oversized images that exceed the max dimension
     *
     * @param int $maxDimension Maximum width or height in pixels
     * @return array Array of ['path' => string, 'width' => int, 'height' => int, 'size' => int]
     */
    public function getOversizedImages(int $maxDimension = 1920): array
    {
        $oversized = [];

        foreach ($this->searchPaths as $searchPath) {
            if (!is_dir($searchPath)) {
                continue;
            }

            $images = $this->findAllImages($searchPath);

            foreach ($images as $imagePath) {
                $info = @getimagesize($imagePath);
                if (!$info) {
                    continue;
                }

                $width = $info[0];
                $height = $info[1];

                if ($width > $maxDimension || $height > $maxDimension) {
                    $oversized[] = [
                        'path' => $imagePath,
                        'width' => $width,
                        'height' => $height,
                        'size' => filesize($imagePath)
                    ];
                }
            }
        }

        return $oversized;
    }

    /**
     * Get statistics about oversized images
     *
     * @param int $maxDimension Maximum dimension threshold
     * @return array Stats with count, total size, etc.
     */
    public function getOversizedStats(int $maxDimension = 1920): array
    {
        $oversized = $this->getOversizedImages($maxDimension);

        $totalSize = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($oversized as $img) {
            $totalSize += $img['size'];
            $maxWidth = max($maxWidth, $img['width']);
            $maxHeight = max($maxHeight, $img['height']);
        }

        return [
            'count' => count($oversized),
            'total_size' => $totalSize,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight,
            'threshold' => $maxDimension
        ];
    }

    /**
     * Resize a single oversized image
     *
     * @param string $imagePath Full path to image
     * @param int $maxDimension Maximum dimension
     * @return array Result with success, original/new dimensions, savings
     */
    public function resizeImage(string $imagePath, int $maxDimension = 1920): array
    {
        if (!file_exists($imagePath)) {
            return ['success' => false, 'message' => 'File not found'];
        }

        if (!function_exists('imagecreatefromjpeg')) {
            return ['success' => false, 'message' => 'GD library not available'];
        }

        $info = @getimagesize($imagePath);
        if (!$info) {
            return ['success' => false, 'message' => 'Could not read image'];
        }

        $srcWidth = $info[0];
        $srcHeight = $info[1];
        $mime = $info['mime'];

        // Check if resize is needed
        if ($srcWidth <= $maxDimension && $srcHeight <= $maxDimension) {
            return [
                'success' => false,
                'message' => 'Image already within limits',
                'skipped' => true
            ];
        }

        // Calculate new dimensions
        if ($srcWidth > $srcHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int)($srcHeight * ($maxDimension / $srcWidth));
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int)($srcWidth * ($maxDimension / $srcHeight));
        }

        // Load image
        switch ($mime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($imagePath);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($imagePath);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($imagePath);
                break;
            default:
                return ['success' => false, 'message' => 'Unsupported image type'];
        }

        if (!$image) {
            return ['success' => false, 'message' => 'Failed to load image'];
        }

        $originalSize = filesize($imagePath);

        // Create resized image
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        // Save back
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($resized, $imagePath, 90);
                break;
            case 'image/png':
                imagepng($resized, $imagePath);
                break;
            case 'image/webp':
                imagewebp($resized, $imagePath, 90);
                break;
            case 'image/gif':
                imagegif($resized, $imagePath);
                break;
        }

        imagedestroy($image);
        imagedestroy($resized);

        clearstatcache(true, $imagePath);
        $newSize = filesize($imagePath);
        $savings = $originalSize - $newSize;
        $savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 1) : 0;

        // Also update WebP version if it exists
        $webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $imagePath);
        if (file_exists($webpPath)) {
            $this->convertImage($imagePath, true);
        }

        return [
            'success' => true,
            'message' => 'Resized successfully',
            'file' => $imagePath,
            'original_width' => $srcWidth,
            'original_height' => $srcHeight,
            'new_width' => $newWidth,
            'new_height' => $newHeight,
            'original_size' => $originalSize,
            'new_size' => $newSize,
            'savings' => $savings,
            'savings_percent' => $savingsPercent
        ];
    }

    /**
     * Find all images (including WebP) in a directory recursively
     *
     * @param string $directory
     * @return array Array of full file paths
     */
    private function findAllImages(string $directory): array
    {
        $images = [];
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $extensions)) {
                    $images[] = $file->getPathname();
                }
            }
        }

        return $images;
    }
}
