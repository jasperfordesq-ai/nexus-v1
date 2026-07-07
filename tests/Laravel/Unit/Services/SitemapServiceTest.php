<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\SitemapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Unit tests for SitemapService — XML sitemap generation.
 *
 * Covers: index generation, per-tenant sitemaps, content type filtering,
 * feature/module gating, XML validity, caching, and edge cases.
 */
class SitemapServiceTest extends TestCase
{
    private SitemapService $service;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SitemapService();
        Cache::flush();

        // Get a valid user ID for FK-constrained inserts
        $user = DB::selectOne("SELECT id FROM users WHERE tenant_id = ? LIMIT 1", [$this->testTenantId]);
        $this->userId = $user ? (int) $user->id : 1;
    }

    // =========================================================================
    // generateIndex()
    // =========================================================================

    public function test_generateIndex_returns_valid_xml(): void
    {
        $xml = $this->service->generateIndex();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<sitemapindex', $xml);
        $this->assertStringContainsString('</sitemapindex>', $xml);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $xml);
    }

    public function test_generateIndex_contains_active_tenants(): void
    {
        $xml = $this->service->generateIndex();

        // Should include the test tenant (hour-timebank)
        $this->assertStringContainsString('sitemap-hour-timebank.xml', $xml);
    }

    public function test_generateIndex_uses_main_for_empty_slug(): void
    {
        // Ensure tenant 1 exists with empty slug
        DB::table('tenants')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Master', 'slug' => '', 'is_active' => 1, 'depth' => 0, 'allows_subtenants' => false, 'created_at' => now(), 'updated_at' => now()]
        );

        Cache::flush();
        $xml = $this->service->generateIndex();
        $this->assertStringContainsString('sitemap-main.xml', $xml);
    }

    public function test_generateIndex_excludes_inactive_tenants(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => 998,
            'name' => 'Inactive Tenant',
            'slug' => 'inactive-test',
            'is_active' => 0,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateIndex();
        $this->assertStringNotContainsString('inactive-test', $xml);
    }

    public function test_generateIndex_is_cached(): void
    {
        $xml1 = $this->service->generateIndex();
        $xml2 = $this->service->generateIndex();
        $this->assertSame($xml1, $xml2);
    }

    // =========================================================================
    // generateForTenant()
    // =========================================================================

    public function test_generateForTenant_returns_valid_xml(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('</urlset>', $xml);
    }

    public function test_generateForTenant_includes_homepage(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        // Should have the base URL with tenant slug suffix ending in /
        $this->assertStringContainsString('<loc>', $xml);
        $this->assertStringContainsString('<priority>1.0</priority>', $xml);
    }

    public function test_generateForTenant_includes_static_pages(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        $this->assertStringContainsString('/about', $xml);
        $this->assertStringContainsString('/help', $xml);
        $this->assertStringContainsString('/terms', $xml);
        $this->assertStringContainsString('/privacy', $xml);
    }

    public function test_generateForTenant_returns_empty_for_nonexistent_tenant(): void
    {
        $xml = $this->service->generateForTenant(99999);

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringNotContainsString('<url>', $xml);
    }

    public function test_generateForTenant_includes_blog_posts(): void
    {
        // Blog data lives in the `posts` table (not `blog_posts`)
        DB::table('posts')->insertOrIgnore([
            'id' => 90001,
            'tenant_id' => $this->testTenantId,
            'author_id' => $this->userId,
            'title' => 'Test Blog Post',
            'slug' => 'test-sitemap-blog-post',
            'content' => 'Test content for sitemap',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringContainsString('/blog/test-sitemap-blog-post', $xml);
    }

    public function test_generateForTenant_excludes_draft_blog_posts(): void
    {
        DB::table('posts')->insertOrIgnore([
            'id' => 90002,
            'tenant_id' => $this->testTenantId,
            'author_id' => $this->userId,
            'title' => 'Draft Post',
            'slug' => 'draft-post-sitemap',
            'content' => 'Draft content',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringNotContainsString('draft-post-sitemap', $xml);
    }

    public function test_generateForTenant_excludes_placeholder_blog_posts(): void
    {
        DB::table('posts')->insertOrIgnore([
            [
                'id' => 90003,
                'tenant_id' => $this->testTenantId,
                'author_id' => $this->userId,
                'title' => 'Aenean sed pulvinar et diam',
                'slug' => 'aenean-sed-pulvinar-et-diam',
                'content' => 'Real-looking title with placeholder body.',
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 90004,
                'tenant_id' => $this->testTenantId,
                'author_id' => $this->userId,
                'title' => 'Placeholder Ipsum',
                'slug' => 'placeholder-ipsum-sitemap',
                'content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);

        $this->assertStringNotContainsString('aenean-sed-pulvinar-et-diam', $xml);
        $this->assertStringNotContainsString('placeholder-ipsum-sitemap', $xml);
    }

    public function test_generateForTenant_includes_active_listings(): void
    {
        $id = DB::table('listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $this->userId,
            'title' => 'Sitemap Test Listing',
            'description' => 'A listing for sitemap testing',
            'status' => 'active',
            'type' => 'offer',
            'created_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringContainsString("/listings/{$id}", $xml);
    }

    public function test_generateForTenant_excludes_expired_listings(): void
    {
        $id = DB::table('listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $this->userId,
            'title' => 'Expired Listing',
            'description' => 'This listing has expired',
            'status' => 'active',
            'type' => 'offer',
            'expires_at' => now()->subDays(5),
            'created_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringNotContainsString("/listings/{$id}", $xml);
    }

    // Content types are now public (routes moved outside ProtectedRoute)
    // and included in the sitemap.

    public function test_generateForTenant_includes_public_groups(): void
    {
        // getGroupUrls() selects public + active groups for the tenant. The clean
        // CI DB has none, so seed one to exercise the per-group URL path.
        $id = DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $this->userId,
            'name' => 'Public Sitemap Group',
            'visibility' => 'public',
            'is_active' => 1,
            'status' => 'active',
            'created_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringContainsString("/groups/{$id}", $xml);
    }

    public function test_generateForTenant_includes_events_listing(): void
    {
        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringContainsString('/events', $xml);
    }

    public function test_generateForTenant_includes_kb_articles(): void
    {
        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        // KB listing page should be present
        $this->assertStringContainsString('/kb', $xml);
    }

    public function test_generateForTenant_includes_volunteer_organisations_when_volunteering_enabled(): void
    {
        [$tenantId, $userId] = $this->seedSitemapTenant([
            'volunteering' => true,
            'organisations' => false,
        ]);
        $orgId = $this->seedVolunteerOrganization($tenantId, $userId, 'active');

        Cache::flush();
        $xml = $this->service->generateForTenant($tenantId);

        $this->assertStringContainsString('/organisations', $xml);
        $this->assertStringContainsString("/organisations/{$orgId}", $xml);
    }

    public function test_generateForTenant_excludes_volunteer_organisations_when_volunteering_disabled(): void
    {
        [$tenantId, $userId] = $this->seedSitemapTenant([
            'volunteering' => false,
            'organisations' => true,
        ]);
        $orgId = $this->seedVolunteerOrganization($tenantId, $userId, 'active');

        Cache::flush();
        $xml = $this->service->generateForTenant($tenantId);

        $this->assertStringNotContainsString('/organisations', $xml);
        $this->assertStringNotContainsString("/organisations/{$orgId}", $xml);
    }

    public function test_generateForTenant_includes_only_public_volunteer_opportunities(): void
    {
        [$tenantId, $userId] = $this->seedSitemapTenant(['volunteering' => true]);
        $activeOrgId = $this->seedVolunteerOrganization($tenantId, $userId, 'approved');
        $pendingOrgId = $this->seedVolunteerOrganization($tenantId, $userId, 'pending');
        $activeOpportunityId = $this->seedVolunteerOpportunity($tenantId, $userId, $activeOrgId, 'active');
        $pendingOrgOpportunityId = $this->seedVolunteerOpportunity($tenantId, $userId, $pendingOrgId, 'active');
        $closedOpportunityId = $this->seedVolunteerOpportunity($tenantId, $userId, $activeOrgId, 'closed');

        Cache::flush();
        $xml = $this->service->generateForTenant($tenantId);

        $this->assertStringContainsString("/volunteering/opportunities/{$activeOpportunityId}", $xml);
        $this->assertStringNotContainsString("/volunteering/opportunities/{$pendingOrgOpportunityId}", $xml);
        $this->assertStringNotContainsString("/volunteering/opportunities/{$closedOpportunityId}", $xml);
    }

    public function test_clearCache_invalidates_override_base_url_tenant_sitemap_variants(): void
    {
        [$tenantId, $userId] = $this->seedSitemapTenant(['volunteering' => true]);
        $overrideBaseUrl = 'https://sitemap-variant.example.test';

        Cache::flush();
        $before = $this->service->generateForTenant($tenantId, $overrideBaseUrl);
        $orgId = $this->seedVolunteerOrganization($tenantId, $userId, 'active');
        $stale = $this->service->generateForTenant($tenantId, $overrideBaseUrl);

        $this->assertSame($before, $stale);

        $this->service->clearCache($tenantId);
        $fresh = $this->service->generateForTenant($tenantId, $overrideBaseUrl);

        $this->assertStringContainsString("/organisations/{$orgId}", $fresh);
    }

    public function test_generateForTenant_excludes_profiles(): void
    {
        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        // Profiles require per-user consent — excluded
        $this->assertStringNotContainsString('/profile/', $xml);
    }

    public function test_generateForTenant_is_cached(): void
    {
        $xml1 = $this->service->generateForTenant($this->testTenantId);
        $xml2 = $this->service->generateForTenant($this->testTenantId);
        $this->assertSame($xml1, $xml2);
    }

    // =========================================================================
    // XML structure & protocol compliance
    // =========================================================================

    public function test_xml_is_well_formed(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($xml);
        $this->assertTrue($loaded, 'Generated sitemap XML is not well-formed');
    }

    public function test_index_xml_is_well_formed(): void
    {
        $xml = $this->service->generateIndex();

        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($xml);
        $this->assertTrue($loaded, 'Generated sitemap index XML is not well-formed');
    }

    public function test_urls_have_required_loc_element(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        // Every <url> should contain a <loc>
        preg_match_all('/<url>(.*?)<\/url>/s', $xml, $matches);
        foreach ($matches[1] as $urlBlock) {
            $this->assertStringContainsString('<loc>', $urlBlock);
        }
    }

    public function test_urls_have_valid_changefreq(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        $validFreqs = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

        preg_match_all('/<changefreq>([^<]+)<\/changefreq>/', $xml, $matches);
        foreach ($matches[1] as $freq) {
            $this->assertContains($freq, $validFreqs, "Invalid changefreq: {$freq}");
        }
    }

    public function test_urls_have_valid_priority(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        preg_match_all('/<priority>([^<]+)<\/priority>/', $xml, $matches);
        foreach ($matches[1] as $priority) {
            $val = (float) $priority;
            $this->assertGreaterThanOrEqual(0.0, $val);
            $this->assertLessThanOrEqual(1.0, $val);
        }
    }

    public function test_urls_have_valid_lastmod_dates(): void
    {
        $xml = $this->service->generateForTenant($this->testTenantId);

        preg_match_all('/<lastmod>([^<]+)<\/lastmod>/', $xml, $matches);
        foreach ($matches[1] as $date) {
            // Should be ISO 8601 date format (YYYY-MM-DD)
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date, "Invalid lastmod date: {$date}");
        }
    }

    public function test_xml_escapes_special_characters(): void
    {
        // The escapeXml method should handle &, <, >, ", '
        $service = new SitemapService();
        $xml = $service->generateForTenant($this->testTenantId);

        // URLs in sitemaps should never contain unescaped & or <
        $this->assertStringNotContainsString('& ', $xml);
    }

    // =========================================================================
    // resolveTenantBySlug() — REMOVED
    //
    // SitemapService::resolveTenantBySlug() was deleted as dead code in commit
    // 983f43d5a once SitemapController::tenant() was refactored to resolve the
    // slug→tenant mapping with direct DB queries (including a 'main' special-case).
    // No service method remains to unit-test, and the controller's inline
    // resolution is covered by the controller/feature suite. The three former
    // unit tests here are intentionally dropped rather than retargeted at a
    // private controller code path.
    // =========================================================================

    // =========================================================================
    // clearCache()
    // =========================================================================

    public function test_clearCache_clears_all(): void
    {
        // Warm cache
        $this->service->generateIndex();
        $this->service->generateForTenant($this->testTenantId);

        $cleared = $this->service->clearCache();
        $this->assertGreaterThan(0, $cleared);
    }

    public function test_clearCache_clears_specific_tenant(): void
    {
        $this->service->generateForTenant($this->testTenantId);

        $cleared = $this->service->clearCache($this->testTenantId);
        $this->assertSame(2, $cleared); // tenant cache + index
    }

    // =========================================================================
    // getStats()
    // =========================================================================

    public function test_getStats_returns_totals(): void
    {
        $stats = $this->service->getStats($this->testTenantId);

        $this->assertArrayHasKey('total_urls', $stats);
        $this->assertArrayHasKey('content_types', $stats);
        $this->assertIsInt($stats['total_urls']);
        $this->assertIsArray($stats['content_types']);
        $this->assertGreaterThan(0, $stats['total_urls']); // At minimum, static pages
    }

    public function test_getStats_returns_zero_for_nonexistent_tenant(): void
    {
        $stats = $this->service->getStats(99999);
        $this->assertSame(0, $stats['total_urls']);
    }

    // =========================================================================
    // Tenant isolation
    // =========================================================================

    public function test_sitemap_does_not_leak_cross_tenant_content(): void
    {
        // Seed content for tenant 999
        DB::table('posts')->insertOrIgnore([
            'id' => 90099,
            'tenant_id' => 999,
            'author_id' => $this->userId,
            'title' => 'Cross Tenant Post',
            'slug' => 'cross-tenant-post-sitemap',
            'content' => 'This belongs to another tenant',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
        $xml = $this->service->generateForTenant($this->testTenantId);
        $this->assertStringNotContainsString('cross-tenant-post-sitemap', $xml);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedSitemapTenant(array $features = []): array
    {
        $now = now();
        $suffix = uniqid();
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Sitemap Tenant ' . $suffix,
            'slug' => 'sitemap-tenant-' . $suffix,
            'domain' => null,
            'is_active' => 1,
            'depth' => 0,
            'allows_subtenants' => false,
            'features' => json_encode($features),
            'configuration' => json_encode(['modules' => []]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $user = User::factory()->forTenant($tenantId)->create([
            'is_approved' => 1,
            'status' => 'active',
        ]);

        return [$tenantId, (int) $user->id];
    }

    private function seedVolunteerOrganization(int $tenantId, int $userId, string $status): int
    {
        $now = now();
        $suffix = uniqid();

        return (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'name' => 'Sitemap Volunteer Org ' . $suffix,
            'slug' => 'sitemap-vol-org-' . $suffix,
            'description' => 'Volunteer organisation used by sitemap regression tests.',
            'contact_email' => 'sitemap-vol-org-' . $suffix . '@example.test',
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedVolunteerOpportunity(int $tenantId, int $userId, int $orgId, string $status): int
    {
        $now = now();
        $suffix = uniqid();

        return (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'created_by' => $userId,
            'title' => 'Sitemap Volunteer Opportunity ' . $suffix,
            'description' => 'Volunteer opportunity used by sitemap regression tests.',
            'location' => 'Remote',
            'status' => $status,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
