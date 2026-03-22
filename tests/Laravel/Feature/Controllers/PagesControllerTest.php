<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the public pages endpoint.
 *
 * Routes are handled by PagesPublicController:
 *   GET /v2/pages/{slug} — public, no auth required
 */
class PagesControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Insert a published page for the test tenant and return its slug.
     */
    private function createPublishedPage(string $slug, array $overrides = []): string
    {
        DB::table('pages')->insert(array_merge([
            'tenant_id'       => $this->testTenantId,
            'slug'            => $slug,
            'title'           => 'Test Page',
            'content'         => '<p>Test content</p>',
            'meta_description' => 'A test page',
            'is_published'    => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $overrides));

        return $slug;
    }

    // ================================================================
    // SHOW — Not found
    // ================================================================

    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->apiGet('/v2/pages/this-slug-does-not-exist-xyz123');

        $response->assertStatus(404);
    }

    // ================================================================
    // SHOW — Draft pages are hidden
    // ================================================================

    public function test_show_returns_404_for_unpublished_page(): void
    {
        $slug = 'draft-page-' . uniqid();
        $this->createPublishedPage($slug, ['is_published' => 0]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertStatus(404);
    }

    // ================================================================
    // SHOW — Happy path (no auth required)
    // ================================================================

    public function test_show_returns_200_for_existing_published_page(): void
    {
        $slug = 'about-us-' . uniqid();
        $this->createPublishedPage($slug);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertStatus(200);
    }

    public function test_show_returns_correct_structure(): void
    {
        $slug = 'terms-' . uniqid();
        $this->createPublishedPage($slug, [
            'title'           => 'Terms and Conditions',
            'content'         => '<p>Our terms.</p>',
            'meta_description' => 'Terms page',
        ]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'slug',
                'content',
                'meta_description',
            ],
        ]);
    }

    public function test_show_returns_correct_content(): void
    {
        $slug = 'privacy-' . uniqid();
        $this->createPublishedPage($slug, [
            'title'   => 'Privacy Policy',
            'content' => '<p>Privacy content here.</p>',
        ]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', $slug);
        $response->assertJsonPath('data.title', 'Privacy Policy');
    }

    // ================================================================
    // SHOW — Tenant isolation
    // ================================================================

    public function test_show_does_not_return_page_from_different_tenant(): void
    {
        // Insert a page belonging to a different tenant (id=999)
        $slug = 'cross-tenant-page-' . uniqid();
        DB::table('pages')->insert([
            'tenant_id'    => 999,
            'slug'         => $slug,
            'title'        => 'Other Tenant Page',
            'content'      => 'Should not be visible',
            'is_published' => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Requesting with our test tenant header should not find this page
        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertStatus(404);
    }
}
