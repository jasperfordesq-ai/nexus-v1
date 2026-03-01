<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * TeamDocumentService - File sharing for project teams (groups)
 *
 * Allows group members to upload, list, and delete documents within
 * their team workspace. Files are stored on disk with metadata in the
 * team_documents table.
 *
 * @package Nexus\Services
 */
class TeamDocumentService
{
    /** @var array Collected errors */
    private static array $errors = [];

    /** Maximum file size: 10 MB */
    private const MAX_FILE_SIZE = 10485760;

    /** Allowed file extensions */
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'md', 'rtf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'zip', 'gz',
    ];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * List documents for a group
     */
    public static function getDocuments(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;
        $cursor = $filters['cursor'] ?? null;

        $params = [$groupId, $tenantId];
        $where = ["d.group_id = ?", "d.tenant_id = ?"];

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "d.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $items = Database::query(
            "SELECT d.*, u.first_name, u.last_name, u.avatar_url
             FROM team_documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE {$whereClause}
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT ?",
            $params
        )->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format
        foreach ($items as &$item) {
            $item['uploader'] = [
                'id' => (int)$item['uploaded_by'],
                'name' => trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
                'avatar_url' => $item['avatar_url'] ?? null,
            ];
            $item['file_size_formatted'] = self::formatFileSize((int)($item['file_size'] ?? 0));
            unset($item['first_name'], $item['last_name'], $item['avatar_url']);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Upload a document
     *
     * @param int $groupId
     * @param int $userId
     * @param array $fileData From $_FILES
     * @param string|null $title Optional title override
     * @return int|null Document ID
     */
    public static function upload(int $groupId, int $userId, array $fileData, ?string $title = null): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Must be group member
        if (!self::isGroupMember($groupId, $userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You must be a group member to upload documents');
            return null;
        }

        // Validate file
        if (empty($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'File is required', 'file');
            return null;
        }

        $fileSize = (int)($fileData['size'] ?? 0);
        if ($fileSize > self::MAX_FILE_SIZE) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'File size exceeds 10 MB limit', 'file');
            return null;
        }

        $originalName = $fileData['name'] ?? 'unknown';
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'File type not allowed', 'file');
            return null;
        }

        $fileType = $fileData['type'] ?? mime_content_type($fileData['tmp_name']);
        $displayTitle = $title ?: $originalName;

        // Generate unique path
        $uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 2) . '/httpdocs/uploads');
        $teamDir = $uploadDir . '/teams/' . $groupId;

        if (!is_dir($teamDir)) {
            mkdir($teamDir, 0755, true);
        }

        $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $filePath = '/uploads/teams/' . $groupId . '/' . $uniqueName;
        $fullPath = $teamDir . '/' . $uniqueName;

        if (!move_uploaded_file($fileData['tmp_name'], $fullPath)) {
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to save file');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO team_documents (group_id, tenant_id, title, file_path, file_type, file_size, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$groupId, $tenantId, $displayTitle, $filePath, $fileType, $fileSize, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Document upload failed: " . $e->getMessage());
            // Clean up the file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to save document record');
            return null;
        }
    }

    /**
     * Delete a document
     */
    public static function delete(int $documentId, int $userId): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        $doc = Database::query(
            "SELECT * FROM team_documents WHERE id = ? AND tenant_id = ?",
            [$documentId, $tenantId]
        )->fetch();

        if (!$doc) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Document not found');
            return false;
        }

        $isUploader = (int)$doc['uploaded_by'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isUploader && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the uploader or an admin can delete documents');
            return false;
        }

        try {
            Database::query(
                "DELETE FROM team_documents WHERE id = ? AND tenant_id = ?",
                [$documentId, $tenantId]
            );

            // Remove physical file
            $uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 2) . '/httpdocs/uploads');
            $basePath = dirname($uploadDir);
            $fullPath = $basePath . '/' . ltrim($doc['file_path'], '/');
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return true;
        } catch (\Throwable $e) {
            error_log("Document deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete document');
            return false;
        }
    }

    /**
     * Format file size for display
     */
    private static function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 1) . ' MB';
    }

    private static function isGroupMember(int $groupId, int $userId): bool
    {
        $result = Database::query(
            "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        )->fetch();

        return !empty($result);
    }

    private static function isAdmin(int $userId): bool
    {
        $user = Database::query(
            "SELECT role FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
