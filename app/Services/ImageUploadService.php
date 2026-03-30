<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Upload an image file.
     *
     * @return array{path: string, url: string, filename: string}
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

        return [
            'path'     => $path,
            'url'      => $this->getUrl($path),
            'filename' => $filename,
        ];
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
     * Get the public URL for a stored image path.
     */
    public function getUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
