<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * KnowledgeBaseService — Laravel DI-based service for knowledge base articles.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\KnowledgeBaseService.
 * Provides CRUD, search, and view tracking for self-service articles.
 */
class KnowledgeBaseService
{
    /**
     * Get all published articles with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('knowledge_base_articles as a');

        if (($filters['published_only'] ?? true)) {
            $query->where('a.is_published', true);
        }
        if (! empty($filters['category_id'])) {
            $query->where('a.category_id', (int) $filters['category_id']);
        }
        if ($cursor !== null) {
            $query->where('a.id', '<', (int) base64_decode($cursor));
        }

        $query->orderByDesc('a.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single article by ID, incrementing view count.
     */
    public function getById(int $id): ?array
    {
        $article = DB::table('knowledge_base_articles')->find($id);
        if (! $article) {
            return null;
        }

        DB::table('knowledge_base_articles')->where('id', $id)->increment('view_count');

        return (array) $article;
    }

    /**
     * Search articles by keyword.
     */
    public function search(string $term, int $limit = 20): array
    {
        $like = '%' . $term . '%';

        return DB::table('knowledge_base_articles')
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('title', 'LIKE', $like)->orWhere('content', 'LIKE', $like))
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Create a new knowledge base article.
     */
    public function create(int $userId, array $data): int
    {
        return DB::table('knowledge_base_articles')->insertGetId([
            'title'        => trim($data['title']),
            'content'      => trim($data['content'] ?? ''),
            'category_id'  => $data['category_id'] ?? null,
            'author_id'    => $userId,
            'is_published' => $data['is_published'] ?? false,
            'view_count'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
