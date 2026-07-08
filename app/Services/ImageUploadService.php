<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * ImageUploadService — Laravel DI-based service for image upload operations.
 *
 * Handles file uploads, deletion, and URL generation using Laravel's
 * Storage facade for disk-agnostic file management.
 */
class ImageUploadService
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB
    private const MAX_IMAGE_PIXELS = 24000000; // 24 MP cap prevents memory-heavy uploads.
    private const MAX_IMAGE_EDGE = 6000;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const THUMBNAIL_WIDTH = 640;
    private const THUMBNAIL_HEIGHT = 480;
    private const THUMBNAIL_QUALITY = 78;
    private const DERIVATIVE_SPECS = [
        ['name' => 'avatar', 'width' => 96, 'height' => 96, 'fit' => 'cover'],
        ['name' => 'card_small', 'width' => 320, 'height' => 180, 'fit' => 'cover'],
        ['name' => 'card', 'width' => 640, 'height' => 360, 'fit' => 'cover'],
        ['name' => 'square', 'width' => 640, 'height' => 640, 'fit' => 'cover'],
        ['name' => 'detail', 'width' => 1200, 'height' => 675, 'fit' => 'contain'],
    ];

    /**
     * Upload an image file.
     *
     * @return array{
     *   path: string,
     *   url: string,
     *   filename: string,
     *   width: int,
     *   height: int,
     *   thumbnail_path?: string,
     *   thumbnail_url?: string,
     *   variants?: array<string, array<string, array{path:string,url:string,width:int,height:int,format:string}>>,
     *   srcsets?: array<string, array<string, string>>
     * }
     * @throws \InvalidArgumentException
     */
    public function upload(UploadedFile $file, string $directory = 'uploads'): array
    {
        // Use filesize() on the temp path — SplFileInfo::getSize() can throw
        // ErrorException in Laravel if the Docker overlay FS loses the temp file
        $tmpPath = $file->getPathname();
        if (!file_exists($tmpPath)) {
            throw new \InvalidArgumentException('Upload failed: temporary file not found. Please try again.');
        }

        if (filesize($tmpPath) > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File exceeds maximum size of 10 MB.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Invalid file type. Allowed: JPEG, PNG, GIF, WebP.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || ($imageInfo[0] ?? 0) <= 0 || ($imageInfo[1] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Upload failed: image dimensions could not be read.');
        }
        $pixels = (int) $imageInfo[0] * (int) $imageInfo[1];
        if ($pixels > self::MAX_IMAGE_PIXELS || max((int) $imageInfo[0], (int) $imageInfo[1]) > self::MAX_IMAGE_EDGE) {
            throw new \InvalidArgumentException('Image dimensions are too large. Please upload an image no larger than 24 megapixels.');
        }

        // Scope uploads by tenant to prevent cross-tenant file access
        $tenantId = \App\Core\TenantContext::getId();
        $tenantDir = $tenantId ? "tenant_{$tenantId}/{$directory}" : $directory;

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($tenantDir, $filename, 'public');
        $thumbnail = $this->createThumbnail($path, $filename);
        $variants = $this->createVariants($path, $filename);

        $result = [
            'path'     => $path,
            'url'      => $this->getUrl($path),
            'filename' => $filename,
            'width'    => (int) $imageInfo[0],
            'height'   => (int) $imageInfo[1],
        ];

        if ($thumbnail !== null) {
            $result['thumbnail_path'] = $thumbnail['path'];
            $result['thumbnail_url'] = $thumbnail['url'];
        }
        if ($variants !== []) {
            $result['variants'] = $variants;
            $result['srcsets'] = $this->srcsets($variants);
        }

        return $result;
    }

    /**
     * Delete an image by its storage path.
     */
    public function delete(string $path): bool
    {
        if (empty($path) || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * List the current tenant's uploaded images, newest first — powers the
     * newsletter builder's asset library (browse + reuse, not just upload).
     *
     * @return array<int, array{path: string, url: string, name: string}>
     */
    public function listImages(int $limit = 60): array
    {
        $tenantId = \App\Core\TenantContext::getId();
        $base = $tenantId ? "tenant_{$tenantId}/uploads" : 'uploads';
        $disk = Storage::disk('public');

        if (! $disk->exists($base)) {
            return [];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $files = [];
        foreach ($disk->allFiles($base) as $path) {
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $allowed, true)) {
                continue;
            }
            $files[] = ['path' => $path, 'ts' => $disk->lastModified($path)];
        }

        usort($files, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);

        return array_map(
            fn (array $f): array => [
                'path' => $f['path'],
                'url'  => (string) $this->getUrl($f['path']),
                'name' => basename($f['path']),
            ],
            array_slice($files, 0, $limit)
        );
    }

    /**
     * Get the public URL for a stored image path.
     */
    public function getUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Create an upload-time WebP/JPEG derivative for list cards and galleries.
     *
     * @return array{path: string, url: string}|null
     */
    private function createThumbnail(string $path, string $filename): ?array
    {
        try {
            $disk = Storage::disk('public');
            $sourcePath = $disk->path($path);
            $info = @getimagesize($sourcePath);
            if ($info === false) {
                return null;
            }

            [$srcWidth, $srcHeight] = $info;
            if ($srcWidth <= 0 || $srcHeight <= 0) {
                return null;
            }

            $source = $this->createImageResource($sourcePath, (string) ($info['mime'] ?? ''));
            if ($source === null) {
                return null;
            }

            [$srcX, $srcY, $copyWidth, $copyHeight] = $this->coverGeometry($srcWidth, $srcHeight);
            $thumb = imagecreatetruecolor(self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled(
                $thumb,
                $source,
                0,
                0,
                $srcX,
                $srcY,
                self::THUMBNAIL_WIDTH,
                self::THUMBNAIL_HEIGHT,
                $copyWidth,
                $copyHeight
            );

            $format = function_exists('imagewebp') ? 'webp' : 'jpg';
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $thumbPath = trim(dirname($path), '.') . '/thumbnails/' . $baseName . '-' . self::THUMBNAIL_WIDTH . 'x' . self::THUMBNAIL_HEIGHT . '.' . $format;
            $fullThumbPath = $disk->path($thumbPath);
            if (! is_dir(dirname($fullThumbPath))) {
                mkdir(dirname($fullThumbPath), 0755, true);
            }

            if ($format === 'webp') {
                imagewebp($thumb, $fullThumbPath, self::THUMBNAIL_QUALITY);
            } else {
                imagejpeg($thumb, $fullThumbPath, 82);
            }

            imagedestroy($source);
            imagedestroy($thumb);

            return [
                'path' => $thumbPath,
                'url' => (string) $this->getUrl($thumbPath),
            ];
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Image thumbnail generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate upload-time responsive variants. The runtime thumbnail endpoint
     * still handles cache-on-demand sizes, but publishing these variants lets
     * clients and builders prefer compact assets immediately after upload.
     *
     * @return array<string, array<string, array{path:string,url:string,width:int,height:int,format:string}>>
     */
    private function createVariants(string $path, string $filename): array
    {
        $variants = [];
        foreach (self::DERIVATIVE_SPECS as $spec) {
            foreach ($this->derivativeFormats() as $format) {
                $variant = $this->createDerivative(
                    $path,
                    $filename,
                    (string) $spec['name'],
                    (int) $spec['width'],
                    (int) $spec['height'],
                    (string) $spec['fit'],
                    $format
                );
                if ($variant !== null) {
                    $variants[(string) $spec['name']][$format] = $variant;
                }
            }
        }

        return $variants;
    }

    /**
     * @param array<string, array<string, array{path:string,url:string,width:int,height:int,format:string}>> $variants
     * @return array<string, array<string, string>>
     */
    private function srcsets(array $variants): array
    {
        $groups = [
            'card' => ['card_small', 'card'],
            'detail' => ['card', 'detail'],
            'square' => ['avatar', 'square'],
        ];
        $srcsets = [];

        foreach ($groups as $group => $names) {
            foreach ($this->derivativeFormats() as $format) {
                $parts = [];
                foreach ($names as $name) {
                    $variant = $variants[$name][$format] ?? null;
                    if ($variant === null) {
                        continue;
                    }
                    $parts[] = $variant['url'] . ' ' . $variant['width'] . 'w';
                }
                if ($parts !== []) {
                    $srcsets[$group][$format] = implode(', ', $parts);
                }
            }
        }

        return $srcsets;
    }

    /**
     * @return string[]
     */
    private function derivativeFormats(): array
    {
        $formats = [];
        if (function_exists('imagewebp')) {
            $formats[] = 'webp';
        }
        if (function_exists('imageavif')) {
            $formats[] = 'avif';
        }

        return $formats !== [] ? $formats : ['jpg'];
    }

    /**
     * @return array{path:string,url:string,width:int,height:int,format:string}|null
     */
    private function createDerivative(string $path, string $filename, string $name, int $width, int $height, string $fit, string $format): ?array
    {
        try {
            $disk = Storage::disk('public');
            $sourcePath = $disk->path($path);
            $info = @getimagesize($sourcePath);
            if ($info === false) {
                return null;
            }

            [$srcWidth, $srcHeight] = $info;
            $source = $this->createImageResource($sourcePath, (string) ($info['mime'] ?? ''));
            if ($source === null || $srcWidth <= 0 || $srcHeight <= 0) {
                return null;
            }

            [$srcX, $srcY, $copyWidth, $copyHeight, $destWidth, $destHeight, $destX, $destY] =
                $this->geometry($srcWidth, $srcHeight, $width, $height, $fit);

            $canvas = imagecreatetruecolor($width, $height);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, $destX, $destY, $srcX, $srcY, $destWidth, $destHeight, $copyWidth, $copyHeight);

            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $variantPath = trim(dirname($path), '.') . '/variants/' . $baseName . "-{$name}-{$width}x{$height}.{$format}";
            $fullVariantPath = $disk->path($variantPath);
            if (! is_dir(dirname($fullVariantPath))) {
                mkdir(dirname($fullVariantPath), 0755, true);
            }

            if ($format === 'avif' && function_exists('imageavif')) {
                imageavif($canvas, $fullVariantPath, self::THUMBNAIL_QUALITY);
            } elseif ($format === 'webp' && function_exists('imagewebp')) {
                imagewebp($canvas, $fullVariantPath, self::THUMBNAIL_QUALITY);
            } else {
                imagejpeg($canvas, $fullVariantPath, 82);
            }

            imagedestroy($source);
            imagedestroy($canvas);

            return [
                'path' => $variantPath,
                'url' => (string) $this->getUrl($variantPath),
                'width' => $width,
                'height' => $height,
                'format' => $format,
            ];
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Image variant generation failed', [
                'path' => $path,
                'variant' => $name,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function coverGeometry(int $srcWidth, int $srcHeight): array
    {
        $targetRatio = self::THUMBNAIL_WIDTH / self::THUMBNAIL_HEIGHT;
        $sourceRatio = $srcWidth / $srcHeight;

        if ($sourceRatio > $targetRatio) {
            $copyHeight = $srcHeight;
            $copyWidth = (int) round($srcHeight * $targetRatio);
            $srcX = (int) (($srcWidth - $copyWidth) / 2);

            return [$srcX, 0, $copyWidth, $copyHeight];
        }

        $copyWidth = $srcWidth;
        $copyHeight = (int) round($srcWidth / $targetRatio);
        $srcY = (int) (($srcHeight - $copyHeight) / 2);

        return [0, $srcY, $copyWidth, $copyHeight];
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int,7:int}
     */
    private function geometry(int $srcWidth, int $srcHeight, int $width, int $height, string $fit): array
    {
        if ($fit === 'contain') {
            $scale = min($width / $srcWidth, $height / $srcHeight);
            $destWidth = max(1, (int) round($srcWidth * $scale));
            $destHeight = max(1, (int) round($srcHeight * $scale));

            return [0, 0, $srcWidth, $srcHeight, $destWidth, $destHeight, (int) (($width - $destWidth) / 2), (int) (($height - $destHeight) / 2)];
        }

        $targetRatio = $width / $height;
        $sourceRatio = $srcWidth / $srcHeight;
        if ($sourceRatio > $targetRatio) {
            $copyHeight = $srcHeight;
            $copyWidth = (int) round($srcHeight * $targetRatio);
            $srcX = (int) (($srcWidth - $copyWidth) / 2);

            return [$srcX, 0, $copyWidth, $copyHeight, $width, $height, 0, 0];
        }

        $copyWidth = $srcWidth;
        $copyHeight = (int) round($srcWidth / $targetRatio);
        $srcY = (int) (($srcHeight - $copyHeight) / 2);

        return [0, $srcY, $copyWidth, $copyHeight, $width, $height, 0, 0];
    }

    private function createImageResource(string $path, string $mime): mixed
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null,
            default => null,
        };
    }
}
