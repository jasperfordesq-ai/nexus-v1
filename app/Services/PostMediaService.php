<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PostMediaService — Manages multi-image/media attachments for feed posts.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class PostMediaService
{
    /** Maximum number of media items per post */
    private const MAX_MEDIA_PER_POST = 10;

    /** Maximum file size in bytes (10MB) */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** Thumbnail max width in pixels */
    private const THUMBNAIL_WIDTH = 400;

    /** Allowed MIME types and their extensions */
    private const ALLOWED_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];

    /**
     * Attach uploaded media files to a post.
     *
     * @param int            $postId The post ID to attach media to.
     * @param UploadedFile[] $files  Array of uploaded files.
     * @return array The created media records.
     */
    public function attachMedia(int $postId, array $files): array
    {
        $tenantId = TenantContext::getId();

        // Check current media count for this post
        $existingCount = DB::table('post_media')
            ->where('tenant_id', $tenantId)
            ->where('post_id', $postId)
            ->count();

        $availableSlots = self::MAX_MEDIA_PER_POST - $existingCount;
        if ($availableSlots <= 0) {
            return [];
        }

        // Limit files to available slots
        $files = array_slice($files, 0, $availableSlots);

        // Get the next display_order value
        $maxOrder = (int) DB::table('post_media')
            ->where('tenant_id', $tenantId)
            ->where('post_id', $postId)
            ->max('display_order');

        $uploadDir = public_path("uploads/posts/{$tenantId}/{$postId}");
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $thumbDir = $uploadDir . '/thumbs';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $results = [];

        foreach ($files as $index => $file) {
            if (!($file instanceof UploadedFile) || !$file->isValid()) {
                continue;
            }

            // Validate file size
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                Log::warning("PostMediaService: File exceeds max size", [
                    'post_id' => $postId,
                    'size' => $file->getSize(),
                ]);
                continue;
            }

            // Validate MIME type
            $mime = $file->getMimeType();
            if (!isset(self::ALLOWED_TYPES[$mime])) {
                Log::warning("PostMediaService: Invalid MIME type", [
                    'post_id' => $postId,
                    'mime' => $mime,
                ]);
                continue;
            }

            // Validate it's a real image
            $imageInfo = @getimagesize($file->getPathname());
            if ($imageInfo === false) {
                continue;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];

            // Determine extension
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, self::ALLOWED_TYPES[$mime], true)) {
                $ext = self::ALLOWED_TYPES[$mime][0];
            }

            // Generate unique filename
            $filename = 'media_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $file->move($uploadDir, $filename);

            $fileUrl = "/uploads/posts/{$tenantId}/{$postId}/{$filename}";

            // Generate thumbnail
            $thumbnailUrl = $this->generateThumbnail(
                $uploadDir . '/' . $filename,
                $thumbDir,
                $filename,
                $mime
            );

            if ($thumbnailUrl) {
                $thumbnailUrl = "/uploads/posts/{$tenantId}/{$postId}/thumbs/{$filename}";
            }

            $displayOrder = $maxOrder + $index + 1;

            $mediaId = DB::table('post_media')->insertGetId([
                'tenant_id'     => $tenantId,
                'post_id'       => $postId,
                'media_type'    => 'image',
                'file_url'      => $fileUrl,
                'thumbnail_url' => $thumbnailUrl,
                'alt_text'      => null,
                'width'         => $width,
                'height'        => $height,
                'file_size'     => $file->getSize(),
                'display_order' => $displayOrder,
                'created_at'    => now(),
            ]);

            $results[] = [
                'id'            => (int) $mediaId,
                'media_type'    => 'image',
                'file_url'      => $fileUrl,
                'thumbnail_url' => $thumbnailUrl,
                'alt_text'      => null,
                'width'         => $width,
                'height'        => $height,
                'file_size'     => $file->getSize(),
                'display_order' => $displayOrder,
            ];
        }

        return $results;
    }

    /**
     * Get all media for a single post, ordered by display_order.
     */
    public function getMediaForPost(int $postId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('post_media')
            ->where('tenant_id', $tenantId)
            ->where('post_id', $postId)
            ->orderBy('display_order')
            ->get()
            ->map(fn ($row) => $this->formatMedia($row))
            ->all();
    }

    /**
     * Bulk fetch media for multiple posts (for feed efficiency).
     *
     * @param int[] $postIds
     * @return array<int, array> Keyed by post_id.
     */
    public function getMediaForPosts(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();

        $rows = DB::table('post_media')
            ->where('tenant_id', $tenantId)
            ->whereIn('post_id', $postIds)
            ->orderBy('post_id')
            ->orderBy('display_order')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->post_id][] = $this->formatMedia($row);
        }

        return $grouped;
    }

    /**
     * Reorder media items for a post.
     *
     * @param int   $postId   The post ID.
     * @param int[] $mediaIds Ordered array of media IDs.
     */
    public function reorderMedia(int $postId, array $mediaIds): void
    {
        $tenantId = TenantContext::getId();

        foreach ($mediaIds as $order => $mediaId) {
            DB::table('post_media')
                ->where('id', (int) $mediaId)
                ->where('post_id', $postId)
                ->where('tenant_id', $tenantId)
                ->update(['display_order' => $order]);
        }
    }

    /**
     * Remove a single media item (also deletes the file from disk).
     */
    public function removeMedia(int $mediaId): void
    {
        $tenantId = TenantContext::getId();

        $media = DB::table('post_media')
            ->where('id', $mediaId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$media) {
            return;
        }

        // Delete files from disk
        $this->deleteFileFromDisk($media->file_url);
        if ($media->thumbnail_url) {
            $this->deleteFileFromDisk($media->thumbnail_url);
        }

        DB::table('post_media')
            ->where('id', $mediaId)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    /**
     * Update alt text for a media item (accessibility).
     */
    public function updateAltText(int $mediaId, string $altText): void
    {
        $tenantId = TenantContext::getId();

        DB::table('post_media')
            ->where('id', $mediaId)
            ->where('tenant_id', $tenantId)
            ->update(['alt_text' => mb_substr($altText, 0, 500)]);
    }

    /**
     * Check if a media item belongs to a specific post owner.
     */
    public function isMediaOwnedByUser(int $mediaId, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('post_media as pm')
            ->join('feed_posts as fp', 'pm.post_id', '=', 'fp.id')
            ->where('pm.id', $mediaId)
            ->where('pm.tenant_id', $tenantId)
            ->where('fp.user_id', $userId)
            ->exists();
    }

    /**
     * Check if a post belongs to a specific user.
     */
    public function isPostOwnedByUser(int $postId, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        return DB::table('feed_posts')
            ->where('id', $postId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Format a media row into the API response shape.
     */
    private function formatMedia(object $row): array
    {
        return [
            'id'            => (int) $row->id,
            'media_type'    => $row->media_type,
            'file_url'      => $row->file_url,
            'thumbnail_url' => $row->thumbnail_url,
            'alt_text'      => $row->alt_text,
            'width'         => $row->width ? (int) $row->width : null,
            'height'        => $row->height ? (int) $row->height : null,
            'file_size'     => $row->file_size ? (int) $row->file_size : null,
            'display_order' => (int) $row->display_order,
        ];
    }

    /**
     * Generate a thumbnail for an image file.
     *
     * @return string|null Thumbnail filename on success, null on failure.
     */
    private function generateThumbnail(
        string $sourcePath,
        string $thumbDir,
        string $filename,
        string $mimeType
    ): ?string {
        try {
            $source = match ($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($sourcePath),
                'image/png'  => @imagecreatefrompng($sourcePath),
                'image/gif'  => @imagecreatefromgif($sourcePath),
                'image/webp' => @imagecreatefromwebp($sourcePath),
                default      => false,
            };

            if (!$source) {
                return null;
            }

            $origWidth = imagesx($source);
            $origHeight = imagesy($source);

            // Only create thumbnail if image is wider than thumb width
            if ($origWidth <= self::THUMBNAIL_WIDTH) {
                imagedestroy($source);
                // Use the original as thumbnail
                return $filename;
            }

            $ratio = self::THUMBNAIL_WIDTH / $origWidth;
            $thumbWidth = self::THUMBNAIL_WIDTH;
            $thumbHeight = (int) round($origHeight * $ratio);

            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
                imagefill($thumb, 0, 0, $transparent);
            }

            imagecopyresampled(
                $thumb, $source,
                0, 0, 0, 0,
                $thumbWidth, $thumbHeight,
                $origWidth, $origHeight
            );

            $thumbPath = $thumbDir . '/' . $filename;

            $saved = match ($mimeType) {
                'image/jpeg' => imagejpeg($thumb, $thumbPath, 80),
                'image/png'  => imagepng($thumb, $thumbPath, 8),
                'image/gif'  => imagegif($thumb, $thumbPath),
                'image/webp' => imagewebp($thumb, $thumbPath, 80),
                default      => false,
            };

            imagedestroy($source);
            imagedestroy($thumb);

            return $saved ? $filename : null;
        } catch (\Exception $e) {
            Log::warning("PostMediaService::generateThumbnail failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a file from disk given a public URL path.
     */
    private function deleteFileFromDisk(string $urlPath): void
    {
        $fullPath = public_path($urlPath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
