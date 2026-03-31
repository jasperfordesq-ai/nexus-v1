<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SeoAuditService — Runs real SEO audits per tenant.
 *
 * Checks meta titles, descriptions, canonical URLs, redirect health,
 * Open Graph configuration, sitemap coverage, and content quality signals.
 *
 * Returns structured results with pass/warning/fail status per check.
 */
class SeoAuditService
{
    private const TITLE_MAX_LENGTH = 60;
    private const TITLE_MIN_LENGTH = 10;
    private const DESC_MAX_LENGTH = 160;
    private const DESC_MIN_LENGTH = 50;

    /**
     * Run a full SEO audit for a tenant and persist results.
     *
     * @return array{checks: array, score: int, max_score: int, grade: string}
     */
    public function runAudit(int $tenantId): array
    {
        $checks = [];

        $checks[] = $this->checkTenantMetadata($tenantId);
        $checks[] = $this->checkSeoSettings($tenantId);
        $checks[] = $this->checkBlogPostMeta($tenantId);
        $checks[] = $this->checkPageMeta($tenantId);
        $checks[] = $this->checkKbArticleMeta($tenantId);
        $checks[] = $this->checkRedirectHealth($tenantId);
        $checks[] = $this->checkDuplicateTitles($tenantId);
        $checks[] = $this->checkSitemapCoverage($tenantId);
        $checks[] = $this->checkCanonicalUrls($tenantId);
        $checks[] = $this->checkOpenGraph($tenantId);
        $checks[] = $this->checkContentQuality($tenantId);

        $score = 0;
        $maxScore = 0;
        foreach ($checks as $check) {
            $maxScore += $check['max_points'];
            $score += $check['points'];
        }

        $grade = $this->calculateGrade($score, $maxScore);

        $results = [
            'checks' => $checks,
            'score' => $score,
            'max_score' => $maxScore,
            'grade' => $grade,
            'run_at' => now()->toIso8601String(),
        ];

        // Persist to database
        $this->persistResults($tenantId, $results);

        return $results;
    }

    /**
     * Get the most recent audit results for a tenant.
     */
    public function getLatestAudit(int $tenantId): ?array
    {
        $row = DB::selectOne(
            "SELECT results, created_at FROM seo_audits WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 1",
            [$tenantId]
        );

        if (!$row || empty($row->results)) {
            return null;
        }

        $results = json_decode($row->results, true);
        if (!is_array($results)) {
            return null;
        }

        $results['stored_at'] = $row->created_at;
        return $results;
    }

    // =========================================================================
    // Individual checks
    // =========================================================================

    private function checkTenantMetadata(int $tenantId): array
    {
        $tenant = DB::selectOne(
            "SELECT meta_title, meta_description, h1_headline FROM tenants WHERE id = ?",
            [$tenantId]
        );

        $issues = [];

        if (empty($tenant->meta_title)) {
            $issues[] = 'Missing default meta title for tenant homepage';
        } elseif (strlen($tenant->meta_title) > self::TITLE_MAX_LENGTH) {
            $issues[] = "Meta title too long (" . strlen($tenant->meta_title) . " chars, max " . self::TITLE_MAX_LENGTH . ")";
        }

        if (empty($tenant->meta_description)) {
            $issues[] = 'Missing default meta description';
        } elseif (strlen($tenant->meta_description) > self::DESC_MAX_LENGTH) {
            $issues[] = "Meta description too long (" . strlen($tenant->meta_description) . " chars, max " . self::DESC_MAX_LENGTH . ")";
        } elseif (strlen($tenant->meta_description) < self::DESC_MIN_LENGTH) {
            $issues[] = "Meta description too short (" . strlen($tenant->meta_description) . " chars, min " . self::DESC_MIN_LENGTH . ")";
        }

        if (empty($tenant->h1_headline)) {
            $issues[] = 'Missing H1 headline for homepage';
        }

        return $this->buildCheck(
            'tenant_metadata',
            'Homepage Meta Tags',
            'Checks that the tenant has a meta title, description, and H1 headline',
            $issues,
            10
        );
    }

    private function checkSeoSettings(int $tenantId): array
    {
        $settings = $this->getTenantSettings($tenantId, [
            'seo_auto_sitemap', 'seo_canonical_urls', 'seo_open_graph',
            'seo_twitter_cards', 'seo_robots_txt', 'seo_title_suffix',
        ]);

        $issues = [];

        if (empty($settings['seo_canonical_urls']) || $settings['seo_canonical_urls'] !== '1') {
            $issues[] = 'Canonical URLs are not enabled';
        }

        if (empty($settings['seo_open_graph']) || $settings['seo_open_graph'] !== '1') {
            $issues[] = 'Open Graph tags are not enabled';
        }

        if (empty($settings['seo_twitter_cards']) || $settings['seo_twitter_cards'] !== '1') {
            $issues[] = 'Twitter Cards are not enabled';
        }

        if (empty($settings['seo_title_suffix'])) {
            $issues[] = 'No title suffix configured (e.g., " | Community Name")';
        }

        return $this->buildCheck(
            'seo_settings',
            'SEO Configuration',
            'Checks that key SEO settings (canonical, OG, Twitter) are enabled',
            $issues,
            10
        );
    }

    private function checkBlogPostMeta(int $tenantId): array
    {
        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM blog_posts WHERE tenant_id = ? AND status = 'published'",
            [$tenantId]
        )->cnt;

        if ($total === 0) {
            return $this->buildCheck('blog_meta', 'Blog Post Meta Tags', 'Checks published blog posts for meta titles/descriptions', [], 10);
        }

        $missingMeta = DB::select(
            "SELECT bp.id, bp.title, bp.slug
             FROM blog_posts bp
             LEFT JOIN seo_metadata sm ON sm.tenant_id = bp.tenant_id
                AND sm.entity_type = 'blog_post' AND sm.entity_id = bp.id
             WHERE bp.tenant_id = ? AND bp.status = 'published'
                AND (sm.meta_title IS NULL OR sm.meta_title = ''
                     OR sm.meta_description IS NULL OR sm.meta_description = '')
             LIMIT 20",
            [$tenantId]
        );

        $issues = [];
        foreach ($missingMeta as $post) {
            $issues[] = "Blog post \"{$post->title}\" (/{$post->slug}) missing meta title or description";
        }

        $missingCount = count($missingMeta);
        if ($missingCount >= 20) {
            $issues[] = "... and possibly more (showing first 20)";
        }

        return $this->buildCheck(
            'blog_meta',
            'Blog Post Meta Tags',
            "Checks {$total} published blog posts for meta titles/descriptions",
            $issues,
            10
        );
    }

    private function checkPageMeta(int $tenantId): array
    {
        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM pages WHERE tenant_id = ? AND is_published = 1",
            [$tenantId]
        )->cnt;

        if ($total === 0) {
            return $this->buildCheck('page_meta', 'CMS Page Meta Tags', 'Checks published CMS pages for meta tags', [], 5);
        }

        $missingMeta = DB::select(
            "SELECT p.id, p.title, p.slug
             FROM pages p
             LEFT JOIN seo_metadata sm ON sm.tenant_id = p.tenant_id
                AND sm.entity_type = 'page' AND sm.entity_id = p.id
             WHERE p.tenant_id = ? AND p.is_published = 1
                AND (sm.meta_title IS NULL OR sm.meta_title = '')
             LIMIT 20",
            [$tenantId]
        );

        $issues = [];
        foreach ($missingMeta as $page) {
            $issues[] = "CMS page \"{$page->title}\" (/{$page->slug}) missing meta title";
        }

        return $this->buildCheck(
            'page_meta',
            'CMS Page Meta Tags',
            "Checks {$total} published CMS pages for meta tags",
            $issues,
            5
        );
    }

    private function checkKbArticleMeta(int $tenantId): array
    {
        $total = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM knowledge_base_articles WHERE tenant_id = ? AND is_published = 1",
            [$tenantId]
        )->cnt;

        if ($total === 0) {
            return $this->buildCheck('kb_meta', 'KB Article Meta Tags', 'Checks KB articles for meta tags', [], 5);
        }

        $missingTitle = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM knowledge_base_articles
             WHERE tenant_id = ? AND is_published = 1 AND (title IS NULL OR title = '')",
            [$tenantId]
        )->cnt;

        $issues = [];
        if ($missingTitle > 0) {
            $issues[] = "{$missingTitle} KB article(s) have empty titles";
        }

        return $this->buildCheck(
            'kb_meta',
            'KB Article Meta Tags',
            "Checks {$total} published KB articles for proper titles",
            $issues,
            5
        );
    }

    private function checkRedirectHealth(int $tenantId): array
    {
        $redirects = DB::select(
            "SELECT id, source_url, destination_url FROM seo_redirects WHERE tenant_id = ?",
            [$tenantId]
        );

        $issues = [];
        $destinationMap = [];

        foreach ($redirects as $r) {
            $from = $r->source_url;
            $to = $r->destination_url;
            $destinationMap[$from] = $to;
        }

        // Check for chains (A → B → C)
        foreach ($destinationMap as $from => $to) {
            if (isset($destinationMap[$to])) {
                $issues[] = "Redirect chain: {$from} → {$to} → {$destinationMap[$to]}";
            }
        }

        // Check for loops (A → B → A)
        foreach ($destinationMap as $from => $to) {
            if (isset($destinationMap[$to]) && $destinationMap[$to] === $from) {
                $issues[] = "Redirect loop: {$from} ↔ {$to}";
            }
        }

        // Check for self-redirects
        foreach ($destinationMap as $from => $to) {
            if ($from === $to) {
                $issues[] = "Self-redirect: {$from} → {$to}";
            }
        }

        return $this->buildCheck(
            'redirect_health',
            'Redirect Health',
            'Checks for redirect chains, loops, and self-redirects (' . count($redirects) . ' redirects)',
            $issues,
            10
        );
    }

    private function checkDuplicateTitles(int $tenantId): array
    {
        $duplicates = DB::select(
            "SELECT meta_title, COUNT(*) AS cnt
             FROM seo_metadata
             WHERE tenant_id = ? AND meta_title IS NOT NULL AND meta_title != ''
             GROUP BY meta_title
             HAVING cnt > 1
             LIMIT 10",
            [$tenantId]
        );

        $issues = [];
        foreach ($duplicates as $dup) {
            $issues[] = "Duplicate meta title \"{$dup->meta_title}\" used {$dup->cnt} times";
        }

        return $this->buildCheck(
            'duplicate_titles',
            'Unique Meta Titles',
            'Checks for duplicate meta titles across pages',
            $issues,
            10
        );
    }

    private function checkSitemapCoverage(int $tenantId): array
    {
        $service = app(SitemapService::class);
        $stats = $service->getStats($tenantId);

        $issues = [];

        if ($stats['total_urls'] === 0) {
            $issues[] = 'Sitemap contains 0 URLs — no content is being indexed';
        } elseif ($stats['total_urls'] < 5) {
            $issues[] = "Sitemap contains only {$stats['total_urls']} URLs — very low coverage";
        }

        // Check for content types with 0 URLs when they should have content
        foreach ($stats['content_types'] as $type => $count) {
            if ($count === 0 && in_array($type, ['static_pages', 'profiles'])) {
                $issues[] = "No {$type} in sitemap — expected at least some";
            }
        }

        return $this->buildCheck(
            'sitemap_coverage',
            'Sitemap Coverage',
            "Checks sitemap URL count and coverage ({$stats['total_urls']} URLs)",
            $issues,
            10
        );
    }

    private function checkCanonicalUrls(int $tenantId): array
    {
        $settings = $this->getTenantSettings($tenantId, ['seo_canonical_urls']);
        $issues = [];

        if (empty($settings['seo_canonical_urls']) || $settings['seo_canonical_urls'] !== '1') {
            $issues[] = 'Canonical URL generation is disabled — duplicate content risk';
        }

        // Check for custom canonical URLs that might be stale
        $customCanonicals = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM seo_metadata
             WHERE tenant_id = ? AND canonical_url IS NOT NULL AND canonical_url != ''",
            [$tenantId]
        )->cnt;

        if ($customCanonicals > 50) {
            $issues[] = "{$customCanonicals} pages have custom canonical URLs — ensure they are still valid";
        }

        return $this->buildCheck(
            'canonical_urls',
            'Canonical URLs',
            'Checks canonical URL configuration and custom overrides',
            $issues,
            10
        );
    }

    private function checkOpenGraph(int $tenantId): array
    {
        $settings = $this->getTenantSettings($tenantId, ['seo_open_graph', 'seo_twitter_cards']);
        $tenant = DB::selectOne("SELECT og_image_url FROM tenants WHERE id = ?", [$tenantId]);

        $issues = [];

        if (empty($settings['seo_open_graph']) || $settings['seo_open_graph'] !== '1') {
            $issues[] = 'Open Graph tags are disabled — social sharing will show generic previews';
        }

        if (empty($settings['seo_twitter_cards']) || $settings['seo_twitter_cards'] !== '1') {
            $issues[] = 'Twitter Cards are disabled';
        }

        if ($tenant && empty($tenant->og_image_url)) {
            $issues[] = 'No default Open Graph image configured — social shares will have no image';
        }

        return $this->buildCheck(
            'open_graph',
            'Social Sharing (OG/Twitter)',
            'Checks Open Graph and Twitter Card configuration',
            $issues,
            10
        );
    }

    private function checkContentQuality(int $tenantId): array
    {
        $issues = [];

        // Check for very short blog posts
        $shortPosts = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM blog_posts
             WHERE tenant_id = ? AND status = 'published' AND LENGTH(content) < 300",
            [$tenantId]
        )->cnt;

        if ($shortPosts > 0) {
            $issues[] = "{$shortPosts} published blog post(s) have very short content (<300 chars) — thin content hurts SEO";
        }

        // Check for pages without titles
        $untitledPages = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM pages
             WHERE tenant_id = ? AND is_published = 1 AND (title IS NULL OR title = '')",
            [$tenantId]
        )->cnt;

        if ($untitledPages > 0) {
            $issues[] = "{$untitledPages} published page(s) have no title";
        }

        return $this->buildCheck(
            'content_quality',
            'Content Quality Signals',
            'Checks for thin content, missing titles, and other quality issues',
            $issues,
            10
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildCheck(string $key, string $name, string $description, array $issues, int $maxPoints): array
    {
        $issueCount = count($issues);

        if ($issueCount === 0) {
            $status = 'pass';
            $points = $maxPoints;
        } elseif ($issueCount <= 2) {
            $status = 'warning';
            $points = (int) round($maxPoints * 0.5);
        } else {
            $status = 'fail';
            $points = 0;
        }

        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'issues' => $issues,
            'issue_count' => $issueCount,
            'points' => $points,
            'max_points' => $maxPoints,
        ];
    }

    private function calculateGrade(int $score, int $maxScore): string
    {
        if ($maxScore === 0) {
            return 'N/A';
        }

        $pct = ($score / $maxScore) * 100;

        return match (true) {
            $pct >= 90 => 'A',
            $pct >= 80 => 'B',
            $pct >= 70 => 'C',
            $pct >= 60 => 'D',
            default => 'F',
        };
    }

    private function getTenantSettings(int $tenantId, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = DB::select(
            "SELECT setting_key, setting_value FROM tenant_settings
             WHERE tenant_id = ? AND setting_key IN ({$placeholders})",
            array_merge([$tenantId], $keys)
        );

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }

        return $settings;
    }

    private function persistResults(int $tenantId, array $results): void
    {
        try {
            DB::insert(
                "INSERT INTO seo_audits (tenant_id, url, results, created_at) VALUES (?, '', ?, NOW())",
                [$tenantId, json_encode($results)]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to persist SEO audit results', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
