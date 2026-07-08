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
 * GOV.UK accessible-frontend: cookie banner/consent + "Report a problem with this page".
 */
class AccessibleCookieSupportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authedUser(): User
    {
        $u = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($u, ['*']);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        return $u;
    }

    private function alphaPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        $token = 'accessible-cookie-support-test-token';

        return $this->withSession(['_token' => $token])
            ->post($uri, array_merge(['_token' => $token], $data));
    }

    // ── Cookie banner + consent ───────────────────────────────────────────────

    public function test_cookie_banner_shows_when_no_choice_made(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/contact");
        $response->assertOk();
        $response->assertSee('govuk-cookie-banner', false);
        $response->assertSee(__('govuk_alpha.cookie_banner.accept'));
        $response->assertSee(__('govuk_alpha.cookie_banner.reject'));
    }

    public function test_cookie_banner_hidden_once_a_choice_cookie_is_present(): void
    {
        $response = $this->withCookie('nexus_alpha_cookie_consent', 'essential')
            ->get("/{$this->testTenantSlug}/alpha/contact");
        $response->assertOk();
        $response->assertDontSee('govuk-cookie-banner', false);
    }

    public function test_accept_records_analytics_consent_and_sets_cookie(): void
    {
        $response = $this->alphaPost("/{$this->testTenantSlug}/alpha/cookie-consent", [
            'cookies' => 'accept',
            'return' => "/{$this->testTenantSlug}/alpha/contact",
        ]);
        $response->assertRedirect("/{$this->testTenantSlug}/alpha/contact");
        $response->assertCookie('nexus_alpha_cookie_consent', 'all');

        $this->assertSame(1, (int) DB::table('cookie_consents')
            ->where('tenant_id', $this->testTenantId)->where('analytics', 1)->count());
    }

    public function test_reject_records_no_analytics_consent(): void
    {
        $response = $this->alphaPost("/{$this->testTenantSlug}/alpha/cookie-consent", [
            'cookies' => 'reject',
            'return' => "/{$this->testTenantSlug}/alpha/contact",
        ]);
        $response->assertCookie('nexus_alpha_cookie_consent', 'essential');

        $this->assertSame(1, (int) DB::table('cookie_consents')
            ->where('tenant_id', $this->testTenantId)->where('analytics', 0)->where('functional', 1)->count());
    }

    public function test_cookie_settings_page_renders_and_saves(): void
    {
        $this->get("/{$this->testTenantSlug}/alpha/cookies")
            ->assertOk()
            ->assertSee(__('govuk_alpha.cookie_settings.analytics_legend'));

        $this->alphaPost("/{$this->testTenantSlug}/alpha/cookie-consent", ['cookies' => 'save', 'analytics' => 'yes'])
            ->assertRedirect()
            ->assertCookie('nexus_alpha_cookie_consent', 'all');

        $this->assertSame(1, (int) DB::table('cookie_consents')
            ->where('tenant_id', $this->testTenantId)->where('analytics', 1)->count());
    }

    // ── Report a problem with this page ───────────────────────────────────────

    public function test_report_problem_redirects_logged_out_to_contact_with_page(): void
    {
        $pageUrl = "/{$this->testTenantSlug}/alpha/listings";
        $this->get("/{$this->testTenantSlug}/alpha/report-a-problem?return=" . urlencode($pageUrl))
            ->assertRedirect(route('govuk-alpha.contact', ['tenantSlug' => $this->testTenantSlug, 'problem_url' => $pageUrl]));
    }

    public function test_report_problem_shows_form_for_logged_in_user(): void
    {
        $this->authedUser();
        $this->get("/{$this->testTenantSlug}/alpha/report-a-problem?return=" . urlencode("/{$this->testTenantSlug}/alpha/dashboard"))
            ->assertOk()
            ->assertSee(__('govuk_alpha.report_problem.title'))
            ->assertSee(__('govuk_alpha.report_problem.summary_label'));
    }

    public function test_report_problem_post_creates_support_report(): void
    {
        $user = $this->authedUser();
        $pageUrl = "/{$this->testTenantSlug}/alpha/dashboard";

        $response = $this->alphaPost("/{$this->testTenantSlug}/alpha/report-a-problem", [
            'summary' => 'The Accept button does nothing',
            'description' => 'I clicked Accept on the cookie banner and nothing happened.',
            'impact' => 'minor',
            'page_url' => $pageUrl,
        ]);
        $response->assertRedirect();

        $this->assertSame(1, (int) DB::table('support_reports')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('source', 'accessible')
            ->where('impact', 'minor')
            ->where('page_url', $pageUrl)
            ->count());
    }

    public function test_report_problem_post_validates_and_does_not_create(): void
    {
        $this->authedUser();
        $response = $this->alphaPost("/{$this->testTenantSlug}/alpha/report-a-problem", [
            'summary' => 'x',          // too short
            'description' => 'short',  // too short
            'impact' => 'nonsense',    // invalid
            'page_url' => "/{$this->testTenantSlug}/alpha/dashboard",
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['summary', 'description', 'impact']);

        $this->assertSame(0, (int) DB::table('support_reports')->where('tenant_id', $this->testTenantId)->count());
    }

    public function test_report_problem_post_requires_login(): void
    {
        $this->alphaPost("/{$this->testTenantSlug}/alpha/report-a-problem", [
            'summary' => 'A valid summary here',
            'description' => 'A valid description that is long enough.',
            'impact' => 'minor',
            'page_url' => "/{$this->testTenantSlug}/alpha/dashboard",
        ])->assertRedirectContains('/alpha/login');
    }
}
