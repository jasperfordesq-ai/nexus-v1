<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Safeguarding;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

class CommentReactionContactPolicyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_denied_reply_with_mention_writes_no_comment_mention_or_notification(): void
    {
        $sender = $this->member();
        $parentAuthor = $this->member();
        $mentioned = $this->member(['username' => 'protected_mention']);
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($sender->id);
        $parentId = $this->comment($postId, $parentAuthor->id, 'Parent comment');
        $content = 'Blocked reply @protected_mention';
        $notificationCount = $this->notificationCount([$parentAuthor->id, $mentioned->id]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with(
                $sender->id,
                $this->sortedIds($parentAuthor->id, $mentioned->id),
                $this->testTenantId,
                'comment_create',
            )
            ->andThrow($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => $postId,
            'parent_id' => $parentId,
            'content' => $content,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('comments', [
            'tenant_id' => $this->testTenantId,
            'content' => $content,
        ]);
        $this->assertDatabaseMissing('mentions', [
            'tenant_id' => $this->testTenantId,
            'mentioned_user_id' => $mentioned->id,
            'mentioning_user_id' => $sender->id,
        ]);
        $this->assertSame($notificationCount, $this->notificationCount([$parentAuthor->id, $mentioned->id]));
    }

    public function test_policy_unavailable_comment_returns_503_without_write(): void
    {
        $sender = $this->member();
        $owner = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($owner->id);
        $content = 'Unavailable policy comment';
        $notificationCount = $this->notificationCount([$owner->id]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with($sender->id, [$owner->id], $this->testTenantId, 'comment_create')
            ->andThrow($this->unavailable());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => $postId,
            'content' => $content,
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertDatabaseMissing('comments', [
            'tenant_id' => $this->testTenantId,
            'content' => $content,
        ]);
        $this->assertSame($notificationCount, $this->notificationCount([$owner->id]));
    }

    public function test_allowed_comment_persists_comment_and_mention(): void
    {
        $sender = $this->member();
        $owner = $this->member();
        $mentioned = $this->member(['username' => 'allowed_mention']);
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($owner->id);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with(
                $sender->id,
                $this->sortedIds($owner->id, $mentioned->id),
                $this->testTenantId,
                'comment_create',
            );
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/comments', [
            'target_type' => 'post',
            'target_id' => $postId,
            'content' => 'Allowed hello @allowed_mention',
        ]);

        $response->assertStatus(201);
        $commentId = (int) $response->json('data.id');
        $this->assertDatabaseHas('comments', [
            'id' => $commentId,
            'tenant_id' => $this->testTenantId,
            'user_id' => $sender->id,
        ]);
        $this->assertDatabaseHas('mentions', [
            'tenant_id' => $this->testTenantId,
            'comment_id' => $commentId,
            'mentioned_user_id' => $mentioned->id,
            'mentioning_user_id' => $sender->id,
        ]);
    }

    public function test_denied_comment_edit_preserves_content_and_existing_mentions(): void
    {
        $sender = $this->member();
        $mentioned = $this->member(['username' => 'edit_protected']);
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($sender->id);
        $commentId = $this->comment($postId, $sender->id, 'Original content');

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with($sender->id, [$mentioned->id], $this->testTenantId, 'comment_update')
            ->andThrow($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/comments/{$commentId}", [
            'content' => 'Blocked edit @edit_protected',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('comments', [
            'id' => $commentId,
            'tenant_id' => $this->testTenantId,
            'content' => 'Original content',
        ]);
        $this->assertDatabaseMissing('mentions', [
            'tenant_id' => $this->testTenantId,
            'comment_id' => $commentId,
            'mentioned_user_id' => $mentioned->id,
        ]);
    }

    public function test_denied_reaction_writes_no_reaction_or_notification(): void
    {
        $sender = $this->member();
        $owner = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($owner->id);
        $notificationCount = $this->notificationCount([$owner->id]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($sender->id, $owner->id, $this->testTenantId, 'reaction')
            ->andThrow($this->denied());
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/posts/{$postId}/reactions", [
            'reaction_type' => 'love',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('reactions', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $sender->id,
        ]);
        $this->assertSame($notificationCount, $this->notificationCount([$owner->id]));
    }

    public function test_allowed_reaction_persists_and_notifies_owner(): void
    {
        $sender = $this->member();
        $owner = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($owner->id);
        $notificationCount = $this->notificationCount([$owner->id]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($sender->id, $owner->id, $this->testTenantId, 'reaction');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/posts/{$postId}/reactions", [
            'reaction_type' => 'clap',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.action', 'added');
        $this->assertDatabaseHas('reactions', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $sender->id,
            'emoji' => 'clap',
        ]);
        $this->assertGreaterThan($notificationCount, $this->notificationCount([$owner->id]));
    }

    public function test_unreact_remains_available_without_a_policy_check(): void
    {
        $sender = $this->member();
        $owner = $this->member();
        Sanctum::actingAs($sender, ['*']);

        $postId = $this->createPost($owner->id);
        DB::table('reactions')->insert([
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $sender->id,
            'emoji' => 'love',
            'created_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldNotReceive('assertLocalContactAllowed');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost("/v2/posts/{$postId}/reactions", [
            'reaction_type' => 'love',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.action', 'removed');
        $this->assertDatabaseMissing('reactions', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $sender->id,
        ]);
    }

    private function member(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    private function createPost(int $ownerId): int
    {
        return DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'content' => 'Safeguarding contact policy target',
            'type' => 'post',
            'visibility' => 'public',
            'publish_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function comment(int $postId, int $authorId, string $content): int
    {
        return DB::table('comments')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $postId,
            'user_id' => $authorId,
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<int> $userIds */
    private function notificationCount(array $userIds): int
    {
        return DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->whereIn('user_id', $userIds)
            ->count();
    }

    /** @return list<int> */
    private function sortedIds(int ...$ids): array
    {
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function denied(): SafeguardingPolicyException
    {
        return new SafeguardingPolicyException(
            'VETTING_REQUIRED',
            __('safeguarding.errors.vetting_required', ['types' => 'Enhanced DBS']),
        );
    }

    private function unavailable(): SafeguardingPolicyException
    {
        return new SafeguardingPolicyException(
            'SAFEGUARDING_POLICY_UNAVAILABLE',
            __('safeguarding.errors.policy_unavailable'),
        );
    }
}
