<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use App\Core\TenantContext;

/**
 * Stores a single message file/image attachment under
 * httpdocs/uploads/{tenantId}/message_attachments and returns its metadata.
 *
 * Mirrors ImageUploader/AudioUploader conventions: a $_FILES-style array in,
 * a public "/uploads/..." path out, content-type validated against an
 * allow-list, with the is_uploaded_file()->move_uploaded_file() safety check
 * and a rename() fallback for framework-injected files in the test suite.
 */
class MessageAttachmentUploader
{
    /** Hard ceiling per file (10 MB). */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Max attachments accepted on a single message. */
    public const MAX_FILES = 5;

    /** extension => allowed detected MIME types. */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    /**
     * @param array{name:string,type?:string,tmp_name:string,error?:int,size?:int} $file
     * @return array{url:string,path:string,name:string,size:int,mime:string,type:string}
     *
     * @throws \InvalidArgumentException on validation failure
     * @throws \RuntimeException on storage failure
     */
    public static function upload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(__('api.message_attachment_upload_error'));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \InvalidArgumentException(__('api.message_attachment_upload_error'));
        }

        $size = (int) ($file['size'] ?? @filesize($tmp) ?: 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException(__('api.message_attachment_too_large'));
        }

        $originalName = trim((string) ($file['name'] ?? 'attachment'));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !isset(self::ALLOWED[$extension])) {
            throw new \InvalidArgumentException(__('api.message_attachment_invalid_type'));
        }

        // Verify the real content type matches the claimed extension (defence in
        // depth: a .png that is actually a script must be rejected).
        $detectedMime = self::detectMime($tmp);
        if (!in_array($detectedMime, self::ALLOWED[$extension], true)) {
            throw new \InvalidArgumentException(__('api.message_attachment_invalid_type'));
        }

        $tenantId = (int) TenantContext::getId();
        $tenantDir = $tenantId . '/message_attachments';
        $targetDir = __DIR__ . '/../../httpdocs/uploads/' . $tenantDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(__('api.message_attachment_upload_error'));
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;
        $publicPath = '/uploads/' . $tenantDir . '/' . $filename;

        // Real HTTP uploads go through move_uploaded_file() for the
        // is_uploaded_file() safety check; the test suite injects files that are
        // not "uploaded", so fall back to rename() for those.
        $moved = \is_uploaded_file($tmp)
            ? \move_uploaded_file($tmp, $targetPath)
            : \rename($tmp, $targetPath);

        if (!$moved) {
            throw new \RuntimeException(__('api.message_attachment_upload_error'));
        }

        @chmod($targetPath, 0644);

        // Keep a clean, length-bounded display name (no path, no control chars).
        $displayName = mb_substr(basename($originalName), 0, 255);

        return [
            'url'  => $publicPath,
            'path' => $publicPath, // file_path column ("storage path") — same /uploads/ ref here
            'name' => $displayName,
            'size' => $size,
            'mime' => $detectedMime,
            'type' => str_starts_with($detectedMime, 'image/') ? 'image' : 'file',
        ];
    }

    /**
     * Remove a staged attachment when validation or message persistence fails.
     * The resolved path is constrained to the current tenant's attachment root.
     */
    public static function delete(string $url): bool
    {
        $tenantId = (int) TenantContext::getId();
        $prefix = '/uploads/' . $tenantId . '/message_attachments/';
        if (! str_starts_with($url, $prefix)) {
            return false;
        }

        $filename = basename($url);
        if ($filename === '' || $filename !== substr($url, strlen($prefix))) {
            return false;
        }

        $baseDir = realpath(__DIR__ . '/../../httpdocs/uploads/' . $tenantId . '/message_attachments');
        if ($baseDir === false) {
            return true;
        }

        $path = $baseDir . DIRECTORY_SEPARATOR . $filename;
        if (! is_file($path)) {
            return true;
        }

        $resolved = realpath($path);
        if ($resolved === false || ! str_starts_with($resolved, $baseDir . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return @unlink($resolved);
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return (string) (mime_content_type($path) ?: 'application/octet-stream');
    }
}
