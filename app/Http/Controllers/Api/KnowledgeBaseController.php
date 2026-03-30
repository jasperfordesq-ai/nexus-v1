<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * KnowledgeBaseController — Community knowledge base articles.
 *
 * Native Eloquent implementation — no legacy delegation.
 */
class KnowledgeBaseController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly KnowledgeBaseService $kbService,
    ) {}

    /**
     * GET /api/v2/kb
     *
     * List knowledge base articles with cursor pagination.
     * Query: per_page, cursor, category_id, include_unpublished.
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit'          => $this->queryInt('per_page', 20, 1, 100),
            'published_only' => true,
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('category_id') !== null) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        // Admins can see unpublished articles
        if ($this->queryBool('include_unpublished') && $userId) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $role = $user->role ?? 'member';
            if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                $filters['published_only'] = false;
            }
        }

        $result = $this->kbService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/kb/{id}
     *
     * Get a single article by ID (increments view count).
     */
    public function show(int $id): JsonResponse
    {
        $article = $this->kbService->getById($id);

        if (! $article) {
            return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
        }

        // Check if unpublished — only admins can see
        if (! ($article['is_published'] ?? true)) {
            $userId = $this->getOptionalUserId();
            if (! $userId) {
                return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
            }
            $user = \Illuminate\Support\Facades\Auth::user();
            $role = $user->role ?? 'member';
            if (! in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
            }
        }

        // Include user's feedback if authenticated
        $userId = $this->getOptionalUserId();
        if ($userId) {
            $feedback = DB::table('knowledge_base_feedback')
                ->where('article_id', $id)
                ->where('user_id', $userId)
                ->where('tenant_id', $this->getTenantId())
                ->first();
            $article['my_feedback'] = $feedback ? (bool) $feedback->is_helpful : null;
        }

        return $this->respondWithData($article);
    }

    /**
     * GET /api/v2/kb/slug/{slug}
     *
     * Get a single article by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $article = DB::table('knowledge_base_articles')
            ->where('slug', $slug)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $article) {
            return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
        }

        $articleArr = (array) $article;

        // Check published status
        if (! ($articleArr['is_published'] ?? true)) {
            $userId = $this->getOptionalUserId();
            if (! $userId) {
                return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
            }
            $user = \Illuminate\Support\Facades\Auth::user();
            $role = $user->role ?? 'member';
            if (! in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
            }
        }

        // Increment view count
        DB::table('knowledge_base_articles')->where('id', $article->id)->where('tenant_id', \App\Core\TenantContext::getId())->increment('view_count');

        return $this->respondWithData($articleArr);
    }

    /**
     * GET /api/v2/kb/search
     *
     * Search knowledge base articles.
     * Query: q (required), limit.
     */
    public function search(): JsonResponse
    {
        $query = $this->query('q', '');
        if (empty(trim($query))) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.kb_search_query_required'), 'q', 400);
        }

        $limit = $this->queryInt('limit', 20, 1, 50);
        $results = $this->kbService->search($query, $limit);

        return $this->respondWithData($results);
    }

    /**
     * POST /api/v2/kb
     *
     * Create a new knowledge base article (admin only).
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->rateLimit('kb_create', 10, 60);

        $data = $this->getAllInput();

        if (empty(trim($data['title'] ?? ''))) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.title_required'), 'title', 400);
        }

        $articleId = $this->kbService->create($userId, $data);
        $article = $this->kbService->getById($articleId);

        return $this->respondWithData($article, null, 201);
    }

    /**
     * PUT /api/v2/kb/{id}
     *
     * Update a knowledge base article (admin only).
     */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('kb_update', 20, 60);

        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $existing = DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $existing) {
            return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
        }

        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = trim($data['title']);
        if (isset($data['slug'])) $updateData['slug'] = $data['slug'];
        if (isset($data['content'])) {
            $contentType = $data['content_type'] ?? ($existing->content_type ?? 'html');
            $updateData['content'] = ($contentType === 'html')
                ? \App\Helpers\HtmlSanitizer::sanitizeCms($data['content'])
                : $data['content'];
        }
        if (isset($data['content_type'])) $updateData['content_type'] = $data['content_type'];
        if (array_key_exists('category_id', $data)) $updateData['category_id'] = $data['category_id'];
        if (array_key_exists('parent_article_id', $data)) $updateData['parent_article_id'] = $data['parent_article_id'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = (int) $data['sort_order'];
        if (isset($data['is_published'])) $updateData['is_published'] = (bool) $data['is_published'];
        $updateData['updated_at'] = now();

        DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($updateData);

        $article = $this->kbService->getById($id);

        return $this->respondWithData($article);
    }

    /**
     * DELETE /api/v2/kb/{id}
     *
     * Delete a knowledge base article (admin only).
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('kb_delete', 10, 60);
        $tenantId = $this->getTenantId();

        // Check for child articles
        $childCount = DB::table('knowledge_base_articles')
            ->where('parent_article_id', $id)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($childCount > 0) {
            return $this->respondWithError('RESOURCE_CONFLICT', __('api.kb_cannot_delete_with_children'), null, 409);
        }

        $deleted = DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        if (! $deleted) {
            return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
        }

        // Clean up feedback
        DB::table('knowledge_base_feedback')
            ->where('article_id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v2/kb/{id}/feedback
     *
     * Submit "Was this helpful?" feedback for an article.
     */
    public function feedback(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $this->rateLimit('kb_feedback', 30, 60);
        $tenantId = $this->getTenantId();

        $isHelpful = $this->input('is_helpful');
        if ($isHelpful === null) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.kb_is_helpful_required'), 'is_helpful', 400);
        }

        $comment = $this->input('comment');

        // Check article exists
        $exists = DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $exists) {
            return $this->respondWithError('NOT_FOUND', __('api.kb_article_not_found'), null, 404);
        }

        // Upsert feedback
        if ($userId) {
            DB::table('knowledge_base_feedback')
                ->updateOrInsert(
                    ['article_id' => $id, 'user_id' => $userId, 'tenant_id' => $tenantId],
                    ['is_helpful' => (bool) $isHelpful, 'comment' => $comment, 'updated_at' => now()]
                );
        } else {
            DB::table('knowledge_base_feedback')->insert([
                'article_id' => $id,
                'user_id'    => null,
                'tenant_id'  => $tenantId,
                'is_helpful' => (bool) $isHelpful,
                'comment'    => $comment,
                'created_at' => now(),
            ]);
        }

        // Update article helpfulness stats
        $helpful = (int) DB::table('knowledge_base_feedback')
            ->where('article_id', $id)->where('tenant_id', $tenantId)->where('is_helpful', true)->count();
        $notHelpful = (int) DB::table('knowledge_base_feedback')
            ->where('article_id', $id)->where('tenant_id', $tenantId)->where('is_helpful', false)->count();

        DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['helpful_count' => $helpful, 'not_helpful_count' => $notHelpful]);

        return $this->respondWithData(['message' => 'Feedback submitted successfully']);
    }
}
