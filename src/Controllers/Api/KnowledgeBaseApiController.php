<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\KnowledgeBaseService;
use Nexus\Core\ApiErrorCodes;

/**
 * KnowledgeBaseApiController - V2 API for knowledge base articles
 *
 * Endpoints:
 * - GET    /api/v2/kb                    - List articles (paginated)
 * - GET    /api/v2/kb/search             - Search articles
 * - GET    /api/v2/kb/{id}               - Get article by ID
 * - GET    /api/v2/kb/slug/{slug}        - Get article by slug
 * - POST   /api/v2/kb                    - Create article (admin)
 * - PUT    /api/v2/kb/{id}               - Update article (admin)
 * - DELETE /api/v2/kb/{id}               - Delete article (admin)
 * - POST   /api/v2/kb/{id}/feedback      - Submit helpfulness feedback
 *
 * @package Nexus\Controllers\Api
 */
class KnowledgeBaseApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/kb
     *
     * List knowledge base articles.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     * - category_id: int (filter by category)
     * - parent_article_id: int (filter by parent, 0 for root)
     * - search: string (full-text search)
     * - published_only: bool (default true, admin can set false)
     *
     * Response: 200 OK with article list
     */
    public function index(): void
    {
        // Optional auth - articles may be publicly readable
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'published_only' => true,
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        if ($this->query('category_id') !== null) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        if ($this->query('parent_article_id') !== null) {
            $filters['parent_article_id'] = $this->queryInt('parent_article_id');
        }

        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }

        // Admins can see unpublished articles
        if ($this->queryBool('include_unpublished') && $userId) {
            $role = $this->getAuthenticatedUserRole() ?? 'member';
            if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                $filters['published_only'] = false;
            }
        }

        $result = KnowledgeBaseService::getAll($filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/kb/search
     *
     * Search knowledge base articles.
     *
     * Query Parameters:
     * - q: string (search query, required)
     * - limit: int (default 20, max 50)
     *
     * Response: 200 OK with search results
     */
    public function search(): void
    {
        $query = $this->query('q', '');

        if (empty(trim($query))) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Search query is required',
                'q',
                400
            );
            return;
        }

        $limit = $this->queryInt('limit', 20, 1, 50);

        $results = KnowledgeBaseService::search($query, $limit);

        $this->respondWithData($results);
    }

    /**
     * GET /api/v2/kb/{id}
     *
     * Get a single article by ID.
     *
     * Response: 200 OK with full article data
     */
    public function show(int $id): void
    {
        $article = KnowledgeBaseService::getById($id, true); // increment views

        if (!$article) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
            return;
        }

        // Check if unpublished — only admins can see
        if (!$article['is_published']) {
            $userId = $this->getOptionalUserId();
            if (!$userId) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
                return;
            }
            $role = $this->getAuthenticatedUserRole() ?? 'member';
            if (!in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
                return;
            }
        }

        // Include user's feedback if authenticated
        $userId = $this->getOptionalUserId();
        if ($userId) {
            $feedback = \Nexus\Core\Database::query(
                "SELECT is_helpful FROM knowledge_base_feedback WHERE article_id = ? AND user_id = ? AND tenant_id = ?",
                [$id, $userId, \Nexus\Core\TenantContext::getId()]
            )->fetch();

            $article['my_feedback'] = $feedback ? (bool)$feedback['is_helpful'] : null;
        }

        $this->respondWithData($article);
    }

    /**
     * GET /api/v2/kb/slug/{slug}
     *
     * Get a single article by slug.
     *
     * Response: 200 OK with full article data
     */
    public function showBySlug(string $slug): void
    {
        $article = KnowledgeBaseService::getBySlug($slug, true);

        if (!$article) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
            return;
        }

        if (!$article['is_published']) {
            $userId = $this->getOptionalUserId();
            if (!$userId) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
                return;
            }
            $role = $this->getAuthenticatedUserRole() ?? 'member';
            if (!in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found', null, 404);
                return;
            }
        }

        $this->respondWithData($article);
    }

    /**
     * POST /api/v2/kb
     *
     * Create a new knowledge base article (admin only).
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "slug": "string (optional - auto-generated)",
     *   "content": "string",
     *   "content_type": "string (html|markdown|plain, default html)",
     *   "category_id": "int|null (optional)",
     *   "parent_article_id": "int|null (optional)",
     *   "sort_order": "int (optional, default 0)",
     *   "is_published": "bool (optional, default false)"
     * }
     *
     * Response: 201 Created with article data
     */
    public function store(): void
    {
        $userId = $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('kb_create', 10, 60);

        $data = $this->getAllInput();

        $articleId = KnowledgeBaseService::create($userId, $data);

        if ($articleId === null) {
            $this->respondWithErrors(KnowledgeBaseService::getErrors(), 422);
        }

        $article = KnowledgeBaseService::getById($articleId);

        $this->respondWithData($article, null, 201);
    }

    /**
     * PUT /api/v2/kb/{id}
     *
     * Update a knowledge base article (admin only).
     *
     * Response: 200 OK with updated article data
     */
    public function update(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('kb_update', 20, 60);

        $data = $this->getAllInput();

        $success = KnowledgeBaseService::update($id, $data);

        if (!$success) {
            $errors = KnowledgeBaseService::getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $article = KnowledgeBaseService::getById($id);

        $this->respondWithData($article);
    }

    /**
     * DELETE /api/v2/kb/{id}
     *
     * Delete a knowledge base article (admin only).
     *
     * Response: 204 No Content
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('kb_delete', 10, 60);

        $success = KnowledgeBaseService::delete($id);

        if (!$success) {
            $errors = KnowledgeBaseService::getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) { $status = 409; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/kb/{id}/feedback
     *
     * Submit "Was this helpful?" feedback for an article.
     *
     * Request Body (JSON):
     * {
     *   "is_helpful": "bool (required)",
     *   "comment": "string (optional)"
     * }
     *
     * Response: 200 OK
     */
    public function feedback(int $id): void
    {
        $userId = $this->getOptionalUserId();
        $this->rateLimit('kb_feedback', 30, 60);

        $isHelpful = $this->input('is_helpful');

        if ($isHelpful === null) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'is_helpful field is required',
                'is_helpful',
                400
            );
            return;
        }

        $comment = $this->input('comment');

        $success = KnowledgeBaseService::submitFeedback($id, $userId, (bool)$isHelpful, $comment);

        if (!$success) {
            $errors = KnowledgeBaseService::getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData(['message' => 'Feedback submitted successfully']);
    }
}
