<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

/**
 * AdminBlogController -- Admin blog post management.
 *
 * Provides full CRUD for blog posts in the admin panel.
 * All endpoints require admin authentication.
 */
class AdminBlogController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/blog
     *
     * Query params: page, limit, status (draft|published), search
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $status = $this->query('status');
        $search = $this->query('search');

        $conditions = ['p.tenant_id = ?'];
        $params = [$tenantId];

        if ($status && in_array($status, ['draft', 'published'], true)) {
            $conditions[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($search) {
            $conditions[] = '(p.title LIKE ? OR p.content LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM posts p WHERE {$where}",
            $params
        )->cnt;

        $items = DB::select(
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
        );

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'title' => $row->title ?? '',
                'slug' => $row->slug ?? '',
                'excerpt' => $row->excerpt ?? '',
                'status' => $row->status ?? 'draft',
                'featured_image' => $row->featured_image ?: null,
                'author_id' => (int) ($row->author_id ?? 0),
                'author_name' => trim($row->author_name ?? ''),
                'category_id' => $row->category_id ? (int) $row->category_id : null,
                'category_name' => $row->category_name ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at ?? null,
            ];
        }, $items);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/blog/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $post = DB::selectOne(
            "SELECT p.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as author_name,
                    c.name as category_name
             FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = ? AND p.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$post) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.blog_post_not_found'), null, 404);
        }

        // Fetch SEO metadata
        $seo = DB::selectOne(
            "SELECT meta_title, meta_description, noindex FROM seo_metadata WHERE entity_type = 'post' AND entity_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return $this->respondWithData([
            'id' => (int) $post->id,
            'title' => $post->title ?? '',
            'slug' => $post->slug ?? '',
            'content' => $post->content ?? '',
            'excerpt' => $post->excerpt ?? '',
            'status' => $post->status ?? 'draft',
            'featured_image' => $post->featured_image ?: null,
            'author_id' => (int) ($post->author_id ?? 0),
            'author_name' => trim($post->author_name ?? ''),
            'category_id' => $post->category_id ? (int) $post->category_id : null,
            'category_name' => $post->category_name ?? null,
            'meta_title' => $seo->meta_title ?? null,
            'meta_description' => $seo->meta_description ?? null,
            'noindex' => !empty($seo->noindex ?? false),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at ?? null,
        ]);
    }

    /**
     * POST /api/v2/admin/blog
     */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $title = trim($this->input('title', ''));

        if (empty($title)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.title_required'), 'title', 400);
        }

        // Auto-generate slug from title
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
        $slug = trim($slug, '-');

        // Ensure slug uniqueness within tenant
        $existingSlug = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ?",
            [$slug, $tenantId]
        )->cnt;

        if ($existingSlug > 0) {
            $slug = $slug . '-' . time();
        }

        $content = \App\Helpers\HtmlSanitizer::sanitizeCms($this->input('content', ''));
        $excerpt = trim($this->input('excerpt', ''));
        $status = in_array($this->input('status', ''), ['draft', 'published'], true) ? $this->input('status') : 'draft';
        $featuredImage = $this->input('featured_image');
        $categoryId = $this->input('category_id') ? (int) $this->input('category_id') : null;

        // Allow custom slug override
        $customSlug = $this->input('slug');
        if (!empty($customSlug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($customSlug)));
            $slug = trim($slug, '-');
            $existingSlug = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ?",
                [$slug, $tenantId]
            )->cnt;
            if ($existingSlug > 0) {
                $slug = $slug . '-' . time();
            }
        }

        $newId = DB::table('posts')->insertGetId([
            'tenant_id' => $tenantId,
            'author_id' => $adminId,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'status' => $status,
            'featured_image' => $featuredImage,
            'category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Save SEO metadata if provided
        $metaTitle = trim($this->input('meta_title', ''));
        $metaDescription = trim($this->input('meta_description', ''));
        $noindex = !empty($this->input('noindex')) ? 1 : 0;
        if ($metaTitle || $metaDescription || $noindex) {
            DB::statement(
                "INSERT INTO seo_metadata (entity_type, entity_id, tenant_id, meta_title, meta_description, noindex, created_at, updated_at)
                 VALUES ('post', ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), noindex = VALUES(noindex), updated_at = NOW()",
                [$newId, $tenantId, $metaTitle ?: null, $metaDescription ?: null, $noindex]
            );
        }

        ActivityLog::create(['user_id' => $adminId, 'action' => 'admin_create_blog_post', 'details' => "Created blog post #{$newId}: {$title}"]);

        return $this->respondWithData([
            'id' => (int) $newId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
        ], null, 201);
    }

    /**
     * PUT /api/v2/admin/blog/{id}
     */
    public function update(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        // Verify post exists and belongs to tenant
        $post = DB::selectOne(
            "SELECT id, title, slug FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$post) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.blog_post_not_found'), null, 404);
        }

        $data = $this->getAllInput();

        // Build dynamic update
        $updates = [];

        if (isset($data['title']) && trim($data['title']) !== '') {
            $updates['title'] = trim($data['title']);

            // Only auto-generate slug from title if no explicit slug provided
            if (!isset($data['slug'])) {
                $newSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($data['title'])));
                $newSlug = trim($newSlug, '-');

                if ($newSlug !== $post->slug) {
                    $existing = DB::selectOne(
                        "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ? AND id != ?",
                        [$newSlug, $tenantId, $id]
                    )->cnt;
                    if ($existing > 0) {
                        $newSlug = $newSlug . '-' . time();
                    }
                    $updates['slug'] = $newSlug;
                }
            }
        }

        // Allow explicit slug override
        if (isset($data['slug']) && trim($data['slug']) !== '') {
            $newSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($data['slug'])));
            $newSlug = trim($newSlug, '-');
            if ($newSlug !== $post->slug) {
                $existing = DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM posts WHERE slug = ? AND tenant_id = ? AND id != ?",
                    [$newSlug, $tenantId, $id]
                )->cnt;
                if ($existing > 0) {
                    $newSlug = $newSlug . '-' . time();
                }
                $updates['slug'] = $newSlug;
            }
        }

        if (array_key_exists('content', $data)) {
            $updates['content'] = \App\Helpers\HtmlSanitizer::sanitizeCms($data['content'] ?? '');
        }

        if (array_key_exists('excerpt', $data)) {
            $updates['excerpt'] = $data['excerpt'] ?? '';
        }

        if (isset($data['status']) && in_array($data['status'], ['draft', 'published'], true)) {
            $updates['status'] = $data['status'];
        }

        if (array_key_exists('featured_image', $data)) {
            $updates['featured_image'] = $data['featured_image'] ?: null;
        }

        if (array_key_exists('category_id', $data)) {
            $updates['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_NO_FIELDS', __('api.no_fields_provided'), null, 400);
        }

        $updates['updated_at'] = now();

        DB::table('posts')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        // Update SEO metadata if provided
        if (array_key_exists('meta_title', $data) || array_key_exists('meta_description', $data) || array_key_exists('noindex', $data)) {
            $metaTitle = isset($data['meta_title']) ? trim($data['meta_title']) : null;
            $metaDescription = isset($data['meta_description']) ? trim($data['meta_description']) : null;
            $noindex = isset($data['noindex']) ? ($data['noindex'] ? 1 : 0) : 0;

            DB::statement(
                "INSERT INTO seo_metadata (entity_type, entity_id, tenant_id, meta_title, meta_description, noindex, created_at, updated_at)
                 VALUES ('post', ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), noindex = VALUES(noindex), updated_at = NOW()",
                [$id, $tenantId, $metaTitle, $metaDescription, $noindex]
            );
        }

        ActivityLog::create(['user_id' => $adminId, 'action' => 'admin_update_blog_post', 'details' => "Updated blog post #{$id}: " . ($data['title'] ?? $post->title)]);

        // Return updated post
        return $this->show($id);
    }

    /**
     * DELETE /api/v2/admin/blog/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $post = DB::selectOne(
            "SELECT id, title FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$post) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.blog_post_not_found'), null, 404);
        }

        DB::delete("DELETE FROM posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        ActivityLog::create(['user_id' => $adminId, 'action' => 'admin_delete_blog_post', 'details' => "Deleted blog post #{$id}: {$post->title}"]);

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/blog/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $post = DB::selectOne(
            "SELECT id, title, status FROM posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$post) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', __('api.blog_post_not_found'), null, 404);
        }

        $newStatus = $post->status === 'published' ? 'draft' : 'published';

        DB::update(
            "UPDATE posts SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newStatus, $id, $tenantId]
        );

        ActivityLog::create(['user_id' => $adminId, 'action' => 'admin_toggle_blog_status', 'details' => "Changed blog post #{$id} status: {$post->status} -> {$newStatus}"]);

        return $this->respondWithData([
            'id' => (int) $id,
            'status' => $newStatus,
        ]);
    }
}
