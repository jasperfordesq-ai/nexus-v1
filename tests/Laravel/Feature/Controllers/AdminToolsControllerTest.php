<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminToolsController.
 *
 * Covers redirects CRUD, 404 errors, health check, WebP stats,
 * seed generator, blog backups, SEO audit, IP debug.
 */
class AdminToolsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // REDIRECTS — GET /v2/admin/tools/redirects
    // ================================================================

    public function test_redirects_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/redirects');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_redirects_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/tools/redirects');

        $response->assertStatus(403);
    }

    public function test_redirects_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/tools/redirects');

        $response->assertStatus(401);
    }

    // ================================================================
    // CREATE REDIRECT — POST /v2/admin/tools/redirects
    // ================================================================

    public function test_create_redirect_requires_from_url(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/redirects', [
            'to_url' => '/new-page',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_redirect_requires_to_url(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/redirects', [
            'from_url' => '/old-page',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_redirect_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/tools/redirects', [
            'from_url' => '/old',
            'to_url' => '/new',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // 404 ERRORS — GET /v2/admin/tools/404-errors
    // ================================================================

    public function test_404_errors_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/404-errors');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_404_errors_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/tools/404-errors');

        $response->assertStatus(403);
    }

    // ================================================================
    // HEALTH CHECK — POST /v2/admin/tools/health-check
    // ================================================================

    public function test_health_check_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/health-check');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['tests', 'summary'],
        ]);
    }

    public function test_health_check_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/tools/health-check');

        $response->assertStatus(403);
    }

    // ================================================================
    // WEBP STATS — GET /v2/admin/tools/webp-stats
    // ================================================================

    public function test_webp_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/webp-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['total_images', 'webp_images', 'pending_conversion'],
        ]);
    }

    // ================================================================
    // SEED GENERATOR — POST /v2/admin/tools/seed
    // ================================================================

    public function test_seed_generator_requires_types(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/seed', []);

        $response->assertStatus(422);
    }

    public function test_seed_generator_rejects_invalid_types(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/seed', [
            'types' => ['invalid_type'],
        ]);

        $response->assertStatus(422);
    }

    public function test_seed_generator_returns_200_for_valid_types(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/tools/seed', [
            'types' => ['users', 'listings'],
            'counts' => ['users' => 10, 'listings' => 5],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['started', 'message', 'types'],
        ]);
    }

    // ================================================================
    // BLOG BACKUPS — GET /v2/admin/tools/blog-backups
    // ================================================================

    public function test_blog_backups_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/blog-backups');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SEO AUDIT — GET /v2/admin/tools/seo-audit
    // ================================================================

    public function test_seo_audit_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/seo-audit');

        $response->assertStatus(200);
    }

    // ================================================================
    // IP DEBUG — GET /v2/admin/tools/ip-debug
    // ================================================================

    public function test_ip_debug_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/tools/ip-debug');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_ip_debug_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/tools/ip-debug');

        $response->assertStatus(403);
    }

    public function test_ip_debug_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/tools/ip-debug');

        $response->assertStatus(401);
    }
}
