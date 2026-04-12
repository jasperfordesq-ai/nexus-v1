<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Core\TenantContext;

/**
 * GroupFileService — Manages file uploads, downloads, and organization within groups.
 */
class GroupFileService
{
    private array $errors = [];

    /** Maximum file size: 25 MB */
    const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /** Allowed MIME types.
     *  SVG is intentionally excluded: SVG can carry inline <script> and event
     *  handlers, so allowing it + serving it inline (.htaccess does) is XSS.
     *  Re-add only if uploads are passed through an SVG sanitizer and served
     *  with Content-Disposition: attachment. */
    const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'text/markdown',
        'application/zip', 'application/x-rar-compressed',
        'video/mp4', 'video/webm',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
    ];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * List files for a group with cursor-based pagination.
     */
    public function list(int $groupId, int $userId, array $filters = []): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isMember($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view files'];
            return null;
        }

        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;
        $folder = $filters['folder'] ?? null;
        $search = $filters['search'] ?? null;

        $query = DB::table('group_files as gf')
            ->join('users as u', 'gf.uploaded_by', '=', 'u.id')
            ->where('gf.group_id', $groupId)
            ->where('gf.tenant_id', $tenantId)
            ->select(
                'gf.id', 'gf.group_id', 'gf.file_name', 'gf.file_path',
                'gf.file_type', 'gf.file_size', 'gf.uploaded_by',
                'gf.created_at',
                'u.name as uploader_name', 'u.avatar_url as uploader_avatar'
            );

        if ($folder !== null) {
            $query->where('gf.folder', $folder);
        }

        if ($search) {
            $query->where('gf.file_name', 'LIKE', '%' . $search . '%');
        }

        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $query->where('gf.id', '<', (int) $decoded);
            }
        }

        $files = $query->orderByDesc('gf.id')
            ->limit($limit + 1)
            ->get()
            ->toArray();

        $hasMore = count($files) > $limit;
        if ($hasMore) {
            array_pop($files);
        }

        $nextCursor = null;
        if ($hasMore && !empty($files)) {
            $last = end($files);
            $nextCursor = base64_encode((string) $last->id);
        }

        return [
            'items' => array_map(fn ($f) => (array) $f, $files),
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Upload a file to a group.
     */
    public function upload(int $groupId, int $userId, array $fileData): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isMember($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to upload files'];
            return null;
        }

        $file = $fileData['file'] ?? null;
        if (!$file || !$file->isValid()) {
            $this->errors[] = ['code' => 'INVALID_FILE', 'message' => 'No valid file provided'];
            return null;
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $this->errors[] = ['code' => 'FILE_TOO_LARGE', 'message' => 'File exceeds maximum size of 25MB'];
            return null;
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
            $this->errors[] = ['code' => 'INVALID_TYPE', 'message' => 'File type not allowed: ' . $mimeType];
            return null;
        }

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $storedName = uniqid('grp_') . '_' . time() . '.' . $extension;
        $storagePath = "groups/{$tenantId}/{$groupId}";

        $path = $file->storeAs($storagePath, $storedName, 'local');

        if (!$path) {
            $this->errors[] = ['code' => 'UPLOAD_FAILED', 'message' => 'Failed to store file'];
            return null;
        }

        $folder = $fileData['folder'] ?? null;
        $description = $fileData['description'] ?? null;

        $id = DB::table('group_files')->insertGetId([
            'group_id' => $groupId,
            'tenant_id' => $tenantId,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_type' => $mimeType,
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
            'folder' => $folder,
            'description' => $description,
            'created_at' => now(),
        ]);

        // Fire integrations
        try { GroupWebhookService::fire($groupId, GroupWebhookService::EVENT_FILE_UPLOADED, ['file_id' => $id, 'file_name' => $originalName]); } catch (\Throwable $e) {}
        try { GroupAuditService::log('file_uploaded', $groupId, $userId, ['file_id' => $id, 'file_name' => $originalName]); } catch (\Throwable $e) {}
        try { GroupChallengeService::incrementProgress($groupId, 'files'); } catch (\Throwable $e) {}

        return [
            'id' => $id,
            'file_name' => $originalName,
            'file_type' => $mimeType,
            'file_size' => $file->getSize(),
            'folder' => $folder,
            'description' => $description,
            'uploaded_by' => $userId,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Download a file (returns file path for streaming).
     */
    public function download(int $fileId, int $userId): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $file = DB::table('group_files')
            ->where('id', $fileId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$file) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'File not found'];
            return null;
        }

        if (!$this->isMember($file->group_id, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to download files'];
            return null;
        }

        // Increment download count
        DB::table('group_files')
            ->where('id', $fileId)
            ->increment('download_count');

        return [
            'file_path' => $file->file_path,
            'file_name' => $file->file_name,
            'file_type' => $file->file_type,
            'file_size' => $file->file_size,
        ];
    }

    /**
     * Delete a file (admin or uploader).
     */
    public function delete(int $fileId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $file = DB::table('group_files')
            ->where('id', $fileId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$file) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'File not found'];
            return false;
        }

        $isUploader = (int) $file->uploaded_by === $userId;
        $isAdmin = $this->isAdmin($file->group_id, $userId, $tenantId);

        if (!$isUploader && !$isAdmin) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the uploader or an admin can delete files'];
            return false;
        }

        // Delete from storage
        if (Storage::disk('local')->exists($file->file_path)) {
            Storage::disk('local')->delete($file->file_path);
        }

        DB::table('group_files')
            ->where('id', $fileId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return true;
    }

    /**
     * Get folders for a group.
     */
    public function getFolders(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_files')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('folder')
            ->where('folder', '!=', '')
            ->select('folder', DB::raw('COUNT(*) as file_count'))
            ->groupBy('folder')
            ->orderBy('folder')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get file stats for a group.
     */
    public function getStats(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $stats = DB::table('group_files')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total_files, COALESCE(SUM(file_size), 0) as total_size, COUNT(DISTINCT uploaded_by) as unique_uploaders')
            ->first();

        return [
            'total_files' => (int) $stats->total_files,
            'total_size' => (int) $stats->total_size,
            'unique_uploaders' => (int) $stats->unique_uploaders,
        ];
    }

    private function isMember(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->where('groups.tenant_id', $tenantId)
            ->exists();
    }

    private function isAdmin(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->whereIn('group_members.role', ['admin', 'owner'])
            ->where('groups.tenant_id', $tenantId)
            ->exists();
    }
}
