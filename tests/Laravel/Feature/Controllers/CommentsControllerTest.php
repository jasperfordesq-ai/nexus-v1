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
 * Feature tests for CommentsController — CRUD and reactions on comments.
 */
class CommentsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createPost(int $userId): int
    {
        return DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'content' => 'Comment target post ' . uniqid(),
            'type' => 'post',
            'visibility' => 'public',
            'publish_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createComment(int $postId, int $userId): int
    {
        return DB::table('comments')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $userId,
            'content' => 'Parent comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createReply(int $postId, int $userId, int $parentId, string $content = 'Child comment'): int
    {
        return DB::table('comments')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /v2/comments
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/comments');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $user = $this->authenticatedUser();
        $postId = $this->createPost($user->id);

        $response = $this->apiGet("/v2/comments?commentable_type=feed_post&commentable_id={$postId}");

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/comments
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/comments', [
            'commentable_type' => 'feed_post',
            'commentable_id' => 1,
            'body' => 'Great post!',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_rejects_missing_target(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => 999999999,
            'content' => 'This should not attach to an arbitrary target.',
        ]);

        $response->assertStatus(404);
    }

    public function test_store_rejects_parent_comment_from_different_target(): void
    {
        $user = $this->authenticatedUser();
        $firstPostId = $this->createPost($user->id);
        $secondPostId = $this->createPost($user->id);
        $parentCommentId = $this->createComment($firstPostId, $user->id);

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => $secondPostId,
            'parent_id' => $parentCommentId,
            'content' => 'Reply should not cross targets.',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('comments', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $secondPostId,
            'parent_id' => $parentCommentId,
        ]);
    }

    public function test_store_creates_comment_for_visible_target(): void
    {
        $user = $this->authenticatedUser();
        $postId = $this->createPost($user->id);

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => $postId,
            'content' => 'A useful reply',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.content', 'A useful reply');
        $this->assertDatabaseHas('comments', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $user->id,
            'content' => 'A useful reply',
        ]);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/comments/{id}
    // ------------------------------------------------------------------

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/comments/1', ['body' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_update_allows_owner_and_rejects_non_owner(): void
    {
        $owner = $this->authenticatedUser();
        $postId = $this->createPost($owner->id);
        $commentId = $this->createComment($postId, $owner->id);

        $this->apiPut("/v2/comments/{$commentId}", [
            'content' => 'Updated comment',
        ])->assertStatus(200);

        $this->assertDatabaseHas('comments', [
            'id' => $commentId,
            'tenant_id' => $this->testTenantId,
            'content' => 'Updated comment',
        ]);

        $other = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($other, ['*']);

        $this->apiPut("/v2/comments/{$commentId}", [
            'content' => 'Hijacked comment',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('comments', [
            'id' => $commentId,
            'tenant_id' => $this->testTenantId,
            'content' => 'Hijacked comment',
        ]);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/comments/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/comments/1');

        $response->assertStatus(401);
    }

    public function test_destroy_soft_deletes_comment_thread_and_returns_deleted_count(): void
    {
        $user = $this->authenticatedUser();
        $postId = $this->createPost($user->id);
        $commentId = $this->createComment($postId, $user->id);
        $replyId = $this->createReply($postId, $user->id, $commentId);
        $nestedReplyId = $this->createReply($postId, $user->id, $replyId, 'Nested child comment');

        $response = $this->apiDelete("/v2/comments/{$commentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.deleted', true);
        $response->assertJsonPath('data.deleted_count', 3);

        foreach ([$commentId, $replyId, $nestedReplyId] as $id) {
            $this->assertSoftDeleted('comments', [
                'id' => $id,
                'tenant_id' => $this->testTenantId,
            ]);
        }
    }

    // ------------------------------------------------------------------
    //  POST /v2/comments/{id}/reactions
    // ------------------------------------------------------------------

    public function test_reactions_requires_auth(): void
    {
        $response = $this->apiPost('/v2/comments/1/reactions', ['type' => 'like']);

        $response->assertStatus(401);
    }
}
