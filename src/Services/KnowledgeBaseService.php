<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * KnowledgeBaseService - Self-service knowledge base articles
 *
 * Provides CRUD operations for knowledge base articles with nested
 * structure, search, "Was this helpful?" feedback, and view counting.
 *
 * @package Nexus\Services
 */
class KnowledgeBaseService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Get all articles (for listing), optionally filtered
     *
     * @param array $filters Keys: cursor, limit, category_id, parent_article_id, search, published_only
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAll(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $parentId = $filters['parent_article_id'] ?? null;
        $search = $filters['search'] ?? null;
        $publishedOnly = $filters['published_only'] ?? true;

        $params = [$tenantId];
        $where = ["a.tenant_id = ?"];

        if ($publishedOnly) {
            $where[] = "a.is_published = 1";
        }

        if ($categoryId !== null) {
            $where[] = "a.category_id = ?";
            $params[] = (int)$categoryId;
        }

        if ($parentId !== null) {
            if ($parentId === 0) {
                $where[] = "a.parent_article_id IS NULL";
            } else {
                $where[] = "a.parent_article_id = ?";
                $params[] = (int)$parentId;
            }
        }

        if ($search) {
            $where[] = "(a.title LIKE ? OR a.content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "a.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                a.id, a.title, a.slug, a.content_type, a.category_id,
                a.parent_article_id, a.sort_order, a.is_published,
                a.views_count, a.helpful_yes, a.helpful_no,
                a.created_by, a.created_at, a.updated_at,
                u.first_name as author_first_name,
                u.last_name as author_last_name,
                rc.name as category_name
            FROM knowledge_base_articles a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN resource_categories rc ON a.category_id = rc.id
            WHERE {$whereClause}
            ORDER BY a.sort_order ASC, a.created_at DESC
            LIMIT ?
        ";

        $articles = Database::query($sql, $params)->fetchAll();

        $hasMore = count($articles) > $limit;
        if ($hasMore) {
            array_pop($articles);
        }

        $nextCursor = null;
        if ($hasMore && !empty($articles)) {
            $lastItem = end($articles);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        $items = array_map(function ($a) {
            return self::formatArticleSummary($a);
        }, $articles);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single article by ID (full content)
     *
     * @param int $id
     * @param bool $incrementViews
     * @return array|null
     */
    public static function getById(int $id, bool $incrementViews = false): ?array
    {
        $tenantId = TenantContext::getId();

        $article = Database::query(
            "SELECT a.*,
                    u.first_name as author_first_name,
                    u.last_name as author_last_name,
                    u.avatar_url as author_avatar,
                    rc.name as category_name
             FROM knowledge_base_articles a
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN resource_categories rc ON a.category_id = rc.id
             WHERE a.id = ? AND a.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$article) {
            return null;
        }

        // Increment view count
        if ($incrementViews) {
            Database::query(
                "UPDATE knowledge_base_articles SET views_count = views_count + 1 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $article['views_count'] = (int)$article['views_count'] + 1;
        }

        // Get child articles
        $children = Database::query(
            "SELECT id, title, slug, sort_order FROM knowledge_base_articles
             WHERE parent_article_id = ? AND tenant_id = ? AND is_published = 1
             ORDER BY sort_order ASC, title ASC",
            [$id, $tenantId]
        )->fetchAll();

        return [
            'id' => (int)$article['id'],
            'title' => $article['title'],
            'slug' => $article['slug'],
            'content' => $article['content'],
            'content_type' => $article['content_type'],
            'category_id' => $article['category_id'] ? (int)$article['category_id'] : null,
            'category_name' => $article['category_name'],
            'parent_article_id' => $article['parent_article_id'] ? (int)$article['parent_article_id'] : null,
            'sort_order' => (int)$article['sort_order'],
            'is_published' => (bool)$article['is_published'],
            'views_count' => (int)$article['views_count'],
            'helpful_yes' => (int)$article['helpful_yes'],
            'helpful_no' => (int)$article['helpful_no'],
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'],
            'author' => $article['created_by'] ? [
                'id' => (int)$article['created_by'],
                'name' => trim(($article['author_first_name'] ?? '') . ' ' . ($article['author_last_name'] ?? '')),
                'avatar_url' => $article['author_avatar'] ?? null,
            ] : null,
            'children' => array_map(function ($c) {
                return [
                    'id' => (int)$c['id'],
                    'title' => $c['title'],
                    'slug' => $c['slug'],
                    'sort_order' => (int)$c['sort_order'],
                ];
            }, $children),
        ];
    }

    /**
     * Get an article by its slug
     *
     * @param string $slug
     * @param bool $incrementViews
     * @return array|null
     */
    public static function getBySlug(string $slug, bool $incrementViews = false): ?array
    {
        $tenantId = TenantContext::getId();

        $article = Database::query(
            "SELECT id FROM knowledge_base_articles WHERE slug = ? AND tenant_id = ?",
            [$slug, $tenantId]
        )->fetch();

        if (!$article) {
            return null;
        }

        return self::getById((int)$article['id'], $incrementViews);
    }

    /**
     * Create a new article (admin only)
     *
     * @param int $userId
     * @param array $data
     * @return int|null Article ID on success
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');
        $slug = trim($data['slug'] ?? '');
        $content = $data['content'] ?? '';
        $contentType = $data['content_type'] ?? 'html';
        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $parentArticleId = isset($data['parent_article_id']) ? (int)$data['parent_article_id'] : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $isPublished = isset($data['is_published']) ? (bool)$data['is_published'] : false;

        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Article title is required', 'title');
            return null;
        }

        // Auto-generate slug
        if (empty($slug)) {
            $slug = self::generateSlug($title);
        }

        // Ensure slug uniqueness within tenant
        $existing = Database::query(
            "SELECT id FROM knowledge_base_articles WHERE slug = ? AND tenant_id = ?",
            [$slug, $tenantId]
        )->fetch();

        if ($existing) {
            $slug = $slug . '-' . time();
        }

        // Validate content_type
        $validContentTypes = ['plain', 'html', 'markdown'];
        if (!in_array($contentType, $validContentTypes, true)) {
            $contentType = 'html';
        }

        // Sanitize HTML content
        if ($contentType === 'html' && !empty($content)) {
            $content = self::sanitizeHtml($content);
        }

        try {
            Database::query(
                "INSERT INTO knowledge_base_articles
                 (tenant_id, title, slug, content, content_type, category_id, parent_article_id, sort_order, is_published, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $title, $slug, $content, $contentType, $categoryId, $parentArticleId, $sortOrder, $isPublished ? 1 : 0, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("KB article creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create article');
            return null;
        }
    }

    /**
     * Update an existing article
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        $article = Database::query(
            "SELECT id FROM knowledge_base_articles WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$article) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found');
            return false;
        }

        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title)) {
                self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title cannot be empty', 'title');
                return false;
            }
            $updates[] = "title = ?";
            $params[] = $title;
        }

        if (isset($data['slug'])) {
            $updates[] = "slug = ?";
            $params[] = trim($data['slug']);
        }

        if (array_key_exists('content', $data)) {
            $content = $data['content'] ?? '';
            $contentType = $data['content_type'] ?? 'html';
            if ($contentType === 'html' && !empty($content)) {
                $content = self::sanitizeHtml($content);
            }
            $updates[] = "content = ?";
            $params[] = $content;
        }

        if (isset($data['content_type'])) {
            $validTypes = ['plain', 'html', 'markdown'];
            if (in_array($data['content_type'], $validTypes, true)) {
                $updates[] = "content_type = ?";
                $params[] = $data['content_type'];
            }
        }

        if (array_key_exists('category_id', $data)) {
            $updates[] = "category_id = ?";
            $params[] = $data['category_id'] !== null ? (int)$data['category_id'] : null;
        }

        if (array_key_exists('parent_article_id', $data)) {
            $updates[] = "parent_article_id = ?";
            $params[] = $data['parent_article_id'] !== null ? (int)$data['parent_article_id'] : null;
        }

        if (isset($data['sort_order'])) {
            $updates[] = "sort_order = ?";
            $params[] = (int)$data['sort_order'];
        }

        if (isset($data['is_published'])) {
            $updates[] = "is_published = ?";
            $params[] = (bool)$data['is_published'] ? 1 : 0;
        }

        if (empty($updates)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE knowledge_base_articles SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("KB article update failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update article');
            return false;
        }
    }

    /**
     * Delete an article
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Check for child articles
        $children = Database::query(
            "SELECT COUNT(*) as count FROM knowledge_base_articles WHERE parent_article_id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if ((int)$children['count'] > 0) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot delete article with child articles. Delete children first.');
            return false;
        }

        try {
            // Delete feedback
            Database::query(
                "DELETE FROM knowledge_base_feedback WHERE article_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            // Delete article
            Database::query(
                "DELETE FROM knowledge_base_articles WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("KB article deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete article');
            return false;
        }
    }

    /**
     * Submit "Was this helpful?" feedback
     *
     * @param int $articleId
     * @param int|null $userId
     * @param bool $isHelpful
     * @param string|null $comment
     * @return bool
     */
    public static function submitFeedback(int $articleId, ?int $userId, bool $isHelpful, ?string $comment = null): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify article exists
        $article = Database::query(
            "SELECT id FROM knowledge_base_articles WHERE id = ? AND tenant_id = ?",
            [$articleId, $tenantId]
        )->fetch();

        if (!$article) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Article not found');
            return false;
        }

        // Check if user already submitted feedback
        if ($userId) {
            $existing = Database::query(
                "SELECT id, is_helpful FROM knowledge_base_feedback WHERE article_id = ? AND user_id = ? AND tenant_id = ?",
                [$articleId, $userId, $tenantId]
            )->fetch();

            if ($existing) {
                // Update existing feedback
                $oldHelpful = (bool)$existing['is_helpful'];

                Database::query(
                    "UPDATE knowledge_base_feedback SET is_helpful = ?, comment = ? WHERE id = ?",
                    [$isHelpful ? 1 : 0, $comment, $existing['id']]
                );

                // Adjust counters
                if ($oldHelpful !== $isHelpful) {
                    if ($isHelpful) {
                        Database::query(
                            "UPDATE knowledge_base_articles SET helpful_yes = helpful_yes + 1, helpful_no = GREATEST(0, helpful_no - 1) WHERE id = ? AND tenant_id = ?",
                            [$articleId, $tenantId]
                        );
                    } else {
                        Database::query(
                            "UPDATE knowledge_base_articles SET helpful_no = helpful_no + 1, helpful_yes = GREATEST(0, helpful_yes - 1) WHERE id = ? AND tenant_id = ?",
                            [$articleId, $tenantId]
                        );
                    }
                }

                return true;
            }
        }

        try {
            Database::query(
                "INSERT INTO knowledge_base_feedback (article_id, user_id, tenant_id, is_helpful, comment, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$articleId, $userId, $tenantId, $isHelpful ? 1 : 0, $comment]
            );

            // Update counters
            if ($isHelpful) {
                Database::query(
                    "UPDATE knowledge_base_articles SET helpful_yes = helpful_yes + 1 WHERE id = ? AND tenant_id = ?",
                    [$articleId, $tenantId]
                );
            } else {
                Database::query(
                    "UPDATE knowledge_base_articles SET helpful_no = helpful_no + 1 WHERE id = ? AND tenant_id = ?",
                    [$articleId, $tenantId]
                );
            }

            return true;
        } catch (\Throwable $e) {
            error_log("KB feedback submission failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to submit feedback');
            return false;
        }
    }

    /**
     * Search articles within the knowledge base
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public static function search(string $query, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();
        $searchTerm = '%' . trim($query) . '%';

        $articles = Database::query(
            "SELECT a.id, a.title, a.slug, a.content_type, a.views_count,
                    a.helpful_yes, a.helpful_no, a.created_at,
                    rc.name as category_name,
                    SUBSTRING(a.content, 1, 200) as content_preview
             FROM knowledge_base_articles a
             LEFT JOIN resource_categories rc ON a.category_id = rc.id
             WHERE a.tenant_id = ? AND a.is_published = 1
               AND (a.title LIKE ? OR a.content LIKE ?)
             ORDER BY
                CASE WHEN a.title LIKE ? THEN 0 ELSE 1 END,
                a.views_count DESC
             LIMIT ?",
            [$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit]
        )->fetchAll();

        return array_map(function ($a) {
            return [
                'id' => (int)$a['id'],
                'title' => $a['title'],
                'slug' => $a['slug'],
                'content_preview' => strip_tags($a['content_preview'] ?? ''),
                'category_name' => $a['category_name'],
                'views_count' => (int)$a['views_count'],
                'helpfulness' => self::calculateHelpfulness((int)$a['helpful_yes'], (int)$a['helpful_no']),
            ];
        }, $articles);
    }

    /**
     * Format an article for summary listing (no content body)
     *
     * @param array $a
     * @return array
     */
    private static function formatArticleSummary(array $a): array
    {
        return [
            'id' => (int)$a['id'],
            'title' => $a['title'],
            'slug' => $a['slug'],
            'content_type' => $a['content_type'],
            'category_id' => $a['category_id'] ? (int)$a['category_id'] : null,
            'category_name' => $a['category_name'] ?? null,
            'parent_article_id' => $a['parent_article_id'] ? (int)$a['parent_article_id'] : null,
            'sort_order' => (int)$a['sort_order'],
            'is_published' => (bool)$a['is_published'],
            'views_count' => (int)$a['views_count'],
            'helpful_yes' => (int)$a['helpful_yes'],
            'helpful_no' => (int)$a['helpful_no'],
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'] ?? null,
            'author' => $a['created_by'] ? [
                'id' => (int)$a['created_by'],
                'name' => trim(($a['author_first_name'] ?? '') . ' ' . ($a['author_last_name'] ?? '')),
            ] : null,
        ];
    }

    /**
     * Calculate helpfulness percentage
     *
     * @param int $yes
     * @param int $no
     * @return float|null
     */
    private static function calculateHelpfulness(int $yes, int $no): ?float
    {
        $total = $yes + $no;
        if ($total === 0) {
            return null;
        }
        return round(($yes / $total) * 100, 1);
    }

    /**
     * Generate a URL-friendly slug
     *
     * @param string $title
     * @return string
     */
    private static function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'article';
    }

    /**
     * Basic HTML sanitization
     *
     * Allows safe HTML tags for rich content. Strips dangerous attributes
     * like onclick, onerror, etc.
     *
     * @param string $html
     * @return string
     */
    private static function sanitizeHtml(string $html): string
    {
        // Allow safe tags
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><strong><em><b><i><u><s>'
            . '<ul><ol><li><a><img><blockquote><pre><code><hr><table><thead><tbody>'
            . '<tr><th><td><div><span><sub><sup>';

        $html = strip_tags($html, $allowedTags);

        // Remove dangerous attributes (event handlers, javascript: urls)
        $html = preg_replace('/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]*/i', '', $html);
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript\s*:.*?\1/i', 'href="$1#$1"', $html);

        return $html;
    }
}
