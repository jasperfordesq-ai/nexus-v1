<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Helpers\UrlHelper;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ResourcesController — Community shared resources (files, links, documents).
 *
 * Native Eloquent implementation — fully converted to Laravel.
 * File uploads use request()->file(), downloads use StreamedResponse.
 */
class ResourcesController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/resources
     *
     * List resources with cursor-based pagination, category & search filters.
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');
        $search = $this->query('search');
        $categoryId = $this->queryInt('category_id');

        $query = DB::table('resources as r')
            ->leftJoin('users as u', 'r.user_id', '=', 'u.id')
            ->leftJoin('categories as c', 'r.category_id', '=', 'c.id')
            ->where('r.tenant_id', $tenantId);

        if ($cursor) {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded !== null) {
                $query->where('r.id', '<', (int) $decoded);
            }
        }

        if ($search) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('r.title', 'LIKE', $term)
                  ->orWhere('r.description', 'LIKE', $term);
            });
        }

        if ($categoryId) {
            $query->where('r.category_id', $categoryId);
        }

        $items = $query
            ->orderBy('r.sort_order')
            ->orderByDesc('r.created_at')
            ->limit($perPage + 1)
            ->select(
                'r.id', 'r.title', 'r.description', 'r.file_path', 'r.file_type', 'r.file_size',
                'r.downloads', 'r.category_id', 'r.user_id', 'r.created_at',
                'r.sort_order', 'r.content_type', 'r.content_body',
                DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as uploader_name"),
                'u.avatar_url as uploader_avatar',
                'c.name as category_name',
                'c.slug as category_slug',
                'c.color as category_color'
            )
            ->get();

        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items->pop();
        }

        $baseUrl = UrlHelper::getBaseUrl();
        $nextCursor = $hasMore && $items->isNotEmpty()
            ? $this->encodeCursor($items->last()->id)
            : null;

        $formatted = $items->map(function ($row) use ($baseUrl, $tenantId) {
            $filePath = $row->file_path ?? '';
            if ($filePath && str_starts_with($filePath, '/uploads/')) {
                $fileUrl = $baseUrl . $filePath;
            } else {
                $fileUrl = $filePath
                    ? $baseUrl . '/uploads/' . $tenantId . '/resources/' . $filePath
                    : '';
            }

            return [
                'id'           => (int) $row->id,
                'title'        => $row->title ?? '',
                'description'  => $row->description ?? '',
                'file_url'     => $fileUrl,
                'file_path'    => $filePath,
                'file_type'    => $row->file_type,
                'file_size'    => (int) ($row->file_size ?? 0),
                'downloads'    => (int) ($row->downloads ?? 0),
                'sort_order'   => (int) ($row->sort_order ?? 0),
                'content_type' => $row->content_type ?? 'plain',
                'content_body' => $row->content_body,
                'created_at'   => $row->created_at,
                'uploader'     => [
                    'id'     => (int) ($row->user_id ?? 0),
                    'name'   => trim($row->uploader_name ?? 'Unknown'),
                    'avatar' => $row->uploader_avatar
                        ? (str_starts_with($row->uploader_avatar, 'http') ? $row->uploader_avatar : $baseUrl . '/' . ltrim($row->uploader_avatar, '/'))
                        : null,
                ],
                'category' => $row->category_name ? [
                    'id'    => (int) $row->category_id,
                    'name'  => $row->category_name,
                    'color' => $row->category_color ?? 'blue',
                ] : null,
            ];
        })->all();

        return $this->respondWithCollection($formatted, $nextCursor, $perPage, $hasMore);
    }

    /**
     * GET /api/v2/resources/categories
     *
     * List resource categories with counts.
     */
    public function categories(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $categories = DB::table('categories as c')
            ->leftJoin('resources as r', function ($join) use ($tenantId) {
                $join->on('r.category_id', '=', 'c.id')
                     ->where('r.tenant_id', $tenantId);
            })
            ->where('c.tenant_id', $tenantId)
            ->where('c.type', 'resource')
            ->select('c.id', 'c.name', 'c.slug', 'c.color', DB::raw('COUNT(r.id) as resource_count'))
            ->groupBy('c.id', 'c.name', 'c.slug', 'c.color')
            ->orderBy('c.name')
            ->get()
            ->map(function ($row) {
                return [
                    'id'             => (int) $row->id,
                    'name'           => $row->name,
                    'slug'           => $row->slug ?? '',
                    'color'          => $row->color ?? 'blue',
                    'resource_count' => (int) $row->resource_count,
                ];
            })
            ->all();

        return $this->respondWithData($categories);
    }

    /**
     * POST /api/v2/resources
     *
     * Upload a new resource (multipart/form-data). Uses request()->file() (Laravel native).
     * Field name: 'file'. Form fields: title, description, category_id.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $title = trim(request()->input('title', ''));
        if (empty($title)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.title_required'), 'title', 400);
        }

        $file = request()->file('file');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.file_required'), 'file', 400);
        }

        // Verify temp file exists on disk (Docker overlay FS can lose temp files)
        $tmpPath = $file->getPathname();
        if (!file_exists($tmpPath)) {
            return $this->respondWithError('FILE_UPLOAD_FAILED', __('api.upload_temp_file_not_found'), 'file', 500);
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        // SVG intentionally excluded — XSS vector (can contain inline JS)
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'png', 'gif', 'webp'];

        // Use filesize() for reliable stat — SplFileInfo::getSize() throws ErrorException in Laravel
        $fileSize = (int) filesize($tmpPath);
        if ($fileSize > $maxSize) {
            return $this->respondWithError('FILE_TOO_LARGE', __('api.file_exceeds_limit'), 'file', 400);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExts, true)) {
            return $this->respondWithError('FILE_TYPE_NOT_ALLOWED', __('api.file_type_not_allowed'), 'file', 400);
        }

        // Double-check MIME type via file content inspection (not just extension)
        // Blocks HTML/SVG/PHP disguised as allowed extensions
        $detectedMime = $file->getMimeType();
        $blockedMimes = ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'application/x-httpd-php'];
        if ($detectedMime && in_array($detectedMime, $blockedMimes, true)) {
            return $this->respondWithError('FILE_TYPE_NOT_ALLOWED', __('api.file_type_blocked'), 'file', 400);
        }

        // Capture MIME type BEFORE move() invalidates the temp file
        $fileType = $file->getClientMimeType();

        // Generate secure unique filename (cryptographic randomness)
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        // Ensure upload directory exists
        $uploadDir = base_path('httpdocs/uploads/' . $tenantId . '/resources');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move file to destination
        $file->move($uploadDir, $filename);

        $description = trim(request()->input('description', ''));
        $categoryId = request()->input('category_id');
        $categoryId = ($categoryId !== null && $categoryId !== '') ? (int) $categoryId : null;

        DB::table('resources')->insert([
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'category_id' => $categoryId,
            'title'       => $title,
            'description' => $description,
            'file_path'   => $filename,
            'file_type'   => $fileType,
            'file_size'   => $fileSize,
            'created_at'  => now(),
        ]);

        $newId = (int) DB::getPdo()->lastInsertId();
        $baseUrl = UrlHelper::getBaseUrl();

        return $this->respondWithData([
            'id'          => $newId,
            'title'       => $title,
            'description' => $description,
            'file_url'    => $baseUrl . '/uploads/' . $tenantId . '/resources/' . $filename,
            'file_path'   => $filename,
            'file_type'   => $fileType,
            'file_size'   => $fileSize,
            'created_at'  => date('Y-m-d H:i:s'),
        ], null, 201);
    }

    /**
     * GET /api/v2/resources/{id}/download
     *
     * Stream a resource file with Content-Disposition: attachment and increment
     * the download counter. Uses Laravel StreamedResponse.
     */
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $tenantId = $this->getTenantId();

        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->select('id', 'file_path', 'file_type', 'title')
            ->first();

        if (!$resource) {
            return $this->respondWithError('NOT_FOUND', __('api.resource_not_found'), null, 404);
        }

        $filePath = $resource->file_path ?? '';
        if (empty($filePath)) {
            return $this->respondWithError('NOT_FOUND', __('api.no_file_for_resource'), null, 404);
        }

        // Resolve full filesystem path
        if (str_starts_with($filePath, '/uploads/')) {
            $fullPath = realpath(base_path('httpdocs' . $filePath));
        } else {
            $fullPath = realpath(base_path('httpdocs/uploads/' . $tenantId . '/resources/' . $filePath));
        }

        $uploadsDir = realpath(base_path('httpdocs/uploads'));
        if (!$fullPath || !$uploadsDir || !str_starts_with($fullPath, $uploadsDir) || !file_exists($fullPath)) {
            return $this->respondWithError('NOT_FOUND', __('api.file_not_found'), null, 404);
        }

        // Increment download counter
        DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->increment('downloads');

        // Build a friendly download filename from the title
        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $resource->title ?? 'download');
        $safeTitle = preg_replace('/\s+/', '_', trim($safeTitle));
        $downloadName = ($safeTitle ?: 'download') . '.' . $ext;

        $mimeType = $resource->file_type ?: (mime_content_type($fullPath) ?: 'application/octet-stream');
        $fileSize = filesize($fullPath);

        return response()->streamDownload(function () use ($fullPath) {
            readfile($fullPath);
        }, $downloadName, [
            'Content-Type'   => $mimeType,
            'Content-Length' => $fileSize,
            'Cache-Control'  => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * DELETE /api/v2/resources/{id}
     *
     * Delete a resource. Only the uploader or an admin can delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $resource = DB::table('resources')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $resource) {
            return $this->respondWithError('NOT_FOUND', __('api.resource_not_found'), null, 404);
        }

        // Check ownership or admin role
        $isOwner = (int) $resource->user_id === $userId;
        $user = \Illuminate\Support\Facades\Auth::user();
        $role = $user->role ?? 'member';
        $isAdmin = in_array($role, ['admin', 'super_admin', 'tenant_admin'], true);

        if (! $isOwner && ! $isAdmin) {
            return $this->respondWithError('FORBIDDEN', __('api.no_permission_delete_resource'), null, 403);
        }

        // Delete file from disk
        $filePath = $resource->file_path ?? '';
        if (! empty($filePath)) {
            $uploadsDir = realpath(base_path('httpdocs/uploads'));
            if (str_starts_with($filePath, '/uploads/')) {
                $fullPath = realpath(base_path('httpdocs' . $filePath));
            } else {
                $fullPath = realpath(base_path('httpdocs/uploads/' . $tenantId . '/resources/' . $filePath));
            }

            if ($fullPath && $uploadsDir && str_starts_with($fullPath, $uploadsDir) && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        DB::table('resources')->where('id', $id)->where('tenant_id', $tenantId)->delete();

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

}
