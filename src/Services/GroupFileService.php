<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;

/**
 * GroupFileService - File sharing within groups
 *
 * Manages file uploads, downloads, and deletion for group file sharing.
 * Files are scoped by tenant and group, with type/size validation.
 *
 * Supported file types: pdf, doc, docx, xls, xlsx, png, jpg, jpeg, gif, webp
 * Default max size: 10MB (configurable per tenant)
 */
class GroupFileService
{
    private static array $errors = [];

    /** Allowed MIME types mapped to extensions */
    private const ALLOWED_TYPES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** Default max file size in bytes (10 MB) */
    private const DEFAULT_MAX_SIZE = 10 * 1024 * 1024;

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * List files for a group
     *
     * @param int $groupId
     * @param int $userId Requesting user (must be member)
     * @param array $filters ['cursor' => string, 'limit' => int]
     * @return array|null Paginated files or null on error
     */
    public static function listFiles(int $groupId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view files'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT gf.*, u.name as uploader_name, u.avatar_url as uploader_avatar
            FROM group_files gf
            JOIN users u ON gf.uploaded_by = u.id
            WHERE gf.group_id = ? AND gf.tenant_id = ?
        ";
        $params = [$groupId, $tenantId];

        if ($cursorId) {
            $sql .= " AND gf.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY gf.created_at DESC, gf.id DESC LIMIT " . ($limit + 1);

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($files) > $limit;
        if ($hasMore) {
            array_pop($files);
        }

        $items = [];
        $lastId = null;

        foreach ($files as $f) {
            $lastId = $f['id'];
            $items[] = [
                'id' => (int)$f['id'],
                'file_name' => $f['file_name'],
                'file_type' => $f['file_type'],
                'file_size' => (int)$f['file_size'],
                'file_size_formatted' => self::formatFileSize((int)$f['file_size']),
                'uploaded_by' => [
                    'id' => (int)$f['uploaded_by'],
                    'name' => $f['uploader_name'],
                    'avatar_url' => $f['uploader_avatar'],
                ],
                'created_at' => $f['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Upload a file to a group
     *
     * @param int $groupId
     * @param int $userId Uploader
     * @param array $file $_FILES entry
     * @return array|null File info or null on error
     */
    public static function upload(int $groupId, int $userId, array $file): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to upload files'];
            return null;
        }

        // Validate upload
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'No file uploaded or upload error', 'field' => 'file'];
            return null;
        }

        // Validate MIME type
        $mimeType = $file['type'] ?? '';
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_TYPES[$detectedMime])) {
            self::$errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => 'File type not allowed. Supported: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, GIF, WEBP',
                'field' => 'file',
            ];
            return null;
        }

        // Validate size
        $maxSize = self::getMaxFileSize();
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / (1024 * 1024), 1);
            self::$errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => "File size exceeds maximum of {$maxMB}MB",
                'field' => 'file',
            ];
            return null;
        }

        $tenantId = TenantContext::getId();
        $ext = self::ALLOWED_TYPES[$detectedMime];
        $originalName = basename($file['name'] ?? 'file.' . $ext);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        // Generate unique file path
        $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/uploads/groups/' . $groupId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uniqueName = uniqid() . '_' . $safeName;
        $filePath = $uploadDir . '/' . $uniqueName;
        $relativePath = '/uploads/groups/' . $groupId . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to save file'];
            return null;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO group_files (group_id, tenant_id, file_name, file_path, file_type, file_size, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$groupId, $tenantId, $safeName, $relativePath, $ext, $file['size'], $userId]);
            $fileId = (int)$db->lastInsertId();

            return [
                'id' => $fileId,
                'file_name' => $safeName,
                'file_type' => $ext,
                'file_size' => (int)$file['size'],
                'file_size_formatted' => self::formatFileSize((int)$file['size']),
                'file_path' => $relativePath,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            error_log("GroupFileService::upload error: " . $e->getMessage());
            // Clean up the file on DB failure
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to save file record'];
            return null;
        }
    }

    /**
     * Delete a file from a group
     *
     * @param int $groupId
     * @param int $fileId
     * @param int $userId Must be uploader or group admin
     * @return bool
     */
    public static function delete(int $groupId, int $fileId, int $userId): bool
    {
        self::$errors = [];

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Get file record
        $stmt = $db->prepare("SELECT * FROM group_files WHERE id = ? AND group_id = ? AND tenant_id = ?");
        $stmt->execute([$fileId, $groupId, $tenantId]);
        $file = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$file) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'File not found'];
            return false;
        }

        // Check permissions: uploader or group admin
        if ((int)$file['uploaded_by'] !== $userId && !Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to delete this file'];
            return false;
        }

        try {
            // Delete DB record
            $db->prepare("DELETE FROM group_files WHERE id = ? AND tenant_id = ?")->execute([$fileId, $tenantId]);

            // Delete physical file
            $fullPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . $file['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return true;
        } catch (\Exception $e) {
            error_log("GroupFileService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete file'];
            return false;
        }
    }

    /**
     * Get file info for download
     */
    public static function getFile(int $groupId, int $fileId, int $userId): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to download files'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM group_files WHERE id = ? AND group_id = ? AND tenant_id = ?");
        $stmt->execute([$fileId, $groupId, $tenantId]);
        $file = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$file) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'File not found'];
            return null;
        }

        return $file;
    }

    /**
     * Get max file size for current tenant (configurable)
     */
    private static function getMaxFileSize(): int
    {
        try {
            $tenantId = TenantContext::getId();
            $stmt = Database::query(
                "SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'group_max_file_size'",
                [$tenantId]
            );
            $row = $stmt->fetch();
            if ($row && is_numeric($row['setting_value'])) {
                return (int)$row['setting_value'];
            }
        } catch (\Exception $e) {
            // Use default
        }
        return self::DEFAULT_MAX_SIZE;
    }

    /**
     * Format file size for display
     */
    private static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
