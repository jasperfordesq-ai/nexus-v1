<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * Feature tests for SitemapController — public XML sitemap endpoints.
 *
 * Routes are served WITHOUT the /api prefix:
 *   GET /sitemap.xml         → Sitemap index
 *   GET /sitemap-{slug}.xml  → Per-tenant sitemap
 */
class SitemapControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // =========================================================================
    // GET /sitemap.xml
    // =========================================================================

    public function test_sitemap_index_returns_200(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
    }

    public function test_sitemap_index_returns_xml_content_type(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_sitemap_index_contains_sitemapindex_root(): void
    {
        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        $this->assertStringContainsString('<sitemapindex', $content);
        $this->assertStringContainsString('</sitemapindex>', $content);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $content);
    }

    public function test_sitemap_index_contains_tenant_sitemaps(): void
    {
        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        $this->assertStringContainsString('<sitemap>', $content);
        $this->assertStringContainsString('<loc>', $content);
    }

    public function test_sitemap_index_has_cache_headers(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertHeader('Cache-Control');
    }

    public function test_sitemap_index_is_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');
        $content = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue(@$doc->loadXML($content), 'Sitemap index is not valid XML');
    }

    // =========================================================================
    // GET /sitemap-{slug}.xml
    // =========================================================================

    public function test_tenant_sitemap_returns_200_for_valid_slug(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $response->assertStatus(200);
    }

    public function test_tenant_sitemap_returns_xml_content_type(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_tenant_sitemap_contains_urlset_root(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $content = $response->getContent();

        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('</urlset>', $content);
    }

    public function test_tenant_sitemap_contains_urls(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $content = $response->getContent();

        $this->assertStringContainsString('<url>', $content);
        $this->assertStringContainsString('<loc>', $content);
    }

    public function test_tenant_sitemap_returns_404_for_unknown_slug(): void
    {
        $response = $this->get('/sitemap-nonexistent-tenant.xml');
        $response->assertStatus(404);
    }

    public function test_tenant_sitemap_404_still_returns_xml(): void
    {
        $response = $this->get('/sitemap-nonexistent-tenant.xml');

        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringContainsString('<urlset', $content);
    }

    public function test_tenant_sitemap_is_valid_xml(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $content = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue(@$doc->loadXML($content), 'Tenant sitemap is not valid XML');
    }

    public function test_tenant_sitemap_includes_homepage(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $content = $response->getContent();

        $this->assertStringContainsString('<priority>1.0</priority>', $content);
    }

    public function test_tenant_sitemap_urls_are_frontend_urls(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $content = $response->getContent();

        // URLs should point to the frontend, not the API backend
        preg_match_all('/<loc>([^<]+)<\/loc>/', $content, $matches);
        foreach ($matches[1] as $url) {
            $this->assertStringNotContainsString('/api/', $url, "Sitemap URL should not contain /api/: {$url}");
        }
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function test_sitemap_slug_allows_hyphens(): void
    {
        $response = $this->get('/sitemap-hour-timebank.xml');
        $response->assertStatus(200);
    }

    public function test_sitemap_slug_rejects_path_traversal(): void
    {
        // The route constraint only allows [a-zA-Z0-9_-]
        $response = $this->get('/sitemap-../etc/passwd.xml');
        $response->assertStatus(404);
    }
}
