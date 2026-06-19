<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Feature\GovukAlphaFrontendTest;

/**
 * Accessible (GOV.UK) frontend — Ideation parity coverage.
 *
 * Extends the same base as GovukAlphaFrontendTest so it inherits the tenant
 * setup, superglobal scrubbing and cache flush. Private helpers in the base
 * class are re-declared here (PHP cannot call a parent's private methods).
 */
class IdeationParityTest extends GovukAlphaFrontendTest
{
    private function ideationEnableFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['ideation_challenges'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function ideationUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function ideationAdmin(): User
    {
        return $this->ideationUser(['role' => 'admin']);
    }

    private function ideationCreateChallenge(int $creatorId, array $overrides = []): int
    {
        return DB::table('ideation_challenges')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $creatorId,
            'title' => 'Greener Streets Challenge',
            'description' => 'Ideas to make our streets greener and safer.',
            'status' => 'open',
            'ideas_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function ideationCreateIdea(int $challengeId, int $authorId, array $overrides = []): int
    {
        return DB::table('challenge_ideas')->insertGetId(array_merge([
            'challenge_id' => $challengeId,
            'user_id' => $authorId,
            'title' => 'Plant more trees',
            'description' => 'Plant native trees along the high street.',
            'votes_count' => 0,
            'comments_count' => 0,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function ideationCreateCampaign(int $creatorId, array $overrides = []): int
    {
        return DB::table('campaigns')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'title' => 'Climate Action Campaign',
            'description' => 'A campaign grouping our sustainability challenges.',
            'status' => 'active',
            'created_by' => $creatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ================================================================
    // Auth gating
    // ================================================================

    public function test_ideation_idea_detail_requires_login(): void
    {
        $this->ideationEnableFeature();
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $challengeId = $this->ideationCreateChallenge($author->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $author->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_ideation_campaigns_requires_login(): void
    {
        $this->ideationEnableFeature();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/campaigns");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    // ================================================================
    // Idea detail page
    // ================================================================

    public function test_ideation_idea_detail_renders_for_member(): void
    {
        $this->ideationEnableFeature();
        $viewer = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($viewer->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $viewer->id, ['title' => 'Bike racks everywhere']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}");

        $resp->assertOk();
        $resp->assertSee('Bike racks everywhere');
        $resp->assertSee(__('govuk_alpha_ideation.comments.heading'));
    }

    public function test_ideation_idea_detail_cross_challenge_returns_404(): void
    {
        $this->ideationEnableFeature();
        $viewer = $this->ideationUser();
        $challengeA = $this->ideationCreateChallenge($viewer->id);
        $challengeB = $this->ideationCreateChallenge($viewer->id, ['title' => 'Other challenge']);
        $ideaId = $this->ideationCreateIdea($challengeA, $viewer->id);

        // Idea belongs to challengeA but the URL references challengeB → 404.
        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeB}/ideas/{$ideaId}");

        $resp->assertNotFound();
    }

    public function test_ideation_idea_detail_unknown_idea_returns_404(): void
    {
        $this->ideationEnableFeature();
        $viewer = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($viewer->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/99999999");

        $resp->assertNotFound();
    }

    public function test_ideation_comment_persists(): void
    {
        $this->ideationEnableFeature();
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $commenter = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($author->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $author->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/comments", [
            'comment_body' => 'A really helpful comment.',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('challenge_idea_comments', [
            'idea_id' => $ideaId,
            'user_id' => $commenter->id,
            'body' => 'A really helpful comment.',
        ]);
    }

    public function test_ideation_empty_comment_is_rejected(): void
    {
        $this->ideationEnableFeature();
        $viewer = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($viewer->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $viewer->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/comments", [
            'comment_body' => '   ',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseMissing('challenge_idea_comments', ['idea_id' => $ideaId]);
    }

    public function test_ideation_vote_toggles_on_someone_elses_idea(): void
    {
        $this->ideationEnableFeature();
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $voter = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($author->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $author->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/toggle-vote");

        $resp->assertRedirect();
        $this->assertDatabaseHas('challenge_idea_votes', [
            'idea_id' => $ideaId,
            'user_id' => $voter->id,
        ]);
    }

    public function test_ideation_admin_can_set_idea_status_winner(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $admin->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/status", [
            'idea_status' => 'winner',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('challenge_ideas', ['id' => $ideaId, 'status' => 'winner']);
    }

    public function test_ideation_non_admin_cannot_set_idea_status(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $member->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/status", [
            'idea_status' => 'winner',
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseHas('challenge_ideas', ['id' => $ideaId, 'status' => 'submitted']);
    }

    public function test_ideation_owner_can_delete_own_idea(): void
    {
        $this->ideationEnableFeature();
        $owner = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($owner->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/delete");

        $resp->assertRedirect();
        $this->assertDatabaseMissing('challenge_ideas', ['id' => $ideaId]);
    }

    public function test_ideation_add_media_persists(): void
    {
        $this->ideationEnableFeature();
        $owner = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($owner->id);
        $ideaId = $this->ideationCreateIdea($challengeId, $owner->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/media", [
            'media_type' => 'link',
            'media_url' => 'https://example.com/mockup',
            'media_caption' => 'Concept mockup',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('idea_media', [
            'idea_id' => $ideaId,
            'url' => 'https://example.com/mockup',
        ]);
    }

    // ================================================================
    // Create / edit challenge (admin only)
    // ================================================================

    public function test_ideation_create_form_renders_for_admin(): void
    {
        $this->ideationEnableFeature();
        $this->ideationAdmin();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/new");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_ideation.form.create_title'));
    }

    public function test_ideation_create_form_forbidden_for_member(): void
    {
        $this->ideationEnableFeature();
        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/new");

        $resp->assertForbidden();
    }

    public function test_ideation_store_challenge_persists(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/new", [
            'title' => 'Brand new challenge',
            'description' => 'Describe the brand new challenge.',
            'challenge_status' => 'open',
            'tags' => 'design, climate',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('ideation_challenges', [
            'tenant_id' => $this->testTenantId,
            'title' => 'Brand new challenge',
            'status' => 'open',
        ]);
    }

    public function test_ideation_store_challenge_rejects_empty_title(): void
    {
        $this->ideationEnableFeature();
        $this->ideationAdmin();

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/new", [
            'title' => '',
            'description' => 'Has a description but no title.',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/new?status=challenge-invalid");
        $this->assertDatabaseMissing('ideation_challenges', [
            'description' => 'Has a description but no title.',
        ]);
    }

    public function test_ideation_edit_form_renders_for_admin(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id, ['title' => 'Editable challenge']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/edit");

        $resp->assertOk();
        $resp->assertSee('Editable challenge');
    }

    public function test_ideation_update_challenge_persists(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/edit", [
            'title' => 'Renamed challenge',
            'description' => 'Updated description text.',
            'challenge_status' => 'open',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('ideation_challenges', [
            'id' => $challengeId,
            'title' => 'Renamed challenge',
        ]);
    }

    // ================================================================
    // Challenge lifecycle / favorite / duplicate / delete / manage
    // ================================================================

    public function test_ideation_manage_page_renders_for_admin(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/manage");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_ideation.manage.heading'));
    }

    public function test_ideation_manage_page_forbidden_for_member(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/manage");

        $resp->assertForbidden();
    }

    public function test_ideation_admin_status_transition_persists(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        // draft → open is a valid transition.
        $challengeId = $this->ideationCreateChallenge($admin->id, ['status' => 'draft']);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/status", [
            'challenge_status' => 'open',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('ideation_challenges', ['id' => $challengeId, 'status' => 'open']);
    }

    public function test_ideation_favorite_toggle_persists(): void
    {
        $this->ideationEnableFeature();
        $user = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($user->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/favorite");

        $resp->assertRedirect();
        $this->assertDatabaseHas('challenge_favorites', [
            'challenge_id' => $challengeId,
            'user_id' => $user->id,
        ]);
    }

    public function test_ideation_admin_can_delete_challenge(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/delete");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation?status=challenge-deleted");
        $this->assertDatabaseMissing('ideation_challenges', ['id' => $challengeId]);
    }

    public function test_ideation_member_cannot_delete_challenge(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/delete");

        $resp->assertForbidden();
        $this->assertDatabaseHas('ideation_challenges', ['id' => $challengeId]);
    }

    public function test_ideation_admin_can_duplicate_challenge(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id, ['title' => 'Original']);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/duplicate");

        $resp->assertRedirect();
        // The duplicate is a draft copy with a "[Copy]" prefixed title.
        $this->assertDatabaseHas('ideation_challenges', [
            'tenant_id' => $this->testTenantId,
            'title' => '[Copy] Original',
            'status' => 'draft',
        ]);
    }

    // ================================================================
    // Campaigns
    // ================================================================

    public function test_ideation_campaigns_page_renders(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $this->ideationCreateCampaign($admin->id, ['title' => 'Listed campaign']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/campaigns");

        $resp->assertOk();
        $resp->assertSee('Listed campaign');
    }

    public function test_ideation_admin_can_create_campaign(): void
    {
        $this->ideationEnableFeature();
        $this->ideationAdmin();

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/campaigns", [
            'title' => 'New 2026 campaign',
            'description' => 'Our flagship campaign.',
            'campaign_status' => 'active',
        ]);

        $resp->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'tenant_id' => $this->testTenantId,
            'title' => 'New 2026 campaign',
        ]);
    }

    public function test_ideation_member_cannot_create_campaign(): void
    {
        $this->ideationEnableFeature();
        $this->ideationUser();

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/campaigns", [
            'title' => 'Sneaky campaign',
        ]);

        $resp->assertForbidden();
        $this->assertDatabaseMissing('campaigns', ['title' => 'Sneaky campaign']);
    }

    public function test_ideation_campaign_detail_renders(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $campaignId = $this->ideationCreateCampaign($admin->id, ['title' => 'Detailed campaign']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/campaigns/{$campaignId}");

        $resp->assertOk();
        $resp->assertSee('Detailed campaign');
    }

    public function test_ideation_campaign_detail_unknown_returns_404(): void
    {
        $this->ideationEnableFeature();
        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/campaigns/99999999");

        $resp->assertNotFound();
    }

    public function test_ideation_admin_can_link_and_unlink_challenge_to_campaign(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id);
        $campaignId = $this->ideationCreateCampaign($admin->id);

        $link = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/link-campaign", [
            'campaign_id' => $campaignId,
        ]);
        $link->assertRedirect();
        $this->assertDatabaseHas('campaign_challenges', [
            'campaign_id' => $campaignId,
            'challenge_id' => $challengeId,
        ]);

        $unlink = $this->post("/{$this->testTenantSlug}/alpha/ideation/campaigns/{$campaignId}/challenges/{$challengeId}/unlink");
        $unlink->assertRedirect();
        $this->assertDatabaseMissing('campaign_challenges', [
            'campaign_id' => $campaignId,
            'challenge_id' => $challengeId,
        ]);
    }

    public function test_ideation_admin_can_delete_campaign(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $campaignId = $this->ideationCreateCampaign($admin->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/campaigns/{$campaignId}/delete");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/campaigns?status=campaign-deleted");
        $this->assertDatabaseMissing('campaigns', ['id' => $campaignId]);
    }

    // ================================================================
    // Outcomes
    // ================================================================

    public function test_ideation_outcomes_dashboard_renders(): void
    {
        $this->ideationEnableFeature();
        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/outcomes");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_ideation.outcomes.title'));
    }

    public function test_ideation_outcome_form_forbidden_for_member(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/outcome");

        $resp->assertForbidden();
    }

    public function test_ideation_admin_can_save_outcome(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id, ['status' => 'closed']);

        // Save status + impact only (no winning idea). The winning-idea path is
        // covered separately by test_ideation_admin_can_save_outcome_with_winning_idea.
        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/outcome", [
            'outcome_status' => 'implemented',
            'impact_description' => 'Trees were planted across the town.',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/outcome?status=outcome-saved");
        $this->assertDatabaseHas('challenge_outcomes', [
            'challenge_id' => $challengeId,
            'tenant_id' => $this->testTenantId,
            'status' => 'implemented',
        ]);
    }

    public function test_ideation_admin_can_save_outcome_with_winning_idea(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeId = $this->ideationCreateChallenge($admin->id, ['status' => 'closed']);
        $ideaId = $this->ideationCreateIdea($challengeId, $admin->id, ['title' => 'The winning idea']);

        // Regression guard (fix 8f56eeae9): challenge_ideas has no tenant_id
        // column, but ChallengeOutcomeService::upsert() once validated the
        // winning idea with ->where('tenant_id', ...) on that table — so saving
        // an outcome WITH a winner threw "Unknown column 'tenant_id'" and 500'd
        // on both the React (PUT /v2/ideation-challenges/{id}/outcome) and
        // accessible frontends. If that clause is reintroduced, upsert() catches
        // the SQL error and returns null, flipping this to ?status=outcome-failed
        // with no persisted row — failing both assertions below.
        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/outcome", [
            'outcome_status' => 'implemented',
            'impact_description' => 'The winning idea was rolled out town-wide.',
            'winning_idea_id' => $ideaId,
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/outcome?status=outcome-saved");
        $this->assertDatabaseHas('challenge_outcomes', [
            'challenge_id' => $challengeId,
            'tenant_id' => $this->testTenantId,
            'status' => 'implemented',
            'winning_idea_id' => $ideaId,
        ]);
    }

    public function test_ideation_outcome_rejects_winning_idea_from_another_challenge(): void
    {
        $this->ideationEnableFeature();
        $admin = $this->ideationAdmin();
        $challengeA = $this->ideationCreateChallenge($admin->id);
        $challengeB = $this->ideationCreateChallenge($admin->id, ['title' => 'Other challenge']);
        // Idea belongs to challengeB, but we try to set it as challengeA's winner.
        $foreignIdeaId = $this->ideationCreateIdea($challengeB, $admin->id);

        // The remaining ->where('challenge_id', ...) filter must still reject a
        // mismatched winner; dropping the tenant_id clause must not weaken this.
        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeA}/outcome", [
            'outcome_status' => 'implemented',
            'winning_idea_id' => $foreignIdeaId,
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeA}/outcome?status=outcome-failed");
        $this->assertDatabaseMissing('challenge_outcomes', ['challenge_id' => $challengeA]);
    }

    // ================================================================
    // Draft ideas
    // ================================================================

    private function ideationCreateDraft(int $challengeId, int $authorId, array $overrides = []): int
    {
        return $this->ideationCreateIdea($challengeId, $authorId, array_merge([
            'title' => 'A half-finished idea',
            'description' => '',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_ideation_drafts_page_requires_login(): void
    {
        $this->ideationEnableFeature();
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $challengeId = $this->ideationCreateChallenge($author->id);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_ideation_drafts_page_lists_only_own_drafts(): void
    {
        $this->ideationEnableFeature();
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);
        $this->ideationCreateDraft($challengeId, $member->id, ['title' => 'My own draft']);
        $this->ideationCreateDraft($challengeId, $other->id, ['title' => 'Someone elses draft']);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts");

        $resp->assertOk();
        $resp->assertSee('My own draft');
        $resp->assertDontSee('Someone elses draft');
    }

    public function test_ideation_drafts_page_unknown_challenge_returns_404(): void
    {
        $this->ideationEnableFeature();
        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/99999999/drafts");

        $resp->assertNotFound();
    }

    public function test_ideation_draft_save_persists_title(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);
        $draftId = $this->ideationCreateDraft($challengeId, $member->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts/{$draftId}", [
            'draft_title' => 'Now with a better title',
            'draft_description' => 'Still working on the detail.',
            'draft_action' => 'save',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts?status=draft-saved");
        $this->assertDatabaseHas('challenge_ideas', [
            'id' => $draftId,
            'title' => 'Now with a better title',
            'status' => 'draft',
        ]);
    }

    public function test_ideation_draft_publish_promotes_to_submitted(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);
        $draftId = $this->ideationCreateDraft($challengeId, $member->id);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts/{$draftId}", [
            'draft_title' => 'Ready to share',
            'draft_description' => 'A fully formed idea with detail.',
            'draft_action' => 'publish',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$draftId}?status=idea-submitted");
        $this->assertDatabaseHas('challenge_ideas', [
            'id' => $draftId,
            'title' => 'Ready to share',
            'status' => 'submitted',
        ]);
    }

    public function test_ideation_draft_save_rejects_empty_title(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);
        $draftId = $this->ideationCreateDraft($challengeId, $member->id, ['title' => 'Original title']);

        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts/{$draftId}", [
            'draft_title' => '   ',
            'draft_action' => 'save',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts?status=draft-invalid");
        $this->assertDatabaseHas('challenge_ideas', ['id' => $draftId, 'title' => 'Original title']);
    }

    public function test_ideation_draft_cannot_be_edited_by_non_owner(): void
    {
        $this->ideationEnableFeature();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $challengeId = $this->ideationCreateChallenge($owner->id);
        $draftId = $this->ideationCreateDraft($challengeId, $owner->id, ['title' => 'Owners draft']);

        // A different member attempts to edit it — the service rejects (not owner),
        // so the title must be unchanged and we land on draft-failed.
        $this->ideationUser();
        $resp = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts/{$draftId}", [
            'draft_title' => 'Hijacked title',
            'draft_action' => 'save',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/drafts?status=draft-failed");
        $this->assertDatabaseHas('challenge_ideas', ['id' => $draftId, 'title' => 'Owners draft']);
    }

    // ================================================================
    // Browse by popular tag
    // ================================================================

    public function test_ideation_tags_page_requires_login(): void
    {
        $this->ideationEnableFeature();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/tags");

        $resp->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_ideation_tags_page_renders_popular_tags(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        $challengeId = $this->ideationCreateChallenge($member->id);

        // Create a tag and link the challenge to it (mirrors getAllTags()).
        $tagId = DB::table('challenge_tags')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Sustainability',
            'slug' => 'sustainability',
            'tag_type' => 'general',
            'created_at' => now(),
        ]);
        DB::table('challenge_tag_links')->insert([
            'challenge_id' => $challengeId,
            'tag_id' => $tagId,
        ]);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/tags");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_ideation.tags.popular_heading'));
        $resp->assertSee('Sustainability');
    }

    public function test_ideation_tags_page_filters_challenges_by_selected_tag(): void
    {
        $this->ideationEnableFeature();
        $member = $this->ideationUser();
        // One challenge tagged 'climate' (stored in the JSON tags column), one not.
        $tagged = $this->ideationCreateChallenge($member->id, [
            'title' => 'Tagged climate challenge',
            'tags' => json_encode(['climate', 'design']),
        ]);
        $this->ideationCreateChallenge($member->id, [
            'title' => 'Untagged challenge',
            'tags' => json_encode([]),
        ]);

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/tags?tag=climate");

        $resp->assertOk();
        $resp->assertSee('Tagged climate challenge');
        $resp->assertDontSee('Untagged challenge');
    }

    public function test_ideation_tags_page_feature_gated_returns_403(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['ideation_challenges'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/tags");

        $resp->assertForbidden();
    }

    // ================================================================
    // Feature gate
    // ================================================================

    public function test_ideation_campaigns_feature_gated_returns_403(): void
    {
        // Disable the feature explicitly then hit a campaigns route.
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['ideation_challenges'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->ideationUser();

        $resp = $this->get("/{$this->testTenantSlug}/alpha/ideation/campaigns");

        $resp->assertForbidden();
    }
}
