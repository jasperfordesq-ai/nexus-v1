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
 * Feature tests for AdminBlogController.
 *
 * Covers CRUD for blog posts and toggle-status.
 */
class AdminBlogControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/blog
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/blog');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/blog');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/blog');

        $response->assertStatus(401);
    }

    // ================================================================
    // STORE — POST /v2/admin/blog
    // ================================================================

    public function test_store_creates_blog_post_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/blog', [
            'title' => 'Test Blog Post',
            'content' => 'This is the content of the test blog post.',
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'title', 'slug', 'status']]);
        $response->assertJsonPath('data.title', 'Test Blog Post');
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_store_returns_400_when_title_missing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/blog', [
            'content' => 'No title provided.',
        ]);

        $response->assertStatus(400);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/blog', [
            'title' => 'Should Not Work',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW — GET /v2/admin/blog/{id}
    // ================================================================

    public function test_show_returns_blog_post_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $postId = DB::table('posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_id' => $admin->id,
            'title' => 'Show Test Post',
            'slug' => 'show-test-post',
            'content' => 'Content here',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet("/v2/admin/blog/{$postId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'content', 'status', 'author_id', 'created_at'],
        ]);
        $response->assertJsonPath('data.title', 'Show Test Post');
    }

    public function test_show_returns_404_for_nonexistent_post(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/blog/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // UPDATE — PUT /v2/admin/blog/{id}
    // ================================================================

    public function test_update_modifies_blog_post_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $postId = DB::table('posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_id' => $admin->id,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'content' => 'Original content',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPut("/v2/admin/blog/{$postId}", [
            'title' => 'Updated Title',
            'status' => 'published',
        ]);

        $response->assertStatus(200);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/blog/{id}
    // ================================================================

    public function test_destroy_deletes_blog_post_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $postId = DB::table('posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_id' => $admin->id,
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'content' => 'To be deleted',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiDelete("/v2/admin/blog/{$postId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.deleted', true);
    }

    public function test_destroy_returns_404_for_nonexistent_post(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/blog/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // TOGGLE STATUS — POST /v2/admin/blog/{id}/toggle-status
    // ================================================================

    public function test_toggle_status_switches_draft_to_published(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $postId = DB::table('posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_id' => $admin->id,
            'title' => 'Toggle Test',
            'slug' => 'toggle-test',
            'content' => 'Content',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost("/v2/admin/blog/{$postId}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'published');
    }

    public function test_toggle_status_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/blog/1/toggle-status');

        $response->assertStatus(403);
    }
}
