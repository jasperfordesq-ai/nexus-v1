<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Helpers\UrlHelper;

/**
 * ResourcesPublicApiController - Public V2 API for community resources (React frontend)
 *
 * Endpoints:
 * - GET  /api/v2/resources             - List resources (paginated, cursor-based)
 * - GET  /api/v2/resources/categories  - List resource categories with counts
 * - POST /api/v2/resources             - Upload a new resource (auth required)
 */
class ResourcesPublicApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/resources
     *
     * Query params: per_page, cursor, search, category_id
     */
    public function index(): void
    {
        $tenantId = TenantContext::getId();

        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
        $cursorParam = $_GET['cursor'] ?? null;
        $search = $_GET['search'] ?? null;
        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

        $conditions = ['r.tenant_id = ?'];
        $params = [$tenantId];

        // Cursor-based pagination
        if ($cursorParam) {
            $cursorId = $this->decodeCursor($cursorParam);
            if ($cursorId !== null) {
                $conditions[] = 'r.id < ?';
                $params[] = (int) $cursorId;
            }
        }

        // Search filter
        if ($search) {
            $conditions[] = '(r.title LIKE ? OR r.description LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Category filter
        if ($categoryId) {
            $conditions[] = 'r.category_id = ?';
            $params[] = $categoryId;
        }

        $where = implode(' AND ', $conditions);

        // Fetch perPage + 1 for has_more detection
        $items = Database::query(
            "SELECT r.id, r.title, r.description, r.file_path, r.file_type, r.file_size,
                    r.downloads, r.category_id, r.user_id, r.created_at,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as uploader_name,
                    u.avatar_url as uploader_avatar,
                    c.name as category_name,
                    c.slug as category_slug,
                    c.color as category_color
             FROM resources r
             LEFT JOIN users u ON r.user_id = u.id
             LEFT JOIN categories c ON r.category_id = c.id
             WHERE {$where}
             ORDER BY r.created_at DESC
             LIMIT ?",
            array_merge($params, [$perPage + 1])
        )->fetchAll();

        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }

        $baseUrl = UrlHelper::getBaseUrl();
        $cursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $cursor = $this->encodeCursor($lastItem['id']);
        }

        $formatted = array_map(function ($row) use ($baseUrl, $tenantId) {
            $filePath = $row['file_path'] ?? '';
            $fileUrl = $filePath
                ? $baseUrl . '/uploads/' . $tenantId . '/resources/' . $filePath
                : '';

            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'file_url' => $fileUrl,
                'file_path' => $filePath,
                'file_type' => $row['file_type'] ?? null,
                'file_size' => (int) ($row['file_size'] ?? 0),
                'downloads' => (int) ($row['downloads'] ?? 0),
                'created_at' => $row['created_at'],
                'uploader' => [
                    'id' => (int) ($row['user_id'] ?? 0),
                    'name' => trim($row['uploader_name'] ?? 'Unknown'),
                    'avatar' => $row['uploader_avatar']
                        ? (str_starts_with($row['uploader_avatar'], 'http') ? $row['uploader_avatar'] : $baseUrl . '/' . ltrim($row['uploader_avatar'], '/'))
                        : null,
                ],
                'category' => $row['category_name'] ? [
                    'id' => (int) $row['category_id'],
                    'name' => $row['category_name'],
                    'color' => $row['category_color'] ?? 'blue',
                ] : null,
            ];
        }, $items);

        $this->respondWithCollection($formatted, $cursor, $perPage, $hasMore);
    }

    /**
     * GET /api/v2/resources/categories
     */
    public function categories(): void
    {
        $tenantId = TenantContext::getId();

        $categories = Database::query(
            "SELECT c.id, c.name, c.slug, c.color,
                    COUNT(r.id) as resource_count
             FROM categories c
             LEFT JOIN resources r ON r.category_id = c.id AND r.tenant_id = c.tenant_id
             WHERE c.tenant_id = ? AND c.type = 'resource'
             GROUP BY c.id, c.name, c.slug, c.color
             ORDER BY c.name ASC",
            [$tenantId]
        )->fetchAll();

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'] ?? '',
                'color' => $row['color'] ?? 'blue',
                'resource_count' => (int) $row['resource_count'],
            ];
        }, $categories);

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/resources
     *
     * Upload a new resource (multipart/form-data)
     * Requires authentication.
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Title is required',
                'title',
                400
            );
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'File is required',
                'file',
                400
            );
            return;
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'png', 'gif', 'svg'];

        // Validate file size
        if ($file['size'] > $maxSize) {
            $this->respondWithError('FILE_TOO_LARGE', 'File exceeds 10MB limit', 'file', 400);
            return;
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $this->respondWithError('FILE_TYPE_NOT_ALLOWED', 'File type not allowed', 'file', 400);
            return;
        }

        // Generate unique filename
        $filename = md5(uniqid((string) $userId, true)) . '.' . $ext;

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../../../httpdocs/uploads/' . $tenantId . '/resources';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to save file', 'file', 500);
            return;
        }

        $description = trim($_POST['description'] ?? '');
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
        $fileType = $file['type'] ?? mime_content_type($destination) ?? null;

        Database::query(
            "INSERT INTO resources (tenant_id, user_id, category_id, title, description, file_path, file_type, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, $userId, $categoryId, $title, $description, $filename, $fileType, $file['size']]
        );

        $newId = Database::lastInsertId();
        $baseUrl = UrlHelper::getBaseUrl();

        $this->respondWithData([
            'id' => (int) $newId,
            'title' => $title,
            'description' => $description,
            'file_url' => $baseUrl . '/uploads/' . $tenantId . '/resources/' . $filename,
            'file_path' => $filename,
            'file_type' => $fileType,
            'file_size' => (int) $file['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ], null, 201);
    }
}
