<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Helpers\UrlHelper;

/**
 * BlogPublicApiController - Public V2 API for blog/news (React frontend)
 *
 * Public read-only endpoints for published blog posts.
 *
 * Endpoints:
 * - GET /api/v2/blog             - List published posts (paginated)
 * - GET /api/v2/blog/categories  - List blog categories
 * - GET /api/v2/blog/{slug}      - Get single published post by slug
 */
class BlogPublicApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/blog
     *
     * Query params: per_page, cursor, search, category_id
     */
    public function index(): void
    {
        $tenantId = TenantContext::getId();

        $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 12)));
        $cursorParam = $_GET['cursor'] ?? null;
        $search = $_GET['search'] ?? null;
        $categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

        $conditions = ['p.tenant_id = ?', "p.status = 'published'"];
        $params = [$tenantId];

        // Cursor-based pagination (cursor = base64-encoded id)
        if ($cursorParam) {
            $cursorId = $this->decodeCursor($cursorParam);
            if ($cursorId !== null) {
                $conditions[] = 'p.id < ?';
                $params[] = (int) $cursorId;
            }
        }

        // Search filter
        if ($search) {
            $conditions[] = '(p.title LIKE ? OR p.excerpt LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Category filter
        if ($categoryId) {
            $conditions[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $where = implode(' AND ', $conditions);

        // Fetch perPage + 1 to detect has_more
        $items = Database::query(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image,
                    p.author_id, p.category_id, p.created_at, p.updated_at,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as author_name,
                    u.avatar_url as author_avatar,
                    c.name as category_name,
                    c.slug as category_slug,
                    c.color as category_color
             FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.created_at DESC
             LIMIT ?",
            array_merge($params, [$perPage + 1])
        )->fetchAll();

        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }

        $baseUrl = UrlHelper::getBaseUrl();
        $cursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $cursor = $this->encodeCursor($lastItem['id']);
        }

        // Estimate reading time (~200 words per minute)
        $formatted = array_map(function ($row) use ($baseUrl) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'slug' => $row['slug'] ?? '',
                'excerpt' => $row['excerpt'] ?? '',
                'featured_image' => $row['featured_image']
                    ? (str_starts_with($row['featured_image'], 'http') ? $row['featured_image'] : $baseUrl . '/' . ltrim($row['featured_image'], '/'))
                    : null,
                'published_at' => $row['created_at'],
                'created_at' => $row['created_at'],
                'views' => 0,
                'reading_time' => 3, // default estimate
                'author' => [
                    'id' => (int) ($row['author_id'] ?? 0),
                    'name' => trim($row['author_name'] ?? 'Unknown'),
                    'avatar' => $row['author_avatar']
                        ? (str_starts_with($row['author_avatar'], 'http') ? $row['author_avatar'] : $baseUrl . '/' . ltrim($row['author_avatar'], '/'))
                        : null,
                ],
                'category' => $row['category_name'] ? [
                    'id' => (int) $row['category_id'],
                    'name' => $row['category_name'],
                    'color' => $row['category_color'] ?? 'blue',
                ] : null,
            ];
        }, $items);

        $this->respondWithCollection($formatted, $cursor, $perPage, $hasMore);
    }

    /**
     * GET /api/v2/blog/categories
     */
    public function categories(): void
    {
        $tenantId = TenantContext::getId();

        $categories = Database::query(
            "SELECT c.id, c.name, c.slug, c.color,
                    COUNT(p.id) as post_count
             FROM categories c
             LEFT JOIN posts p ON p.category_id = c.id AND p.tenant_id = c.tenant_id AND p.status = 'published'
             WHERE c.tenant_id = ? AND c.type = 'blog'
             GROUP BY c.id, c.name, c.slug, c.color
             ORDER BY c.name ASC",
            [$tenantId]
        )->fetchAll();

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'] ?? '',
                'color' => $row['color'] ?? 'blue',
                'post_count' => (int) $row['post_count'],
            ];
        }, $categories);

        $this->respondWithData($formatted);
    }

    /**
     * GET /api/v2/blog/{slug}
     */
    public function show(string $slug): void
    {
        $tenantId = TenantContext::getId();

        $post = Database::query(
            "SELECT p.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as author_name,
                    u.avatar_url as author_avatar,
                    c.name as category_name,
                    c.slug as category_slug,
                    c.color as category_color
             FROM posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.slug = ? AND p.tenant_id = ? AND p.status = 'published'",
            [$slug, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Blog post not found', null, 404);
            return;
        }

        $baseUrl = UrlHelper::getBaseUrl();
        $content = $post['html_render'] ?? $post['content'] ?? '';
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $this->respondWithData([
            'id' => (int) $post['id'],
            'title' => $post['title'] ?? '',
            'slug' => $post['slug'] ?? '',
            'excerpt' => $post['excerpt'] ?? '',
            'content' => $content,
            'featured_image' => $post['featured_image']
                ? (str_starts_with($post['featured_image'], 'http') ? $post['featured_image'] : $baseUrl . '/' . ltrim($post['featured_image'], '/'))
                : null,
            'published_at' => $post['created_at'],
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at'] ?? null,
            'views' => 0,
            'reading_time' => $readingTime,
            'meta_title' => $post['title'] ?? null,
            'meta_description' => $post['excerpt'] ?? null,
            'author' => [
                'id' => (int) ($post['author_id'] ?? 0),
                'name' => trim($post['author_name'] ?? 'Unknown'),
                'avatar' => $post['author_avatar']
                    ? (str_starts_with($post['author_avatar'], 'http') ? $post['author_avatar'] : $baseUrl . '/' . ltrim($post['author_avatar'], '/'))
                    : null,
            ],
            'category' => $post['category_name'] ? [
                'id' => (int) $post['category_id'],
                'name' => $post['category_name'],
                'color' => $post['category_color'] ?? 'blue',
            ] : null,
        ]);
    }
}
