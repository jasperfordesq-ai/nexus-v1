<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for accessible (GOV.UK) inline likes on listing detail + blog
 * post pages. The toggle mirrors SocialController::likeV2's `likes`-table logic.
 */
class SocialLikesParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function enableFeatures(array $features): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        foreach ($features as $f) {
            $current[$f] = true;
        }
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function enableModule(string $module): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('configuration');
        $config = $row ? (json_decode($row, true) ?: []) : [];
        $config['modules'] = $config['modules'] ?? [];
        $config['modules'][$module] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['configuration' => json_encode($config)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active', 'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedBlogPost(int $authorId): array
    {
        $slug = 'like-blog-' . uniqid();
        $id = (int) DB::table('posts')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'author_id'  => $authorId,
            'title'      => 'Likeable Post',
            'slug'       => $slug,
            'excerpt'    => 'Post excerpt.',
            'content'    => 'Post body content for the like parity test.',
            'status'     => 'published',
            'created_at' => now(),
        ]);
        return ['id' => $id, 'slug' => $slug];
    }

    public function test_listing_like_toggles_and_persists(): void
    {
        $this->enableModule('listings');
        $owner = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'title'   => 'Likeable Listing',
            'status'  => 'active',
        ]);

        $liker = $this->authenticatedUser();
        Sanctum::actingAs($liker, ['*']);

        // Like
        $res = $this->post("/{$this->testTenantSlug}/accessible/listings/{$listing->id}/like");
        $res->assertRedirect();
        $this->assertDatabaseHas('likes', [
            'user_id' => $liker->id, 'target_type' => 'listing', 'target_id' => $listing->id, 'tenant_id' => $this->testTenantId,
        ]);

        // Unlike (toggle off)
        $this->post("/{$this->testTenantSlug}/accessible/listings/{$listing->id}/like");
        $this->assertDatabaseMissing('likes', [
            'user_id' => $liker->id, 'target_type' => 'listing', 'target_id' => $listing->id, 'tenant_id' => $this->testTenantId,
        ]);
    }

    public function test_listing_detail_shows_like_button(): void
    {
        $this->enableModule('listings');
        $owner = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id, 'title' => 'Visible Listing', 'status' => 'active',
        ]);
        $viewer = $this->authenticatedUser();
        Sanctum::actingAs($viewer, ['*']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/listings/{$listing->id}");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_listings.detail.like'));
    }

    public function test_blog_post_like_toggles_and_persists(): void
    {
        $this->enableFeatures(['blog']);
        $author = $this->authenticatedUser();
        $post = $this->seedBlogPost($author->id);

        $liker = $this->authenticatedUser();
        Sanctum::actingAs($liker, ['*']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/blog/{$post['slug']}/like");
        $res->assertRedirect();
        $this->assertDatabaseHas('likes', [
            'user_id' => $liker->id, 'target_type' => 'blog', 'target_id' => $post['id'], 'tenant_id' => $this->testTenantId,
        ]);
    }
}
