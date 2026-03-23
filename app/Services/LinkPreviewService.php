<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkPreviewService — Fetches, caches, and retrieves Open Graph metadata for URLs.
 *
 * Features:
 * - HTTP fetching with timeout and size limits
 * - OG tag parsing with <title>/<meta description> fallbacks
 * - Favicon extraction
 * - SHA-256 URL hashing for cache deduplication
 * - 7-day cache expiry in the link_previews table
 * - SSRF protection (private IP blocking, protocol allowlist)
 * - YouTube/Vimeo special handling (video embeds)
 * - Post and message link preview associations
 *
 * Note: link_previews is NOT tenant-scoped — URLs are global cache entries.
 * The post_link_previews and message_link_previews junction tables associate
 * cached previews with tenant-scoped posts/messages.
 */
class LinkPreviewService
{
    /** Maximum response body to read (500 KB) */
    private const MAX_BODY_SIZE = 512000;

    /** HTTP timeout in seconds */
    private const HTTP_TIMEOUT = 5;

    /** Cache duration in days */
    private const CACHE_DAYS = 7;

    /** User-Agent string for fetching */
    private const USER_AGENT = 'NexusBot/1.0 (+https://project-nexus.ie)';

    /** Private/reserved IP ranges for SSRF protection */
    private const PRIVATE_IP_RANGES = [
        '127.',
        '10.',
        '0.',
        '169.254.',
        '::1',
        'fc00:',
        'fe80:',
    ];

    /** Additional private CIDR ranges */
    private const PRIVATE_CIDR = [
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
    ];

    /**
     * Fetch (or return cached) link preview for a URL.
     *
     * @return array|null  The preview data, or null if the URL cannot be fetched/parsed.
     */
    public function fetchPreview(string $url): ?array
    {
        $url = trim($url);

        if (! $this->isAllowedUrl($url)) {
            return null;
        }

        // Normalize URL for hashing (lowercase scheme + host)
        $normalizedUrl = $this->normalizeUrl($url);
        $urlHash = hash('sha256', $normalizedUrl);

        // Check cache
        $cached = $this->getCachedPreview($urlHash);
        if ($cached !== null) {
            return $cached;
        }

        // SSRF check: resolve hostname and verify it's not a private IP
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return null;
        }

        if ($this->isPrivateHost($parsed['host'])) {
            return null;
        }

        // Fetch the URL with SSRF-safe redirect validation
        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions([
                    'stream' => true,
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'protocols' => ['http', 'https'],
                        // Recheck IP after every redirect to prevent DNS rebinding
                        'on_redirect' => function ($request, $response, $uri) {
                            $redirectHost = $uri->getHost();
                            if ($this->isPrivateHost($redirectHost)) {
                                throw new \RuntimeException("SSRF: redirect to private host blocked: {$redirectHost}");
                            }
                        },
                    ],
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            // Post-fetch SSRF check: verify the final resolved IP is not private
            // This catches DNS rebinding where the initial resolution was public
            // but the connection was made to a private IP
            $effectiveUrl = $response->effectiveUri()?->getHost() ?? $parsed['host'];
            if ($effectiveUrl !== $parsed['host'] && $this->isPrivateHost($effectiveUrl)) {
                Log::warning('LinkPreviewService: SSRF — final host resolved to private IP', [
                    'original' => $parsed['host'],
                    'effective' => $effectiveUrl,
                ]);
                return null;
            }

            // Check content type
            $contentType = $response->header('Content-Type') ?? '';
            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
                return null;
            }

            // Read body with size limit
            $body = substr($response->body(), 0, self::MAX_BODY_SIZE);
        } catch (\Exception $e) {
            Log::debug('LinkPreviewService: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        // Parse OG metadata
        $domain = $parsed['host'];
        $domain = preg_replace('/^www\./', '', $domain);

        $preview = $this->parseHtml($body, $url, $domain);

        if ($preview === null) {
            return null;
        }

        // YouTube / Vimeo special handling
        $preview = $this->handleVideoEmbeds($preview, $url, $domain);

        // Store in cache
        $preview['url'] = $url;
        $preview['url_hash'] = $urlHash;
        $preview['domain'] = $domain;

        $this->storePreview($preview);

        return $this->formatPreviewResponse($preview);
    }

    /**
     * Extract all URLs from a text string.
     *
     * @return string[]
     */
    public function extractUrls(string $text): array
    {
        // Strip HTML tags first to get plain text
        $plainText = strip_tags($text);

        $pattern = '/https?:\/\/[^\s<>\'")\]]+/i';
        preg_match_all($pattern, $plainText, $matches);

        // Deduplicate and trim trailing punctuation
        $urls = [];
        foreach ($matches[0] as $url) {
            // Remove trailing punctuation that's not part of the URL
            $url = rtrim($url, '.,;:!?)>');
            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Get cached link previews for a post.
     *
     * @return array[]
     */
    public function getPreviewsForPost(int $postId): array
    {
        return DB::table('post_link_previews as plp')
            ->join('link_previews as lp', 'plp.link_preview_id', '=', 'lp.id')
            ->where('plp.post_id', $postId)
            ->orderBy('plp.display_order')
            ->select([
                'lp.id',
                'lp.url',
                'lp.title',
                'lp.description',
                'lp.image_url',
                'lp.site_name',
                'lp.favicon_url',
                'lp.domain',
                'lp.content_type',
                'lp.embed_html',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Get cached link preview for a message.
     *
     * @return array[]
     */
    public function getPreviewsForMessage(int $messageId): array
    {
        return DB::table('message_link_previews as mlp')
            ->join('link_previews as lp', 'mlp.link_preview_id', '=', 'lp.id')
            ->where('mlp.message_id', $messageId)
            ->select([
                'lp.id',
                'lp.url',
                'lp.title',
                'lp.description',
                'lp.image_url',
                'lp.site_name',
                'lp.favicon_url',
                'lp.domain',
                'lp.content_type',
                'lp.embed_html',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Attach a link preview to a post.
     */
    public function attachPreviewToPost(int $postId, int $previewId, int $displayOrder = 0): void
    {
        DB::table('post_link_previews')->insertOrIgnore([
            'post_id' => $postId,
            'link_preview_id' => $previewId,
            'display_order' => $displayOrder,
        ]);
    }

    /**
     * Attach a link preview to a message.
     */
    public function attachPreviewToMessage(int $messageId, int $previewId): void
    {
        DB::table('message_link_previews')->insertOrIgnore([
            'message_id' => $messageId,
            'link_preview_id' => $previewId,
        ]);
    }

    /**
     * Process URLs in post content: extract, fetch previews, attach to post.
     *
     * @return array[] The link previews that were attached.
     */
    public function processPostUrls(int $postId, string $content): array
    {
        $urls = $this->extractUrls($content);
        if (empty($urls)) {
            return [];
        }

        $previews = [];
        foreach (array_slice($urls, 0, 3) as $order => $url) {
            $preview = $this->fetchPreview($url);
            if ($preview !== null) {
                // Get the preview ID from DB
                $urlHash = hash('sha256', $this->normalizeUrl($url));
                $row = DB::table('link_previews')->where('url_hash', $urlHash)->first();
                if ($row) {
                    $this->attachPreviewToPost($postId, (int) $row->id, $order);
                    $previews[] = $preview;
                }
            }
        }

        return $previews;
    }

    /**
     * Batch load link previews for multiple posts.
     *
     * @param int[] $postIds
     * @return array<int, array[]>  Keyed by post_id.
     */
    public function batchLoadPostPreviews(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $rows = DB::table('post_link_previews as plp')
            ->join('link_previews as lp', 'plp.link_preview_id', '=', 'lp.id')
            ->whereIn('plp.post_id', $postIds)
            ->orderBy('plp.display_order')
            ->select([
                'plp.post_id',
                'lp.url',
                'lp.title',
                'lp.description',
                'lp.image_url',
                'lp.site_name',
                'lp.favicon_url',
                'lp.domain',
                'lp.content_type',
                'lp.embed_html',
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $postId = (int) $row->post_id;
            $preview = (array) $row;
            unset($preview['post_id']);
            $result[$postId][] = $preview;
        }

        return $result;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Check if a URL is allowed for fetching (protocol allowlist).
     */
    private function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        // Only allow HTTP and HTTPS
        $scheme = strtolower($parsed['scheme']);
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Check if a hostname resolves to a private IP address (SSRF protection).
     */
    private function isPrivateHost(string $host): bool
    {
        // Resolve hostname to IP
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // If DNS resolution fails, block for safety
            return true;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP address is in a private/reserved range.
     */
    private function isPrivateIp(string $ip): bool
    {
        // Check simple prefix ranges
        foreach (self::PRIVATE_IP_RANGES as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }

        // Check CIDR ranges (172.16-31.x, 192.168.x)
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true; // Can't parse = block
        }

        foreach (self::PRIVATE_CIDR as [$start, $end]) {
            $startLong = ip2long($start);
            $endLong = ip2long($end);
            if ($ipLong >= $startLong && $ipLong <= $endLong) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a URL for consistent hashing.
     */
    private function normalizeUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (! $parsed) {
            return $url;
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host'] ?? '');
        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    /**
     * Check cache for a preview by URL hash.
     */
    private function getCachedPreview(string $urlHash): ?array
    {
        $row = DB::table('link_previews')
            ->where('url_hash', $urlHash)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $row) {
            return null;
        }

        return $this->formatPreviewResponse((array) $row);
    }

    /**
     * Store a preview in the cache table.
     */
    private function storePreview(array $preview): void
    {
        $expiresAt = now()->addDays(self::CACHE_DAYS);

        DB::table('link_previews')->updateOrInsert(
            ['url_hash' => $preview['url_hash']],
            [
                'url' => $preview['url'],
                'title' => $this->sanitizeText($preview['title'] ?? null, 500),
                'description' => $this->sanitizeText($preview['description'] ?? null, 2000),
                'image_url' => $preview['image_url'] ?? null,
                'site_name' => $this->sanitizeText($preview['site_name'] ?? null, 255),
                'favicon_url' => $preview['favicon_url'] ?? null,
                'domain' => $preview['domain'],
                'content_type' => $preview['content_type'] ?? 'website',
                'embed_html' => $preview['embed_html'] ?? null,
                'fetched_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Format a preview row for API response.
     */
    private function formatPreviewResponse(array $preview): array
    {
        return [
            'url' => $preview['url'],
            'title' => $preview['title'] ?? null,
            'description' => $preview['description'] ?? null,
            'image' => $preview['image_url'] ?? null,
            'image_url' => $preview['image_url'] ?? null,
            'siteName' => $preview['site_name'] ?? null,
            'site_name' => $preview['site_name'] ?? null,
            'favicon_url' => $preview['favicon_url'] ?? null,
            'domain' => $preview['domain'] ?? null,
            'content_type' => $preview['content_type'] ?? 'website',
            'embed_html' => $preview['embed_html'] ?? null,
        ];
    }

    /**
     * Parse HTML to extract OG metadata.
     */
    private function parseHtml(string $html, string $url, string $domain): ?array
    {
        $result = [
            'title' => null,
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'favicon_url' => null,
            'content_type' => 'website',
            'embed_html' => null,
        ];

        // Use DOMDocument for robust parsing
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $metaTags = $doc->getElementsByTagName('meta');

        foreach ($metaTags as $meta) {
            $property = $meta->getAttribute('property') ?: $meta->getAttribute('name');
            $content = $meta->getAttribute('content');

            if (! $property || ! $content) {
                continue;
            }

            switch ($property) {
                case 'og:title':
                    $result['title'] = $content;
                    break;
                case 'og:description':
                    $result['description'] = $content;
                    break;
                case 'og:image':
                    $result['image_url'] = $this->resolveUrl($content, $url);
                    break;
                case 'og:site_name':
                    $result['site_name'] = $content;
                    break;
                case 'og:type':
                    $result['content_type'] = $content;
                    break;
                case 'description':
                    // Fallback: <meta name="description">
                    if (! $result['description']) {
                        $result['description'] = $content;
                    }
                    break;
            }
        }

        // Fallback: <title> tag
        if (! $result['title']) {
            $titleTags = $doc->getElementsByTagName('title');
            if ($titleTags->length > 0) {
                $result['title'] = $titleTags->item(0)->textContent;
            }
        }

        // If we still have no title, this page has no useful metadata
        if (! $result['title']) {
            return null;
        }

        // Extract favicon
        $result['favicon_url'] = $this->extractFavicon($doc, $url);

        return $result;
    }

    /**
     * Extract favicon URL from HTML document.
     */
    private function extractFavicon(\DOMDocument $doc, string $baseUrl): ?string
    {
        $linkTags = $doc->getElementsByTagName('link');

        foreach ($linkTags as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if (in_array($rel, ['icon', 'shortcut icon', 'apple-touch-icon'], true)) {
                $href = $link->getAttribute('href');
                if ($href) {
                    return $this->resolveUrl($href, $baseUrl);
                }
            }
        }

        // Fallback: /favicon.ico
        $parsed = parse_url($baseUrl);
        if ($parsed && isset($parsed['scheme'], $parsed['host'])) {
            return $parsed['scheme'] . '://' . $parsed['host'] . '/favicon.ico';
        }

        return null;
    }

    /**
     * Resolve a potentially relative URL against a base URL.
     */
    private function resolveUrl(string $relative, string $base): string
    {
        // Already absolute
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        // Protocol-relative
        if (str_starts_with($relative, '//')) {
            $parsed = parse_url($base);
            return ($parsed['scheme'] ?? 'https') . ':' . $relative;
        }

        // Absolute path
        $parsed = parse_url($base);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if (str_starts_with($relative, '/')) {
            return $origin . $relative;
        }

        // Relative path
        $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '/';
        return $origin . rtrim($basePath, '/') . '/' . $relative;
    }

    /**
     * Handle YouTube/Vimeo special video embeds.
     */
    private function handleVideoEmbeds(array $preview, string $url, string $domain): array
    {
        // YouTube
        if (preg_match('/(?:youtube\.com|youtu\.be)/i', $domain)) {
            $videoId = $this->extractYouTubeVideoId($url);
            if ($videoId) {
                $preview['content_type'] = 'video';
                $preview['embed_html'] = 'https://www.youtube-nocookie.com/embed/' . $videoId;

                // Use YouTube's thumbnail if no OG image
                if (! $preview['image_url']) {
                    $preview['image_url'] = 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
                }
            }
        }

        // Vimeo
        if (preg_match('/vimeo\.com/i', $domain)) {
            $preview['content_type'] = 'video';
        }

        return $preview;
    }

    /**
     * Extract YouTube video ID from various URL formats.
     */
    private function extractYouTubeVideoId(string $url): ?string
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Sanitize text by stripping HTML tags and limiting length.
     */
    private function sanitizeText(?string $text, int $maxLength): ?string
    {
        if ($text === null) {
            return null;
        }

        // Strip HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Trim whitespace
        $text = trim($text);

        // Limit length
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text ?: null;
    }
}
