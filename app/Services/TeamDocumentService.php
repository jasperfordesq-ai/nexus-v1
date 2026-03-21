<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * TeamDocumentService — Native Eloquent/DB implementation for team document management.
 *
 * Manages file uploads/listing within groups (ideation challenge teams).
 * Uses cursor-based pagination for listing.
 */
class TeamDocumentService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get documents for a group with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getDocuments(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 50;

        $query = DB::table('team_documents')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId);

        if (!empty($filters['cursor'])) {
            $query->where('id', '<', (int) $filters['cursor']);
        }

        $docs = $query->orderByDesc('id')
            ->limit($limit + 1)
            ->get()
            ->toArray();

        $hasMore = count($docs) > $limit;
        if ($hasMore) {
            array_pop($docs);
        }

        $items = array_map(fn ($row) => (array) $row, $docs);
        $cursor = !empty($items) ? (string) end($items)['id'] : null;

        return [
            'items' => $items,
            'cursor' => $cursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Upload a document to a group.
     *
     * @param int $groupId Group to attach the document to
     * @param int $userId Uploader user ID
     * @param array $fileData File data in $_FILES format (name, type, tmp_name, error, size)
     * @param string|null $title Optional display title (defaults to filename)
     * @return int|null Inserted document ID, or null on failure
     */
    public function upload(int $groupId, int $userId, array $fileData, ?string $title = null): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        // Validate file data
        if (empty($fileData) || empty($fileData['tmp_name'])) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => 'No file provided',
                'field' => 'file',
            ];
            return null;
        }

        if (($fileData['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => 'File upload failed',
                'field' => 'file',
            ];
            return null;
        }

        // Validate MIME type using file content (not user-provided type)
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes, true)) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => 'File type not allowed. Accepted: PDF, images, Office documents, CSV, text.',
                'field' => 'file',
            ];
            return null;
        }

        // Validate file size (20MB max)
        $maxSize = 20 * 1024 * 1024;
        $fileSize = $fileData['size'] ?? filesize($fileData['tmp_name']);
        if ($fileSize > $maxSize) {
            $this->errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => 'File must be under 20MB',
                'field' => 'file',
            ];
            return null;
        }

        // Determine file extension from MIME
        $extMap = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ];
        $ext = $extMap[$mimeType] ?? 'bin';

        // Store file
        $uploadDir = "uploads/team_documents/{$tenantId}/{$groupId}";
        $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = "{$uploadDir}/{$filename}";
        if (!copy($fileData['tmp_name'], $destPath)) {
            $this->errors[] = [
                'code' => 'SERVER_ERROR',
                'message' => 'Failed to save uploaded file',
            ];
            return null;
        }

        $displayTitle = $title ?: ($fileData['name'] ?? $filename);

        $id = DB::table('team_documents')->insertGetId([
            'group_id' => $groupId,
            'tenant_id' => $tenantId,
            'title' => $displayTitle,
            'file_path' => $destPath,
            'file_type' => $mimeType,
            'file_size' => (int) $fileSize,
            'uploaded_by' => $userId,
            'created_at' => now(),
        ]);

        return (int) $id;
    }

    /**
     * Delete a document by ID.
     */
    public function delete(int $documentId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $doc = DB::table('team_documents')
            ->where('id', $documentId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$doc) {
            $this->errors[] = [
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => 'Document not found',
            ];
            return false;
        }

        // Delete the physical file if it exists
        if (!empty($doc->file_path) && file_exists($doc->file_path)) {
            @unlink($doc->file_path);
        }

        DB::table('team_documents')
            ->where('id', $documentId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return true;
    }
}
