<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * BlogService — Laravel DI-based service for blog/post operations.
 *
 * Uses the Post model (posts table) which stores blog articles.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class BlogService
{
    public function __construct(
        private readonly Post $post,
        private readonly Category $category,
    ) {}

    /**
     * Get published blog posts with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->post->newQuery()
            ->published()
            ->with(['author:id,first_name,last_name,avatar_url', 'category:id,name,slug,color']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('excerpt', 'LIKE', $term);
            });
        }

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single blog post by slug.
     */
    public function getBySlug(string $slug): ?Post
    {
        return $this->post->newQuery()
            ->published()
            ->with(['author:id,first_name,last_name,avatar_url', 'category:id,name,slug,color'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Get blog categories.
     */
    public function getCategories(): array
    {
        return $this->category->newQuery()
            ->ofType('blog')
            ->active()
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
