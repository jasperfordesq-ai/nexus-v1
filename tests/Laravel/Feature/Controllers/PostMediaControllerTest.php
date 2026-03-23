<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\FeedPost;
use App\Models\User;
use App\Services\PostMediaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Feature tests for PostMediaController — upload, reorder, remove, alt text.
 */
class PostMediaControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createPost(User $user): FeedPost
    {
        return FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Test post with media.',
        ]);
    }

    // ------------------------------------------------------------------
    //  POST /api/v2/posts/{id}/media — uploadMedia
    // ------------------------------------------------------------------

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/v2/posts/1/media', [], $this->withTenantHeader());

        $response->assertStatus(401);
    }

    public function test_upload_returns_403_for_non_owner(): void
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();
        $post = $this->createPost($otherUser);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(false);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->postJson(
            "/api/v2/posts/{$post->id}/media",
            [],
            $this->withTenantHeader()
        );

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_upload_returns_422_when_no_files_provided(): void
    {
        $user = $this->authenticatedUser();
        $post = $this->createPost($user);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(true);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->postJson(
            "/api/v2/posts/{$post->id}/media",
            [],
            $this->withTenantHeader()
        );

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    // ------------------------------------------------------------------
    //  PUT /api/v2/posts/{id}/media/reorder — reorderMedia
    // ------------------------------------------------------------------

    public function test_reorder_requires_authentication(): void
    {
        $response = $this->apiPut('/v2/posts/1/media/reorder', ['media_ids' => [1, 2]]);

        $response->assertStatus(401);
    }

    public function test_reorder_returns_403_for_non_owner(): void
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();
        $post = $this->createPost($otherUser);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(false);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut("/v2/posts/{$post->id}/media/reorder", [
            'media_ids' => [1, 2, 3],
        ]);

        $response->assertStatus(403);
    }

    public function test_reorder_fails_with_empty_media_ids(): void
    {
        $user = $this->authenticatedUser();
        $post = $this->createPost($user);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(true);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut("/v2/posts/{$post->id}/media/reorder", [
            'media_ids' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
    }

    public function test_reorder_fails_without_media_ids_field(): void
    {
        $user = $this->authenticatedUser();
        $post = $this->createPost($user);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(true);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut("/v2/posts/{$post->id}/media/reorder", []);

        $response->assertStatus(422);
    }

    public function test_reorder_succeeds_with_valid_media_ids(): void
    {
        $user = $this->authenticatedUser();
        $post = $this->createPost($user);

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isPostOwnedByUser')
            ->once()
            ->with($post->id, $user->id)
            ->andReturn(true);
        $mock->shouldReceive('reorderMedia')
            ->once()
            ->with($post->id, [3, 1, 2]);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut("/v2/posts/{$post->id}/media/reorder", [
            'media_ids' => [3, 1, 2],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
    }

    // ------------------------------------------------------------------
    //  DELETE /api/v2/posts/media/{mediaId} — removeMedia
    // ------------------------------------------------------------------

    public function test_remove_requires_authentication(): void
    {
        $response = $this->apiDelete('/v2/posts/media/1');

        $response->assertStatus(401);
    }

    public function test_remove_returns_403_for_non_owner(): void
    {
        $user = $this->authenticatedUser();

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isMediaOwnedByUser')
            ->once()
            ->with(99, $user->id)
            ->andReturn(false);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiDelete('/v2/posts/media/99');

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_remove_succeeds_for_owner(): void
    {
        $user = $this->authenticatedUser();

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isMediaOwnedByUser')
            ->once()
            ->with(99, $user->id)
            ->andReturn(true);
        $mock->shouldReceive('removeMedia')
            ->once()
            ->with(99);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiDelete('/v2/posts/media/99');

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
    }

    // ------------------------------------------------------------------
    //  PUT /api/v2/posts/media/{mediaId}/alt — updateAltText
    // ------------------------------------------------------------------

    public function test_update_alt_text_requires_authentication(): void
    {
        $response = $this->apiPut('/v2/posts/media/1/alt', ['alt_text' => 'A description']);

        $response->assertStatus(401);
    }

    public function test_update_alt_text_returns_403_for_non_owner(): void
    {
        $user = $this->authenticatedUser();

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isMediaOwnedByUser')
            ->once()
            ->with(99, $user->id)
            ->andReturn(false);
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut('/v2/posts/media/99/alt', ['alt_text' => 'Description']);

        $response->assertStatus(403);
    }

    public function test_update_alt_text_succeeds_for_owner(): void
    {
        $user = $this->authenticatedUser();

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isMediaOwnedByUser')
            ->once()
            ->with(99, $user->id)
            ->andReturn(true);
        $mock->shouldReceive('updateAltText')
            ->once()
            ->with(99, 'A scenic mountain landscape');
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut('/v2/posts/media/99/alt', [
            'alt_text' => 'A scenic mountain landscape',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
    }

    public function test_update_alt_text_accepts_empty_string(): void
    {
        $user = $this->authenticatedUser();

        $mock = Mockery::mock(PostMediaService::class);
        $mock->shouldReceive('isMediaOwnedByUser')
            ->once()
            ->with(99, $user->id)
            ->andReturn(true);
        $mock->shouldReceive('updateAltText')
            ->once()
            ->with(99, '');
        $this->app->instance(PostMediaService::class, $mock);

        $response = $this->apiPut('/v2/posts/media/99/alt', [
            'alt_text' => '',
        ]);

        $response->assertStatus(200);
    }
}
