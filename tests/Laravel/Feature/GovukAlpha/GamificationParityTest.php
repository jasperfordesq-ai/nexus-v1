<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature coverage for the accessible-frontend gamification parity module
 * (XP shop, badge collections/detail/showcase, competitive leaderboard with the
 * nexus_score metric, seasons, personal journey, member spotlight, engagement
 * history, nexus tier ladder, and ranked-choice / managed polls).
 *
 * Mirrors GovukAlphaFrontendTest's base class, traits and helpers. Every test
 * method is prefixed test_gamification_ and globally unique.
 */
class GamificationParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

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

    // ==================================================================
    //  XP SHOP
    // ==================================================================

    public function test_gamification_shop_requires_auth(): void
    {
        $this->get("/{$this->testTenantSlug}/alpha/achievements/shop")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_gamification_shop_renders_with_balance(): void
    {
        $this->authenticatedUser(['name' => 'Shopper One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/achievements/shop");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.shop.title'));
        $res->assertSee(__('govuk_alpha_gamification.shop.balance_label'));
    }

    public function test_gamification_shop_purchase_redirects_with_status(): void
    {
        $this->authenticatedUser(['name' => 'Shopper Buy']);
        // No such item → the purchase fails gracefully and redirects back.
        $res = $this->post("/{$this->testTenantSlug}/alpha/achievements/shop/purchase", [
            'item_id' => 999999,
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=purchase-failed');
    }

    // ==================================================================
    //  COLLECTIONS
    // ==================================================================

    public function test_gamification_collections_renders(): void
    {
        $this->authenticatedUser(['name' => 'Collector One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/achievements/collections");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.collections.title'));
    }

    // ==================================================================
    //  SHOWCASE
    // ==================================================================

    public function test_gamification_showcase_renders(): void
    {
        $this->authenticatedUser(['name' => 'Showcase One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/achievements/showcase");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.showcase.title'));
    }

    public function test_gamification_showcase_update_persists_owned_badge(): void
    {
        $user = $this->authenticatedUser(['name' => 'Showcase Save']);
        // Seed an earned badge for the member.
        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'badge_key' => 'vol_1h',
            'is_showcased' => 0,
            'awarded_at' => now(),
        ]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/achievements/showcase", [
            'badge_keys' => ['vol_1h'],
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=showcase-updated');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertSame(1, (int) DB::table('user_badges')
            ->where('user_id', $user->id)
            ->where('badge_key', 'vol_1h')
            ->where('is_showcased', 1)
            ->count());
    }

    public function test_gamification_showcase_rejects_more_than_five(): void
    {
        $this->authenticatedUser(['name' => 'Showcase Many']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/achievements/showcase", [
            'badge_keys' => ['a', 'b', 'c', 'd', 'e', 'f'],
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=showcase-too-many');
    }

    // ==================================================================
    //  BADGE DETAIL
    // ==================================================================

    public function test_gamification_badge_detail_renders_known_badge(): void
    {
        $this->authenticatedUser(['name' => 'Badge Viewer']);
        // vol_1h is a static badge definition.
        $res = $this->get("/{$this->testTenantSlug}/alpha/achievements/badges/vol_1h");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.badge.not_earned_status'));
    }

    public function test_gamification_badge_detail_404_for_unknown_badge(): void
    {
        $this->authenticatedUser(['name' => 'Badge Missing']);
        $this->get("/{$this->testTenantSlug}/alpha/achievements/badges/no_such_badge_key")
            ->assertStatus(404);
    }

    // ==================================================================
    //  ENGAGEMENT
    // ==================================================================

    public function test_gamification_engagement_renders(): void
    {
        $this->authenticatedUser(['name' => 'Engaged One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/achievements/engagement");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.engagement.title'));
    }

    // ==================================================================
    //  COMPETITIVE LEADERBOARD
    // ==================================================================

    public function test_gamification_competitive_renders_with_metric_filter(): void
    {
        $this->authenticatedUser(['name' => 'Competitor One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/competitive");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.competitive.title'));
        // The nexus_score metric (missing from the legacy leaderboard) is offered here.
        $res->assertSee(__('govuk_alpha_gamification.competitive.metrics.nexus_score'));
    }

    public function test_gamification_competitive_accepts_nexus_score_type(): void
    {
        $this->authenticatedUser(['name' => 'Competitor Nexus']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/competitive?type=nexus_score&period=all");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.competitive.metrics.nexus_score'));
    }

    public function test_gamification_competitive_requires_auth(): void
    {
        $this->get("/{$this->testTenantSlug}/alpha/leaderboard/competitive")
            ->assertRedirectContains('/alpha/login');
    }

    // ==================================================================
    //  SEASONS / JOURNEY / SPOTLIGHT
    // ==================================================================

    public function test_gamification_seasons_renders(): void
    {
        $this->authenticatedUser(['name' => 'Season One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/seasons");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.seasons.title'));
    }

    public function test_gamification_journey_renders(): void
    {
        $this->authenticatedUser(['name' => 'Journey One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/journey");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.journey.title'));
    }

    public function test_gamification_spotlight_renders(): void
    {
        $this->authenticatedUser(['name' => 'Spotlight One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/spotlight");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.spotlight.title'));
    }

    // ==================================================================
    //  NEXUS TIER LADDER
    // ==================================================================

    public function test_gamification_tier_ladder_renders(): void
    {
        $this->authenticatedUser(['name' => 'Tier One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/nexus-score/tiers");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.tiers.title'));
        $res->assertSee(__('govuk_alpha_gamification.tiers.names.legendary'));
    }

    // ==================================================================
    //  POLL CREATE (parity — supports ranked)
    // ==================================================================

    public function test_gamification_poll_create_form_renders(): void
    {
        $this->authenticatedUser(['name' => 'Poll Author']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/polls/parity/create");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.poll_create.title'));
        // The correct ranked enum is offered (the legacy form sends 'multiple').
        $res->assertSee('value="ranked"', false);
    }

    public function test_gamification_store_ranked_poll_persists(): void
    {
        $user = $this->authenticatedUser(['name' => 'Ranked Author']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/parity/create", [
            'question' => 'Pick a meeting time, ranked',
            'options' => ['Morning', 'Afternoon', 'Evening'],
            'poll_type' => 'ranked',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=poll-created');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('polls', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'question' => 'Pick a meeting time, ranked',
            'poll_type' => 'ranked',
        ]);
    }

    public function test_gamification_store_poll_validation_redirects_back(): void
    {
        $this->authenticatedUser(['name' => 'Poll Blank']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/parity/create", [
            'question' => '',
            'options' => ['only-one'],
            'poll_type' => 'standard',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=poll-create-failed');
    }

    // ==================================================================
    //  RANKED VOTING
    // ==================================================================

    public function test_gamification_ranked_vote_page_renders_for_ranked_poll(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Ranked Owner']);
        $pollId = $this->seedRankedPoll($creator->id);

        $this->authenticatedUser(['name' => 'Ranked Voter']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}/rank");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.ranked.badge'));
        $res->assertSee(__('govuk_alpha_gamification.ranked.submit_button'));
    }

    public function test_gamification_ranked_vote_404_for_standard_poll(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Standard Owner']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creator->id, 'question' => 'Standard poll',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'standard',
        ]);

        $this->authenticatedUser(['name' => 'Confused Voter']);
        $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}/rank")->assertStatus(404);
    }

    public function test_gamification_store_ranked_vote_persists(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Rank Owner Two']);
        [$pollId, $optA, $optB] = $this->seedRankedPollWithOptions($creator->id);

        $voter = $this->authenticatedUser(['name' => 'Rank Submitter']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/rank", [
            'rank' => [$optA => 1, $optB => 2],
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=ranked');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertSame(2, (int) DB::table('poll_rankings')
            ->where('poll_id', $pollId)
            ->where('user_id', $voter->id)
            ->count());
    }

    // ==================================================================
    //  POLL MANAGE / DELETE / EXPORT
    // ==================================================================

    public function test_gamification_manage_polls_renders(): void
    {
        $this->authenticatedUser(['name' => 'Manager One']);
        $res = $this->get("/{$this->testTenantSlug}/alpha/polls/parity/manage");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.poll_manage.title'));
    }

    public function test_gamification_delete_poll_owner_only(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Delete Owner']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $owner->id, 'question' => 'Delete me',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'standard',
        ]);

        // A different member cannot delete it (service ownership check → fails).
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($other, ['*']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/delete");
        $res->assertRedirectContains('status=poll-delete-failed');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('polls', ['id' => $pollId]);

        // The owner can delete it.
        Sanctum::actingAs($owner, ['*']);
        $res2 = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/delete");
        $res2->assertRedirectContains('status=poll-deleted');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('polls', ['id' => $pollId]);
    }

    public function test_gamification_export_poll_404_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Export Owner']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $owner->id, 'question' => 'Export poll',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'standard',
        ]);

        // A non-owner gets a 404 (PollExportService returns null → abort 404).
        $this->authenticatedUser(['name' => 'Export Stranger']);
        $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}/export")->assertStatus(404);
    }

    public function test_gamification_export_poll_returns_csv_for_owner(): void
    {
        $owner = $this->authenticatedUser(['name' => 'CSV Owner']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $owner->id, 'question' => 'CSV poll',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'standard',
        ]);
        DB::table('poll_options')->insert([
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Yes', 'votes' => 0],
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'No', 'votes' => 0],
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}/export");
        $res->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    // ==================================================================
    //  COMPETITIVE LEADERBOARD — load more
    // ==================================================================

    public function test_gamification_competitive_renders_with_load_more_param(): void
    {
        $this->authenticatedUser(['name' => 'Comp Viewer']);
        // A larger limit window still renders the page (mirrors React load-more).
        $res = $this->get("/{$this->testTenantSlug}/alpha/leaderboard/competitive?type=xp&period=all&limit=40");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.competitive.title'));
    }

    // ==================================================================
    //  POLL DETAIL + SOCIAL (like / comment)
    // ==================================================================

    public function test_gamification_poll_detail_requires_auth(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'PD Owner']);
        $pollId = $this->seedStandardPoll($owner->id);
        $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_gamification_poll_detail_renders(): void
    {
        $owner = $this->authenticatedUser(['name' => 'PD Author']);
        $pollId = $this->seedStandardPoll($owner->id);
        $res = $this->get("/{$this->testTenantSlug}/alpha/polls/{$pollId}");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_gamification.poll_detail.social_heading'));
        $res->assertSee(__('govuk_alpha_gamification.poll_detail.comments_heading'));
    }

    public function test_gamification_poll_detail_404_for_missing_poll(): void
    {
        $this->authenticatedUser(['name' => 'PD Missing']);
        $this->get("/{$this->testTenantSlug}/alpha/polls/9999999")->assertStatus(404);
    }

    public function test_gamification_poll_like_persists_a_like_row(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Like Owner']);
        $pollId = $this->seedStandardPoll($owner->id);

        $liker = $this->authenticatedUser(['name' => 'Liker One']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/like");
        $res->assertRedirectContains('status=poll-liked');

        $this->assertDatabaseHas('likes', [
            'target_type' => 'poll',
            'target_id'   => $pollId,
            'user_id'     => $liker->id,
            'tenant_id'   => $this->testTenantId,
        ]);

        // A second toggle removes the like.
        $res2 = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/like");
        $res2->assertRedirectContains('status=poll-unliked');
        $this->assertDatabaseMissing('likes', [
            'target_type' => 'poll',
            'target_id'   => $pollId,
            'user_id'     => $liker->id,
            'tenant_id'   => $this->testTenantId,
        ]);
    }

    public function test_gamification_poll_comment_persists(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Comment Owner']);
        $pollId = $this->seedStandardPoll($owner->id);

        $commenter = $this->authenticatedUser(['name' => 'Commenter One']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/comment", [
            'content' => 'Great poll, thanks for setting this up.',
        ]);
        $res->assertRedirectContains('status=poll-comment-created');

        $this->assertDatabaseHas('comments', [
            'target_type' => 'poll',
            'target_id'   => $pollId,
            'user_id'     => $commenter->id,
            'tenant_id'   => $this->testTenantId,
        ]);
    }

    public function test_gamification_poll_comment_empty_redirects_back(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Empty Owner']);
        $pollId = $this->seedStandardPoll($owner->id);

        $this->authenticatedUser(['name' => 'Empty Commenter']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/comment", [
            'content' => '   ',
        ]);
        $res->assertRedirectContains('status=poll-comment-empty');
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    /** An open standard poll with two options (for detail/like/comment tests). */
    private function seedStandardPoll(int $creatorId): int
    {
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creatorId, 'question' => 'What should we do next?',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'standard',
        ]);
        DB::table('poll_options')->insert([
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Plan A', 'votes' => 0],
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Plan B', 'votes' => 0],
        ]);

        return $pollId;
    }

    private function seedRankedPoll(int $creatorId): int
    {
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creatorId, 'question' => 'Rank these options',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'ranked',
        ]);
        DB::table('poll_options')->insert([
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Option A', 'votes' => 0],
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Option B', 'votes' => 0],
        ]);

        return $pollId;
    }

    /** @return array{0:int,1:int,2:int} pollId, optionA id, optionB id */
    private function seedRankedPollWithOptions(int $creatorId): array
    {
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creatorId, 'question' => 'Order them',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(), 'poll_type' => 'ranked',
        ]);
        $optA = DB::table('poll_options')->insertGetId(['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'First', 'votes' => 0]);
        $optB = DB::table('poll_options')->insertGetId(['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Second', 'votes' => 0]);

        return [$pollId, $optA, $optB];
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
