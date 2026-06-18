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
 * Members module — accessible (GOV.UK) frontend parity tests.
 *
 * Covers the React-parity "Reputation and recognition" page
 * (govuk-alpha.members.insights) that surfaces the NEXUS score, the full stats
 * grid (incl. groups joined + events attended), the per-method verification
 * badge row and the showcased earned badges — none of which the core
 * accessible profile page renders.
 *
 * Auth/tenant scrubbing mirrors GovukAlphaFrontendTest::setUp().
 */
class MembersParityTest extends TestCase
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

    private function makeVisibleMember(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'name' => 'Insights Member',
            'first_name' => 'Insights',
            'last_name' => 'Member',
            'status' => 'active',
            'is_approved' => true,
            'is_verified' => true,
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ], $overrides));
    }

    public function test_members_insights_redirects_anonymous_to_login(): void
    {
        $member = $this->makeVisibleMember();

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertRedirect();
        $response->assertRedirectContains('/alpha/login');
    }

    public function test_members_insights_renders_stats_grid_with_groups_and_events(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Insights Viewer']);
        $member = $this->makeVisibleMember();

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.heading'));
        // The two gap stats the core profile page does not show.
        $response->assertSee(__('govuk_alpha_members.insights.stat_groups'));
        $response->assertSee(__('govuk_alpha_members.insights.stat_events'));
        $response->assertSee(__('govuk_alpha_members.insights.stats_title'));
        // Back link to the core profile.
        $response->assertSee(route('govuk-alpha.members.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $member->id]), false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_members_insights_shows_nexus_score_badge_when_present(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Score Viewer']);
        $member = $this->makeVisibleMember(['name' => 'Scored Member']);

        DB::table('nexus_score_cache')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'total_score' => 720.00,
            'engagement_score' => 180.00,
            'quality_score' => 150.00,
            'volunteer_score' => 120.00,
            'activity_score' => 90.00,
            'badge_score' => 50.00,
            'impact_score' => 30.00,
            'percentile' => 88,
            'tier' => 'Expert',
            'calculated_at' => now(),
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.nexus_score_title'));
        $response->assertSee('720.0');
        $response->assertSee(__('govuk_alpha_members.insights.tier_expert'));
        // Percentile progress is rendered as a native <progress> element.
        $response->assertSee('<progress', false);
    }

    public function test_members_insights_shows_nexus_empty_state_without_cache(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'No Score Viewer']);
        $member = $this->makeVisibleMember(['name' => 'Unscored Member']);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.nexus_empty'));
    }

    public function test_members_insights_renders_per_method_verification_badges(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Verify Viewer']);
        $member = $this->makeVisibleMember(['name' => 'Verified Member']);

        DB::table('member_verification_badges')->insert([
            'user_id' => $member->id,
            'tenant_id' => $this->testTenantId,
            'badge_type' => 'id_verified',
            'verified_by' => $viewer->id,
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.verification_title'));
        $response->assertSee(__('govuk_alpha_members.insights.verification_type_id_verified'));
    }

    public function test_members_insights_shows_verification_empty_state(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Empty Verify Viewer']);
        $member = $this->makeVisibleMember(['name' => 'Unverified Member']);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.verification_empty'));
    }

    public function test_members_insights_shows_earned_badges(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Badge Viewer']);
        $member = $this->makeVisibleMember(['name' => 'Badged Insights Member']);

        DB::table('badges')->insertOrIgnore([
            'tenant_id' => $this->testTenantId,
            'badge_key' => 'parity_community_helper',
            'name' => 'Parity Community Helper',
            'icon' => '⭐',
            'description' => 'Helped the community.',
        ]);
        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'badge_key' => 'parity_community_helper',
            'name' => 'Parity Community Helper',
            'icon' => '⭐',
            'awarded_at' => now(),
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.badges_title'));
        $response->assertSee('Parity Community Helper');
    }

    public function test_members_insights_returns_404_for_cross_tenant_member(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Cross Tenant Viewer']);
        $other = User::factory()->forTenant(999)->create([
            'name' => 'Other Tenant Member',
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$other->id}/insights");

        $response->assertNotFound();
    }

    public function test_members_insights_renders_own_profile_with_hint(): void
    {
        $viewer = $this->authenticatedUser([
            'name' => 'Own Insights Member',
            'first_name' => 'Own',
            'last_name' => 'Member',
            'onboarding_completed' => true,
        ]);

        DB::table('nexus_score_cache')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $viewer->id,
            'total_score' => 510.00,
            'engagement_score' => 120.00,
            'quality_score' => 100.00,
            'volunteer_score' => 100.00,
            'activity_score' => 90.00,
            'badge_score' => 50.00,
            'impact_score' => 50.00,
            'percentile' => 60,
            'tier' => 'Proficient',
            'calculated_at' => now(),
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$viewer->id}/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_members.insights.intro_own'));
        $response->assertSee(__('govuk_alpha_members.insights.nexus_own_hint'));
    }
}
