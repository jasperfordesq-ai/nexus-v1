<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Admin\WebPConverter;

/**
 * Upload Service with Automatic WebP Conversion
 *
 * Handles file uploads and automatically converts images to WebP
 */
class UploadService
{
    private WebPConverter $webpConverter;
    private bool $autoConvertWebP = true;

    public function __construct()
    {
        $this->webpConverter = new WebPConverter();
    }

    /**
     * Handle file upload with automatic WebP conversion
     *
     * @param array $file $_FILES array element
     * @param string $destination Destination directory
     * @param string|null $newFilename Optional custom filename
     * @return array Result with 'success', 'file_path', 'webp_path', etc.
     */
    public function handleUpload(array $file, string $destination, ?string $newFilename = null): array
    {
        // Validate upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'error' => 'Invalid file upload'
            ];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => $this->getUploadErrorMessage($file['error'])
            ];
        }

        // Ensure destination directory exists
        if (!is_dir($destination)) {
            if (!mkdir($destination, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create destination directory'
                ];
            }
        }

        // Generate filename
        $filename = $newFilename ?? $this->generateSafeFilename($file['name']);
        $filePath = $destination . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }

        // Get MIME type using finfo (replaces deprecated mime_content_type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $result = [
            'success' => true,
            'file_path' => $filePath,
            'filename' => $filename,
            'size' => filesize($filePath),
            'mime_type' => $mimeType
        ];

        // Auto-convert to WebP if enabled and it's an image
        if ($this->autoConvertWebP && $this->isConvertibleImage($filePath)) {
            $webpResult = $this->webpConverter->convertOnUpload($filePath);

            if ($webpResult['success']) {
                $result['webp_converted'] = true;
                $result['webp_path'] = $webpResult['webp_file'];
                $result['webp_size'] = $webpResult['webp_size'];
                $result['savings'] = $webpResult['savings'];
            }
        }

        return $result;
    }

    /**
     * Enable or disable automatic WebP conversion
     *
     * @param bool $enabled
     */
    public function setAutoConvertWebP(bool $enabled): void
    {
        $this->autoConvertWebP = $enabled;
    }

    /**
     * Check if file is a convertible image
     *
     * @param string $filePath
     * @return bool
     */
    private function isConvertibleImage(string $filePath): bool
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        return in_array($mimeType, ['image/jpeg', 'image/png']);
    }

    /**
     * Generate safe filename
     *
     * @param string $originalName
     * @return string
     */
    private function generateSafeFilename(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        // Sanitize filename
        $name = preg_replace('/[^a-zA-Z0-9-_]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');

        // Add timestamp to avoid collisions
        $timestamp = time();

        return "{$name}-{$timestamp}.{$ext}";
    }

    /**
     * Get user-friendly upload error message
     *
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
}
