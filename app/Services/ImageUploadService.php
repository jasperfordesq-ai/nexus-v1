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
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const THUMBNAIL_WIDTH = 640;
    private const THUMBNAIL_HEIGHT = 480;
    private const THUMBNAIL_QUALITY = 78;

    /**
     * Upload an image file.
     *
     * @return array{path: string, url: string, filename: string, thumbnail_path?: string, thumbnail_url?: string}
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

        // Scope uploads by tenant to prevent cross-tenant file access
        $tenantId = \App\Core\TenantContext::getId();
        $tenantDir = $tenantId ? "tenant_{$tenantId}/{$directory}" : $directory;

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($tenantDir, $filename, 'public');
        $thumbnail = $this->createThumbnail($path, $filename);

        $result = [
            'path'     => $path,
            'url'      => $this->getUrl($path),
            'filename' => $filename,
        ];

        if ($thumbnail !== null) {
            $result['thumbnail_path'] = $thumbnail['path'];
            $result['thumbnail_url'] = $thumbnail['url'];
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
