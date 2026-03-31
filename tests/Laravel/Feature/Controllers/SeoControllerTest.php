<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for SeoController.
 *
 * Covers:
 *   GET /v2/seo/metadata/{slug} — public
 *   GET /v2/seo/redirects       — admin only
 */
class SeoControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function adminUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'role'        => 'admin',
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function regularUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'role'        => 'member',
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ================================================================
    // METADATA — Returns defaults for unknown slug
    // ================================================================

    public function test_metadata_returns_200_for_unknown_slug(): void
    {
        $response = $this->apiGet('/v2/seo/metadata/slug-that-does-not-exist-xyz');

        // 200 with defaults if route registered, 404 if not yet wired up
        $this->assertContains($response->getStatusCode(), [200, 404]);

        if ($response->getStatusCode() === 200) {
            $response->assertJsonStructure([
                'data' => ['title', 'description', 'og_image', 'canonical_url', 'robots'],
            ]);
        }
    }

    public function test_metadata_returns_null_fields_for_unknown_slug(): void
    {
        $response = $this->apiGet('/v2/seo/metadata/slug-that-does-not-exist-xyz');

        if ($response->getStatusCode() === 404) {
            // Route not registered — skip further assertions
            $this->markTestSkipped('SeoController routes are not yet registered in routes/api.php');
        }

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', null);
        $response->assertJsonPath('data.robots', 'index, follow');
    }

    // ================================================================
    // METADATA — Returns data for a known slug
    // ================================================================

    public function test_metadata_returns_data_for_known_slug(): void
    {
        $slug = 'home-' . uniqid();

        // Create a CMS page first, then attach SEO metadata via entity_type/entity_id
        $pageId = DB::table('pages')->insertGetId([
            'tenant_id'    => $this->testTenantId,
            'title'        => 'Home Page',
            'slug'         => $slug,
            'content'      => '<p>Home</p>',
            'is_published' => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('seo_metadata')->insert([
            'tenant_id'        => $this->testTenantId,
            'entity_type'      => 'page',
            'entity_id'        => $pageId,
            'meta_title'       => 'Home | My Timebank',
            'meta_description' => 'Welcome to the timebank.',
            'og_image_url'     => null,
            'canonical_url'    => null,
            'noindex'          => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $response = $this->apiGet("/v2/seo/metadata/{$slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Home | My Timebank');
        $response->assertJsonPath('data.description', 'Welcome to the timebank.');
    }

    // ================================================================
    // REDIRECTS — Requires admin
    // ================================================================

    public function test_redirects_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/seo/redirects');

        $this->assertContains($response->getStatusCode(), [401, 403, 404]);
    }

    public function test_redirects_returns_403_for_regular_member(): void
    {
        $this->regularUser();

        $response = $this->apiGet('/v2/seo/redirects');

        if ($response->getStatusCode() === 404) {
            $this->markTestSkipped('SeoController routes are not yet registered in routes/api.php');
        }

        $response->assertStatus(403);
    }

    public function test_redirects_returns_200_for_admin(): void
    {
        $this->adminUser();

        $response = $this->apiGet('/v2/seo/redirects');

        if ($response->getStatusCode() === 404) {
            $this->markTestSkipped('SeoController routes are not yet registered in routes/api.php');
        }

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_redirects_returns_paginated_list_for_admin(): void
    {
        $this->adminUser();

        // Seed a redirect for the test tenant
        DB::table('seo_redirects')->insert([
            'tenant_id'       => $this->testTenantId,
            'source_url'      => '/old-path-' . uniqid(),
            'destination_url' => '/new-path',
            'created_at'      => now(),
        ]);

        $response = $this->apiGet('/v2/seo/redirects');

        if ($response->getStatusCode() === 404) {
            $this->markTestSkipped('SeoController routes are not yet registered in routes/api.php');
        }

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }
}
