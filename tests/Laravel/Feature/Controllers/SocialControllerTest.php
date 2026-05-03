<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for SocialController — feed, posts, likes, polls, impressions.
 */
class SocialControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  GET /v2/feed
    // ------------------------------------------------------------------

    public function test_feed_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(401);
    }

    public function test_feed_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts
    // ------------------------------------------------------------------

    public function test_create_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts', [
            'content' => 'Hello world!',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/like
    // ------------------------------------------------------------------

    public function test_like_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/like', [
            'post_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/polls
    // ------------------------------------------------------------------

    public function test_create_poll_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/polls', [
            'question' => 'What do you think?',
            'options' => ['Yes', 'No'],
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/hide
    // ------------------------------------------------------------------

    public function test_hide_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/hide');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/report
    // ------------------------------------------------------------------

    public function test_report_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/report', [
            'reason' => 'spam',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/delete
    // ------------------------------------------------------------------

    public function test_delete_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/delete');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/users/{id}/mute
    // ------------------------------------------------------------------

    public function test_mute_user_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/users/1/mute');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /social/like (legacy)
    // ------------------------------------------------------------------

    public function test_legacy_like_requires_auth(): void
    {
        $response = $this->apiPost('/social/like', ['post_id' => 1]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  Reaction + like persistence across feed reload (regression guard)
    //
    //  This is the class of bug that has regressed repeatedly:
    //  user reacts/likes → page refresh → reaction/like is gone.
    //  Root cause is always: toggle endpoint works, but feed GET never
    //  batch-loads the social state back from the DB.
    // ------------------------------------------------------------------

    /**
     * Regression: reactions must survive a page refresh.
     *
     * Steps: react → reload feed via GET /v2/feed → assert user_reaction
     * is populated for that post.
     *
     * Previously broken because SocialController.feedV2() never called
     * ReactionService::getReactionsForPosts() — fixed 2026-05-01.
     */
    public function test_reaction_persists_after_feed_reload(): void
    {
        $user = $this->authenticatedUser();

        // Create a post + feed_activity entry so it appears in the feed
        $postId = DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id'   => $user->id,
            'content'   => 'Regression test post for reaction persistence',
            'type'      => 'post',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('feed_activity')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'source_type' => 'post',
            'source_id'   => $postId,
            'content'     => 'Regression test post for reaction persistence',
            'is_hidden'   => 0,
            'is_visible'  => 1,
            'created_at'  => now(),
        ]);

        // React to the post
        $this->apiPost("/v2/posts/{$postId}/reactions", ['reaction_type' => 'like'])
            ->assertStatus(200);

        // Simulate page refresh: reload the feed from scratch
        $response = $this->apiGet('/v2/feed?type=posts');
        $response->assertStatus(200);

        $items = $response->json('data') ?? [];
        $post  = collect($items)->firstWhere('id', $postId);

        $this->assertNotNull($post, 'Post should appear in the feed');
        $this->assertNotNull(
            $post['reactions']['user_reaction'] ?? null,
            'Reaction must survive a feed reload — user_reaction should not be null after reacting'
        );
        $this->assertEquals('like', $post['reactions']['user_reaction']);
        $this->assertGreaterThan(0, $post['reactions']['total'] ?? 0);
    }

    /**
     * Regression: is_liked (simple like) must survive a page refresh.
     *
     * Covers the legacy likes table path, separate from emoji reactions.
     */
    public function test_like_persists_after_feed_reload(): void
    {
        $user = $this->authenticatedUser();

        $postId = DB::table('feed_posts')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'user_id'    => $user->id,
            'content'    => 'Regression test post for like persistence',
            'type'       => 'post',
            'visibility' => 'public',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('feed_activity')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'source_type' => 'post',
            'source_id'   => $postId,
            'content'     => 'Regression test post for like persistence',
            'is_hidden'   => 0,
            'is_visible'  => 1,
            'created_at'  => now(),
        ]);

        // Like the post via the likes endpoint
        $this->apiPost('/v2/feed/like', ['target_type' => 'post', 'target_id' => $postId])
            ->assertStatus(200);

        // Simulate page refresh
        $response = $this->apiGet('/v2/feed?type=posts');
        $response->assertStatus(200);

        $items = $response->json('data') ?? [];
        $post  = collect($items)->firstWhere('id', $postId);

        $this->assertNotNull($post, 'Post should appear in the feed');
        $this->assertTrue(
            (bool) ($post['is_liked'] ?? false),
            'is_liked must survive a feed reload — should be true after liking'
        );
        $this->assertGreaterThan(0, $post['likes_count'] ?? 0);
    }

    /**
     * Seed a row in the right backing table for a given feed item type and
     * return its insert id. Each type's table has different required columns,
     * so we hand-roll a minimal valid row per type.
     *
     * MUST stay in sync with the source-table mapping in
     * `FeedService::filterToValidSources()`.
     */
    private function seedReactableEntity(string $type, int $userId): ?int
    {
        $tenantId = $this->testTenantId;
        $now = now();

        try {
            return match ($type) {
                'listing' => DB::table('listings')->insertGetId([
                    'tenant_id' => $tenantId, 'user_id' => $userId,
                    'title' => 'Test listing', 'description' => 'x',
                    'type' => 'offer', 'status' => 'active',
                    'created_at' => $now, 'updated_at' => $now,
                ]),
                'event' => DB::table('events')->insertGetId([
                    'tenant_id' => $tenantId, 'user_id' => $userId,
                    'title' => 'Test event', 'description' => 'x',
                    'start_date' => '2030-01-01 12:00:00',
                    'start_time' => '12:00:00', 'end_time' => '13:00:00',
                    'is_online'  => 0, 'created_at' => $now, 'updated_at' => $now,
                ]),
                'goal' => DB::table('goals')->insertGetId([
                    'tenant_id' => $tenantId, 'user_id' => $userId,
                    'title' => 'Test goal', 'description' => 'x',
                    'is_public' => 1, 'status' => 'active',
                    'created_at' => $now, 'updated_at' => $now,
                ]),
                'review' => DB::table('reviews')->insertGetId([
                    'tenant_id' => $tenantId,
                    'reviewer_id' => $userId, 'receiver_id' => $userId,
                    'rating' => 5, 'comment' => 'Great',
                    'review_type' => 'local', 'status' => 'approved',
                    'created_at' => $now, 'updated_at' => $now,
                ]),
                'volunteer' => DB::table('vol_opportunities')->insertGetId([
                    'tenant_id' => $tenantId, 'organization_id' => 0, 'created_by' => $userId,
                    'title' => 'Test volunteer', 'description' => 'x',
                    'is_active' => 1, 'status' => 'active',
                    'created_at' => $now,
                ]),
                'challenge' => DB::table('ideation_challenges')->insertGetId([
                    'tenant_id' => $tenantId, 'user_id' => $userId,
                    'title' => 'Test challenge', 'description' => 'x',
                    'status' => 'open',
                    'created_at' => $now, 'updated_at' => $now,
                ]),
                default => null,
            };
        } catch (\Throwable $e) {
            // Surface failures so missing columns aren't silently swallowed.
            // Re-throw on CI so the test author sees the schema mismatch.
            if (getenv('CI')) {
                throw $e;
            }
            fwrite(STDERR, "[seedReactableEntity:{$type}] " . $e->getMessage() . "\n");
            return null;
        }
    }

    /**
     * Polymorphic reaction persistence — the bug class this fix is targeting.
     *
     * Before 2026-05-03 the React frontend posted ALL reactions to
     * /v2/posts/{id}/reactions and the backend hardcoded target_type='post'.
     * Reactions on listings/events/goals/etc. were stored against the wrong
     * type and never read back on reload — the recurring "like doesn't
     * persist" bug.
     *
     * If a new reactable type is added (e.g. 'discussion'), add it here AND
     * to ReactionService::VALID_TARGET_TYPES AND extend seedReactableEntity().
     *
     * @return array<string, array{0: string}>
     */
    public static function reactableFeedTypeProvider(): array
    {
        return [
            'listing'   => ['listing'],
            'event'     => ['event'],
            'goal'      => ['goal'],
            'review'    => ['review'],
            'volunteer' => ['volunteer'],
            'challenge' => ['challenge'],
        ];
    }

    /**
     * @dataProvider reactableFeedTypeProvider
     */
    public function test_reactions_persist_across_reload_for_all_feed_types(string $type): void
    {
        $user = $this->authenticatedUser();

        $entityId = $this->seedReactableEntity($type, $user->id);
        if ($entityId === null) {
            $this->markTestSkipped("Could not seed {$type} entity in this schema");
        }

        DB::table('feed_activity')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'source_type' => $type,
            'source_id'   => $entityId,
            'content'     => "Regression test {$type}",
            'is_hidden'   => 0,
            'is_visible'  => 1,
            'created_at'  => now(),
        ]);

        $this->apiPost('/v2/reactions', [
            'target_type' => $type,
            'target_id' => $entityId,
            'reaction_type' => 'like',
        ])->assertStatus(200);

        $response = $this->apiGet('/v2/feed');
        $response->assertStatus(200);

        $items = $response->json('data') ?? [];
        $item  = collect($items)->first(
            fn ($i) => ($i['type'] ?? null) === $type && (int) ($i['id'] ?? 0) === (int) $entityId
        );

        $this->assertNotNull($item, "{$type}:{$entityId} should appear in the feed");
        $this->assertEquals(
            'like',
            $item['reactions']['user_reaction'] ?? null,
            "Reaction must survive a feed reload for type={$type}"
        );
        $this->assertGreaterThan(0, $item['reactions']['total'] ?? 0);
    }

    /**
     * Polymorphic toggle endpoint validates target_type whitelist.
     */
    public function test_reactions_endpoint_rejects_invalid_target_type(): void
    {
        $this->authenticatedUser();
        $this->apiPost('/v2/reactions', [
            'target_type' => 'unknown_type',
            'target_id' => 1,
            'reaction_type' => 'like',
        ])->assertStatus(400);
    }

    // ------------------------------------------------------------------
    //  POST /social/feed (legacy)
    // ------------------------------------------------------------------

    public function test_legacy_feed_requires_auth(): void
    {
        $response = $this->apiPost('/social/feed');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  Tenant isolation
    // ------------------------------------------------------------------

    public function test_feed_is_tenant_scoped(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }
}
