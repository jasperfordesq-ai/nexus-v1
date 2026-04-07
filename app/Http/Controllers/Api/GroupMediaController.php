<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Core\TenantContext;

/**
 * GroupMediaController — Media gallery for groups: list, upload, and delete images/videos.
 *
 * Uses direct DB queries (no dedicated service — keep it simple).
 */
class GroupMediaController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/media
     *
     * List media for a group with cursor pagination and optional type filter.
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('per_page', 20, 1, 100);
        $cursor = $this->query('cursor');
        $type = $this->query('type'); // image, video

        $params = [$tenantId, $id, $tenantId];
        $where = "WHERE m.group_id = ? AND m.tenant_id = ?";

        if ($type && in_array($type, ['image', 'video'], true)) {
            $where .= " AND m.media_type = ?";
            $params = [$tenantId, $id, $tenantId, $type];
        }

        if ($cursor) {
            $decodedCursor = $this->decodeCursor($cursor);
            if ($decodedCursor !== null) {
                $where .= " AND m.id < ?";
                $params[] = (int) $decodedCursor;
            }
        }

        $sql = "SELECT m.id, m.file_path, m.url, m.media_type AS type,
                       m.thumbnail_path AS thumbnail_url,
                       m.caption, m.file_size, m.uploaded_by, u.name AS uploader_name,
                       m.created_at
                FROM group_media m
                LEFT JOIN users u ON u.id = m.uploaded_by AND u.tenant_id = ?
                {$where}
                ORDER BY m.id DESC
                LIMIT ?";
        $params[] = $limit + 1;

        $rows = DB::select($sql, $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = $this->encodeCursor($lastItem->id);
        }

        return $this->successResponse([
            'items' => $rows,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * POST /api/v2/groups/{id}/media
     *
     * Upload a media file (image or video) for a group.
     */
    public function upload(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $file = request()->file('file');
        if (!$file || !$file->isValid()) {
            return $this->errorResponse('No valid file provided', 400);
        }

        // Determine type from mime
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mime, 'video/')) {
            $type = 'video';
        } else {
            return $this->errorResponse('Only image and video files are accepted', 422);
        }

        $storagePath = "groups/{$tenantId}/{$id}/media";
        $path = $file->store($storagePath, 'public');

        if (!$path) {
            return $this->errorResponse('Failed to store file', 500);
        }

        $fileUrl = Storage::disk('public')->url($path);
        $now = now()->toDateTimeString();

        DB::insert(
            "INSERT INTO group_media (group_id, tenant_id, file_path, url, media_type, file_size, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $tenantId, $path, $fileUrl, $type, $file->getSize(), $userId, $now]
        );

        $mediaId = (int) DB::getPdo()->lastInsertId();

        $media = DB::selectOne(
            "SELECT * FROM group_media WHERE id = ? AND tenant_id = ?",
            [$mediaId, $tenantId]
        );

        return $this->successResponse($media, 201);
    }

    /**
     * DELETE /api/v2/groups/{id}/media/{mediaId}
     *
     * Delete a media item from storage and database.
     */
    public function destroy(int $id, int $mediaId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $media = DB::selectOne(
            "SELECT * FROM group_media WHERE id = ? AND group_id = ? AND tenant_id = ?",
            [$mediaId, $id, $tenantId]
        );

        if (!$media) {
            return $this->errorResponse('Media not found', 404);
        }

        // Authorization: only uploader or group admin can delete
        $isUploader = (int) $media->uploaded_by === $userId;
        $isAdmin = DB::selectOne(
            "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active' AND role IN ('admin', 'owner')",
            [$id, $userId]
        );
        if (!$isUploader && !$isAdmin) {
            return $this->errorResponse('Only the uploader or a group admin can delete media', 403);
        }

        // Delete file from storage
        if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }

        DB::delete(
            "DELETE FROM group_media WHERE id = ? AND tenant_id = ?",
            [$mediaId, $tenantId]
        );

        return $this->successResponse(['message' => __('api_controllers_3.group_media.media_deleted')]);
    }
}
