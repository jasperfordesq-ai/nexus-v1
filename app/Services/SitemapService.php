<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SitemapService — Generates XML sitemaps per the Sitemaps 0.9 protocol.
 *
 * Multi-tenant aware: produces a sitemap index listing per-tenant sitemaps,
 * and per-tenant sitemaps containing all public content URLs.
 *
 * Content URLs point to the React frontend (app.project-nexus.ie).
 * Sitemap XML is served from the API backend.
 *
 * Respects feature/module gates per tenant — only includes content types
 * that the tenant has enabled.
 */
class SitemapService
{
    private const CACHE_PREFIX = 'sitemap:';
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_URLS_PER_SITEMAP = 50000;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Generate the sitemap index XML listing all active tenant sitemaps.
     */
    public function generateIndex(): string
    {
        return Cache::remember(self::CACHE_PREFIX . 'index', self::CACHE_TTL, function () {
            $tenants = DB::select(
                "SELECT id, slug, domain, updated_at FROM tenants WHERE is_active = 1 ORDER BY id"
            );

            // Use the frontend URL for sub-sitemap references so Google finds
            // them on the same domain as the content URLs.
            $frontendBase = rtrim(env('FRONTEND_URL', 'https://app.project-nexus.ie'), '/');
            $sitemaps = [];

            foreach ($tenants as $tenant) {
                $slug = !empty($tenant->slug) ? $tenant->slug : 'main';
                // Tenants with custom domains get their sitemap referenced from that domain
                $base = !empty($tenant->domain) ? ('https://' . rtrim($tenant->domain, '/')) : $frontendBase;
                $sitemaps[] = [
                    'loc' => "{$base}/sitemap-{$slug}.xml",
                    'lastmod' => $this->formatDate($tenant->updated_at),
                ];
            }

            return $this->buildSitemapIndexXml($sitemaps);
        });
    }

    /**
     * Generate sitemap XML for a specific tenant.
     * If $overrideBaseUrl is provided, use it instead of the tenant's domain.
     * This lets any domain serve a sitemap with its own URLs.
     */
    public function generateForTenant(int $tenantId, ?string $overrideBaseUrl = null): string
    {
        $cacheKey = self::CACHE_PREFIX . "tenant:{$tenantId}" . ($overrideBaseUrl ? ':' . md5($overrideBaseUrl) : '');
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId, $overrideBaseUrl) {
            $tenant = DB::selectOne(
                "SELECT id, slug, domain, features, configuration FROM tenants WHERE id = ? AND is_active = 1",
                [$tenantId]
            );

            if (!$tenant) {
                return $this->buildUrlsetXml([]);
            }

            return $this->buildTenantSitemap($tenant, $overrideBaseUrl);
        });
    }

    /**
     * Generate a combined sitemap for app.project-nexus.ie.
     * Includes all tenants that don't have their own custom domain,
     * with URLs prefixed by /{tenant-slug}/.
     */
    public function generateForAppDomain(): string
    {
        return Cache::remember(self::CACHE_PREFIX . 'app-domain', self::CACHE_TTL, function () {
            $tenants = DB::select(
                "SELECT id, slug, domain, features, configuration FROM tenants WHERE is_active = 1 ORDER BY id"
            );

            $frontendBase = rtrim(env('FRONTEND_URL', 'https://app.project-nexus.ie'), '/');
            $allUrls = [];

            foreach ($tenants as $tenant) {
                // Skip tenants with their own custom domain — they have their own sitemap
                if (!empty($tenant->domain)) {
                    continue;
                }

                $baseUrl = empty($tenant->slug)
                    ? $frontendBase
                    : $frontendBase . '/' . $tenant->slug;

                $urls = $this->collectTenantUrls($tenant, $baseUrl);
                $allUrls = array_merge($allUrls, $urls);
            }

            if (count($allUrls) > self::MAX_URLS_PER_SITEMAP) {
                $allUrls = array_slice($allUrls, 0, self::MAX_URLS_PER_SITEMAP);
            }

            return $this->buildUrlsetXml($allUrls);
        });
    }

    /**
     * Resolve a tenant ID from a slug (for the public controller).
     */
    public function resolveTenantBySlug(string $slug): ?int
    {
        if ($slug === 'main') {
            $tenant = DB::selectOne(
                "SELECT id FROM tenants WHERE (slug IS NULL OR slug = '') AND is_active = 1 ORDER BY id LIMIT 1"
            );
        } else {
            $tenant = DB::selectOne(
                "SELECT id FROM tenants WHERE slug = ? AND is_active = 1",
                [$slug]
            );
        }

        return $tenant ? (int) $tenant->id : null;
    }

    /**
     * Clear cached sitemaps. If tenantId is null, clears everything.
     */
    public function clearCache(?int $tenantId = null): int
    {
        $cleared = 0;

        if ($tenantId !== null) {
            Cache::forget(self::CACHE_PREFIX . "tenant:{$tenantId}");
            $cleared++;
        } else {
            // Clear all tenant caches
            $tenants = DB::select("SELECT id FROM tenants WHERE is_active = 1");
            foreach ($tenants as $tenant) {
                Cache::forget(self::CACHE_PREFIX . "tenant:{$tenant->id}");
                $cleared++;
            }
        }

        Cache::forget(self::CACHE_PREFIX . 'index');
        Cache::forget(self::CACHE_PREFIX . 'app-domain');
        $cleared++;

        return $cleared;
    }

    /**
     * Get statistics about what would be in the sitemap for a tenant.
     */
    public function getStats(int $tenantId): array
    {
        $tenant = DB::selectOne(
            "SELECT id, slug, domain, features, configuration FROM tenants WHERE id = ? AND is_active = 1",
            [$tenantId]
        );

        if (!$tenant) {
            return ['total_urls' => 0, 'content_types' => []];
        }

        $baseUrl = $this->resolveBaseUrl($tenant);
        $stats = [];

        $contentMethods = $this->getContentMethods($tenant);
        foreach ($contentMethods as $label => $method) {
            $urls = $method($tenantId, $baseUrl);
            $stats[$label] = count($urls);
        }

        // Static pages
        $staticUrls = $this->getStaticPageUrls($tenant, $baseUrl);
        $stats['static_pages'] = count($staticUrls);

        return [
            'total_urls' => array_sum($stats),
            'content_types' => $stats,
        ];
    }

    // =========================================================================
    // Core sitemap builder
    // =========================================================================

    private function buildTenantSitemap(object $tenant, ?string $overrideBaseUrl = null): string
    {
        $urls = $this->collectTenantUrls($tenant, $overrideBaseUrl);

        // Enforce protocol limit
        if (count($urls) > self::MAX_URLS_PER_SITEMAP) {
            Log::warning('Sitemap URL count exceeds limit', [
                'tenant_id' => (int) $tenant->id,
                'url_count' => count($urls),
                'max' => self::MAX_URLS_PER_SITEMAP,
            ]);
            $urls = array_slice($urls, 0, self::MAX_URLS_PER_SITEMAP);
        }

        return $this->buildUrlsetXml($urls);
    }

    /**
     * Collect all URLs for a tenant. Reusable by both single-tenant and app-domain sitemaps.
     */
    private function collectTenantUrls(object $tenant, ?string $overrideBaseUrl = null): array
    {
        $baseUrl = $overrideBaseUrl ?? $this->resolveBaseUrl($tenant);
        $tenantId = (int) $tenant->id;
        $urls = [];

        $urls = array_merge($urls, $this->getStaticPageUrls($tenant, $baseUrl));

        $contentMethods = $this->getContentMethods($tenant);
        foreach ($contentMethods as $method) {
            $urls = array_merge($urls, $method($tenantId, $baseUrl));
        }

        return $urls;
    }

    /**
     * Returns a map of label → callable for each enabled content type.
     */
    private function getContentMethods(object $tenant): array
    {
        $methods = [];

        // ── PUBLIC content pages (no auth required — crawlers CAN access these) ──
        if ($this->hasFeature($tenant, 'blog')) {
            $methods['blog_posts'] = fn (int $tid, string $base) => $this->getBlogUrls($tid, $base);
        }
        if ($this->hasModule($tenant, 'listings')) {
            $methods['listings'] = fn (int $tid, string $base) => $this->getListingUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'events')) {
            $methods['events'] = fn (int $tid, string $base) => $this->getEventUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'groups')) {
            $methods['groups'] = fn (int $tid, string $base) => $this->getGroupUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'job_vacancies')) {
            $methods['job_vacancies'] = fn (int $tid, string $base) => $this->getJobUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'volunteering')) {
            $methods['volunteering'] = fn (int $tid, string $base) => $this->getVolunteeringUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'ideation_challenges')) {
            $methods['ideation'] = fn (int $tid, string $base) => $this->getIdeationUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'resources')) {
            $methods['resources'] = fn (int $tid, string $base) => $this->getResourceUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'organisations')) {
            $methods['organisations'] = fn (int $tid, string $base) => $this->getOrganizationUrls($tid, $base);
        }
        if ($this->hasFeature($tenant, 'marketplace')) {
            $methods['marketplace_listings'] = fn (int $tid, string $base) => $this->getMarketplaceListingUrls($tid, $base);
            $methods['marketplace_categories'] = fn (int $tid, string $base) => $this->getMarketplaceCategoryUrls($tid, $base);
        }
        $methods['cms_pages'] = fn (int $tid, string $base) => $this->getCmsPageUrls($tid, $base);
        $methods['kb_articles'] = fn (int $tid, string $base) => $this->getKbArticleUrls($tid, $base);

        // EXCLUDED: profiles (personal data, requires per-user opt-in consent)

        return $methods;
    }

    // =========================================================================
    // Content URL generators
    // =========================================================================

    private function getStaticPageUrls(object $tenant, string $baseUrl): array
    {
        $now = $this->formatDate(date('Y-m-d'));
        $urls = [];

        // Homepage (highest priority — refreshes daily)
        $urls[] = $this->url($baseUrl, '/', $now, 'daily', '1.0');

        // About, help, explore, contact, FAQ (always present)
        $urls[] = $this->url($baseUrl, '/about', $now, 'monthly', '0.6');
        $urls[] = $this->url($baseUrl, '/help', $now, 'monthly', '0.5');
        $urls[] = $this->url($baseUrl, '/explore', $now, 'weekly', '0.7');
        $urls[] = $this->url($baseUrl, '/contact', $now, 'yearly', '0.4');
        $urls[] = $this->url($baseUrl, '/faq', $now, 'monthly', '0.5');

        // Legal pages
        foreach (['terms', 'privacy', 'cookies', 'accessibility', 'acceptable-use', 'community-guidelines'] as $page) {
            $urls[] = $this->url($baseUrl, "/{$page}", $now, 'yearly', '0.2');
        }
        // Legal hub & version history
        $urls[] = $this->url($baseUrl, '/legal', $now, 'monthly', '0.3');
        $urls[] = $this->url($baseUrl, '/legal/history', $now, 'monthly', '0.2');

        // Platform-level legal pages
        foreach (['platform/terms', 'platform/privacy', 'platform/disclaimer'] as $page) {
            $urls[] = $this->url($baseUrl, "/{$page}", $now, 'yearly', '0.2');
        }

        // Timebanking guide (public educational content)
        $urls[] = $this->url($baseUrl, '/timebanking-guide', $now, 'monthly', '0.5');

        // NOTE: /members and /leaderboard are PROTECTED routes (require auth).
        // Do NOT add them to the sitemap — crawlers cannot access them.

        // Knowledge base listing
        $urls[] = $this->url($baseUrl, '/kb', $now, 'weekly', '0.5');

        // Public content listing pages (all now accessible without login)
        if ($this->hasModule($tenant, 'listings')) {
            $urls[] = $this->url($baseUrl, '/listings', $now, 'daily', '0.8');
        }
        if ($this->hasFeature($tenant, 'blog')) {
            $urls[] = $this->url($baseUrl, '/blog', $now, 'daily', '0.8');
        }
        if ($this->hasFeature($tenant, 'events')) {
            $urls[] = $this->url($baseUrl, '/events', $now, 'daily', '0.8');
        }
        if ($this->hasFeature($tenant, 'groups')) {
            $urls[] = $this->url($baseUrl, '/groups', $now, 'weekly', '0.7');
        }
        if ($this->hasFeature($tenant, 'job_vacancies')) {
            $urls[] = $this->url($baseUrl, '/jobs', $now, 'daily', '0.8');
        }
        if ($this->hasFeature($tenant, 'volunteering')) {
            $urls[] = $this->url($baseUrl, '/volunteering', $now, 'daily', '0.7');
        }
        if ($this->hasFeature($tenant, 'ideation_challenges')) {
            $urls[] = $this->url($baseUrl, '/ideation', $now, 'weekly', '0.6');
        }
        if ($this->hasFeature($tenant, 'resources')) {
            $urls[] = $this->url($baseUrl, '/resources', $now, 'weekly', '0.6');
        }
        if ($this->hasFeature($tenant, 'organisations')) {
            $urls[] = $this->url($baseUrl, '/organisations', $now, 'weekly', '0.6');
        }
        if ($this->hasFeature($tenant, 'marketplace')) {
            $urls[] = $this->url($baseUrl, '/marketplace', $now, 'daily', '0.8');
            $urls[] = $this->url($baseUrl, '/marketplace/free', $now, 'weekly', '0.6');
            $urls[] = $this->url($baseUrl, '/marketplace/map', $now, 'weekly', '0.5');
        }

        return $urls;
    }

    private function getBlogUrls(int $tenantId, string $baseUrl): array
    {
        // Blog data lives in the `posts` table (not `blog_posts` which is empty).
        // The `posts` table has no `published_at` column — use updated_at/created_at.
        $rows = DB::select(
            "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
             FROM posts
             WHERE tenant_id = ? AND status = 'published'
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/blog/{$r->slug}", $this->formatDate($r->lastmod), 'weekly', '0.7'),
            $rows
        );
    }

    private function getListingUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM listings
             WHERE tenant_id = ? AND status = 'active'
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/listings/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.6'),
            $rows
        );
    }

    private function getEventUrls(int $tenantId, string $baseUrl): array
    {
        // Include both future and past active/completed events for SEO archival value.
        // Exclude cancelled and draft events.
        $rows = DB::select(
            "SELECT id, created_at AS lastmod
             FROM events
             WHERE tenant_id = ? AND (status IS NULL OR status IN ('active', 'completed'))
             ORDER BY start_time DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/events/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.7'),
            $rows
        );
    }

    private function getGroupUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM `groups`
             WHERE tenant_id = ? AND visibility = 'public' AND is_active = 1
             ORDER BY COALESCE(updated_at, created_at) DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/groups/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.6'),
            $rows
        );
    }

    private function getJobUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM job_vacancies
             WHERE tenant_id = ? AND status = 'open'
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/jobs/{$r->id}", $this->formatDate($r->lastmod), 'daily', '0.7'),
            $rows
        );
    }

    private function getVolunteeringUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, created_at AS lastmod
             FROM vol_opportunities
             WHERE tenant_id = ? AND status = 'open' AND is_active = 1
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/volunteering/opportunities/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.6'),
            $rows
        );
    }

    private function getIdeationUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM ideation_challenges
             WHERE tenant_id = ? AND status IN ('open', 'voting', 'evaluating')
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/ideation/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.6'),
            $rows
        );
    }

    private function getResourceUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, created_at AS lastmod
             FROM resources
             WHERE tenant_id = ?
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/resources/{$r->id}", $this->formatDate($r->lastmod), 'monthly', '0.5'),
            $rows
        );
    }

    private function getMarketplaceListingUrls(int $tenantId, string $baseUrl): array
    {
        if (!Schema::hasTable('marketplace_listings')) {
            return [];
        }

        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM marketplace_listings
             WHERE tenant_id = ? AND status = 'active' AND moderation_status = 'approved'
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/marketplace/{$r->id}", $this->formatDate($r->lastmod), 'weekly', '0.7'),
            $rows
        );
    }

    private function getMarketplaceCategoryUrls(int $tenantId, string $baseUrl): array
    {
        if (!Schema::hasTable('marketplace_categories')) {
            return [];
        }

        $rows = DB::select(
            "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
             FROM marketplace_categories
             WHERE (tenant_id = ? OR tenant_id IS NULL) AND is_active = 1
             ORDER BY sort_order ASC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/marketplace/category/{$r->slug}", $this->formatDate($r->lastmod), 'weekly', '0.6'),
            $rows
        );
    }

    private function getCmsPageUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
             FROM pages
             WHERE tenant_id = ? AND is_published = 1
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/page/{$r->slug}", $this->formatDate($r->lastmod), 'monthly', '0.5'),
            $rows
        );
    }

    private function getKbArticleUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM knowledge_base_articles
             WHERE tenant_id = ? AND is_published = 1
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/kb/{$r->id}", $this->formatDate($r->lastmod), 'monthly', '0.6'),
            $rows
        );
    }

    private function getOrganizationUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, COALESCE(updated_at, created_at) AS lastmod
             FROM organizations
             WHERE tenant_id = ? AND status = 'active'
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/organisations/{$r->id}", $this->formatDate($r->lastmod), 'monthly', '0.5'),
            $rows
        );
    }

    private function getProfileUrls(int $tenantId, string $baseUrl): array
    {
        $rows = DB::select(
            "SELECT id, created_at AS lastmod
             FROM users
             WHERE tenant_id = ? AND is_approved = 1
             ORDER BY created_at DESC",
            [$tenantId]
        );

        return array_map(
            fn ($r) => $this->url($baseUrl, "/profile/{$r->id}", $this->formatDate($r->lastmod), 'monthly', '0.3'),
            $rows
        );
    }

    private function getHelpArticleUrls(int $tenantId, string $baseUrl): array
    {
        // React help center is at /help (list view only) — individual articles
        // are rendered inline within the help center, not as separate routes.
        // We include the help center listing page in static URLs instead.
        // No individual article URLs to generate.
        return [];
    }

    // =========================================================================
    // XML builders
    // =========================================================================

    private function buildUrlsetXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $entry) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . $this->escapeXml($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            }
            if (!empty($entry['changefreq'])) {
                $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            }
            if (!empty($entry['priority'])) {
                $xml .= "    <priority>{$entry['priority']}</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return $xml;
    }

    private function buildSitemapIndexXml(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sitemaps as $entry) {
            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>" . $this->escapeXml($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            }
            $xml .= "  </sitemap>\n";
        }

        $xml .= "</sitemapindex>\n";

        return $xml;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a URL entry array.
     */
    private function url(string $baseUrl, string $path, ?string $lastmod, string $changefreq, string $priority): array
    {
        return [
            'loc' => rtrim($baseUrl, '/') . $path,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    /**
     * Resolve the frontend base URL for a tenant's content.
     * Includes the tenant slug prefix for non-master tenants.
     */
    private function resolveBaseUrl(object $tenant): string
    {
        // Custom domain takes priority
        if (!empty($tenant->domain)) {
            return 'https://' . rtrim($tenant->domain, '/');
        }

        $frontendBase = rtrim(
            env('FRONTEND_URL', 'https://app.project-nexus.ie'),
            '/'
        );

        // Master tenant (empty slug) has no prefix
        if (empty($tenant->slug)) {
            return $frontendBase;
        }

        return $frontendBase . '/' . $tenant->slug;
    }

    /**
     * Get the API backend base URL (where sitemaps are served from).
     */
    private function getApiBaseUrl(): string
    {
        return rtrim(env('APP_URL', 'https://api.project-nexus.ie'), '/');
    }

    /**
     * Check if a tenant has a specific feature enabled.
     * Features default to true per TenantFeatureConfig::FEATURE_DEFAULTS.
     */
    private function hasFeature(object $tenant, string $feature): bool
    {
        $features = json_decode($tenant->features ?? '{}', true) ?: [];

        return (bool) ($features[$feature] ?? true);
    }

    /**
     * Check if a tenant has a specific module enabled.
     * Modules default to true.
     */
    private function hasModule(object $tenant, string $module): bool
    {
        $config = json_decode($tenant->configuration ?? '{}', true) ?: [];
        $modules = $config['modules'] ?? [];

        return (bool) ($modules[$module] ?? true);
    }

    /**
     * Format a date/datetime string to ISO 8601 date (YYYY-MM-DD).
     */
    private function formatDate(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return date('Y-m-d', strtotime($datetime));
        } catch (\Throwable $e) {
            Log::debug('[SitemapService] formatDate failed for value: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Escape special XML characters in a string.
     */
    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
