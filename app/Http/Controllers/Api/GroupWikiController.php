<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Core\TenantContext;

/**
 * GroupWikiController — Wiki pages and revisions for groups.
 *
 * Uses direct DB queries (no dedicated service — keep it simple).
 */
class GroupWikiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/wiki
     *
     * List wiki pages for a group.
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $pages = DB::select(
            "SELECT wp.id, wp.title, wp.slug, wp.parent_id, wp.sort_order,
                    wp.is_published, wp.created_by, u.name AS author_name,
                    wp.created_at, wp.updated_at
             FROM group_wiki_pages wp
             LEFT JOIN users u ON u.id = wp.created_by AND u.tenant_id = ?
             WHERE wp.group_id = ? AND wp.tenant_id = ?
             ORDER BY wp.sort_order ASC, wp.title ASC",
            [$tenantId, $id, $tenantId]
        );

        // Nest author for frontend compatibility
        $pages = array_map(fn ($p) => $this->nestWikiAuthor($p), $pages);

        return $this->successResponse($pages);
    }

    /**
     * POST /api/v2/groups/{id}/wiki
     *
     * Create a new wiki page with an initial revision.
     */
    public function create(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $title = request()->input('title');
        $content = request()->input('content', '');
        $parentId = request()->input('parent_id');
        $sortOrder = (int) request()->input('sort_order', 0);
        $isPublished = (bool) request()->input('is_published', true);

        if (empty($title)) {
            return $this->errorResponse('Title is required', 422);
        }

        $slug = Str::slug($title);

        // Ensure slug uniqueness within the group
        $existing = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM group_wiki_pages WHERE group_id = ? AND tenant_id = ? AND slug = ?",
            [$id, $tenantId, $slug]
        );
        if ($existing && $existing->cnt > 0) {
            $slug .= '-' . time();
        }

        $now = now()->toDateTimeString();

        DB::insert(
            "INSERT INTO group_wiki_pages (group_id, tenant_id, title, slug, content, parent_id, sort_order, is_published, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, $tenantId, $title, $slug, $content, $parentId, $sortOrder, $isPublished ? 1 : 0, $userId, $now, $now]
        );

        $pageId = (int) DB::getPdo()->lastInsertId();

        // Create initial revision
        DB::insert(
            "INSERT INTO group_wiki_revisions (page_id, content, edited_by, change_summary, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [$pageId, $content, $userId, 'Initial version', $now]
        );

        $page = DB::selectOne(
            "SELECT wp.*, u.name AS author_name
             FROM group_wiki_pages wp
             LEFT JOIN users u ON u.id = wp.created_by AND u.tenant_id = ?
             WHERE wp.id = ? AND wp.tenant_id = ?",
            [$tenantId, $pageId, $tenantId]
        );

        return $this->successResponse($this->nestWikiAuthor($page), 201);
    }

    /**
     * GET /api/v2/groups/{id}/wiki/{slug}
     *
     * Show a single wiki page by slug with content and author info.
     */
    public function show(int $id, string $slug): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $page = DB::selectOne(
            "SELECT wp.*, u.name AS author_name
             FROM group_wiki_pages wp
             LEFT JOIN users u ON u.id = wp.created_by AND u.tenant_id = ?
             WHERE wp.group_id = ? AND wp.tenant_id = ? AND wp.slug = ?",
            [$tenantId, $id, $tenantId, $slug]
        );

        if (!$page) {
            return $this->errorResponse('Wiki page not found', 404);
        }

        return $this->successResponse($this->nestWikiAuthor($page));
    }

    /**
     * PUT /api/v2/groups/{id}/wiki/{pageId}
     *
     * Update a wiki page's content and create a new revision with the old content.
     */
    public function update(int $id, int $pageId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $page = DB::selectOne(
            "SELECT * FROM group_wiki_pages WHERE id = ? AND group_id = ? AND tenant_id = ?",
            [$pageId, $id, $tenantId]
        );

        if (!$page) {
            return $this->errorResponse('Wiki page not found', 404);
        }

        $content = request()->input('content', $page->content);
        $title = request()->input('title', $page->title);
        $isPublished = request()->input('is_published');
        $sortOrder = request()->input('sort_order');
        $changeSummary = request()->input('change_summary', '');

        $now = now()->toDateTimeString();

        // Save old content as a revision
        DB::insert(
            "INSERT INTO group_wiki_revisions (page_id, content, edited_by, change_summary, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [$pageId, $page->content, $userId, $changeSummary, $now]
        );

        // Update the page
        $updates = [
            'content' => $content,
            'title' => $title,
            'updated_at' => $now,
        ];

        if ($isPublished !== null) {
            $updates['is_published'] = (bool) $isPublished ? 1 : 0;
        }
        if ($sortOrder !== null) {
            $updates['sort_order'] = (int) $sortOrder;
        }

        DB::table('group_wiki_pages')
            ->where('id', $pageId)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        $updated = DB::selectOne(
            "SELECT wp.*, u.name AS author_name
             FROM group_wiki_pages wp
             LEFT JOIN users u ON u.id = wp.created_by AND u.tenant_id = ?
             WHERE wp.id = ? AND wp.tenant_id = ?",
            [$tenantId, $pageId, $tenantId]
        );

        return $this->successResponse($this->nestWikiAuthor($updated));
    }

    /**
     * DELETE /api/v2/groups/{id}/wiki/{pageId}
     *
     * Delete a wiki page and all its revisions.
     */
    public function destroy(int $id, int $pageId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        $page = DB::selectOne(
            "SELECT id FROM group_wiki_pages WHERE id = ? AND group_id = ? AND tenant_id = ?",
            [$pageId, $id, $tenantId]
        );

        if (!$page) {
            return $this->errorResponse('Wiki page not found', 404);
        }

        // Delete revisions first, then the page
        DB::delete(
            "DELETE FROM group_wiki_revisions WHERE page_id = ?",
            [$pageId]
        );

        DB::delete(
            "DELETE FROM group_wiki_pages WHERE id = ? AND tenant_id = ?",
            [$pageId, $tenantId]
        );

        return $this->successResponse(['message' => 'Wiki page deleted']);
    }

    /**
     * GET /api/v2/groups/{id}/wiki/{pageId}/revisions
     *
     * List revisions for a wiki page.
     */
    public function revisions(int $id, int $pageId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $tenantId = TenantContext::getId();

        // Verify page belongs to group
        $page = DB::selectOne(
            "SELECT id FROM group_wiki_pages WHERE id = ? AND group_id = ? AND tenant_id = ?",
            [$pageId, $id, $tenantId]
        );

        if (!$page) {
            return $this->errorResponse('Wiki page not found', 404);
        }

        $revisions = DB::select(
            "SELECT r.id, r.content, r.edited_by, u.name AS editor_name,
                    r.change_summary, r.created_at
             FROM group_wiki_revisions r
             LEFT JOIN users u ON u.id = r.edited_by AND u.tenant_id = ?
             WHERE r.page_id = ?
             ORDER BY r.created_at DESC",
            [$tenantId, $pageId]
        );

        // Nest editor for frontend compatibility
        $revisions = array_map(function ($r) {
            $arr = (array) $r;
            $arr['editor'] = [
                'id' => $arr['edited_by'] ?? null,
                'name' => $arr['editor_name'] ?? null,
            ];
            unset($arr['edited_by'], $arr['editor_name']);
            return $arr;
        }, $revisions);

        return $this->successResponse($revisions);
    }

    /**
     * Transform flat author fields into nested object for frontend.
     */
    private function nestWikiAuthor(object $row): array
    {
        $arr = (array) $row;
        $arr['author'] = [
            'id' => $arr['created_by'] ?? null,
            'name' => $arr['author_name'] ?? null,
        ];
        unset($arr['author_name']);
        return $arr;
    }
}
