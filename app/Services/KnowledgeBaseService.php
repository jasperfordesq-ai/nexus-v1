<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * KnowledgeBaseService — Laravel DI-based service for knowledge base articles.
 *
 * Provides CRUD, search, feedback, and view tracking for self-service articles.
 */
class KnowledgeBaseService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all articles with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit  = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $tenantId = TenantContext::getId();

        $query = DB::table('knowledge_base_articles as a')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->leftJoin('resource_categories as rc', 'a.category_id', '=', 'rc.id')
            ->where('a.tenant_id', $tenantId);

        if ($filters['published_only'] ?? true) {
            $query->where('a.is_published', true);
        }

        if (! empty($filters['category_id'])) {
            $query->where('a.category_id', (int) $filters['category_id']);
        }

        if (isset($filters['parent_article_id'])) {
            $parentId = $filters['parent_article_id'];
            if ($parentId === 0) {
                $query->whereNull('a.parent_article_id');
            } else {
                $query->where('a.parent_article_id', (int) $parentId);
            }
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('a.title', 'LIKE', $term)
                  ->orWhere('a.content', 'LIKE', $term);
            });
        }

        if ($cursor !== null) {
            $query->where('a.id', '<', (int) base64_decode($cursor));
        }

        $query->orderBy('a.sort_order')->orderByDesc('a.created_at');

        $query->select(
            'a.id', 'a.title', 'a.slug', 'a.content_type', 'a.category_id',
            'a.parent_article_id', 'a.sort_order', 'a.is_published',
            'a.views_count', 'a.helpful_yes', 'a.helpful_no',
            'a.created_by', 'a.created_at', 'a.updated_at',
            'u.first_name as author_first_name',
            'u.last_name as author_last_name',
            'rc.name as category_name'
        );

        $items   = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $formatted = $items->map(function ($a) {
            return [
                'id'                => (int) $a->id,
                'title'             => $a->title,
                'slug'              => $a->slug,
                'content_type'      => $a->content_type,
                'category_id'       => $a->category_id ? (int) $a->category_id : null,
                'category_name'     => $a->category_name ?? null,
                'parent_article_id' => $a->parent_article_id ? (int) $a->parent_article_id : null,
                'sort_order'        => (int) $a->sort_order,
                'is_published'      => (bool) $a->is_published,
                'views_count'       => (int) $a->views_count,
                'helpful_yes'       => (int) $a->helpful_yes,
                'helpful_no'        => (int) $a->helpful_no,
                'created_at'        => $a->created_at,
                'updated_at'        => $a->updated_at ?? null,
                'author'            => $a->created_by ? [
                    'id'   => (int) $a->created_by,
                    'name' => trim(($a->author_first_name ?? '') . ' ' . ($a->author_last_name ?? '')),
                ] : null,
            ];
        })->all();

        return [
            'items'    => $formatted,
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single article by ID with full content.
     */
    public function getById(int $id, bool $incrementViews = true): ?array
    {
        $tenantId = TenantContext::getId();

        $article = DB::table('knowledge_base_articles as a')
            ->leftJoin('users as u', 'a.created_by', '=', 'u.id')
            ->leftJoin('resource_categories as rc', 'a.category_id', '=', 'rc.id')
            ->where('a.id', $id)
            ->where('a.tenant_id', $tenantId)
            ->select(
                'a.*',
                'u.first_name as author_first_name',
                'u.last_name as author_last_name',
                'u.avatar_url as author_avatar',
                'rc.name as category_name'
            )
            ->first();

        if (! $article) {
            return null;
        }

        // Increment view count
        if ($incrementViews) {
            DB::table('knowledge_base_articles')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->increment('views_count');
        }

        // Get child articles
        $children = DB::table('knowledge_base_articles')
            ->where('parent_article_id', $id)
            ->where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->select('id', 'title', 'slug', 'sort_order')
            ->get();

        // Get attachments
        $attachments = DB::table('knowledge_base_attachments')
            ->where('article_id', $id)
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'         => (int) $a->id,
                'file_name'  => $a->file_name,
                'file_url'   => $a->file_url,
                'mime_type'  => $a->mime_type,
                'file_size'  => (int) $a->file_size,
                'sort_order' => (int) $a->sort_order,
                'created_at' => $a->created_at,
            ])
            ->all();

        return [
            'id'                => (int) $article->id,
            'title'             => $article->title,
            'slug'              => $article->slug,
            'content'           => $article->content,
            'content_type'      => $article->content_type,
            'category_id'       => $article->category_id ? (int) $article->category_id : null,
            'category_name'     => $article->category_name,
            'parent_article_id' => $article->parent_article_id ? (int) $article->parent_article_id : null,
            'sort_order'        => (int) $article->sort_order,
            'is_published'      => (bool) $article->is_published,
            'views_count'       => (int) $article->views_count + ($incrementViews ? 1 : 0),
            'helpful_yes'       => (int) $article->helpful_yes,
            'helpful_no'        => (int) $article->helpful_no,
            'created_at'        => $article->created_at,
            'updated_at'        => $article->updated_at,
            'author'            => $article->created_by ? [
                'id'         => (int) $article->created_by,
                'name'       => trim(($article->author_first_name ?? '') . ' ' . ($article->author_last_name ?? '')),
                'avatar_url' => $article->author_avatar ?? null,
            ] : null,
            'children' => $children->map(function ($c) {
                return [
                    'id'         => (int) $c->id,
                    'title'      => $c->title,
                    'slug'       => $c->slug,
                    'sort_order' => (int) $c->sort_order,
                ];
            })->all(),
            'attachments' => $attachments,
        ];
    }

    /**
     * Search articles by keyword.
     */
    public function search(string $term, int $limit = 20): array
    {
        $like     = '%' . $term . '%';
        $tenantId = TenantContext::getId();

        return DB::table('knowledge_base_articles as a')
            ->leftJoin('resource_categories as rc', 'a.category_id', '=', 'rc.id')
            ->where('a.tenant_id', $tenantId)
            ->where('a.is_published', true)
            ->where(function ($q) use ($like) {
                $q->where('a.title', 'LIKE', $like)
                  ->orWhere('a.content', 'LIKE', $like);
            })
            ->orderByRaw('CASE WHEN a.title LIKE ? THEN 0 ELSE 1 END', [$like])
            ->orderByDesc('a.views_count')
            ->limit($limit)
            ->select(
                'a.id', 'a.title', 'a.slug', 'a.content_type', 'a.views_count',
                'a.helpful_yes', 'a.helpful_no', 'a.created_at',
                'rc.name as category_name',
                DB::raw('SUBSTRING(a.content, 1, 200) as content_preview')
            )
            ->get()
            ->map(function ($a) {
                $total = (int) $a->helpful_yes + (int) $a->helpful_no;
                return [
                    'id'              => (int) $a->id,
                    'title'           => $a->title,
                    'slug'            => $a->slug,
                    'content_preview' => strip_tags($a->content_preview ?? ''),
                    'category_name'   => $a->category_name,
                    'views_count'     => (int) $a->views_count,
                    'helpfulness'     => $total > 0 ? round(((int) $a->helpful_yes / $total) * 100, 1) : null,
                ];
            })
            ->all();
    }

    /**
     * Create a new knowledge base article.
     */
    public function create(int $userId, array $data): int
    {
        $tenantId = TenantContext::getId();

        $title       = trim($data['title']);
        $slug        = trim($data['slug'] ?? '');
        $content     = $data['content'] ?? '';
        $contentType = $data['content_type'] ?? 'html';

        // Auto-generate slug
        if (empty($slug)) {
            $slug = $this->generateSlug($title);
        }

        // Ensure slug uniqueness within tenant
        $slugExists = DB::table('knowledge_base_articles')
            ->where('slug', $slug)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($slugExists) {
            $slug = $slug . '-' . time();
        }

        // Sanitize HTML content
        if ($contentType === 'html' && ! empty($content)) {
            $content = $this->sanitizeHtml($content);
        }

        return DB::table('knowledge_base_articles')->insertGetId([
            'tenant_id'         => $tenantId,
            'title'             => $title,
            'slug'              => $slug,
            'content'           => $content,
            'content_type'      => in_array($contentType, ['plain', 'html', 'markdown'], true) ? $contentType : 'html',
            'category_id'       => $data['category_id'] ?? null,
            'parent_article_id' => isset($data['parent_article_id']) ? (int) $data['parent_article_id'] : null,
            'sort_order'        => (int) ($data['sort_order'] ?? 0),
            'is_published'      => $data['is_published'] ?? false,
            'created_by'        => $userId,
            'views_count'       => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Delete a knowledge base article.
     */
    public function delete(int $id): bool
    {
        $tenantId = TenantContext::getId();

        // Check for child articles
        $childCount = DB::table('knowledge_base_articles')
            ->where('parent_article_id', $id)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($childCount > 0) {
            $this->errors[] = ['code' => 'CONFLICT', 'message' => 'Cannot delete article with child articles. Delete children first.'];
            return false;
        }

        // Delete feedback
        DB::table('knowledge_base_feedback')
            ->where('article_id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        // Delete article
        return (bool) DB::table('knowledge_base_articles')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();
    }

    /**
     * Submit "Was this helpful?" feedback.
     */
    public function submitFeedback(int $articleId, ?int $userId, bool $isHelpful, ?string $comment = null): bool
    {
        $tenantId = TenantContext::getId();

        $articleExists = DB::table('knowledge_base_articles')
            ->where('id', $articleId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $articleExists) {
            return false;
        }

        // Check if user already submitted feedback
        if ($userId) {
            $existing = DB::table('knowledge_base_feedback')
                ->where('article_id', $articleId)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existing) {
                $oldHelpful = (bool) $existing->is_helpful;

                DB::table('knowledge_base_feedback')
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['is_helpful' => $isHelpful ? 1 : 0, 'comment' => $comment]);

                // Adjust counters
                if ($oldHelpful !== $isHelpful) {
                    if ($isHelpful) {
                        DB::table('knowledge_base_articles')
                            ->where('id', $articleId)->where('tenant_id', $tenantId)
                            ->update([
                                'helpful_yes' => DB::raw('helpful_yes + 1'),
                                'helpful_no'  => DB::raw('GREATEST(0, helpful_no - 1)'),
                            ]);
                    } else {
                        DB::table('knowledge_base_articles')
                            ->where('id', $articleId)->where('tenant_id', $tenantId)
                            ->update([
                                'helpful_no'  => DB::raw('helpful_no + 1'),
                                'helpful_yes' => DB::raw('GREATEST(0, helpful_yes - 1)'),
                            ]);
                    }
                }

                return true;
            }
        }

        DB::table('knowledge_base_feedback')->insert([
            'article_id'  => $articleId,
            'user_id'     => $userId,
            'tenant_id'   => $tenantId,
            'is_helpful'  => $isHelpful ? 1 : 0,
            'comment'     => $comment,
            'created_at'  => now(),
        ]);

        // Update counters
        $column = $isHelpful ? 'helpful_yes' : 'helpful_no';
        DB::table('knowledge_base_articles')
            ->where('id', $articleId)
            ->where('tenant_id', $tenantId)
            ->increment($column);

        return true;
    }

    /**
     * Generate a URL-friendly slug.
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'article';
    }

    /**
     * HTML sanitization — delegates to the centralized HtmlSanitizer helper.
     */
    private function sanitizeHtml(string $html): string
    {
        return \App\Helpers\HtmlSanitizer::sanitizeCms($html);
    }
}
