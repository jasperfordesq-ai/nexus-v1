<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
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

    public function test_show_preserves_static_editorial_attribution(): void
    {
        $slug = 'editorial-attribution-' . uniqid();
        $this->createPublishedPage($slug, [
            'content' => '<blockquote>A static testimonial.</blockquote><cite>Published with consent</cite>',
        ]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $slug);
    }

    /**
     * @dataProvider memberIdentitySurfaceProvider
     */
    public function test_show_fails_closed_for_account_derived_member_surfaces(string $content): void
    {
        $slug = 'member-surface-' . uniqid();
        $this->createPublishedPage($slug, ['content' => $content]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertNotFound();
        $response->assertJsonMissing(['content' => $content]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function memberIdentitySurfaceProvider(): array
    {
        return [
            'profile link' => ['<a href="/profile/42">Account profile</a>'],
            'tenant-prefixed member link' => ['<a href="/community/members/42">Account profile</a>'],
            'encoded profile link' => ['<a href="&#x2F;profile&#x2F;42">Account profile</a>'],
            'legacy rendered member card' => ['<div class="pb-member-card">Account card</div>'],
            'account identifier attribute' => ['<div data-user-id="42">Account card</div>'],
        ];
    }

    public function test_show_fails_closed_when_legacy_page_contains_members_grid_block(): void
    {
        $slug = 'member-block-' . uniqid();
        $this->createPublishedPage($slug);
        $pageId = (int) DB::table('pages')->where('tenant_id', $this->testTenantId)->where('slug', $slug)->value('id');

        DB::table('page_blocks')->insert([
            'page_id' => $pageId,
            'block_type' => 'members-grid',
            'block_data' => json_encode(['limit' => 6, 'columns' => 3], JSON_THROW_ON_ERROR),
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->apiGet("/v2/pages/{$slug}")->assertNotFound();
    }

    public function test_show_fails_closed_when_builder_design_contains_members_grid(): void
    {
        $slug = 'member-design-' . uniqid();
        $this->createPublishedPage($slug, [
            'design_json' => json_encode([
                'blocks' => [['type' => 'members-grid', 'data' => ['limit' => 6]]],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->apiGet("/v2/pages/{$slug}")->assertNotFound();
    }

    public function test_show_fails_closed_when_page_embeds_an_account_avatar(): void
    {
        $avatarUrl = '/uploads/avatars/account-only-' . uniqid() . '.png';
        User::factory()->forTenant($this->testTenantId)->create(['avatar_url' => $avatarUrl]);

        $slug = 'account-avatar-' . uniqid();
        $content = '<img src="' . $avatarUrl . '" alt="Editorial image">';
        $this->createPublishedPage($slug, ['content' => $content]);

        $response = $this->apiGet("/v2/pages/{$slug}");

        $response->assertNotFound();
        $response->assertJsonMissing(['content' => $content]);
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
