<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\FeedActivity;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — feed parity coverage.
 *
 * Exercises the new feed-parity routes built in FeedParity: hashtag discovery
 * and browse, the polymorphic feed-item permalink, the soft "not interested"
 * signal, and the emoji reaction on non-post feed items. Mirrors the test
 * harness GovukAlphaFrontendTest uses (DatabaseTransactions + tenant/superglobal
 * scrub) so it can run inside the full suite without leaking request state.
 */
class FeedParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Scrub request-scoped tenant/auth state that earlier suite tests leak
        // through superglobals + the auth guards (see GovukAlphaFrontendTest).
        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        \Illuminate\Support\Facades\Cache::flush();
    }

    private function feedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** Create a public feed post + its feed_activity row so the feed query surfaces it. */
    private function feedPostWithActivity(int $userId, string $content): FeedPost
    {
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $userId,
            'content' => $content,
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $userId,
            'content' => $content,
            'created_at' => now()->addMinute(),
        ]);

        return $post;
    }

    /** Tag a feed post with a hashtag (hashtags + post_hashtags rows). */
    private function tagPost(int $postId, string $tag): int
    {
        $tag = strtolower($tag);
        $hashtagId = DB::table('hashtags')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'tag' => $tag,
            'post_count' => 1,
            'last_used_at' => now(),
            'created_at' => now(),
        ]);
        DB::table('post_hashtags')->insert([
            'post_id' => $postId,
            'hashtag_id' => $hashtagId,
            'tenant_id' => $this->testTenantId,
            'created_at' => now(),
        ]);

        return $hashtagId;
    }

    // =====================================================================
    //  Hashtag discovery
    // =====================================================================

    public function test_feed_hashtags_discovery_lists_trending_tags(): void
    {
        $user = $this->feedUser();
        $post = $this->feedPostWithActivity($user->id, 'Post about gardening #garden');
        $this->tagPost($post->id, 'gardenparity');

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/hashtags");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('AGPL-3.0-or-later');
        $response->assertSee(__('govuk_alpha_feed.hashtags.title'));
        $response->assertSee('#gardenparity');
        // Links through to the single-tag browse page.
        $response->assertSee(route('govuk-alpha.feed.hashtag', ['tenantSlug' => $this->testTenantSlug, 'tag' => 'gardenparity']), false);
    }

    public function test_feed_hashtags_search_filters_by_prefix(): void
    {
        $user = $this->feedUser();
        $a = $this->feedPostWithActivity($user->id, 'Post A');
        $b = $this->feedPostWithActivity($user->id, 'Post B');
        $this->tagPost($a->id, 'communityplan');
        $this->tagPost($b->id, 'sportsday');

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/hashtags?q=community");

        $response->assertOk();
        $response->assertSee('#communityplan');
        $response->assertDontSee('#sportsday');
    }

    // =====================================================================
    //  Single hashtag browse
    // =====================================================================

    public function test_feed_hashtag_page_renders_tagged_posts(): void
    {
        $user = $this->feedUser();
        $post = $this->feedPostWithActivity($user->id, 'Tagged feed post body');
        $this->tagPost($post->id, 'taggedtopic');

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/hashtag/taggedtopic");

        $response->assertOk();
        $response->assertSee('#taggedtopic');
        $response->assertSee('Tagged feed post body');
        // Each card deep-links to the post permalink.
        $response->assertSee(route('govuk-alpha.feed.posts.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $post->id]), false);
    }

    public function test_feed_hashtag_unknown_tag_shows_empty_state(): void
    {
        $this->feedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/hashtag/nonexistenttag");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_feed.hashtag.empty_title'));
    }

    // =====================================================================
    //  Polymorphic item permalink
    // =====================================================================

    public function test_feed_item_detail_renders_post_via_polymorphic_route(): void
    {
        $user = $this->feedUser();
        $post = $this->feedPostWithActivity($user->id, 'Polymorphic permalink post');

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/item/post/{$post->id}");

        $response->assertOk();
        $response->assertSee('Polymorphic permalink post');
        $response->assertSee('class="govuk-back-link"', false);
        // The typed-item reaction picker is present (parity gap #5 closed).
        $response->assertSee(route('govuk-alpha.feed.items.react', ['tenantSlug' => $this->testTenantSlug, 'type' => 'post', 'id' => $post->id]), false);
    }

    public function test_feed_item_detail_renders_event_card(): void
    {
        $user = $this->feedUser();
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Polymorphic feed event',
            'description' => 'An event opened via the feed item permalink.',
            'location' => 'Feed Hall',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'event',
            'source_id' => $eventId,
            'user_id' => $user->id,
            'content' => 'Polymorphic feed event',
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/item/event/{$eventId}");

        $response->assertOk();
        $response->assertSee('Polymorphic feed event');
        $response->assertSee(__('govuk_alpha_feed.item_types.event'));
    }

    public function test_feed_item_detail_rejects_unknown_type(): void
    {
        $this->feedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/item/badtype/1");

        $response->assertNotFound();
    }

    public function test_feed_item_detail_404s_for_missing_post(): void
    {
        $this->feedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/item/post/99999999");

        $response->assertNotFound();
    }

    // =====================================================================
    //  Not-interested signal
    // =====================================================================

    public function test_feed_item_not_interested_records_hidden_signal(): void
    {
        $author = $this->feedUser(['name' => 'Author One']);
        $post = $this->feedPostWithActivity($author->id, 'Post to mark not interested');

        // Act as a different member so the not-interested write is meaningful.
        $viewer = $this->feedUser(['name' => 'Viewer One']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/feed/items/post/{$post->id}/not-interested");

        $response->assertRedirectContains('status=not-interested');
        $this->assertDatabaseHas('feed_hidden', [
            'user_id' => $viewer->id,
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $post->id,
        ]);
    }

    public function test_feed_item_not_interested_requires_authentication(): void
    {
        // No authenticated user — the POST must redirect (not write a row).
        $response = $this->post("/{$this->testTenantSlug}/accessible/feed/items/post/1/not-interested");

        $response->assertRedirectContains('status=auth-required');
        $this->assertDatabaseMissing('feed_hidden', [
            'target_type' => 'post',
            'target_id' => 1,
        ]);
    }

    // =====================================================================
    //  Typed-item emoji reaction
    // =====================================================================

    public function test_feed_item_reaction_persists_on_post(): void
    {
        $author = $this->feedUser(['name' => 'Reaction Author']);
        $post = $this->feedPostWithActivity($author->id, 'Post to react to');

        $reactor = $this->feedUser(['name' => 'Reactor']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/feed/items/post/{$post->id}/react", [
            'emoji' => 'love',
        ]);

        $response->assertRedirectContains('status=reaction-added');
        $this->assertDatabaseHas('reactions', [
            'user_id' => $reactor->id,
            'target_type' => 'post',
            'target_id' => $post->id,
            'emoji' => 'love',
        ]);
    }

    public function test_feed_item_reaction_rejects_unknown_emoji(): void
    {
        $author = $this->feedUser(['name' => 'Reaction Author 2']);
        $post = $this->feedPostWithActivity($author->id, 'Post for bad reaction');

        $this->feedUser(['name' => 'Reactor 2']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/feed/items/post/{$post->id}/react", [
            'emoji' => 'not-a-real-reaction',
        ]);

        $response->assertRedirectContains('status=reaction-failed');
        $this->assertDatabaseMissing('reactions', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'emoji' => 'not-a-real-reaction',
        ]);
    }
}
