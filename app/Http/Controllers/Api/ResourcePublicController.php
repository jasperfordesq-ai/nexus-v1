<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Helpers\UrlHelper;

/**
 * ResourcePublicController — Public resource library.
 *
 * Native DB facade implementation — no legacy delegation except for store()
 * (file upload via $_FILES) and download() (readfile()+exit streaming).
 */
class ResourcePublicController extends BaseApiController
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
     * Upload a new resource (multipart/form-data). Kept as delegation because
     * it involves file upload via $_FILES which is tightly coupled to legacy PHP.
     */
    public function store(): JsonResponse
    {
        $this->requireAuth();

        return $this->delegate(\Nexus\Controllers\Api\ResourcesPublicApiController::class, 'store');
    }

    /**
     * GET /api/v2/resources/{id}/download
     *
     * Stream a resource file and increment download counter. Kept as delegation
     * because it uses readfile() + exit with raw headers (not a JsonResponse).
     */
    public function download(int $id): JsonResponse
    {
        $this->requireAuth();

        return $this->delegate(\Nexus\Controllers\Api\ResourcesPublicApiController::class, 'download', [$id]);
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

        if (!$resource) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Resource not found', null, 404);
        }

        // Check ownership or admin role
        $isOwner = (int) $resource->user_id === $userId;
        $user = \Illuminate\Support\Facades\Auth::user();
        $role = $user->role ?? ($_SESSION['user_role'] ?? 'member');
        $isAdmin = in_array($role, ['admin', 'super_admin', 'tenant_admin'], true);

        if (!$isOwner && !$isAdmin) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to delete this resource', null, 403);
        }

        // Delete file from disk
        $filePath = $resource->file_path ?? '';
        if (!empty($filePath)) {
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

    /**
     * Delegate to legacy controller via output buffering (for store/download only).
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
