<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * BlogService — Eloquent-based service for blog/post operations.
 *
 * Uses the Post model (posts table) which stores blog articles.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class BlogService
{
    private const PLACEHOLDER_BLOG_SLUGS = [
        'aenean-sed-pulvinar-et-diam',
    ];

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
        $this->excludePlaceholderPosts($query);

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

        $query->orderByDesc('created_at');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $baseUrl = rtrim(env('APP_URL', 'https://api.project-nexus.ie'), '/');

        $formatted = $items->map(function (Post $post) use ($baseUrl) {
            return $this->formatPostSummary($post, $baseUrl);
        })->all();

        return [
            'items'    => $formatted,
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get published blog posts with offset pagination.
     *
     * @return array{items: array, total: int}
     */
    public function getPosts(int $tenantId, int $page = 1, int $perPage = 20, ?int $categoryId = null): array
    {
        $query = $this->post->newQuery()
            ->published()
            ->with(['author:id,first_name,last_name,avatar_url', 'category:id,name,slug,color']);
        $this->excludePlaceholderPosts($query);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $baseUrl = rtrim(env('APP_URL', 'https://api.project-nexus.ie'), '/');

        return [
            'items' => $items->map(fn (Post $p) => $this->formatPostSummary($p, $baseUrl))->all(),
            'total' => $total,
        ];
    }

    /**
     * Get a single blog post by slug.
     */
    public function getBySlug(string $slug, ?int $tenantId = null): ?array
    {
        $post = $this->post->newQuery()
            ->published()
            ->with(['author:id,first_name,last_name,avatar_url', 'category:id,name,slug,color'])
            ->whereNotIn('slug', self::PLACEHOLDER_BLOG_SLUGS)
            ->whereRaw("LOWER(CONCAT_WS(' ', title, excerpt, content, html_render)) NOT LIKE ?", ['%lorem ipsum%'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return null;
        }

        $baseUrl = rtrim(env('APP_URL', 'https://api.project-nexus.ie'), '/');
        $content = $post->html_render ?? $post->content ?? '';
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        return [
            'id'               => $post->id,
            'title'            => $post->title ?? '',
            'slug'             => $post->slug ?? '',
            'excerpt'          => $post->excerpt ?? '',
            'content'          => $content,
            'featured_image'   => $this->resolveImageUrl($post->featured_image, $baseUrl),
            'published_at'     => (string) $post->created_at,
            'created_at'       => (string) $post->created_at,
            'updated_at'       => $post->updated_at ? (string) $post->updated_at : null,
            'views'            => 0,
            'reading_time'     => $readingTime,
            'meta_title'       => $post->title ?? null,
            'meta_description' => $post->excerpt ?? null,
            'author'           => $this->formatAuthor($post->author, $baseUrl),
            'category'         => $post->category ? [
                'id'    => $post->category->id,
                'name'  => $post->category->name,
                'color' => $post->category->color ?? 'blue',
            ] : null,
        ];
    }

    /**
     * Get blog categories with post counts.
     */
    public function getCategories(?int $tenantId = null): array
    {
        return $this->category->newQuery()
            ->ofType('blog')
            ->active()
            ->withCount(['posts' => function ($q) {
                $q->where('status', 'published');
                $this->excludePlaceholderPosts($q);
            }])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'slug'       => $c->slug ?? '',
                'color'      => $c->color ?? 'blue',
                'post_count' => (int) ($c->posts_count ?? 0),
            ])
            ->all();
    }

    // -- Helpers ---------------------------------------------------------------

    private function excludePlaceholderPosts(Builder $query): void
    {
        $query
            ->whereNotIn('slug', self::PLACEHOLDER_BLOG_SLUGS)
            ->whereRaw("LOWER(CONCAT_WS(' ', title, excerpt, content, html_render)) NOT LIKE ?", ['%lorem ipsum%']);
    }

    private function formatPostSummary(Post $post, string $baseUrl): array
    {
        return [
            'id'             => $post->id,
            'title'          => $post->title ?? '',
            'slug'           => $post->slug ?? '',
            'excerpt'        => $post->excerpt ?? '',
            'featured_image' => $this->resolveImageUrl($post->featured_image, $baseUrl),
            'published_at'   => (string) $post->created_at,
            'created_at'     => (string) $post->created_at,
            'views'          => 0,
            'reading_time'   => 3,
            'author'         => $this->formatAuthor($post->author, $baseUrl),
            'category'       => $post->category ? [
                'id'    => $post->category->id,
                'name'  => $post->category->name,
                'color' => $post->category->color ?? 'blue',
            ] : null,
        ];
    }

    private function formatAuthor($author, string $baseUrl): array
    {
        if (! $author) {
            return ['id' => 0, 'name' => 'Unknown', 'avatar' => null];
        }

        return [
            'id'     => $author->id,
            'name'   => trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')),
            'avatar' => $this->resolveImageUrl($author->avatar_url, $baseUrl),
        ];
    }

    private function resolveImageUrl(?string $url, string $baseUrl): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        return $baseUrl . '/' . ltrim($url, '/');
    }
}
