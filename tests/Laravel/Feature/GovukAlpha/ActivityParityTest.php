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
 * Activity insights parity (accessible GOV.UK frontend).
 *
 * Covers the React-parity GET /{tenantSlug}/accessible/activity/insights surface
 * (AlphaController::activityInsights via the ActivityParity trait), which renders
 * MemberActivityService::getDashboardData() with activity-type badges, skill
 * offering/requesting tags + endorsements, a dual-bar monthly chart, sign-aware
 * net balance, and a two-column quick-stats layout.
 */
class ActivityParityTest extends TestCase
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

    public function test_activity_insights_redirects_to_login_when_anonymous(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/accessible/activity/insights");

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/accessible/login',
            $response->headers->get('Location') ?? ''
        );
    }

    public function test_activity_insights_renders_for_authenticated_member(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/activity/insights");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_activity.insights.heading'));
        $response->assertSee(__('govuk_alpha_activity.insights.timeline_title'));
        $response->assertSee(__('govuk_alpha_activity.insights.quick_stats_title'));
        $response->assertSee(__('govuk_alpha_activity.insights.skills_title'));
        // Two-column GOV.UK grid is present.
        $response->assertSee('govuk-grid-column-two-thirds', false);
        $response->assertSee('govuk-grid-column-one-third', false);
        // Back-link to the core activity dashboard.
        $response->assertSee(route('govuk-alpha.activity', ['tenantSlug' => $this->testTenantSlug]), false);
        // AGPL attribution still rendered by the layout.
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_activity_insights_shows_empty_timeline_state_for_new_member(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/activity/insights");

        $response->assertOk();
        // A brand-new member has no posts/transactions, so the timeline empty
        // state and the skills/chart empty messages render.
        $response->assertSee(__('govuk_alpha_activity.insights.timeline_empty_title'));
        $response->assertSee(__('govuk_alpha_activity.insights.chart_empty'));
        $response->assertSee(__('govuk_alpha_activity.insights.skills_empty'));
    }

    public function test_activity_insights_renders_skill_offering_and_endorsement_badges(): void
    {
        $user = $this->authenticatedUser();

        // Seed a skill the member is offering, plus an endorsement of it. If the
        // user_skills / skill_endorsements tables are not present in the test
        // schema, the service falls back gracefully — so we only assert the
        // badge label set, which is rendered whenever any skill is present.
        try {
            DB::table('user_skills')->insert([
                'tenant_id' => $this->testTenantId,
                'user_id' => $user->id,
                'skill_name' => 'Gardening',
                'is_offering' => 1,
                'is_requesting' => 0,
                'proficiency' => 'intermediate',
                'created_at' => now(),
            ]);
            DB::table('skill_endorsements')->insert([
                'tenant_id' => $this->testTenantId,
                'endorser_id' => $user->id,
                'endorsed_id' => $user->id,
                'skill_name' => 'Gardening',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('user_skills / skill_endorsements not available in test schema: ' . $e->getMessage());
        }

        $response = $this->get("/{$this->testTenantSlug}/accessible/activity/insights");

        $response->assertOk();
        $response->assertSee('Gardening');
        $response->assertSee(__('govuk_alpha_activity.insights.skill_offering'));
    }

    public function test_activity_insights_wrong_tenant_slug_does_not_serve_page(): void
    {
        $this->authenticatedUser();

        // The request resolves to the test tenant (id 2), but the URL slug names
        // a different community. assertTenantSlug must refuse to render — the
        // insights heading must never appear under a mismatched slug. (Unknown
        // slugs may surface as 404 or a tenant-resolution error; the security
        // guarantee under test is "page not served", not the exact status code.)
        $response = $this->get('/no-such-tenant-xyz/accessible/activity/insights');

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertDontSee(__('govuk_alpha_activity.insights.heading'));
    }

    public function test_activity_insights_route_name_resolves(): void
    {
        $url = route('govuk-alpha.activity.insights', ['tenantSlug' => $this->testTenantSlug]);

        $this->assertStringContainsString('/accessible/activity/insights', $url);
    }

    /**
     * Create an active, approved member on the test tenant and act as them.
     * Session auth is what AlphaController::currentUserId() reads; Sanctum acting
     * is sufficient for the alpha pages (mirrors GovukAlphaFrontendTest).
     */
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
