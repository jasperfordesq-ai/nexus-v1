<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;

/**
 * AdminBlogApiController - V2 API for React admin blog management
 *
 * Provides full CRUD for blog posts in the admin panel.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/blog                     - List posts (paginated, filterable)
 * - POST   /api/v2/admin/blog                     - Create a new post
 * - GET    /api/v2/admin/blog/{id}                - Get single post detail
 * - PUT    /api/v2/admin/blog/{id}                - Update a post
 * - DELETE /api/v2/admin/blog/{id}                - Delete a post
 * - POST   /api/v2/admin/blog/{id}/toggle-status  - Toggle draft/published
 */
class AdminBlogApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/blog
     *
     * Query params: page, limit, status (draft|published), search
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = ['p.tenant_id = ?'];
        $params = [$tenantId];

        // Status filter
        if ($status && in_array($status, ['draft', 'published'], true)) {
            $conditions[] = 'p.status = ?';
            $params[] = $status;
        }

        // Search filter
        if ($search) {
            $conditions[] = '(p.title LIKE ? OR p.content LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM posts p WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Paginated results
        $items = Database::query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.status, p.featured_image,
                    p.author_id, p.category_id, p.created_at, p.updated_at,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as author_name,
                    c.name as category_name
             FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        )->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? '',
                'excerpt' => $row['excerpt'] ?? '',
                'status' => $row['status'] ?? 'draft',
                'featured_image' => $row['featured_image'] ?: null,
                'author_id' => (int) ($row['author_id'] ?? 0),
                'author_name' => trim($row['author_name'] ?? ''),
                'category_id' => $row['category_id'] ? (int) $row['category_id'] : null,
                'category_name' => $row['category_name'] ?? null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/blog/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $post = Database::query(
            "SELECT p.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as author_name,
                    c.name as category_name
             FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = ? AND p.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Blog post not found', null, 404);
            return;
        }

        $this->respondWithData([
            'id' => (int) $post['id'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'content' => $post['content'] ?? '',
            'excerpt' => $post['excerpt'] ?? '',
            'status' => $post['status'] ?? 'draft',
            'featured_image' => $post['featured_image'] ?: null,
            'author_id' => (int) ($post['author_id'] ?? 0),
            'author_name' => trim($post['author_name'] ?? ''),
            'category_id' => $post['category_id'] ? (int) $post['category_id'] : null,
            'category_name' => $post['category_name'] ?? null,
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at'] ?? null,
        ]);
    }

    /**
     * POST /api/v2/admin/blog
     */
    public function store(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = $this->getAllInput();
        $title = trim($data['title'] ?? '');

        if (empty($title)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Title is required',
                'title',
                400
            );
            return;
        }

        // Auto-generate slug from title
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
        $slug = trim($slug, '-');

        // Ensure slug uniqueness within tenant
        $existingSlug = Database::query(
            "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ?",
            [$slug, $tenantId]
        )->fetch()['cnt'];

        if ($existingSlug > 0) {
            $slug = $slug . '-' . time();
        }

        $content = $data['content'] ?? '';
        $excerpt = $data['excerpt'] ?? '';
        $status = in_array($data['status'] ?? '', ['draft', 'published'], true) ? $data['status'] : 'draft';
        $featuredImage = $data['featured_image'] ?? null;
        $categoryId = isset($data['category_id']) && $data['category_id'] ? (int) $data['category_id'] : null;

        Database::query(
            "INSERT INTO posts (tenant_id, author_id, title, slug, content, excerpt, status, featured_image, category_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$tenantId, $adminId, $title, $slug, $content, $excerpt, $status, $featuredImage, $categoryId]
        );

        $newId = Database::lastInsertId();

        ActivityLog::log($adminId, 'admin_create_blog_post', "Created blog post #{$newId}: {$title}");

        $this->respondWithData([
            'id' => (int) $newId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
        ], null, 201);
    }

    /**
     * PUT /api/v2/admin/blog/{id}
     */
    public function update(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Verify post exists and belongs to tenant
        $post = Database::query(
            "SELECT id, title, slug FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Blog post not found', null, 404);
            return;
        }

        $data = $this->getAllInput();

        // Build dynamic update
        $fields = [];
        $params = [];

        if (isset($data['title']) && trim($data['title']) !== '') {
            $fields[] = 'title = ?';
            $params[] = trim($data['title']);

            // Regenerate slug if title changed
            $newSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($data['title'])));
            $newSlug = trim($newSlug, '-');

            // Only update slug if title changed from original
            if ($newSlug !== $post['slug']) {
                // Check uniqueness (excluding current post)
                $existing = Database::query(
                    "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ? AND id != ?",
                    [$newSlug, $tenantId, $id]
                )->fetch()['cnt'];

                if ($existing > 0) {
                    $newSlug = $newSlug . '-' . time();
                }

                $fields[] = 'slug = ?';
                $params[] = $newSlug;
            }
        }

        if (array_key_exists('content', $data)) {
            $fields[] = 'content = ?';
            $params[] = $data['content'] ?? '';
        }

        if (array_key_exists('excerpt', $data)) {
            $fields[] = 'excerpt = ?';
            $params[] = $data['excerpt'] ?? '';
        }

        if (isset($data['status']) && in_array($data['status'], ['draft', 'published'], true)) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (array_key_exists('featured_image', $data)) {
            $fields[] = 'featured_image = ?';
            $params[] = $data['featured_image'] ?: null;
        }

        if (array_key_exists('category_id', $data)) {
            $fields[] = 'category_id = ?';
            $params[] = $data['category_id'] ? (int) $data['category_id'] : null;
        }

        if (empty($fields)) {
            $this->respondWithError('VALIDATION_NO_FIELDS', 'No fields provided to update', null, 400);
            return;
        }

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;

        $setClause = implode(', ', $fields);

        Database::query(
            "UPDATE posts SET {$setClause} WHERE id = ? AND tenant_id = ?",
            $params
        );

        ActivityLog::log($adminId, 'admin_update_blog_post', "Updated blog post #{$id}: " . ($data['title'] ?? $post['title']));

        // Return updated post
        $this->show($id);
    }

    /**
     * DELETE /api/v2/admin/blog/{id}
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $post = Database::query(
            "SELECT id, title FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Blog post not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_delete_blog_post', "Deleted blog post #{$id}: {$post['title']}");

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/blog/{id}/toggle-status
     */
    public function toggleStatus(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $post = Database::query(
            "SELECT id, title, status FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Blog post not found', null, 404);
            return;
        }

        $newStatus = $post['status'] === 'published' ? 'draft' : 'published';

        Database::query(
            "UPDATE posts SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newStatus, $id, $tenantId]
        );

        ActivityLog::log(
            $adminId,
            'admin_toggle_blog_status',
            "Changed blog post #{$id} status: {$post['status']} -> {$newStatus}"
        );

        $this->respondWithData([
            'id' => (int) $id,
            'status' => $newStatus,
        ]);
    }
}
