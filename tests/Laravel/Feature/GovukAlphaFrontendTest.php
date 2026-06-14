<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\FeedActivity;
use App\Models\FeedPost;
use App\Models\Listing;
use App\Models\User;
use App\Core\ImageUploader;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class GovukAlphaFrontendTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Defensively scrub request-scoped tenant/auth state that earlier tests in the
     * full suite leak through PHP superglobals and the auth guards.
     *
     * Hypothesis for the polluted failure of test_root_renders_accessible_tenant_chooser:
     * AlphaController::tenantChooser() only renders the chooser when TenantContext resolves
     * to the host tenant (id 1). The test resets TenantContext, but on the GET '/' the context
     * re-resolves from the request — and TenantContext::get() reads $_SERVER['HTTP_X_TENANT_ID']
     * / HTTP_X_TENANT_SLUG / HTTP_AUTHORIZATION. A prior API request in the suite leaves
     * X-Tenant-ID=2 (and friends) in $_SERVER, so '/' re-pins to tenant 2 and the controller
     * redirects to the tenant home instead of returning 200 with the chooser.
     *
     * Clearing those superglobals (and forgetting stale guards) makes '/' resolve to the host
     * tenant exactly as it does when this file runs on its own.
     */
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

        \App\Core\TenantContext::reset();
        \App\Core\TenantContext::setById($this->testTenantId);

        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_root_renders_accessible_tenant_chooser(): void
    {
        \App\Core\TenantContext::reset();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.tenant_chooser.title'));
        $response->assertSee($this->testTenantSlug);
        $response->assertSee("/{$this->testTenantSlug}/alpha", false);
        $response->assertSee('href="' . __('govuk_alpha.feedback_url') . '"', false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_home_login_and_register_pages_render_for_tenant(): void
    {
        $feedbackUrl = route('govuk-alpha.contact', ['tenantSlug' => $this->testTenantSlug]);

        foreach (['/alpha', '/alpha/login', '/alpha/register'] as $path) {
            $response = $this->get("/{$this->testTenantSlug}{$path}");

            $response->assertOk();
            $response->assertHeader('content-type', 'text/html; charset=UTF-8');
            $response->assertSee('Project NEXUS Accessible');
            $response->assertSee('class="govuk-skip-link"', false);
            $response->assertSee('class="govuk-phase-banner"', false);
            $response->assertSee('href="' . $feedbackUrl . '"', false);
            $response->assertSee('AGPL-3.0-or-later');
        }
    }

    public function test_global_language_switcher_changes_and_persists_the_locale(): void
    {
        // The no-JS switcher renders in the header with the supported languages.
        $page = $this->get("/{$this->testTenantSlug}/alpha/login");
        $page->assertOk();
        $page->assertSee('name="locale"', false);
        $page->assertSee(__('govuk_alpha.profile_settings.languages.ga'), false);
        $page->assertSee(__('govuk_alpha.header.language_submit'));

        // Switching to Irish renders that locale and sets <html lang>.
        $ga = $this->get("/{$this->testTenantSlug}/alpha/login?locale=ga");
        $ga->assertOk();
        $ga->assertSee('lang="ga"', false);
        $ga->assertSee($this->alphaText('ga', 'auth.login_description', ['community' => 'Hour Timebank']));
        $ga->assertDontSee($this->alphaText('en', 'auth.login_description', ['community' => 'Hour Timebank']));

        // The choice persists to the next request via the session (no ?locale param) —
        // this is the bug the AlphaSetLocale middleware fixes.
        $next = $this->get("/{$this->testTenantSlug}/alpha/login");
        $next->assertSee('lang="ga"', false);

        // Arabic switches the document direction to RTL.
        $ar = $this->get("/{$this->testTenantSlug}/alpha/login?locale=ar");
        $ar->assertSee('lang="ar"', false);
        $ar->assertSee('dir="rtl"', false);
        $ar->assertSee($this->alphaText('ar', 'auth.login_description', ['community' => 'Hour Timebank']));
        $ar->assertDontSee($this->alphaText('en', 'auth.login_description', ['community' => 'Hour Timebank']));
    }

    public function test_login_unverified_shows_resend_form_and_resend_is_generic(): void
    {
        // The login page surfaces a resend-verification form for unverified accounts.
        $page = $this->get("/{$this->testTenantSlug}/alpha/login?status=email-not-verified");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.auth.resend_verification_button'));
        $page->assertSee(route('govuk-alpha.login.resend', ['tenantSlug' => $this->testTenantSlug]), false);

        // Posting returns the generic (anti-enumeration) confirmation regardless.
        $resend = $this->post("/{$this->testTenantSlug}/alpha/login/resend-verification", [
            'email' => 'nobody@example.com',
        ]);
        $resend->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=verification-resent");

        $confirm = $this->get("/{$this->testTenantSlug}/alpha/login?status=verification-resent");
        $confirm->assertSee(__('govuk_alpha.auth.verification_resent'));
    }

    public function test_failed_login_repopulates_the_email_field(): void
    {
        $login = $this->post("/{$this->testTenantSlug}/alpha/login", [
            'email' => 'someone@example.com',
            'password' => 'definitely-the-wrong-password',
        ]);

        $login->assertRedirect();
        // withInput() flashes the email so the form can pre-fill old('email').
        $login->assertSessionHasInput('email', 'someone@example.com');
    }

    public function test_home_module_grid_distinguishes_signin_from_disabled(): void
    {
        // Anonymous viewer: auth-gated modules (Dashboard, My Profile, …) are not
        // "disabled" — they need sign-in. The grid must say so, not "not enabled
        // for this community".
        $anon = $this->get("/{$this->testTenantSlug}/alpha");
        $anon->assertOk();
        $anon->assertSee(__('govuk_alpha.home.module_signin'));
        $anon->assertSee(__('govuk_alpha.home.module_signin_hint'));

        // Signed in: the dashboard module becomes available.
        $this->authenticatedUser();
        $authed = $this->get("/{$this->testTenantSlug}/alpha");
        $authed->assertOk();
        $authed->assertSee(__('govuk_alpha.home.module_available'));
        $authed->assertSee(route('govuk-alpha.dashboard', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_register_page_shows_closed_registration_message_and_hides_form(): void
    {
        DB::table('tenant_registration_policies')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            [
                'registration_mode' => 'open',
                'verification_level' => 'none',
                'post_verification' => 'activate',
                'fallback_mode' => 'none',
                'require_email_verify' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            [
                'setting_value' => 'closed',
                'setting_type' => 'string',
                'updated_at' => now(),
            ]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $response = $this->get("/{$this->testTenantSlug}/alpha/register");

        $response->assertOk();
        $response->assertSee('<h1 class="govuk-heading-xl">' . __('govuk_alpha.auth.registration_closed_title') . '</h1>', false);
        $response->assertDontSee('<h1 class="govuk-heading-xl">' . __('govuk_alpha.auth.register_title') . '</h1>', false);
        $response->assertSee(__('govuk_alpha.auth.registration_closed_title'));
        $response->assertSee(__('govuk_alpha.auth.registration_closed_body'));
        $response->assertDontSee('name="first_name"', false);
        // The registration form (and its submit button) is hidden. Assert the
        // register-specific submit label rather than any type="submit", since the
        // global header language switcher legitimately renders a submit button on
        // every page.
        $response->assertDontSee(__('govuk_alpha.auth.register_action'));
    }

    public function test_register_page_shows_per_field_inline_errors(): void
    {
        // Ensure registration is open so the form (and its fields) renders.
        DB::table('tenant_registration_policies')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            [
                'registration_mode' => 'open',
                'verification_level' => 'none',
                'post_verification' => 'activate',
                'fallback_mode' => 'none',
                'require_email_verify' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            ['setting_value' => 'open', 'setting_type' => 'string', 'updated_at' => now()]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        // Password mismatch → inline error on the password field + anchored summary.
        $pw = $this->get("/{$this->testTenantSlug}/alpha/register?status=register-password-mismatch");
        $pw->assertOk();
        $pw->assertSee('name="password"', false);
        $pw->assertSee('govuk-form-group--error', false);
        $pw->assertSee('id="password-error"', false);
        $pw->assertSee('href="#password"', false);
        $pw->assertSee(__('govuk_alpha.auth.register_password_mismatch'));

        // Invalid email domain → inline error on the email field.
        $email = $this->get("/{$this->testTenantSlug}/alpha/register?status=register-email-domain-invalid");
        $email->assertOk();
        $email->assertSee('id="email-error"', false);
        $email->assertSee('href="#email"', false);

        // Terms required → inline error on the terms checkbox.
        $terms = $this->get("/{$this->testTenantSlug}/alpha/register?status=register-terms-required");
        $terms->assertOk();
        $terms->assertSee('id="terms_accepted-error"', false);
        $terms->assertSee('href="#terms_accepted"', false);
    }

    public function test_contact_page_renders_govuk_alpha_form_and_feedback_link_targets_it(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/contact");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee(__('govuk_alpha.contact.title'));
        $response->assertSee('class="govuk-fieldset"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="subject"', false);
        $response->assertSee('name="message"', false);
        $response->assertSee('href="' . route('govuk-alpha.contact', ['tenantSlug' => $this->testTenantSlug]) . '"', false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_contact_link_is_in_accessible_footer_links_not_service_navigation(): void
    {
        $contactUrl = route('govuk-alpha.contact', ['tenantSlug' => $this->testTenantSlug]);

        $response = $this->get("/{$this->testTenantSlug}/alpha");

        $response->assertOk();
        // Contact now lives in the GOV.UK footer Support column, not the service nav.
        $response->assertSee('class="govuk-footer__navigation"', false);
        $response->assertSee('<a class="govuk-footer__link" href="' . $contactUrl . '">' . __('govuk_alpha.footer.columns.support.contact') . '</a>', false);
        // Guests do not see the sign-out control.
        $response->assertDontSee(__('govuk_alpha.footer.sign_out'));
        $response->assertDontSee('<a class="govuk-service-navigation__link" href="' . $contactUrl . '"', false);
    }

    public function test_accessible_header_hides_home_navigation_link_for_signed_in_users(): void
    {
        $homeUrl = route('govuk-alpha.home', ['tenantSlug' => $this->testTenantSlug]);

        $guest = $this->get("/{$this->testTenantSlug}/alpha");

        $guest->assertOk();
        $guest->assertSee('<a class="govuk-service-navigation__link" href="' . $homeUrl . '"', false);

        $this->authenticatedUser();

        $signedIn = $this->get("/{$this->testTenantSlug}/alpha/feed");

        $signedIn->assertOk();
        $signedIn->assertDontSee('<a class="govuk-service-navigation__link" href="' . $homeUrl . '"', false);
        $signedIn->assertSee(__('govuk_alpha.nav.dashboard'));
    }

    public function test_logout_is_a_csrf_protected_post_form_in_the_accessible_footer(): void
    {
        $this->authenticatedUser();

        $logoutUrl = route('govuk-alpha.logout', ['tenantSlug' => $this->testTenantSlug]);

        $response = $this->get("/{$this->testTenantSlug}/alpha");

        $response->assertOk();
        $response->assertSee('class="govuk-footer__meta"', false);
        // Sign-out changes state, so it is a POST form (with CSRF), not a GET link.
        $response->assertSee('<form method="post" action="' . $logoutUrl . '"', false);
        $response->assertSee(__('govuk_alpha.footer.sign_out'));
        $response->assertDontSee('<a class="govuk-footer__link" href="' . $logoutUrl . '">', false);

        // The GET method is no longer routable for the state-changing sign-out.
        $this->get("/{$this->testTenantSlug}/alpha/logout")->assertStatus(405);

        $logout = $this->post("/{$this->testTenantSlug}/alpha/logout");
        $logout->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=signed-out");
        $logout->assertCookieExpired('auth_token');
    }

    public function test_govuk_footer_renders_columns_attribution_and_github_link(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha");

        $response->assertOk();
        $response->assertSee('class="govuk-footer"', false);
        $response->assertSee('class="govuk-footer__navigation"', false);
        // Support and Legal columns are universal (Platform is feature-gated).
        $response->assertSee(__('govuk_alpha.footer.columns.support.heading'));
        $response->assertSee(__('govuk_alpha.footer.columns.legal.heading'));
        $response->assertSee('<a class="govuk-footer__link" href="' . route('govuk-alpha.about', ['tenantSlug' => $this->testTenantSlug]) . '">' . __('govuk_alpha.footer.columns.support.about') . '</a>', false);
        $response->assertSee('<a class="govuk-footer__link" href="' . route('govuk-alpha.legal.terms', ['tenantSlug' => $this->testTenantSlug]) . '">' . __('govuk_alpha.footer.columns.legal.terms') . '</a>', false);
        // AGPL Section 7(b) attribution + a link to the source repository.
        $response->assertSee('class="govuk-footer__meta-custom"', false);
        $response->assertSee('AGPL-3.0-or-later');
        $response->assertSee('https://github.com/jasperfordesq-ai/nexus-v1', false);
        // Strictly not an official government service: no crown, no OGL licence.
        $response->assertDontSee('govuk-footer__crown', false);
        $response->assertDontSee('Open Government Licence');
    }

    public function test_about_page_renders_react_about_content(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/about");

        $response->assertOk();
        $response->assertSee('AGPL-3.0-or-later');
        $response->assertSee(__('govuk_alpha.about.how_it_works.title'));
        $response->assertSee(__('govuk_alpha.about.values.title'));
        $response->assertSee(__('govuk_alpha.about.credits.title'));
        // Contributors come from the shared react-frontend contributors.json.
        $response->assertSee('Mary Casey');
        $response->assertSee('https://github.com/jasperfordesq-ai/nexus-v1', false);
    }

    public function test_legal_hub_and_documents_render(): void
    {
        $hub = $this->get("/{$this->testTenantSlug}/alpha/legal");
        $hub->assertOk();
        $hub->assertSee(__('govuk_alpha.legal.documents.terms.title'));
        $hub->assertSee(__('govuk_alpha.legal.documents.privacy.title'));
        $hub->assertSee(route('govuk-alpha.legal.cookies', ['tenantSlug' => $this->testTenantSlug]), false);

        $terms = $this->get("/{$this->testTenantSlug}/alpha/legal/terms");
        $terms->assertOk();
        $terms->assertSee(__('govuk_alpha.legal.documents.terms.title'));
        $terms->assertSee('class="govuk-back-link"', false);

        // Community guidelines render (tenant-managed document or GOV.UK fallback).
        $cg = $this->get("/{$this->testTenantSlug}/alpha/legal/community-guidelines");
        $cg->assertOk();
        $cg->assertSee(__('govuk_alpha.legal.documents.community_guidelines.title'));
    }

    public function test_accessibility_statement_renders_wcag_22_and_feedback_route(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/accessibility");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.accessibility.title'));
        $response->assertSee(__('govuk_alpha.accessibility.standard_value'));
        $response->assertSee(route('govuk-alpha.contact', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_trust_safety_page_renders_sections_and_safeguarding(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/trust-and-safety");

        $response->assertOk();
        $response->assertSee('class="govuk-warning-text"', false);
        $response->assertSee(__('govuk_alpha.trust_safety.safeguarding_title'));
        $response->assertSee(__('govuk_alpha.trust_safety.sections.how_exchanges.heading'));
        $response->assertSee(__('govuk_alpha.trust_safety.sections.rights.heading'));
    }

    public function test_help_page_renders_search_and_shell(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/help");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.help.title'));
        $response->assertSee('type="search"', false);
        $response->assertSee(__('govuk_alpha.help.contact_cta_title'));
    }

    public function test_kb_and_blog_indexes_render_and_unknown_detail_404s(): void
    {
        $kb = $this->get("/{$this->testTenantSlug}/alpha/kb");
        $kb->assertOk();
        $kb->assertSee(__('govuk_alpha.kb.title'));
        $kb->assertSee('type="search"', false);
        $this->get("/{$this->testTenantSlug}/alpha/kb/99999999")->assertNotFound();

        $blog = $this->get("/{$this->testTenantSlug}/alpha/blog");
        $blog->assertOk();
        $blog->assertSee(__('govuk_alpha.blog.title'));
        $this->get("/{$this->testTenantSlug}/alpha/blog/this-slug-does-not-exist")->assertNotFound();
    }

    public function test_new_content_pages_are_tenant_scoped(): void
    {
        // A slug that does not resolve to a tenant is rejected before the page
        // renders (the tenant-resolution middleware returns 400), so the new
        // content pages are never served outside their tenant context.
        foreach (['/not-the-tenant/alpha/about', '/not-the-tenant/alpha/legal', '/not-the-tenant/alpha/legal/terms'] as $path) {
            $response = $this->get($path);
            $this->assertSame(400, $response->getStatusCode(), "Expected {$path} to be blocked by tenant resolution");
            $response->assertDontSee(__('govuk_alpha.about.how_it_works.title'));
        }
    }

    public function test_contact_page_preserves_react_contact_validation_contract(): void
    {
        $redirect = $this->post("/{$this->testTenantSlug}/alpha/contact", [
            'name' => '',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => '',
        ]);

        $redirect->assertRedirect("/{$this->testTenantSlug}/alpha/contact?status=contact-validation");

        $response = $this->get("/{$this->testTenantSlug}/alpha/contact?status=contact-validation");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.error_title'));
        $response->assertSee('class="govuk-error-summary"', false);
        $response->assertSee('href="#name"', false);
        $response->assertSee('href="#email"', false);
        $response->assertSee('href="#message"', false);
    }

    public function test_contact_page_submits_to_same_v2_contact_contract_as_react_page(): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['contact_email' => 'support@example.test']);
        TenantContext::setById($this->testTenantId);

        $redirect = $this->post("/{$this->testTenantSlug}/alpha/contact", [
            'name' => 'Accessible Contact User',
            'email' => 'accessible-contact@example.test',
            'subject' => '',
            'message' => 'This came from the accessible frontend contact page.',
        ]);

        $redirect->assertRedirect("/{$this->testTenantSlug}/alpha/contact?status=contact-sent");
        $this->assertDatabaseHas('contact_submissions', [
            'tenant_id' => $this->testTenantId,
            'name' => 'Accessible Contact User',
            'email' => 'accessible-contact@example.test',
            'subject' => 'General Inquiry',
            'message' => 'This came from the accessible frontend contact page.',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/contact?status=contact-sent");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.contact.success_title'));
        $response->assertSee(__('govuk_alpha.contact.success_message'));
    }

    public function test_accessible_login_persists_token_cookie_for_server_rendered_pages(): void
    {
        $email = 'alpha-login-' . bin2hex(random_bytes(4)) . '@example.test';

        User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $login = $this->post("/{$this->testTenantSlug}/alpha/login", [
            'email' => $email,
            'password' => 'CorrectPassword123',
        ]);

        $login->assertRedirect("/{$this->testTenantSlug}/alpha/feed?status=signed-in");
        $login->assertCookie('auth_token');

        $cookie = null;
        foreach ($login->headers->getCookies() as $responseCookie) {
            if ($responseCookie->getName() === 'auth_token') {
                $cookie = $responseCookie->getValue();
                break;
            }
        }
        $this->assertNotNull($cookie);

        $feed = $this->withUnencryptedCookie('auth_token', $cookie)->get("/{$this->testTenantSlug}/alpha/feed");

        $feed->assertOk();
        $feed->assertDontSee(__('govuk_alpha.states.auth_required'));
        $feed->assertSee(__('govuk_alpha.feed.post_label'));
    }

    public function test_feed_page_renders_govuk_alpha_shell_and_feed_item(): void
    {
        $user = $this->authenticatedUser();
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Alpha feed verification post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Alpha feed verification post',
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('class="govuk-skip-link"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('class="govuk-phase-banner"', false);
        $response->assertSee('AGPL-3.0-or-later');
        $response->assertSee('class="govuk-fieldset"', false);
        $response->assertSee('class="govuk-textarea"', false);
        $response->assertSee('class="govuk-select"', false);
        $response->assertSee('name="mode"', false);
        $response->assertSee('name="subtype"', false);
        $response->assertSee('class="govuk-tag govuk-tag--grey"', false);
        $response->assertSee('Alpha feed verification post');
    }

    public function test_event_feed_card_links_to_the_accessible_event_detail(): void
    {
        $user = $this->authenticatedUser();
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Linked feed event',
            'description' => 'An event surfaced in the accessible feed.',
            'location' => 'Feed Hall',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'event',
            'source_id' => $eventId,
            'user_id' => $user->id,
            'content' => 'Linked feed event',
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed");
        $response->assertOk();
        // Previously only listing cards linked through; event cards were dead ends.
        $response->assertSee(route('govuk-alpha.events.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $eventId]), false);
        $response->assertSee(__('govuk_alpha.actions.view_details'));
    }

    public function test_feed_author_can_reply_edit_and_delete_own_post_and_comments(): void
    {
        $user = $this->authenticatedUser(['name' => 'Feed Manager']);
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Original post content.',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Original post content.',
            'created_at' => now()->addMinute(),
        ]);

        // Add a comment, then reply to it (threaded via parent_id).
        $comment = $this->post("/{$this->testTenantSlug}/alpha/feed/items/post/{$post->id}/comments", [
            'content' => 'A first comment.',
        ]);
        $comment->assertRedirectContains('status=comment-created');
        $commentId = DB::table('comments')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('content', 'A first comment.')
            ->value('id');
        $this->assertNotNull($commentId);

        $reply = $this->post("/{$this->testTenantSlug}/alpha/feed/items/post/{$post->id}/comments", [
            'content' => 'A reply to the comment.',
            'parent_id' => $commentId,
        ]);
        $reply->assertRedirectContains('status=comment-created');
        $this->assertDatabaseHas('comments', [
            'tenant_id' => $this->testTenantId,
            'parent_id' => $commentId,
            'content' => 'A reply to the comment.',
        ]);

        // Edit the post.
        $editPost = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/update", [
            'content' => 'Edited post content.',
        ]);
        $editPost->assertRedirectContains('status=post-updated');
        $this->assertSame('Edited post content.', DB::table('feed_posts')->where('id', $post->id)->value('content'));

        // Edit the comment.
        $editComment = $this->post("/{$this->testTenantSlug}/alpha/feed/comments/{$commentId}/update", [
            'content' => 'Edited comment.',
        ]);
        $editComment->assertRedirectContains('status=comment-updated');
        $this->assertSame('Edited comment.', DB::table('comments')->where('id', $commentId)->value('content'));

        // Delete the comment (cascades to its reply). Comments soft-delete.
        $deleteComment = $this->post("/{$this->testTenantSlug}/alpha/feed/comments/{$commentId}/delete");
        $deleteComment->assertRedirectContains('status=comment-deleted');
        $this->assertSoftDeleted('comments', ['id' => $commentId]);

        // Delete the post.
        $deletePost = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/delete");
        $deletePost->assertRedirectContains('status=post-deleted');
        $this->assertDatabaseMissing('feed_posts', ['id' => $post->id]);
    }

    public function test_feed_post_management_rejects_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Post Owner',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'content' => 'Owner post content.',
            'visibility' => 'public',
        ]);

        // A different signed-in member cannot edit or delete someone else's post.
        $this->authenticatedUser(['name' => 'Not The Owner']);

        $edit = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/update", [
            'content' => 'Hijacked content.',
        ]);
        $edit->assertRedirectContains('status=post-update-failed');
        $this->assertSame('Owner post content.', DB::table('feed_posts')->where('id', $post->id)->value('content'));

        $delete = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/delete");
        $delete->assertRedirectContains('status=post-delete-failed');
        $this->assertDatabaseHas('feed_posts', ['id' => $post->id]);
    }

    public function test_feed_moderation_hide_mute_and_report(): void
    {
        $author = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Feed Author',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $author->id,
            'content' => 'A post to moderate.',
            'visibility' => 'public',
        ]);

        $viewer = $this->authenticatedUser(['name' => 'Moderating Viewer']);

        // Hide the post from the viewer's own feed.
        $hide = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/hide", ['type' => 'post']);
        $hide->assertRedirectContains('status=content-hidden');
        $this->assertDatabaseHas('feed_hidden', [
            'user_id' => $viewer->id,
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $post->id,
        ]);

        // Mute the author.
        $mute = $this->post("/{$this->testTenantSlug}/alpha/feed/users/{$author->id}/mute");
        $mute->assertRedirectContains('status=author-muted');
        $this->assertDatabaseHas('feed_muted_users', [
            'user_id' => $viewer->id,
            'muted_user_id' => $author->id,
            'tenant_id' => $this->testTenantId,
        ]);

        // Report the post (reason required).
        $report = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/report", [
            'type' => 'post',
            'reason' => 'Spam content',
        ]);
        $report->assertRedirectContains('status=content-reported');
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $viewer->id,
            'tenant_id' => $this->testTenantId,
            'target_type' => 'post',
            'target_id' => $post->id,
            'status' => 'open',
        ]);

        // Reporting with no reason fails validation.
        $noReason = $this->post("/{$this->testTenantSlug}/alpha/feed/posts/{$post->id}/report", [
            'type' => 'post',
            'reason' => '',
        ]);
        $noReason->assertRedirectContains('status=');
    }

    public function test_feed_page_has_html_auth_required_state_when_unauthenticated(): void
    {
        // Pin the tenant display name so the community-name assertion does not depend
        // on whatever name a persistent (non-transactional) local test DB happens to hold.
        DB::table('tenants')->where('id', $this->testTenantId)->update(['name' => 'hOUR Timebank']);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.auth_required'));
        $response->assertSee(__('govuk_alpha.feed.auth_required_detail', ['community' => 'hOUR Timebank']));
        $response->assertSee('class="govuk-notification-banner"', false);
        $response->assertSee(route('govuk-alpha.login', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.register', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertDontSee('name="content"', false);
        $response->assertSee('class="govuk-select"', false);
        $response->assertSee(__('govuk_alpha.feed.empty'));
    }

    public function test_feed_poll_vote_records_a_vote_and_rejects_a_second(): void
    {
        $user = $this->authenticatedUser();
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'question' => 'What is the best day to meet?',
            'is_active' => 1,
            'end_date' => now()->addWeek(),
            'created_at' => now(),
        ]);
        $optionId = DB::table('poll_options')->insertGetId([
            'poll_id' => $pollId,
            'tenant_id' => $this->testTenantId,
            'label' => 'Monday',
        ]);

        $vote = $this->post("/{$this->testTenantSlug}/alpha/feed/polls/{$pollId}/vote", ['option_id' => $optionId]);
        $vote->assertRedirectContains('status=poll-voted');
        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $pollId,
            'option_id' => $optionId,
            'user_id' => $user->id,
        ]);

        // A second vote by the same member is rejected (already voted).
        $again = $this->post("/{$this->testTenantSlug}/alpha/feed/polls/{$pollId}/vote", ['option_id' => $optionId]);
        $again->assertRedirectContains('status=poll-vote-failed');
    }

    public function test_feed_page_renders_post_empty_error_state(): void
    {
        $this->authenticatedUser();

        $redirect = $this->post("/{$this->testTenantSlug}/alpha/feed/posts", [
            'content' => '   ',
        ]);

        $redirect->assertRedirect("/{$this->testTenantSlug}/alpha/feed?status=post-empty");

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed?status=post-empty");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.post_empty'));
        $response->assertSee('class="govuk-error-summary"', false);
        $response->assertSee('class="govuk-error-message"', false);
        $response->assertSee('govuk-textarea--error', false);
        $response->assertSee('href="#content"', false);
    }

    public function test_feed_page_renders_post_created_success_state(): void
    {
        $this->authenticatedUser();

        $redirect = $this->post("/{$this->testTenantSlug}/alpha/feed/posts", [
            'content' => 'Created from the accessible frontend feature test.',
        ]);

        $redirect->assertRedirect("/{$this->testTenantSlug}/alpha/feed?status=post-created");

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed?status=post-created");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.post_created'));
        $response->assertSee('govuk-notification-banner--success', false);
    }

    public function test_accessible_feed_likes_sync_with_v2_social_feed(): void
    {
        $user = $this->authenticatedUser();
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Accessible like sync post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Accessible like sync post',
            'created_at' => now()->addMinute(),
        ]);

        $like = $this->post("/{$this->testTenantSlug}/alpha/feed/items/post/{$post->id}/like", [
            'type' => 'posts',
            'mode' => 'ranking',
        ]);

        $like->assertRedirectContains("status=like-added");
        $this->assertDatabaseHas('likes', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'target_type' => 'post',
            'target_id' => $post->id,
        ]);

        $page = $this->get("/{$this->testTenantSlug}/alpha/feed?type=posts");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.actions.unlike'));
        $page->assertSee(trans_choice('govuk_alpha.feed.likes', 1, ['count' => 1]));

        $api = $this->getJson('/api/v2/feed?per_page=20&type=posts&personalised=false', $this->withTenantHeader());
        $api->assertOk();
        $apiItems = $api->json('data');
        $apiPost = collect($apiItems)->firstWhere('id', $post->id);
        $this->assertSame(1, (int) ($apiPost['likes_count'] ?? 0));
        $this->assertTrue((bool) ($apiPost['is_liked'] ?? false));
    }

    public function test_accessible_feed_comments_sync_with_v2_social_feed(): void
    {
        $user = $this->authenticatedUser();
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Accessible comment sync post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Accessible comment sync post',
            'created_at' => now()->addMinute(),
        ]);

        $comment = $this->post("/{$this->testTenantSlug}/alpha/feed/items/post/{$post->id}/comments", [
            'type' => 'posts',
            'mode' => 'ranking',
            'content' => 'Accessible frontend comment synced to social module.',
        ]);

        $comment->assertRedirectContains("status=comment-created");
        $this->assertDatabaseHas('comments', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'target_type' => 'post',
            'target_id' => $post->id,
            'content' => 'Accessible frontend comment synced to social module.',
        ]);

        $page = $this->get("/{$this->testTenantSlug}/alpha/feed?type=posts");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.feed.comments_summary'));
        $page->assertSee('Accessible frontend comment synced to social module.');
        $page->assertSee(trans_choice('govuk_alpha.feed.comments', 1, ['count' => 1]));

        $api = $this->getJson('/api/v2/feed?per_page=20&type=posts&personalised=false', $this->withTenantHeader());
        $api->assertOk();
        $apiItems = $api->json('data');
        $apiPost = collect($apiItems)->firstWhere('id', $post->id);
        $this->assertSame(1, (int) ($apiPost['comments_count'] ?? 0));
    }

    public function test_feed_page_keeps_tenant_items_isolated(): void
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->forTenant(999)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $visiblePost = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Visible alpha tenant post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $visiblePost->id,
            'user_id' => $user->id,
            'content' => 'Visible alpha tenant post',
            'created_at' => now()->addMinute(),
        ]);

        $otherPost = FeedPost::factory()->forTenant(999)->create([
            'user_id' => $otherUser->id,
            'content' => 'Other tenant alpha feed post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant(999)->create([
            'source_type' => 'post',
            'source_id' => $otherPost->id,
            'user_id' => $otherUser->id,
            'content' => 'Other tenant alpha feed post',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed");

        $response->assertOk();
        $response->assertSee('Visible alpha tenant post');
        $response->assertDontSee('Other tenant alpha feed post');
    }

    public function test_feed_page_renders_pagination_when_more_items_exist(): void
    {
        $user = $this->authenticatedUser();

        foreach (['First paginated alpha post', 'Second paginated alpha post'] as $index => $content) {
            $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
                'user_id' => $user->id,
                'content' => $content,
                'visibility' => 'public',
                'created_at' => now()->subMinutes($index),
            ]);
            FeedActivity::factory()->forTenant($this->testTenantId)->create([
                'source_type' => 'post',
                'source_id' => $post->id,
                'user_id' => $user->id,
                'content' => $content,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed?per_page=1");

        $response->assertOk();
        $response->assertSee('class="govuk-pagination govuk-pagination--block', false);
        $response->assertSee(__('govuk_alpha.actions.load_more'));
        $response->assertSee(__('govuk_alpha.feed.more_results_label'));
        $response->assertSee('rel="next"', false);
    }

    public function test_listings_page_renders_filters_results_and_tenant_isolation(): void
    {
        $user = $this->authenticatedUser();
        $this->ensureListingCategory();
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Alpha listing verification',
            'description' => 'Visible listing for GOV.UK alpha.',
            'type' => 'offer',
            'category_id' => 1,
        ]);
        Listing::factory()->forTenant(999)->create([
            'title' => 'Other tenant alpha listing',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings");

        $response->assertOk();
        $response->assertSee('class="govuk-phase-banner"', false);
        $response->assertSee('class="govuk-fieldset"', false);
        $response->assertSee('type="search"', false);
        $response->assertSee('class="govuk-select"', false);
        $response->assertSee('class="govuk-details"', false);
        $response->assertSee('name="hours"', false);
        $response->assertSee('name="service"', false);
        $response->assertSee('name="posted"', false);
        $response->assertSee('class="govuk-tag govuk-tag--blue"', false);
        $response->assertSee('Alpha listing verification');
        $response->assertSee(__('govuk_alpha.actions.view_details'));
        $response->assertDontSee('Other tenant alpha listing');

        $filtered = $this->get("/{$this->testTenantSlug}/alpha/listings?hours=short&service=in_person&posted=30");
        $filtered->assertOk();
        $filtered->assertSee('value="short" selected', false);
        $filtered->assertSee('value="in_person" selected', false);
        $filtered->assertSee('value="30" selected', false);
    }

    public function test_listings_sort_recommended_surfaces_featured_first(): void
    {
        $user = $this->authenticatedUser();
        $this->ensureListingCategory();
        // Featured is created FIRST (lower id) so default "newest" (id desc) would
        // put it LAST — only "recommended" (featured_first) lifts it to the top.
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id, 'title' => 'Featured listing alpha', 'type' => 'offer',
            'category_id' => 1, 'is_featured' => true,
        ]);
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id, 'title' => 'Plain listing alpha', 'type' => 'offer',
            'category_id' => 1, 'is_featured' => false,
        ]);

        $page = $this->get("/{$this->testTenantSlug}/alpha/listings?sort=recommended");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.listings.sort_label'));
        $page->assertSee('value="recommended" selected', false);

        $content = $page->getContent();
        $this->assertLessThan(
            strpos($content, 'Plain listing alpha'),
            strpos($content, 'Featured listing alpha'),
            'Recommended sort should surface the featured listing before the plain one'
        );
    }

    public function test_listings_page_renders_module_disabled_state(): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['configuration' => json_encode(['modules' => ['listings' => false]])]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings");

        $response->assertStatus(403);
        $response->assertSee(__('govuk_alpha.states.module_disabled'));
        $response->assertSee('class="govuk-notification-banner"', false);
    }

    public function test_listing_detail_page_renders_summary(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Alpha detail listing',
            'description' => 'Detail page description.',
            'type' => 'request',
            'expires_at' => now()->addDays(30),
            'renewal_count' => 2,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");

        $response->assertOk();
        $response->assertSee('Alpha detail listing');
        $response->assertSee('class="govuk-back-link"', false);
        $response->assertSee('class="govuk-summary-list"', false);
        $response->assertSee('class="govuk-tag govuk-tag--purple"', false);
        // Expiry + renewal rows now surface (data was already in the getById payload).
        $response->assertSee(__('govuk_alpha.listings.expires_label'));
        $response->assertSee(trans_choice('govuk_alpha.listings.renewed_count', 2, ['count' => 2]));
    }

    public function test_listings_card_renders_cover_image_when_present(): void
    {
        $user = $this->authenticatedUser();
        $this->ensureListingCategory();
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Listing with a cover photo',
            'description' => 'Has an image.',
            'type' => 'offer',
            'category_id' => 1,
            'image_url' => '/uploads/tenants/' . $this->testTenantSlug . '/listings/cover-card.jpg',
            'is_featured' => true,
            'service_type' => 'remote_only',
        ]);
        // A second listing with no image must NOT render a broken figure.
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Listing without a photo',
            'description' => 'No image.',
            'type' => 'offer',
            'category_id' => 1,
            'image_url' => null,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings");

        $response->assertOk();
        $response->assertSee('class="nexus-alpha-card-thumb"', false);
        $response->assertSee('cover-card.jpg', false);
        $response->assertSee(__('govuk_alpha.listings.image_alt', ['title' => 'Listing with a cover photo']), false);
        // Featured + remote delivery badges from the payload.
        $response->assertSee(__('govuk_alpha.listings.featured'));
        $response->assertSee(__('govuk_alpha.listings.service_types.remote_only'));
    }

    public function test_listing_detail_renders_hero_image_gallery_and_author_block(): void
    {
        $user = $this->authenticatedUser(['name' => 'Cover Author']);
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Detail listing with photos',
            'description' => 'Detail with a hero image and a gallery.',
            'type' => 'offer',
            'status' => 'active',
            'service_type' => 'hybrid',
            'image_url' => '/uploads/tenants/' . $this->testTenantSlug . '/listings/hero.jpg',
        ]);
        DB::table('listing_images')->insert([
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'image_url' => '/uploads/tenants/' . $this->testTenantSlug . '/listings/gallery-1.jpg',
            'sort_order' => 1,
            'alt_text' => 'A descriptive gallery caption',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");

        $response->assertOk();
        // Hero cover image.
        $response->assertSee('class="nexus-alpha-detail-hero"', false);
        $response->assertSee('hero.jpg', false);
        // Gallery thumbnail with its own alt text.
        $response->assertSee('class="nexus-alpha-thumb-list"', false);
        $response->assertSee('gallery-1.jpg', false);
        $response->assertSee('A descriptive gallery caption', false);
        // Status + delivery-mode summary rows.
        $response->assertSee(__('govuk_alpha.listings.status_values.active'));
        $response->assertSee(__('govuk_alpha.listings.service_types.hybrid'));
        // Author block + per-listing Open Graph image.
        $response->assertSee(__('govuk_alpha.listings.author_title'));
        $response->assertSee('property="og:image" content="' . url('/uploads/tenants/' . $this->testTenantSlug . '/listings/hero.jpg') . '"', false);
    }

    public function test_listings_create_form_renders_and_creates_a_listing(): void
    {
        $user = $this->authenticatedUser();
        $this->ensureListingCategory();

        $form = $this->get("/{$this->testTenantSlug}/alpha/listings/new");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha.listings.create.title'));
        $form->assertSee('name="type"', false);
        $form->assertSee('name="title"', false);
        $form->assertSee('name="description"', false);
        $form->assertSee('class="govuk-file-upload"', false);
        $form->assertSee('enctype="multipart/form-data"', false);

        $create = $this->post("/{$this->testTenantSlug}/alpha/listings/new", [
            'type' => 'offer',
            'title' => 'Accessible created listing',
            'description' => 'Created through the accessible alpha listing form for verification.',
            'category_id' => 1,
            'hours_estimate' => 2,
            'service_type' => 'remote_only',
            'location' => 'Anytown',
        ]);

        $listingId = DB::table('listings')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', 'Accessible created listing')
            ->value('id');

        $this->assertNotNull($listingId);
        $create->assertRedirect("/{$this->testTenantSlug}/alpha/listings/{$listingId}?status=listing-created");
        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'type' => 'offer',
            'service_type' => 'remote_only',
        ]);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listingId}?status=listing-created");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.listings.create.created'));
    }

    public function test_listings_create_rejects_invalid_input_with_field_errors(): void
    {
        $this->authenticatedUser();
        $this->ensureListingCategory();

        $create = $this->post("/{$this->testTenantSlug}/alpha/listings/new", [
            'type' => 'offer',
            'title' => '',
            'description' => 'short',
            'category_id' => 1,
        ]);

        $create->assertRedirect("/{$this->testTenantSlug}/alpha/listings/new");
        $create->assertSessionHasErrors(['title', 'description']);

        // No listing should have been created from invalid input.
        $this->assertDatabaseMissing('listings', [
            'tenant_id' => $this->testTenantId,
            'description' => 'short',
        ]);

        $form = $this->get("/{$this->testTenantSlug}/alpha/listings/new");
        $form->assertOk();
        $form->assertSee('class="govuk-error-summary"', false);
        $form->assertSee('href="#title"', false);
        $form->assertSee('href="#description"', false);
        $form->assertSee('govuk-input--error', false);
    }

    public function test_listing_detail_can_start_accessible_exchange_request(): void
    {
        $requester = $this->authenticatedUser(['name' => 'Alpha Requester']);
        $provider = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Alpha Provider',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->enableExchangeWorkflow();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $provider->id,
            'title' => 'Alpha exchange listing',
            'description' => 'Exchange request target.',
            'type' => 'offer',
            'hours_estimate' => 2,
        ]);

        Sanctum::actingAs($requester, ['*']);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.actions.request_exchange'));
        $detail->assertSee(route('govuk-alpha.exchanges.request', ['tenantSlug' => $this->testTenantSlug, 'listingId' => $listing->id]), false);

        $form = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/exchange-request");
        $form->assertOk();
        $form->assertSee('name="proposed_hours"', false);
        $form->assertSee('class="govuk-textarea"', false);
        // Listing summary card now includes the time estimate, and the page shows the
        // requester's wallet balance as context.
        $form->assertSee(__('govuk_alpha.listings.hours_label'));
        $form->assertSee('Your time-credit balance is', false);

        $request = $this->post("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/exchange-request", [
            'proposed_hours' => 2.5,
            'prep_time' => 0.5,
            'message' => 'I can do this through the accessible exchange workflow.',
        ]);

        $exchangeId = DB::table('exchange_requests')
            ->where('tenant_id', $this->testTenantId)
            ->where('listing_id', $listing->id)
            ->where('requester_id', $requester->id)
            ->value('id');

        $this->assertNotNull($exchangeId);
        $request->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=exchange-created");
        $this->assertDatabaseHas('exchange_requests', [
            'id' => $exchangeId,
            'tenant_id' => $this->testTenantId,
            'provider_id' => $provider->id,
            'status' => 'pending_provider',
        ]);
    }

    public function test_exchanges_list_action_chip_and_detail_per_party_confirmation(): void
    {
        $requester = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Chip Requester',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $provider = $this->authenticatedUser(['name' => 'Chip Provider']);
        $this->enableExchangeWorkflow();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $provider->id,
            'title' => 'Chip listing',
            'type' => 'offer',
        ]);

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'proposed_hours' => 2,
            'status' => 'pending_provider',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The provider sees an "action needed: respond" chip in the list.
        $list = $this->get("/{$this->testTenantSlug}/alpha/exchanges");
        $list->assertOk();
        $list->assertSee(__('govuk_alpha.exchanges.action_respond'));

        // Once in progress, the detail page shows both parties' confirmation state.
        DB::table('exchange_requests')->where('id', $exchangeId)->update(['status' => 'in_progress']);
        $detail = $this->get("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.exchanges.requester_confirmation_label'));
        $detail->assertSee(__('govuk_alpha.exchanges.provider_confirmation_label'));
        $detail->assertSee(__('govuk_alpha.exchanges.awaiting_confirmation'));
    }

    public function test_accessible_exchange_detail_supports_provider_lifecycle_actions(): void
    {
        $requester = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Exchange Requester',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $provider = $this->authenticatedUser(['name' => 'Exchange Provider']);
        $this->enableExchangeWorkflow();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $provider->id,
            'title' => 'Lifecycle exchange listing',
            'type' => 'offer',
        ]);

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'proposed_hours' => 3,
            'requester_notes' => 'Please help with this.',
            'status' => 'pending_provider',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($provider, ['*']);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}");
        $detail->assertOk();
        $detail->assertSee('Lifecycle exchange listing');
        $detail->assertSee(__('govuk_alpha.actions.accept'));
        $detail->assertSee(__('govuk_alpha.actions.decline'));

        $accept = $this->post("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}", ['action' => 'accept']);
        $accept->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=exchange-updated");
        $this->assertDatabaseHas('exchange_requests', ['id' => $exchangeId, 'status' => 'accepted']);

        $start = $this->post("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}", ['action' => 'start']);
        $start->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=exchange-updated");
        $this->assertDatabaseHas('exchange_requests', ['id' => $exchangeId, 'status' => 'in_progress']);

        $complete = $this->post("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}", ['action' => 'complete']);
        $complete->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=exchange-updated");
        $this->assertDatabaseHas('exchange_requests', ['id' => $exchangeId, 'status' => 'pending_confirmation']);
    }

    public function test_completed_exchange_offers_a_review_prompt_then_records_the_rating(): void
    {
        $requester = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Rating Requester',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $provider = $this->authenticatedUser(['name' => 'Rating Provider']);
        $this->enableExchangeWorkflow();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $provider->id,
            'title' => 'Completed exchange listing',
            'type' => 'offer',
        ]);

        $exchangeId = DB::table('exchange_requests')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'listing_id' => $listing->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'proposed_hours' => 2,
            'final_hours' => 2,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($provider, ['*']);

        // Completed exchange prompts the viewer to rate it.
        $detail = $this->get("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.exchanges.review_title'));
        $detail->assertSee('name="rating"', false);
        $detail->assertSee(route('govuk-alpha.exchanges.rate.store', ['tenantSlug' => $this->testTenantSlug, 'id' => $exchangeId]), false);

        // Submit a rating.
        $rate = $this->post("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}/rate", [
            'rating' => 5,
            'comment' => 'A great exchange, thank you.',
        ]);
        $rate->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=rating-submitted");

        // Now that it is rated, the form is replaced by a thank-you note.
        $after = $this->get("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}");
        $after->assertOk();
        $after->assertSee(__('govuk_alpha.exchanges.review_thanks'));
        $after->assertDontSee('name="rating"', false);

        // The submitted rating is surfaced back on the page (mirrors React's ratings list),
        // and the completed-status plain-English description renders.
        $after->assertSee(__('govuk_alpha.exchanges.ratings_title'));
        $after->assertSee('A great exchange, thank you.');
        $after->assertSee(__('govuk_alpha.exchanges.status_descriptions.completed'));

        // An invalid rating is rejected.
        $invalid = $this->post("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}/rate", ['rating' => 9]);
        $invalid->assertRedirect("/{$this->testTenantSlug}/alpha/exchanges/{$exchangeId}?status=rating-invalid");
    }

    public function test_accessible_messages_render_send_and_archive_flow(): void
    {
        $sender = $this->authenticatedUser(['name' => 'Message Sender']);
        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Message Recipient',
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($sender, ['*']);

        $newConversation = $this->get("/{$this->testTenantSlug}/alpha/messages/new/{$recipient->id}");
        $newConversation->assertOk();
        $newConversation->assertSee(__('govuk_alpha.messages.conversation_title', ['name' => 'Message Recipient']));
        $newConversation->assertSee('class="govuk-textarea"', false);

        $send = $this->post("/{$this->testTenantSlug}/alpha/messages/{$recipient->id}", [
            'body' => 'Accessible message workflow verification.',
        ]);

        $send->assertRedirect("/{$this->testTenantSlug}/alpha/messages/{$recipient->id}?status=message-sent");
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Accessible message workflow verification.',
        ]);

        $conversation = $this->get("/{$this->testTenantSlug}/alpha/messages/{$recipient->id}");
        $conversation->assertOk();
        $conversation->assertSee('Accessible message workflow verification.');

        $index = $this->get("/{$this->testTenantSlug}/alpha/messages");
        $index->assertOk();
        $index->assertSee('class="govuk-tabs"', false);
        $index->assertSee('Accessible message workflow verification.');
        $index->assertSee(route('govuk-alpha.messages.show', ['tenantSlug' => $this->testTenantSlug, 'userId' => $recipient->id]), false);
        // The index offers a way to start a new conversation (link to the directory).
        $index->assertSee(__('govuk_alpha.messages.start_new'));
        $index->assertSee(route('govuk-alpha.members.index', ['tenantSlug' => $this->testTenantSlug]), false);

        $archive = $this->post("/{$this->testTenantSlug}/alpha/messages/{$recipient->id}/archive");
        $archive->assertRedirect("/{$this->testTenantSlug}/alpha/messages?status=conversation-archived");

        $archived = $this->get("/{$this->testTenantSlug}/alpha/messages?archived=1");
        $archived->assertOk();
        $archived->assertSee(__('govuk_alpha.actions.restore_conversation'));
    }

    public function test_messages_inline_start_conversation_search(): void
    {
        $this->authenticatedUser(['name' => 'Conversation Starter']);

        // The inline search form renders on the messages index.
        $page = $this->get("/{$this->testTenantSlug}/alpha/messages");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.messages.search_label'));
        $page->assertSee('name="q"', false);

        // A query with no matches shows the empty state (works regardless of
        // whether the search index is available in the test environment).
        $noMatch = $this->get("/{$this->testTenantSlug}/alpha/messages?q=zzznosuchmemberzzz");
        $noMatch->assertOk();
        $noMatch->assertSee(__('govuk_alpha.messages.search_empty'));
    }

    public function test_unread_message_count_shows_in_the_navigation(): void
    {
        $sender = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Nav Sender',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $recipient = $this->authenticatedUser(['name' => 'Nav Recipient']);

        DB::table('messages')->insert([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Unread nav badge check.',
            'is_read' => 0,
            'is_federated' => 0,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($recipient, ['*']);

        $dashboard = $this->get("/{$this->testTenantSlug}/alpha/dashboard");
        $dashboard->assertOk();
        // The Messages nav item carries an unread badge announced to screen readers.
        $dashboard->assertSee('nexus-alpha-nav-badge', false);
        $dashboard->assertSee(trans_choice('govuk_alpha.messages.unread_count', 1, ['count' => 1]));
    }

    public function test_events_pages_render_filters_detail_and_rsvp_flow(): void
    {
        $user = $this->authenticatedUser();
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Alpha event verification',
            'description' => 'Accessible alpha event detail body.',
            'location' => 'Alpha Hall',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
            'max_attendees' => 12,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $index = $this->get("/{$this->testTenantSlug}/alpha/events");

        $index->assertOk();
        $index->assertSee('class="govuk-fieldset"', false);
        $index->assertSee('type="search"', false);
        $index->assertSee('class="govuk-select"', false);
        $index->assertSee(__('govuk_alpha.actions.create_event'));
        $index->assertSee(route('govuk-alpha.events.create', ['tenantSlug' => $this->testTenantSlug]), false);
        $index->assertSee('Alpha event verification');
        $index->assertSee(route('govuk-alpha.events.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $eventId]), false);

        $createForm = $this->get("/{$this->testTenantSlug}/alpha/events/new");

        $createForm->assertOk();
        $createForm->assertSee(__('govuk_alpha.events.create_title'));
        $createForm->assertSee('name="title"', false);
        $createForm->assertSee('type="datetime-local"', false);
        $createForm->assertSee('name="max_attendees"', false);

        $create = $this->post("/{$this->testTenantSlug}/alpha/events/new", [
            'title' => 'Alpha created event',
            'description' => 'Created through the accessible alpha event form.',
            'start_time' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'end_time' => now()->addDays(10)->addHours(2)->format('Y-m-d\TH:i'),
            'location' => 'Accessible Hall',
            'max_attendees' => 20,
        ]);

        $createdEventId = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', 'Alpha created event')
            ->value('id');

        $this->assertNotNull($createdEventId);
        $create->assertRedirect("/{$this->testTenantSlug}/alpha/events/{$createdEventId}?status=event-created");
        $this->assertDatabaseHas('events', [
            'id' => $createdEventId,
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Alpha created event',
            'location' => 'Accessible Hall',
            'max_attendees' => 20,
        ]);

        $createdDetail = $this->get("/{$this->testTenantSlug}/alpha/events/{$createdEventId}?status=event-created");

        $createdDetail->assertOk();
        $createdDetail->assertSee(__('govuk_alpha.events.created'));
        $createdDetail->assertSee('Alpha created event');

        $detail = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}");

        $detail->assertOk();
        $detail->assertSee('Alpha event verification');
        $detail->assertSee('class="govuk-back-link"', false);
        $detail->assertSee('class="govuk-summary-list"', false);
        $detail->assertSee('class="govuk-radios"', false);

        $rsvp = $this->post("/{$this->testTenantSlug}/alpha/events/{$eventId}/rsvp", [
            'status' => 'going',
        ]);

        $rsvp->assertRedirect("/{$this->testTenantSlug}/alpha/events/{$eventId}?status=rsvp-updated");
        $this->assertDatabaseHas('event_rsvps', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'going',
        ]);

        // Once people have RSVP'd, the detail page shows the attendee roster.
        $afterRsvp = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}");
        $afterRsvp->assertSee(__('govuk_alpha.events.attendees_title'));
    }

    public function test_event_create_shows_per_field_validation_errors(): void
    {
        $this->authenticatedUser();

        // Submitting with no title/description must return per-field errors and an
        // anchored error summary, not a single generic message.
        $create = $this->followingRedirects()->post("/{$this->testTenantSlug}/alpha/events/new", [
            'title' => '',
            'description' => '',
        ]);

        $create->assertOk();
        $create->assertSee('class="govuk-error-summary"', false);
        $create->assertSee('govuk-form-group--error', false);
        $create->assertSee('class="govuk-error-message"', false);
    }

    public function test_event_organiser_can_edit_cancel_and_delete_their_event(): void
    {
        $user = $this->authenticatedUser();
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Organiser event',
            'description' => 'Original description.',
            'location' => 'Organiser Hall',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The organiser sees Edit/Cancel/Delete controls on the detail page.
        $detail = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.events.edit_event'));
        $detail->assertSee(route('govuk-alpha.events.edit', ['tenantSlug' => $this->testTenantSlug, 'id' => $eventId]), false);

        // The edit form is prefilled.
        $edit = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}/edit");
        $edit->assertOk();
        $edit->assertSee('Organiser event', false);
        $edit->assertSee(__('govuk_alpha.events.update_submit'));

        // Update the event.
        $update = $this->post("/{$this->testTenantSlug}/alpha/events/{$eventId}/edit", [
            'title' => 'Updated event title',
            'description' => 'Updated description.',
            'start_time' => now()->addDays(8)->format('Y-m-d\TH:i'),
            'location' => 'Organiser Hall',
        ]);
        $update->assertRedirect("/{$this->testTenantSlug}/alpha/events/{$eventId}?status=event-updated");
        $this->assertSame('Updated event title', DB::table('events')->where('id', $eventId)->value('title'));

        // Cancel the event.
        $cancel = $this->post("/{$this->testTenantSlug}/alpha/events/{$eventId}/cancel", ['reason' => 'No longer running.']);
        $cancel->assertRedirectContains('status=event-cancelled');

        // Delete the event (returns to the list).
        $delete = $this->post("/{$this->testTenantSlug}/alpha/events/{$eventId}/delete");
        $delete->assertRedirect("/{$this->testTenantSlug}/alpha/events?status=event-deleted");
    }

    public function test_event_edit_rejects_a_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'title' => 'Someone elses event',
            'description' => 'Not yours to edit.',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(1),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A different signed-in member cannot edit or delete it.
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}/edit")->assertForbidden();
        $this->post("/{$this->testTenantSlug}/alpha/events/{$eventId}/delete")->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $eventId, 'title' => 'Someone elses event']);
    }

    public function test_event_card_and_detail_render_cover_image_and_online_link(): void
    {
        $user = $this->authenticatedUser();
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Event with a cover photo',
            'description' => 'Event detail with a hero image.',
            'location' => 'Photo Hall',
            'cover_image' => '/uploads/tenants/' . $this->testTenantSlug . '/events/cover.jpg',
            'online_link' => 'https://example.test/live',
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $index = $this->get("/{$this->testTenantSlug}/alpha/events");
        $index->assertOk();
        $index->assertSee('class="nexus-alpha-card-thumb"', false);
        $index->assertSee('events/cover.jpg', false);
        $index->assertSee(__('govuk_alpha.events.image_alt', ['title' => 'Event with a cover photo']), false);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}");
        $detail->assertOk();
        $detail->assertSee('class="nexus-alpha-detail-hero"', false);
        $detail->assertSee('events/cover.jpg', false);
        // online_link captured but previously never shown — now surfaced safely.
        $detail->assertSee(__('govuk_alpha.events.online_link_label'));
        $detail->assertSee('property="og:image" content="' . url('/uploads/tenants/' . $this->testTenantSlug . '/events/cover.jpg') . '"', false);
    }

    public function test_volunteering_pages_render_opportunity_detail_and_application_flow(): void
    {
        $user = $this->authenticatedUser();
        // The opportunity must be created by a DIFFERENT user — creators can
        // no longer apply to their own opportunities (VolunteerService::apply
        // rejects them), and this test exercises the applicant path.
        $creator = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $organizationId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'name' => 'Alpha Volunteer Organisation',
            'slug' => 'alpha-volunteer-organisation',
            'description' => 'A volunteering organisation for the accessible alpha.',
            'contact_email' => 'volunteer-alpha@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $organizationId,
            'created_by' => $creator->id,
            'title' => 'Alpha volunteering opportunity',
            'description' => 'Accessible volunteering detail body.',
            'location' => 'Volunteer Centre',
            'is_remote' => 1,
            'skills_needed' => 'Listening, organising',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shiftId = DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->addDays(10),
            'end_time' => now()->addDays(10)->addHours(3),
            'capacity' => 5,
            'created_at' => now(),
        ]);

        $index = $this->get("/{$this->testTenantSlug}/alpha/volunteering");

        $index->assertOk();
        $index->assertSee('class="govuk-fieldset"', false);
        $index->assertSee('class="govuk-checkboxes ', false);
        $index->assertSee('class="govuk-tabs ', false);
        $index->assertSee('Alpha volunteering opportunity');
        $index->assertSee('Alpha Volunteer Organisation');
        $index->assertSee(route('govuk-alpha.volunteering.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $opportunityId]), false);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}");

        $detail->assertOk();
        $detail->assertSee('Alpha volunteering opportunity');
        $detail->assertSee('class="govuk-summary-list"', false);
        $detail->assertSee('class="govuk-textarea"', false);
        $detail->assertSee('name="shift_id"', false);

        $apply = $this->post("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}/apply", [
            'message' => 'I can help with this accessible alpha test.',
            'shift_id' => $shiftId,
        ]);

        $apply->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}?status=apply-created");
        $this->assertDatabaseHas('vol_applications', [
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'user_id' => $user->id,
            'shift_id' => $shiftId,
            'status' => 'pending',
        ]);

        $applications = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=applications");
        $applications->assertOk();
        $applications->assertSee(__('govuk_alpha.volunteering.applications_title'));
        $applications->assertSee('Alpha volunteering opportunity');
        $applications->assertSee(__('govuk_alpha.volunteering.status_values.pending'));
        $applications->assertSee(__('govuk_alpha.volunteering.applied_on'));

        $organisations = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=organisations");
        $organisations->assertOk();
        $organisations->assertSee(__('govuk_alpha.volunteering.organisations_title'));
        $organisations->assertSee('Alpha Volunteer Organisation');
        $organisations->assertSee(__('govuk_alpha.volunteering.roles.owner'));
    }

    public function test_volunteering_recommended_tab_renders(): void
    {
        $this->authenticatedUser();

        // The skills-based "For you" tab renders (empty state when no matching
        // shifts exist for the member).
        $page = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=recommended");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.volunteering.tabs.recommended'));
        $page->assertSee(__('govuk_alpha.volunteering.recommended_title'));
        $page->assertSee('aria-current="page"', false);
    }

    public function test_volunteering_applications_tab_supports_status_filter_and_withdraw(): void
    {
        $user = $this->authenticatedUser();
        $creator = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $organizationId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $creator->id,
            'name' => 'Withdraw Test Organisation',
            'slug' => 'withdraw-test-organisation',
            'description' => 'Org for the withdraw flow.',
            'contact_email' => 'withdraw-alpha@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $organizationId,
            'created_by' => $creator->id,
            'title' => 'Withdrawable opportunity',
            'description' => 'Apply then withdraw.',
            'location' => 'Centre',
            'is_remote' => 0,
            'skills_needed' => 'Listening',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $applicationId = DB::table('vol_applications')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'user_id' => $user->id,
            'status' => 'pending',
            'message' => 'Please consider me.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $withdrawUrl = route('govuk-alpha.volunteering.applications.withdraw', ['tenantSlug' => $this->testTenantSlug, 'id' => $applicationId]);

        $tab = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=applications");
        $tab->assertOk();
        $tab->assertSee('name="app_status"', false);
        $tab->assertSee(__('govuk_alpha.volunteering.withdraw_application'));
        $tab->assertSee($withdrawUrl, false);

        // Status filter: only approved → the pending one is hidden, empty state shows.
        $approvedOnly = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=applications&app_status=approved");
        $approvedOnly->assertOk();
        $approvedOnly->assertSee('value="approved" selected', false);
        $approvedOnly->assertSee(__('govuk_alpha.volunteering.empty_applications'));
        $approvedOnly->assertDontSee(__('govuk_alpha.volunteering.withdraw_application'));

        // Withdraw the pending application.
        $withdraw = $this->post($withdrawUrl);
        $withdraw->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering?tab=applications&status=application-withdrawn");
        $this->assertDatabaseMissing('vol_applications', ['id' => $applicationId]);

        $after = $this->get("/{$this->testTenantSlug}/alpha/volunteering?tab=applications&status=application-withdrawn");
        $after->assertOk();
        $after->assertSee(__('govuk_alpha.volunteering.application_withdrawn'));
    }

    public function test_volunteering_opportunity_shows_org_logo_and_hours_show_breakdowns(): void
    {
        $user = $this->authenticatedUser();
        $organizationId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'name' => 'Logo Org',
            'slug' => 'logo-org',
            'description' => 'Org with a logo and logged hours.',
            'contact_email' => 'logo-alpha@example.test',
            'logo_url' => '/uploads/tenants/' . $this->testTenantSlug . '/vol/org-logo.png',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $organizationId,
            'created_by' => $user->id,
            'title' => 'Logo opportunity',
            'description' => 'Has an organisation logo.',
            'location' => 'Centre',
            'is_remote' => 0,
            'skills_needed' => 'Support',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vol_logs')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'organization_id' => $organizationId,
            'opportunity_id' => $opportunityId,
            'date_logged' => now()->toDateString(),
            'hours' => 3.5,
            'description' => 'Approved log entry.',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}");
        $detail->assertOk();
        $detail->assertSee('class="nexus-alpha-org-logo', false);
        $detail->assertSee('org-logo.png', false);
        $detail->assertSee(__('govuk_alpha.volunteering.org_logo_alt', ['name' => 'Logo Org']), false);

        $hours = $this->get("/{$this->testTenantSlug}/alpha/volunteering/hours");
        $hours->assertOk();
        $hours->assertSee(__('govuk_alpha.volunteering.hours_by_org_title'));
        $hours->assertSee('Logo Org');
        $hours->assertSee(__('govuk_alpha.volunteering.hours_by_month_title'));
        $hours->assertSee('class="govuk-table"', false);
    }

    public function test_volunteering_shift_signup_and_cancel_for_approved_applicant(): void
    {
        $user = $this->authenticatedUser();
        $creator = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $organizationId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $creator->id,
            'name' => 'Shift Org',
            'slug' => 'shift-org',
            'description' => 'Shift signup org.',
            'contact_email' => 'shift-alpha@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $organizationId,
            'created_by' => $creator->id,
            'title' => 'Shift opportunity',
            'description' => 'Has a future shift.',
            'location' => 'Centre',
            'is_remote' => 0,
            'skills_needed' => 'Support',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shiftId = DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'start_time' => now()->addDays(10),
            'end_time' => now()->addDays(10)->addHours(3),
            'capacity' => 5,
            'created_at' => now(),
        ]);
        $applicationId = DB::table('vol_applications')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'user_id' => $user->id,
            'status' => 'approved',
            'message' => 'Approved volunteer.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $signupUrl = route('govuk-alpha.volunteering.shifts.signup', ['tenantSlug' => $this->testTenantSlug, 'id' => $opportunityId, 'shiftId' => $shiftId]);
        $cancelUrl = route('govuk-alpha.volunteering.shifts.cancel', ['tenantSlug' => $this->testTenantSlug, 'id' => $opportunityId, 'shiftId' => $shiftId]);

        // Approved applicant sees a sign-up control on the shift.
        $detail = $this->get("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}");
        $detail->assertOk();
        $detail->assertSee(__('govuk_alpha.volunteering.sign_up_shift'));
        $detail->assertSee($signupUrl, false);

        // Sign up → shift_id set on the application.
        $signup = $this->post($signupUrl);
        $signup->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}?status=shift-signed-up");
        $this->assertDatabaseHas('vol_applications', ['id' => $applicationId, 'shift_id' => $shiftId]);

        // Detail now shows the "Signed up" state + cancel control.
        $afterSignup = $this->get("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}");
        $afterSignup->assertOk();
        $afterSignup->assertSee(__('govuk_alpha.volunteering.shift_signed_up'));
        $afterSignup->assertSee($cancelUrl, false);

        // Cancel → shift_id cleared.
        $cancel = $this->post($cancelUrl);
        $cancel->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering/opportunities/{$opportunityId}?status=shift-cancelled");
        $this->assertDatabaseHas('vol_applications', ['id' => $applicationId, 'shift_id' => null]);
    }

    public function test_volunteering_accessibility_needs_form_renders_saves_and_clears(): void
    {
        $user = $this->authenticatedUser();

        $form = $this->get("/{$this->testTenantSlug}/alpha/volunteering/accessibility");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha.volunteering.accessibility_title'));
        $form->assertSee('name="need_types[]"', false);
        $form->assertSee(__('govuk_alpha.volunteering.need_type_labels.mobility'));
        $form->assertSee('name="emergency_contact_phone"', false);

        $save = $this->post("/{$this->testTenantSlug}/alpha/volunteering/accessibility", [
            'need_types' => ['mobility', 'dietary'],
            'description' => 'Needs step-free access to venues.',
            'accommodations_required' => 'A quiet space during breaks.',
            'emergency_contact_name' => 'Jo Carer',
            'emergency_contact_phone' => '+1 555 123 4567',
        ]);
        $save->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering/accessibility?status=accessibility-saved");
        $this->assertDatabaseHas('vol_accessibility_needs', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'need_type' => 'mobility',
            'description' => 'Needs step-free access to venues.',
            'emergency_contact_name' => 'Jo Carer',
        ]);
        $this->assertDatabaseHas('vol_accessibility_needs', [
            'user_id' => $user->id,
            'need_type' => 'dietary',
        ]);

        // Re-render reflects the saved selections + shared detail.
        $after = $this->get("/{$this->testTenantSlug}/alpha/volunteering/accessibility?status=accessibility-saved");
        $after->assertOk();
        $after->assertSee('value="mobility" checked', false);
        $after->assertSee('value="dietary" checked', false);
        $after->assertSee('Needs step-free access to venues.', false);
        $after->assertSee(__('govuk_alpha.volunteering.accessibility_saved'));

        // Full-replace: submitting with no categories clears all saved needs.
        $clear = $this->post("/{$this->testTenantSlug}/alpha/volunteering/accessibility", [
            'description' => '',
        ]);
        $clear->assertRedirect("/{$this->testTenantSlug}/alpha/volunteering/accessibility?status=accessibility-saved");
        $this->assertDatabaseMissing('vol_accessibility_needs', ['user_id' => $user->id]);
    }

    public function test_volunteering_hours_page_renders_member_hour_logging_form(): void
    {
        $user = $this->authenticatedUser();
        $organizationId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'name' => 'Alpha Hours Organisation',
            'slug' => 'alpha-hours-organisation',
            'description' => 'Organisation for hour logging.',
            'contact_email' => 'hours-alpha@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $organizationId,
            'created_by' => $user->id,
            'title' => 'Alpha hours opportunity',
            'description' => 'Opportunity used for hour logging.',
            'location' => 'Hours Centre',
            'is_remote' => 0,
            'skills_needed' => 'Support',
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vol_applications')->insert([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $opportunityId,
            'user_id' => $user->id,
            'status' => 'approved',
            'message' => 'Approved for logging.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/hours");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.volunteering.hours_title'));
        $response->assertSee('class="govuk-fieldset"', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('type="number"', false);
        $response->assertSee('Alpha Hours Organisation');
        $response->assertSee('Alpha hours opportunity');
    }

    public function test_members_page_renders_directory_for_authenticated_user(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Viewer Member']);
        User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Alpha Directory Member',
            'first_name' => 'Alpha',
            'last_name' => 'Member',
            'location' => 'Alpha Town',
            'bio' => 'Alpha directory test member biography.',
            'avatar_url' => '/assets/img/defaults/default_avatar.png',
            'onboarding_completed' => true,
            'status' => 'active',
            'is_approved' => true,
            'is_verified' => true,
            'privacy_search' => true,
            'created_at' => now()->addYear(),
        ]);
        User::factory()->forTenant(999)->create([
            'name' => 'Other Tenant Member',
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members?sort=joined&order=DESC");

        $response->assertOk();
        $response->assertSee('class="govuk-fieldset"', false);
        $response->assertSee('type="search"', false);
        $response->assertSee('class="govuk-select"', false);
        $response->assertSee('Alpha Member');
        $response->assertSee('Alpha Town');
        $response->assertSee('class="govuk-tag govuk-tag--green"', false);
        $response->assertSee("/{$this->testTenantSlug}/alpha/members/", false);
        $response->assertDontSee('Other Tenant Member');
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_member_profile_page_renders_accessible_profile_details(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Profile Viewer']);
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Alpha Profile Member',
            'first_name' => 'Alpha',
            'last_name' => 'Profile',
            'location' => 'Profile Town',
            'bio' => 'Accessible member profile biography.',
            'tagline' => 'Useful neighbour',
            'skills' => 'Gardening, Repairs',
            'avatar_url' => '/assets/img/defaults/default_avatar.png',
            'status' => 'active',
            'is_approved' => true,
            'is_verified' => true,
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}");

        $response->assertOk();
        // Surnames are private by default: UserService::getPublicProfile() hides
        // last_name from non-admin viewers, so the displayed name is the first name.
        $response->assertSee('Alpha');
        $response->assertSee('Useful neighbour');
        $response->assertSee('Accessible member profile biography.');
        $response->assertSee(__('govuk_alpha.profile.skills_title'));
        $response->assertSee(__('govuk_alpha.profile.activity_title'));
        $response->assertSee('class="govuk-summary-list"', false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_member_profile_shows_send_message_button_and_earned_badges(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Profile Visitor']);
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Badged Member',
            'first_name' => 'Badged',
            'last_name' => 'Member',
            'status' => 'active',
            'is_approved' => true,
            'is_verified' => true,
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ]);
        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'badge_key' => 'community_helper',
            'name' => 'Community Helper',
            'icon' => '⭐',
            'awarded_at' => now(),
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}");
        $response->assertOk();
        // Direct-message entry point (conversations previously could only begin from a listing).
        $response->assertSee(__('govuk_alpha.actions.send_message'));
        $response->assertSee(route('govuk-alpha.messages.new', ['tenantSlug' => $this->testTenantSlug, 'userId' => $member->id]), false);
        // Public earned badges.
        $response->assertSee(__('govuk_alpha.profile.badges_title'));
        $response->assertSee('Community Helper');
    }

    public function test_member_profile_connection_request_accept_and_remove_flow(): void
    {
        $viewer = $this->authenticatedUser([
            'name' => 'Connector One',
            'first_name' => 'Connector',
            'last_name' => 'One',
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ]);
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Connector Two',
            'first_name' => 'Connector',
            'last_name' => 'Two',
            'status' => 'active',
            'is_approved' => true,
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ]);

        // The viewer sees a Connect button on the member's profile.
        Sanctum::actingAs($viewer, ['*']);
        $profile = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}");
        $profile->assertOk();
        $profile->assertSee(__('govuk_alpha.profile.connection.connect'));

        // The viewer sends a connection request.
        $send = $this->post("/{$this->testTenantSlug}/alpha/members/{$member->id}/connection", ['action' => 'connect']);
        $send->assertRedirectContains('status=connection-sent');
        $this->assertDatabaseHas('connections', [
            'requester_id' => $viewer->id,
            'receiver_id' => $member->id,
            'status' => 'pending',
        ]);

        // Re-viewing shows the pending-sent state, and a duplicate request is rejected
        // rather than creating a second row.
        $pending = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}");
        $pending->assertSee(__('govuk_alpha.profile.connection.request_sent'));
        $pending->assertSee(__('govuk_alpha.profile.connection.cancel_request'));
        $dup = $this->post("/{$this->testTenantSlug}/alpha/members/{$member->id}/connection", ['action' => 'connect']);
        $dup->assertRedirectContains('status=connection-failed');
        $this->assertSame(
            1,
            DB::table('connections')->where('requester_id', $viewer->id)->where('receiver_id', $member->id)->count()
        );

        // The member accepts from their side (viewing the requester's profile).
        Sanctum::actingAs($member, ['*']);
        $received = $this->get("/{$this->testTenantSlug}/alpha/members/{$viewer->id}");
        $received->assertSee(__('govuk_alpha.profile.connection.accept'));
        $accept = $this->post("/{$this->testTenantSlug}/alpha/members/{$viewer->id}/connection", ['action' => 'accept']);
        $accept->assertRedirectContains('status=connection-accepted');
        $this->assertDatabaseHas('connections', [
            'requester_id' => $viewer->id,
            'receiver_id' => $member->id,
            'status' => 'accepted',
        ]);

        // Either side can now remove the connection.
        $remove = $this->post("/{$this->testTenantSlug}/alpha/members/{$viewer->id}/connection", ['action' => 'remove']);
        $remove->assertRedirectContains('status=connection-removed');
        $this->assertDatabaseMissing('connections', [
            'requester_id' => $viewer->id,
            'receiver_id' => $member->id,
        ]);
    }

    public function test_member_profile_shows_recent_activity_timeline(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Active Member',
            'status' => 'active',
            'is_approved' => true,
        ]);
        FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $member->id,
            'content' => 'Timeline post content.',
            'visibility' => 'public',
        ]);
        // A private post must NOT leak into another viewer's activity timeline.
        FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $member->id,
            'content' => 'Secret private diary entry.',
            'visibility' => 'private',
        ]);

        $this->authenticatedUser(['name' => 'Timeline Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members/{$member->id}");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.profile.recent_activity_title'));
        $response->assertSee(__('govuk_alpha.profile.activity_types.post'));
        $response->assertSee('Timeline post content.');
        $response->assertDontSee('Secret private diary entry.');
    }

    public function test_member_profile_skill_can_be_endorsed_and_unendorsed(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Endorser']);
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Endorsed Member',
            'first_name' => 'Endorsed',
            'last_name' => 'Member',
            'status' => 'active',
            'is_approved' => true,
            'privacy_search' => true,
            'privacy_profile' => 'members',
            'onboarding_completed' => true,
        ]);

        Sanctum::actingAs($viewer, ['*']);

        // Endorse a skill.
        $endorse = $this->post("/{$this->testTenantSlug}/alpha/members/{$member->id}/endorse", [
            'skill_name' => 'Gardening',
            'action' => 'endorse',
        ]);
        $endorse->assertRedirectContains('status=endorsement-added');
        $this->assertDatabaseHas('skill_endorsements', [
            'endorser_id' => $viewer->id,
            'endorsed_id' => $member->id,
            'skill_name' => 'Gardening',
        ]);

        // Remove the endorsement.
        $remove = $this->post("/{$this->testTenantSlug}/alpha/members/{$member->id}/endorse", [
            'skill_name' => 'Gardening',
            'action' => 'remove',
        ]);
        $remove->assertRedirectContains('status=endorsement-removed');
        $this->assertDatabaseMissing('skill_endorsements', [
            'endorser_id' => $viewer->id,
            'endorsed_id' => $member->id,
            'skill_name' => 'Gardening',
        ]);

        // You cannot endorse your own skill.
        $self = $this->post("/{$this->testTenantSlug}/alpha/members/{$viewer->id}/endorse", [
            'skill_name' => 'Gardening',
            'action' => 'endorse',
        ]);
        $self->assertRedirectContains('status=endorsement-failed');
    }

    public function test_my_profile_and_settings_update_stay_inside_accessible_frontend(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'Before',
            'last_name' => 'Member',
            'name' => 'Before Member',
            'phone' => '+15551234567',
            'privacy_profile' => 'public',
            'privacy_search' => true,
        ]);

        $profile = $this->get("/{$this->testTenantSlug}/alpha/profile");
        $profileUrl = route('govuk-alpha.profile.me', ['tenantSlug' => $this->testTenantSlug]);

        $profile->assertOk();
        // Personal items now live behind the top "My account" hub link, not a
        // direct header profile link nor the service navigation.
        $accountUrl = route('govuk-alpha.account', ['tenantSlug' => $this->testTenantSlug]);
        $profile->assertSee(__('govuk_alpha.nav.account'));
        $profile->assertSee('class="nexus-alpha-header__link" href="' . $accountUrl . '"', false);
        $profile->assertDontSee('class="govuk-service-navigation__link" href="' . $profileUrl . '"', false);
        $profile->assertDontSee(__('govuk_alpha.header.back_to_main_site'));
        $profile->assertSee(route('govuk-alpha.profile.settings', ['tenantSlug' => $this->testTenantSlug]), false);

        $dashboard = $this->get("/{$this->testTenantSlug}/alpha/dashboard");
        $dashboard->assertOk();
        $dashboard->assertSee(__('govuk_alpha.dashboard.title'));
        $dashboard->assertSee(__('govuk_alpha.dashboard.quick_links_title'));
        $dashboard->assertSee(route('govuk-alpha.profile.me', ['tenantSlug' => $this->testTenantSlug]), false);

        $settings = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");
        $settings->assertOk();
        $settings->assertSee(__('govuk_alpha.profile_settings.title'));
        $settings->assertSee('name="privacy_profile"', false);

        $update = $this->post("/{$this->testTenantSlug}/alpha/profile/settings", [
            'first_name' => 'After',
            'last_name' => 'Member',
            'phone' => '+1 555 987 6543',
            'profile_type' => 'individual',
            'organization_name' => '',
            'tagline' => 'Updated accessible tagline',
            'bio' => 'Updated accessible biography.',
            'location' => 'Updated Town',
            'privacy_profile' => 'members',
            'privacy_search' => '1',
        ]);

        $update->assertRedirect("/{$this->testTenantSlug}/alpha/profile?status=profile-updated");

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'first_name' => 'After',
            'last_name' => 'Member',
            'tagline' => 'Updated accessible tagline',
            'privacy_profile' => 'members',
            'privacy_search' => 1,
        ]);
    }

    public function test_members_page_has_html_auth_required_state_when_unauthenticated(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/members");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.auth_required'));
        $response->assertSee('class="govuk-notification-banner"', false);
        $response->assertSee(route('govuk-alpha.login', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.register', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_members_page_renders_empty_state_for_authenticated_user(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Only Visible Member']);

        DB::table('users')
            ->where('tenant_id', $this->testTenantId)
            ->where('id', '!=', $viewer->id)
            ->update(['privacy_search' => 0]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.empty_title'));
        $response->assertSee(__('govuk_alpha.members.empty'));
        $response->assertSee('class="govuk-inset-text"', false);
    }

    public function test_phase_banner_is_promoted_to_beta(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha");

        $response->assertOk();
        $response->assertSee('class="govuk-phase-banner"', false);
        $this->assertSame('Beta', __('govuk_alpha.phase'));
        $response->assertSee('>' . __('govuk_alpha.phase') . '<', false);
        $response->assertDontSee('>Alpha<', false);
    }

    public function test_profile_settings_page_renders_photo_marketing_and_gdpr_sections(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");

        $response->assertOk();
        // Multipart photo upload field.
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="avatar"', false);
        $response->assertSee('type="file"', false);
        $response->assertSee('class="govuk-file-upload"', false);
        // Marketing consent control.
        $response->assertSee('name="newsletter_opt_in"', false);
        $response->assertSee(__('govuk_alpha.profile_settings.marketing_title'));
        // GDPR data + delete controls.
        $response->assertSee(__('govuk_alpha.profile_settings.data_title'));
        $response->assertSee(route('govuk-alpha.profile.data-export', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.profile.delete', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee('govuk-button--warning', false);
    }

    public function test_profile_settings_email_form_shows_field_level_error(): void
    {
        $this->authenticatedUser(['name' => 'Field Error User']);

        // An invalid-email failure renders a GOV.UK field-level error on the
        // email input, not just a top-of-page banner.
        $page = $this->get("/{$this->testTenantSlug}/alpha/profile/settings?status=email-invalid");
        $page->assertOk();
        $page->assertSee('govuk-form-group--error', false);
        $page->assertSee('id="new_email-error"', false);
        $page->assertSee('aria-describedby="new_email-error"', false);
    }

    public function test_profile_settings_renders_notification_and_passkey_sections(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");

        $response->assertOk();
        // Notification preferences form + a representative toggle from each group.
        $response->assertSee(__('govuk_alpha.profile_settings.notifications.title'));
        $response->assertSee(route('govuk-alpha.profile.notifications.update', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee('name="email_messages"', false);
        $response->assertSee('name="email_org_admin"', false);
        $response->assertSee('name="push_enabled"', false);
        // Passkey management section (none registered yet).
        $response->assertSee(__('govuk_alpha.profile_settings.passkeys.title'));
        $response->assertSee(__('govuk_alpha.profile_settings.passkeys.none'));
        // No raw translation keys leaked into the rendered page.
        $response->assertDontSee('govuk_alpha.profile_settings.notifications', false);
        $response->assertDontSee('govuk_alpha.profile_settings.passkeys', false);
    }

    public function test_profile_settings_notification_preferences_can_be_saved(): void
    {
        $user = $this->authenticatedUser();

        // Submit a subset on; everything not posted is treated as off.
        $response = $this->post("/{$this->testTenantSlug}/alpha/profile/notifications", [
            'email_messages' => '1',
            'email_reviews' => '1',
            'push_enabled' => '1',
            'federation_notifications_enabled' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=notifications-saved', (string) $response->headers->get('Location'));

        $prefs = json_decode((string) DB::table('users')
            ->where('id', $user->id)->where('tenant_id', $this->testTenantId)
            ->value('notification_preferences'), true) ?: [];

        $this->assertSame(1, $prefs['email_messages'] ?? null);
        $this->assertSame(1, $prefs['email_reviews'] ?? null);
        $this->assertSame(0, $prefs['email_listings'] ?? null); // not posted => off
        $this->assertSame(1, (int) DB::table('users')->where('id', $user->id)->value('federation_notifications_enabled'));
    }

    public function test_profile_settings_personalisation_match_and_skills(): void
    {
        $user = $this->authenticatedUser(['name' => 'Settings Parity User']);

        // Personalisation: chronological feed + UGC auto-translate.
        $p = $this->post("/{$this->testTenantSlug}/alpha/profile/personalisation", [
            'prefers_chronological' => '1',
            'auto_translate_ugc' => '1',
            'auto_translate_target_locale' => 'ga',
        ]);
        $p->assertRedirectContains('status=personalisation-saved');
        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertSame(1, (int) $row->prefers_chronological_feed);
        $this->assertSame(1, (int) $row->auto_translate_ugc);
        $this->assertSame('ga', $row->auto_translate_target_locale);

        // Match notification preferences.
        $m = $this->post("/{$this->testTenantSlug}/alpha/profile/match-preferences", [
            'notification_frequency' => 'weekly',
            'notify_hot_matches' => '1',
        ]);
        $m->assertRedirectContains('status=match-prefs-saved');
        $this->assertDatabaseHas('match_preferences', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'notification_frequency' => 'weekly',
        ]);

        // Add a free-text skill, then remove it.
        $add = $this->post("/{$this->testTenantSlug}/alpha/profile/skills/add", [
            'skill_name' => 'Gardening',
            'is_offering' => '1',
        ]);
        $add->assertRedirectContains('status=skill-added');
        $skillId = DB::table('user_skills')
            ->where('user_id', $user->id)->where('skill_name', 'Gardening')->value('id');
        $this->assertNotNull($skillId);

        $remove = $this->post("/{$this->testTenantSlug}/alpha/profile/skills/remove", [
            'user_skill_id' => $skillId,
        ]);
        $remove->assertRedirectContains('status=skill-removed');
        $this->assertDatabaseMissing('user_skills', ['id' => $skillId]);
    }

    public function test_profile_settings_safeguarding_list_and_revoke(): void
    {
        $user = $this->authenticatedUser(['name' => 'Safeguarded Member']);

        $optionId = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'option_key' => 'needs_vetted',
            'label' => 'I only interact with vetted members',
            'description' => 'Extra protection for my interactions.',
            'triggers' => json_encode(['restricts_messaging' => true, 'requires_vetted_interaction' => true]),
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => now(),
        ]);
        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'option_id' => $optionId,
            'selected_value' => '1',
            'consent_given_at' => now(),
            'created_at' => now(),
        ]);

        // The settings page lists the active safeguarding preference + what it activates.
        $page = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");
        $page->assertOk();
        $page->assertSee('I only interact with vetted members');
        $page->assertSee(__('govuk_alpha.profile_settings.safeguarding.activations.restricts_messaging'));
        $page->assertSee(__('govuk_alpha.profile_settings.safeguarding.revoke_button'));

        // Withdraw it.
        $revoke = $this->post("/{$this->testTenantSlug}/alpha/profile/safeguarding/revoke", [
            'option_id' => $optionId,
        ]);
        $revoke->assertRedirectContains('status=safeguarding-revoked');
        $this->assertNotNull(DB::table('user_safeguarding_preferences')
            ->where('option_id', $optionId)->where('user_id', $user->id)->value('revoked_at'));
    }

    public function test_profile_settings_passkey_can_be_renamed_and_removed(): void
    {
        $user = $this->authenticatedUser();

        DB::table('webauthn_credentials')->insert([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => 'test-cred-abc',
            'public_key' => 'PEM',
            'device_name' => 'Old name',
            'authenticator_type' => 'platform',
            'created_at' => now(),
        ]);

        // It is listed on the settings page.
        $page = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");
        $page->assertOk();
        $page->assertSee('Old name');

        // Rename.
        $rename = $this->post("/{$this->testTenantSlug}/alpha/profile/passkeys/rename", [
            'credential_id' => 'test-cred-abc',
            'device_name' => 'My laptop',
        ]);
        $rename->assertRedirect();
        $this->assertStringContainsString('status=passkey-renamed', (string) $rename->headers->get('Location'));
        $this->assertSame('My laptop', DB::table('webauthn_credentials')
            ->where('credential_id', 'test-cred-abc')->where('user_id', $user->id)->value('device_name'));

        // Remove.
        $remove = $this->post("/{$this->testTenantSlug}/alpha/profile/passkeys/remove", [
            'credential_id' => 'test-cred-abc',
        ]);
        $remove->assertRedirect();
        $this->assertStringContainsString('status=passkey-removed', (string) $remove->headers->get('Location'));
        $this->assertDatabaseMissing('webauthn_credentials', [
            'credential_id' => 'test-cred-abc',
            'user_id' => $user->id,
        ]);
    }

    public function test_profile_settings_avatar_upload_stores_a_400_by_400_image(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'Photo',
            'last_name' => 'Member',
            'name' => 'Photo Member',
        ]);

        // Keep the stored extension deterministic in the test (no cwebp dependency).
        ImageUploader::setAutoConvertWebP(false);

        try {
            $update = $this->post("/{$this->testTenantSlug}/alpha/profile/settings", [
                'first_name' => 'Photo',
                'last_name' => 'Member',
                'profile_type' => 'individual',
                'privacy_profile' => 'public',
                'privacy_search' => '1',
                'avatar' => UploadedFile::fake()->image('portrait.png', 800, 600),
            ]);

            $update->assertRedirect("/{$this->testTenantSlug}/alpha/profile?status=profile-updated");

            $avatarUrl = (string) DB::table('users')
                ->where('id', $user->id)
                ->where('tenant_id', $this->testTenantId)
                ->value('avatar_url');

            $this->assertNotSame('', $avatarUrl);
            $this->assertStringContainsString('/uploads/', $avatarUrl);

            $storedPath = base_path('httpdocs' . parse_url($avatarUrl, PHP_URL_PATH));
            $this->assertFileExists($storedPath);

            // The avatar pipeline crops to a 400x400 square — verify the real output.
            [$width, $height] = getimagesize($storedPath);
            $this->assertSame(400, $width, 'Avatar width should be cropped to 400px');
            $this->assertSame(400, $height, 'Avatar height should be cropped to 400px');

            @unlink($storedPath);
        } finally {
            ImageUploader::setAutoConvertWebP(true);
        }
    }

    public function test_profile_settings_rejects_a_non_image_avatar(): void
    {
        $this->authenticatedUser(['first_name' => 'Valid', 'last_name' => 'Name']);

        $update = $this->post("/{$this->testTenantSlug}/alpha/profile/settings", [
            'first_name' => 'Valid',
            'last_name' => 'Name',
            'profile_type' => 'individual',
            'privacy_profile' => 'public',
            'avatar' => UploadedFile::fake()->create('notes.txt', 16, 'text/plain'),
        ]);

        $update->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=avatar-invalid");

        $response = $this->get("/{$this->testTenantSlug}/alpha/profile/settings?status=avatar-invalid");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.avatar-invalid'));
        $response->assertSee('class="govuk-error-summary"', false);
    }

    public function test_profile_settings_persists_marketing_consent_choice(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'Consent',
            'last_name' => 'Member',
            'name' => 'Consent Member',
            'newsletter_opt_in' => 0,
        ]);

        $base = [
            'first_name' => 'Consent',
            'last_name' => 'Member',
            'profile_type' => 'individual',
            'privacy_profile' => 'public',
            'privacy_search' => '1',
        ];

        $optIn = $this->post("/{$this->testTenantSlug}/alpha/profile/settings", $base + ['newsletter_opt_in' => '1']);
        $optIn->assertRedirect("/{$this->testTenantSlug}/alpha/profile?status=profile-updated");
        $this->assertSame(1, (int) DB::table('users')->where('id', $user->id)->value('newsletter_opt_in'));

        // Omitting the checkbox must withdraw the consent.
        $optOut = $this->post("/{$this->testTenantSlug}/alpha/profile/settings", $base);
        $optOut->assertRedirect("/{$this->testTenantSlug}/alpha/profile?status=profile-updated");
        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('newsletter_opt_in'));
    }

    public function test_feed_post_accepts_an_image_upload(): void
    {
        $user = $this->authenticatedUser();

        $post = $this->post("/{$this->testTenantSlug}/alpha/feed/posts", [
            'content' => 'Accessible feed post with a photo attached.',
            'image' => UploadedFile::fake()->image('feed-photo.jpg', 800, 800),
            'image_alt' => 'A description of the test photo',
        ]);

        $post->assertRedirect("/{$this->testTenantSlug}/alpha/feed?status=post-created");

        $postId = DB::table('feed_posts')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('id');
        $this->assertNotNull($postId);

        $media = DB::table('post_media')
            ->where('tenant_id', $this->testTenantId)
            ->where('post_id', $postId)
            ->first();

        $this->assertNotNull($media, 'A post_media row should be created for the uploaded image');
        $this->assertSame('image', $media->media_type);
        $this->assertSame('A description of the test photo', $media->alt_text);
        $this->assertSame(800, (int) $media->width);
        $this->assertSame(800, (int) $media->height);
        $this->assertNotEmpty($media->file_url);

        foreach (array_filter([$media->file_url, $media->thumbnail_url]) as $url) {
            $path = base_path('httpdocs' . parse_url($url, PHP_URL_PATH));
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_event_create_accepts_a_cover_image_upload(): void
    {
        $user = $this->authenticatedUser();

        // The create form must declare the multipart encoding and a file input.
        $form = $this->get("/{$this->testTenantSlug}/alpha/events/new");
        $form->assertOk();
        $form->assertSee('enctype="multipart/form-data"', false);
        $form->assertSee('name="image"', false);
        $form->assertSee(__('govuk_alpha.events.create_image_label'));

        $create = $this->post("/{$this->testTenantSlug}/alpha/events/new", [
            'title' => 'Alpha event with a cover image',
            'description' => 'Created through the accessible alpha event form with an image.',
            'start_time' => now()->addDays(12)->format('Y-m-d\TH:i'),
            'end_time' => now()->addDays(12)->addHours(2)->format('Y-m-d\TH:i'),
            'location' => 'Accessible Hall',
            'image' => UploadedFile::fake()->image('event-cover.jpg', 1200, 675),
        ]);

        $eventId = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('title', 'Alpha event with a cover image')
            ->value('id');
        $this->assertNotNull($eventId);
        $create->assertRedirect("/{$this->testTenantSlug}/alpha/events/{$eventId}?status=event-created");

        $coverImage = DB::table('events')->where('id', $eventId)->value('cover_image');
        $this->assertNotEmpty($coverImage, 'The uploaded cover image should be stored on the event');

        // The detail page should now render the stored cover image hero.
        $detail = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}");
        $detail->assertOk();
        $detail->assertSee('nexus-alpha-detail-hero', false);

        $path = base_path('httpdocs' . parse_url((string) $coverImage, PHP_URL_PATH));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function test_profile_data_export_request_creates_a_gdpr_request(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/profile/data-export");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=data-export-requested");
        $this->assertDatabaseHas('gdpr_requests', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'request_type' => 'portability',
            'status' => 'pending',
        ]);

        $settings = $this->get("/{$this->testTenantSlug}/alpha/profile/settings?status=data-export-requested");
        $settings->assertOk();
        $settings->assertSee(__('govuk_alpha.states.data-export-requested'));
    }

    public function test_account_deletion_requires_password_and_confirmation_then_signs_out(): void
    {
        $password = 'CorrectHorseBattery1!';
        $user = $this->authenticatedUser([
            'password_hash' => Hash::make($password),
        ]);

        $confirm = $this->get("/{$this->testTenantSlug}/alpha/profile/delete-account");
        $confirm->assertOk();
        $confirm->assertSee(__('govuk_alpha.delete_account.title'));
        $confirm->assertSee('name="password"', false);
        $confirm->assertSee('name="confirm"', false);
        $confirm->assertSee('class="govuk-warning-text"', false);
        $confirm->assertSee('govuk-button--warning', false);

        // Wrong password must not create an erasure request.
        $wrong = $this->post("/{$this->testTenantSlug}/alpha/profile/delete-account", [
            'password' => 'NotMyPassword',
            'confirm' => '1',
        ]);
        $wrong->assertRedirect("/{$this->testTenantSlug}/alpha/profile/delete-account?status=delete-password-incorrect");
        $this->assertDatabaseMissing('gdpr_requests', [
            'user_id' => $user->id,
            'request_type' => 'erasure',
        ]);

        // Missing the explicit confirmation checkbox is rejected.
        $noConfirm = $this->post("/{$this->testTenantSlug}/alpha/profile/delete-account", [
            'password' => $password,
        ]);
        $noConfirm->assertRedirect("/{$this->testTenantSlug}/alpha/profile/delete-account?status=delete-confirm-required");

        // Correct password + confirmation creates the erasure request and signs out.
        $deleted = $this->post("/{$this->testTenantSlug}/alpha/profile/delete-account", [
            'password' => $password,
            'confirm' => '1',
            'reason' => 'Moving on from the community.',
        ]);
        $deleted->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=account-deletion-requested");
        $deleted->assertCookieExpired('auth_token');
        $this->assertDatabaseHas('gdpr_requests', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'request_type' => 'erasure',
            'status' => 'pending',
        ]);
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/login");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.auth.forgot_link'));
        $response->assertSee(route('govuk-alpha.login.forgot', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_forgot_password_flow_renders_and_requests_a_reset(): void
    {
        $form = $this->get("/{$this->testTenantSlug}/alpha/login/forgot-password");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha.auth.forgot_title'));
        $form->assertSee('name="email"', false);

        // Invalid email → anchored field error, no request made.
        $invalid = $this->post("/{$this->testTenantSlug}/alpha/login/forgot-password", ['email' => 'not-an-email']);
        $invalid->assertRedirect("/{$this->testTenantSlug}/alpha/login/forgot-password?status=forgot-invalid");

        // Any syntactically valid email → the same anti-enumeration confirmation.
        $sent = $this->post("/{$this->testTenantSlug}/alpha/login/forgot-password", ['email' => 'nobody-' . bin2hex(random_bytes(3)) . '@example.test']);
        $sent->assertRedirect("/{$this->testTenantSlug}/alpha/login/forgot-password?status=forgot-sent");

        $confirm = $this->get("/{$this->testTenantSlug}/alpha/login/forgot-password?status=forgot-sent");
        $confirm->assertOk();
        $confirm->assertSee(__('govuk_alpha.auth.forgot_sent_title'));
    }

    public function test_reset_password_page_renders_and_validates_pre_checks(): void
    {
        // No token → invalid-link state with a "request a new link" route.
        $noToken = $this->get("/{$this->testTenantSlug}/alpha/password/reset");
        $noToken->assertOk();
        $noToken->assertSee(__('govuk_alpha.auth.reset_link_invalid_title'));
        $noToken->assertSee(route('govuk-alpha.login.forgot', ['tenantSlug' => $this->testTenantSlug]), false);
        $noToken->assertDontSee('name="password"', false);

        // With a token → the new-password form.
        $withToken = $this->get("/{$this->testTenantSlug}/alpha/password/reset?token=demo-token-123");
        $withToken->assertOk();
        $withToken->assertSee(__('govuk_alpha.auth.reset_title'));
        $withToken->assertSee('name="password"', false);
        $withToken->assertSee('name="password_confirmation"', false);
        $withToken->assertSee('value="demo-token-123"', false);

        // Mismatched passwords → field error, no v2 call.
        $mismatch = $this->post("/{$this->testTenantSlug}/alpha/password/reset", [
            'token' => 'demo-token-123',
            'password' => 'LongEnoughPass1!',
            'password_confirmation' => 'DifferentPass1!',
        ]);
        $mismatch->assertRedirectContains('status=reset-mismatch');

        // Too-short password → weak.
        $weak = $this->post("/{$this->testTenantSlug}/alpha/password/reset", [
            'token' => 'demo-token-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);
        $weak->assertRedirectContains('status=reset-weak');

        // Missing token → token-missing.
        $missing = $this->post("/{$this->testTenantSlug}/alpha/password/reset", [
            'password' => 'LongEnoughPass1!',
            'password_confirmation' => 'LongEnoughPass1!',
        ]);
        $missing->assertRedirectContains('status=reset-token-missing');
    }

    public function test_profile_settings_account_section_renders_and_updates(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'Account',
            'last_name' => 'User',
            'name' => 'Account User',
            'password_hash' => Hash::make('CurrentPass123!'),
            'email' => 'account-' . bin2hex(random_bytes(3)) . '@example.test',
            'preferred_language' => 'en',
        ]);

        $page = $this->get("/{$this->testTenantSlug}/alpha/profile/settings");
        $page->assertOk();
        $page->assertSee(__('govuk_alpha.profile_settings.security_title'));
        $page->assertSee('name="new_password"', false);
        $page->assertSee('id="language"', false);
        $page->assertSee(__('govuk_alpha.profile_settings.languages.ga'));

        // Language change (direct DB update, no email).
        $lang = $this->post("/{$this->testTenantSlug}/alpha/profile/language", ['language' => 'ga']);
        $lang->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=language-changed");
        $this->assertDatabaseHas('users', ['id' => $user->id, 'preferred_language' => 'ga']);

        $langBad = $this->post("/{$this->testTenantSlug}/alpha/profile/language", ['language' => 'xx']);
        $langBad->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=language-invalid");

        // Password pre-checks.
        $mismatch = $this->post("/{$this->testTenantSlug}/alpha/profile/password", [
            'current_password' => 'CurrentPass123!',
            'new_password' => 'NewStrongPass456!',
            'new_password_confirmation' => 'DifferentPass456!',
        ]);
        $mismatch->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=password-mismatch");

        $weak = $this->post("/{$this->testTenantSlug}/alpha/profile/password", [
            'current_password' => 'CurrentPass123!',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ]);
        $weak->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=password-weak");

        // Password change happy path (no HIBP at this layer; fresh user has no history).
        $changed = $this->post("/{$this->testTenantSlug}/alpha/profile/password", [
            'current_password' => 'CurrentPass123!',
            'new_password' => 'NewStrongPass456!',
            'new_password_confirmation' => 'NewStrongPass456!',
        ]);
        $changed->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=password-changed");
        $newHash = (string) DB::table('users')->where('id', $user->id)->value('password_hash');
        $this->assertTrue(Hash::check('NewStrongPass456!', $newHash), 'The stored password hash should match the new password');

        // Wrong current password is rejected.
        $wrong = $this->post("/{$this->testTenantSlug}/alpha/profile/password", [
            'current_password' => 'TotallyWrong1!',
            'new_password' => 'AnotherStrong789!',
            'new_password_confirmation' => 'AnotherStrong789!',
        ]);
        $wrong->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=password-current-incorrect");

        // Email change re-authenticates: wrong password and invalid address are rejected
        // (the actual change emails the old address, so the happy path is left to the API tests).
        $emailWrong = $this->post("/{$this->testTenantSlug}/alpha/profile/email", [
            'email' => 'changed@example.test',
            'current_password' => 'NopeNotIt1!',
        ]);
        $emailWrong->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=email-password-incorrect");

        $emailBad = $this->post("/{$this->testTenantSlug}/alpha/profile/email", [
            'email' => 'not-an-email',
            'current_password' => 'NewStrongPass456!',
        ]);
        $emailBad->assertRedirect("/{$this->testTenantSlug}/alpha/profile/settings?status=email-invalid");
    }

    public function test_two_factor_page_requires_a_pending_challenge(): void
    {
        // No pending challenge in the session → bounced back to sign in.
        $noChallenge = $this->get("/{$this->testTenantSlug}/alpha/login/two-factor");
        $noChallenge->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=two-factor-expired");

        // With a pending challenge → the code-entry form renders.
        $form = $this->withSession(['alpha_2fa_token' => 'demo-challenge'])
            ->get("/{$this->testTenantSlug}/alpha/login/two-factor");
        $form->assertOk();
        $form->assertSee(__('govuk_alpha.auth.two_factor_title'));
        $form->assertSee('name="code"', false);
        $form->assertSee('name="use_backup_code"', false);

        // Submitting with no code (challenge present) → anchored field error.
        $noCode = $this->withSession(['alpha_2fa_token' => 'demo-challenge'])
            ->post("/{$this->testTenantSlug}/alpha/login/two-factor", ['code' => '']);
        $noCode->assertRedirect("/{$this->testTenantSlug}/alpha/login/two-factor?status=two-factor-code-required");

        // Submitting with no challenge → bounced to sign in.
        $noSession = $this->post("/{$this->testTenantSlug}/alpha/login/two-factor", ['code' => '123456']);
        $noSession->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=two-factor-expired");
    }

    public function test_dashboard_shows_wallet_gamification_and_upcoming_events_sections(): void
    {
        $user = $this->authenticatedUser();
        DB::table('events')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Dashboard upcoming event',
            'description' => 'Shown on the dashboard upcoming-events list.',
            'location' => 'Community Hall',
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");

        $response->assertOk();
        // Core time-credit balance tile (was entirely absent before) — the value
        // comes from WalletService; here we assert the tile itself renders.
        $response->assertSee(__('govuk_alpha.dashboard.timebank_title'));
        $response->assertSee(__('govuk_alpha.dashboard.balance_label'));
        // Gamification: level/XP heading + progress bar.
        $response->assertSee(__('govuk_alpha.dashboard.progress_title'));
        $response->assertSee('<progress', false);
        // Upcoming events (community-wide, same as React).
        $response->assertSee(__('govuk_alpha.dashboard.upcoming_events_title'));
        $response->assertSee('Dashboard upcoming event');
    }

    public function test_event_detail_renders_for_owner_with_null_end_time(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Event Owner 326']);
        $eventId = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'title' => 'Owner event no end time',
            'description' => 'Repro of the events/{id}?status=event-created 500.',
            'location' => 'Skibbereen, County Cork, Ireland',
            'start_time' => now()->addDays(2),
            'end_time' => null,
            'status' => 'active',
            'max_attendees' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/events/{$eventId}?status=event-created");
        $response->assertOk();
        $response->assertSee('Owner event no end time');
        $response->assertSee(__('govuk_alpha.events.created'));
    }

    public function test_dashboard_shows_personalised_header_and_onboarding_prompt(): void
    {
        $user = $this->authenticatedUser(['name' => 'Dash User', 'first_name' => 'Dash']);
        DB::table('users')->where('id', $user->id)->update(['onboarding_completed' => 0]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");

        $response->assertOk();
        // Personalised welcome using the member's first name.
        $response->assertSee(__('govuk_alpha.dashboard.welcome', ['name' => 'Dash']));
        // Onboarding prompt while onboarding is incomplete.
        $response->assertSee(__('govuk_alpha.dashboard.onboarding_title'));
        // Prominent create-listing call to action.
        $response->assertSee(__('govuk_alpha.dashboard.new_listing'));
        $response->assertSee(route('govuk-alpha.listings.create', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_wallet_page_renders_balance_and_transaction_history(): void
    {
        $user = $this->authenticatedUser(['name' => 'Wallet Owner']);
        DB::table('users')->where('id', $user->id)->update(['balance' => 20]);

        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Gardening',
            'last_name' => 'Neighbour',
        ]);
        DB::table('users')->where('id', $recipient->id)->update(['balance' => 0]);

        // Seed a transaction through the real transfer endpoint so every column is
        // set correctly and the history table renders a row.
        $this->post("/{$this->testTenantSlug}/alpha/wallet/transfer", [
            'recipient_id' => $recipient->id,
            'amount' => '5',
            'note' => 'Allotment digging',
        ])->assertRedirectContains('status=transfer-sent');

        $response = $this->get("/{$this->testTenantSlug}/alpha/wallet");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.wallet.title'));
        // Balance is 20 − 5 = 15.00, formatted to two decimals.
        $response->assertSee(__('govuk_alpha.wallet.hours_value', ['value' => '15.00']));
        $response->assertSee(__('govuk_alpha.wallet.history_title'));
        $response->assertSee('Allotment digging');
        $response->assertSee('Gardening Neighbour');
    }

    public function test_wallet_recipient_search_disambiguates_same_named_members(): void
    {
        $this->disableMeiliSearch();
        $this->authenticatedUser(['name' => 'Searcher Member']);

        // Two members with the IDENTICAL display name, told apart ONLY by
        // location + "member since". The surname is an invented token so
        // Meilisearch can't fuzz-match it to real seed members — that guarantees
        // the deterministic SQL LIKE fallback runs and returns BOTH test users
        // (which, being transaction-scoped, are never in the Meili index).
        $maryA = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true,
            'first_name' => 'Quenby', 'last_name' => 'Zzyzxington',
        ]);
        DB::table('users')->where('id', $maryA->id)->update(['location' => 'Cork', 'created_at' => '2024-03-15 10:00:00']);

        $maryB = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true,
            'first_name' => 'Quenby', 'last_name' => 'Zzyzxington',
        ]);
        DB::table('users')->where('id', $maryB->id)->update(['location' => 'Galway', 'created_at' => '2023-09-01 10:00:00']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/wallet?recipient_q=Zzyzxington");

        $response->assertOk();
        // Both identically-named members appear, distinguished by location + member-since.
        $response->assertSee('Quenby Zzyzxington');
        $response->assertSee('Cork');
        $response->assertSee('Galway');
        $response->assertSee(__('govuk_alpha.wallet.member_since', ['date' => 'March 2024']));
        $response->assertSee(__('govuk_alpha.wallet.member_since', ['date' => 'September 2023']));
    }

    public function test_account_hub_renders_personal_facilities(): void
    {
        $this->authenticatedUser(['name' => 'Hub User']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/account");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.account.title'));
        $response->assertSee(__('govuk_alpha.account.wallet_title'));
        $response->assertSee(__('govuk_alpha.account.profile_title'));
        $response->assertSee(__('govuk_alpha.account.settings_title'));
        // Cards link to the real destinations, including Matches + Group exchanges
        // which now live in the hub rather than the service nav.
        $response->assertSee(route('govuk-alpha.wallet.index', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.profile.settings', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.matches.index', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertSee(route('govuk-alpha.group-exchanges.index', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_service_nav_lists_polls_last_and_excludes_personal_items(): void
    {
        $this->authenticatedUser(['name' => 'Nav User']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");
        $response->assertOk();
        $html = $response->getContent();

        $navList = '<ul class="govuk-service-navigation__list"';
        $navStart = strpos($html, $navList);
        $this->assertNotFalse($navStart, 'service navigation list should render');
        $navHtml = substr($html, $navStart);

        $volunteeringPos = strpos($navHtml, route('govuk-alpha.volunteering.index', ['tenantSlug' => $this->testTenantSlug]));
        $pollsPos = strpos($navHtml, route('govuk-alpha.polls.index', ['tenantSlug' => $this->testTenantSlug]));
        $this->assertNotFalse($volunteeringPos);
        $this->assertNotFalse($pollsPos);
        // Polls comes after Volunteering (it is the last item in the bar).
        $this->assertGreaterThan($volunteeringPos, $pollsPos);

        // Matches + Group exchanges are NOT in the service navigation any more.
        $this->assertStringNotContainsString(
            'govuk-service-navigation__link" href="' . route('govuk-alpha.matches.index', ['tenantSlug' => $this->testTenantSlug]),
            $navHtml
        );
        $this->assertStringNotContainsString(
            'govuk-service-navigation__link" href="' . route('govuk-alpha.group-exchanges.index', ['tenantSlug' => $this->testTenantSlug]),
            $navHtml
        );
    }

    public function test_header_surfaces_account_link_when_signed_in(): void
    {
        $this->authenticatedUser(['name' => 'Header User']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");

        $response->assertOk();
        // The top zone holds a single "My account" hub link (wallet and the rest
        // of the personal items live behind it, not as separate header chips).
        $response->assertSee(__('govuk_alpha.nav.account'));
        $response->assertSee('class="nexus-alpha-header__link" href="' . route('govuk-alpha.account', ['tenantSlug' => $this->testTenantSlug]) . '"', false);
        // Wallet is no longer a separate header chip.
        $response->assertDontSee('class="nexus-alpha-header__link nexus-alpha-header__link--wallet"', false);
    }

    public function test_wallet_recipient_autocomplete_endpoint_and_pick_by_id(): void
    {
        $this->disableMeiliSearch();
        $this->authenticatedUser(['name' => 'Picker Member']);
        $target = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true,
            'first_name' => 'Quenby', 'last_name' => 'Zzyzxington',
        ]);
        DB::table('users')->where('id', $target->id)->update(['location' => 'Cork', 'created_at' => '2024-03-15 10:00:00']);

        // JSON suggestions endpoint (powers the JS autocomplete).
        $json = $this->getJson("/{$this->testTenantSlug}/alpha/wallet/recipients?q=Zzyzxington");
        $json->assertOk();
        // Position-independent: just assert our member is present with the
        // disambiguation fields + id.
        $json->assertJsonFragment(['id' => $target->id, 'name' => 'Quenby Zzyzxington', 'location' => 'Cork', 'since' => 'Mar 2024']);

        // Picking by recipient_id resolves to exactly that one transfer card.
        $page = $this->get("/{$this->testTenantSlug}/alpha/wallet?recipient_id={$target->id}");
        $page->assertOk();
        $page->assertSee('Quenby Zzyzxington');
        $page->assertSee(route('govuk-alpha.wallet.transfer', ['tenantSlug' => $this->testTenantSlug]), false);
        // The progressive-enhancement hooks are present in the markup.
        $page->assertSee('data-alpha-recipient-autocomplete', false);
        $page->assertSee(route('govuk-alpha.wallet.recipients', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_wallet_recipient_autocomplete_endpoint_requires_min_query(): void
    {
        $this->authenticatedUser(['name' => 'Picker Member']);

        // Too-short queries return no results (don't hammer the search backend).
        $json = $this->getJson("/{$this->testTenantSlug}/alpha/wallet/recipients?q=a");
        $json->assertOk();
        $json->assertExactJson(['results' => []]);
    }

    public function test_wallet_transfer_moves_credits_between_members(): void
    {
        $sender = $this->authenticatedUser(['name' => 'Sender Member']);
        DB::table('users')->where('id', $sender->id)->update(['balance' => 10]);

        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Recipient Member',
            'balance' => 0,
        ]);

        // Whole-hour amount keeps the assertion exact regardless of the test DB's
        // balance column precision (nexus_test is int; production is decimal(10,2)).
        $response = $this->post("/{$this->testTenantSlug}/alpha/wallet/transfer", [
            'recipient_id' => $recipient->id,
            'amount' => '3',
            'note' => 'Thanks for the help',
        ]);

        $response->assertRedirectContains('status=transfer-sent');

        $this->assertSame(7.0, (float) DB::table('users')->where('id', $sender->id)->value('balance'));
        $this->assertSame(3.0, (float) DB::table('users')->where('id', $recipient->id)->value('balance'));
    }

    public function test_wallet_transfer_rejects_a_recipient_from_another_tenant(): void
    {
        $sender = $this->authenticatedUser(['name' => 'Tenant Two Sender']);
        DB::table('users')->where('id', $sender->id)->update(['balance' => 10]);

        // A user that belongs to the pre-seeded second tenant (id 999).
        $foreign = User::factory()->forTenant(999)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Foreign Member',
            'balance' => 0,
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/wallet/transfer", [
            'recipient_id' => $foreign->id,
            'amount' => '3',
        ]);

        // The defensive same-tenant guard rejects before any balance moves.
        $response->assertRedirectContains('error=not-found');
        $this->assertSame(10.0, (float) DB::table('users')->where('id', $sender->id)->value('balance'));
        $this->assertSame(0.0, (float) DB::table('users')->where('id', $foreign->id)->value('balance'));
    }

    public function test_wallet_transfer_rejects_insufficient_balance(): void
    {
        $sender = $this->authenticatedUser(['name' => 'Broke Member']);
        DB::table('users')->where('id', $sender->id)->update(['balance' => 1]);

        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Hopeful Member',
            'balance' => 0,
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/wallet/transfer", [
            'recipient_id' => $recipient->id,
            'amount' => 5,
        ]);

        $response->assertRedirectContains('error=insufficient');
        $this->assertSame(1.0, (float) DB::table('users')->where('id', $sender->id)->value('balance'));
        $this->assertSame(0.0, (float) DB::table('users')->where('id', $recipient->id)->value('balance'));
    }

    public function test_connections_inbox_renders_received_sent_and_accepted(): void
    {
        $me = $this->authenticatedUser(['name' => 'Me Member']);
        $requester = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Rhea Requester']);
        $sentTo = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Sandro Sent']);
        $friend = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Fiona Friend']);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $requester->id, 'receiver_id' => $me->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'requester_id' => $me->id, 'receiver_id' => $sentTo->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.connections.received_title'));
        $response->assertSee(__('govuk_alpha.connections.accepted_title'));
        $response->assertSee(__('govuk_alpha.connections.sent_title'));
        $response->assertSee('Rhea Requester');  // pending received
        $response->assertSee('Sandro Sent');     // pending sent
        $response->assertSee('Fiona Friend');    // accepted
    }

    public function test_connection_accept_marks_it_accepted(): void
    {
        $me = $this->authenticatedUser(['name' => 'Me Member']);
        $requester = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Req User']);
        $cid = DB::table('connections')->insertGetId([
            'tenant_id' => $this->testTenantId, 'requester_id' => $requester->id, 'receiver_id' => $me->id,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/connections/{$cid}/accept");

        $response->assertRedirectContains('status=connection-accepted');
        $this->assertSame('accepted', DB::table('connections')->where('id', $cid)->value('status'));
    }

    public function test_connection_decline_deletes_the_request(): void
    {
        $me = $this->authenticatedUser(['name' => 'Me Member']);
        $requester = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Req User']);
        $cid = DB::table('connections')->insertGetId([
            'tenant_id' => $this->testTenantId, 'requester_id' => $requester->id, 'receiver_id' => $me->id,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/connections/{$cid}/decline");

        $response->assertRedirectContains('status=connection-declined');
        $this->assertSame(0, DB::table('connections')->where('id', $cid)->count());
    }

    public function test_connection_cancel_removes_a_sent_request(): void
    {
        $me = $this->authenticatedUser(['name' => 'Me Member']);
        $sentTo = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Target User']);
        $cid = DB::table('connections')->insertGetId([
            'tenant_id' => $this->testTenantId, 'requester_id' => $me->id, 'receiver_id' => $sentTo->id,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/connections/{$cid}/remove");

        $response->assertRedirectContains('status=connection-removed');
        $this->assertSame(0, DB::table('connections')->where('id', $cid)->count());
    }

    public function test_connection_accept_rejects_a_non_participant(): void
    {
        $this->authenticatedUser(['name' => 'Bystander']);
        $a = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Alpha User']);
        $b = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Bravo User']);
        // A pending request between two OTHER members — the signed-in user is neither.
        $cid = DB::table('connections')->insertGetId([
            'tenant_id' => $this->testTenantId, 'requester_id' => $a->id, 'receiver_id' => $b->id,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post("/{$this->testTenantSlug}/alpha/connections/{$cid}/accept");

        $response->assertRedirectContains('status=connection-failed');
        $this->assertSame('pending', DB::table('connections')->where('id', $cid)->value('status'));
    }

    public function test_group_exchange_create_redirects_to_detail_and_lists(): void
    {
        $creator = $this->authenticatedUser(['name' => 'GX Creator']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/group-exchanges/new", [
            'title' => 'Spring cleanup',
            'total_hours' => 3,
            'split_type' => 'equal',
        ]);

        $response->assertRedirectContains('/group-exchanges/');
        $response->assertRedirectContains('status=created');
        $this->assertSame(1, DB::table('group_exchanges')
            ->where('tenant_id', $this->testTenantId)
            ->where('organizer_id', $creator->id)
            ->where('title', 'Spring cleanup')
            ->count());

        $list = $this->get("/{$this->testTenantSlug}/alpha/group-exchanges");
        $list->assertOk();
        $list->assertSee('Spring cleanup');
    }

    public function test_group_exchange_organiser_adds_and_removes_a_participant(): void
    {
        $organiser = $this->authenticatedUser(['name' => 'Organiser']);
        $svc = app(\App\Services\GroupExchangeService::class);
        $exId = $svc->create($organiser->id, ['title' => 'GX', 'total_hours' => 2, 'split_type' => 'custom', 'status' => 'draft']);

        $member = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active', 'is_approved' => true, 'first_name' => 'Pat', 'last_name' => 'Helper',
        ]);

        $add = $this->post("/{$this->testTenantSlug}/alpha/group-exchanges/{$exId}/participants", [
            'participant_id' => $member->id, 'role' => 'provider', 'hours' => 2,
        ]);
        $add->assertRedirectContains('status=participant-added');

        $detail = $this->get("/{$this->testTenantSlug}/alpha/group-exchanges/{$exId}");
        $detail->assertOk();
        $detail->assertSee('Pat Helper');

        $remove = $this->post("/{$this->testTenantSlug}/alpha/group-exchanges/{$exId}/participants/{$member->id}/remove");
        $remove->assertRedirectContains('status=participant-removed');
        $this->assertSame(0, DB::table('group_exchange_participants')->where('group_exchange_id', $exId)->count());
    }

    public function test_group_exchange_complete_moves_credits_when_all_confirmed(): void
    {
        $organiser = $this->authenticatedUser(['name' => 'Organiser']);
        $svc = app(\App\Services\GroupExchangeService::class);
        $exId = $svc->create($organiser->id, ['title' => 'Pool of hours', 'total_hours' => 4, 'split_type' => 'custom', 'status' => 'draft']);

        $provider = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Provider P']);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Receiver R']);
        DB::table('users')->where('id', $provider->id)->update(['balance' => 0]);
        DB::table('users')->where('id', $receiver->id)->update(['balance' => 10]);

        // Whole hours keep the assertion exact on the int-column test DB.
        $svc->addParticipant($exId, $provider->id, 'provider', 4, 1.0);
        $svc->addParticipant($exId, $receiver->id, 'receiver', 4, 1.0);
        $svc->confirmParticipation($exId, $provider->id);
        $svc->confirmParticipation($exId, $receiver->id);

        $complete = $this->post("/{$this->testTenantSlug}/alpha/group-exchanges/{$exId}/complete");

        $complete->assertRedirectContains('status=completed');
        $this->assertSame(4.0, (float) DB::table('users')->where('id', $provider->id)->value('balance'));
        $this->assertSame(6.0, (float) DB::table('users')->where('id', $receiver->id)->value('balance'));
        $this->assertSame('completed', DB::table('group_exchanges')->where('id', $exId)->value('status'));
    }

    public function test_group_exchange_add_participant_rejected_for_non_organiser(): void
    {
        $organiser = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Real Organiser']);
        $svc = app(\App\Services\GroupExchangeService::class);
        $exId = $svc->create($organiser->id, ['title' => 'Not yours', 'total_hours' => 2, 'split_type' => 'custom', 'status' => 'draft']);

        // A different signed-in member tries to add someone to an exchange they don't own.
        $this->authenticatedUser(['name' => 'Outsider']);
        $victim = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Victim']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/group-exchanges/{$exId}/participants", [
            'participant_id' => $victim->id, 'role' => 'provider', 'hours' => 1,
        ]);

        $response->assertRedirectContains('status=add-failed');
        $this->assertSame(0, DB::table('group_exchange_participants')->where('group_exchange_id', $exId)->count());
    }

    public function test_matches_page_renders_for_a_signed_in_member(): void
    {
        $this->authenticatedUser(['name' => 'Matcher Member']);

        // The page must render whether or not the engine finds matches (the
        // controller degrades to an empty state on any engine error).
        $response = $this->get("/{$this->testTenantSlug}/alpha/matches");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.matches.title'));
        $response->assertSee(__('govuk_alpha.matches.description'));
    }

    public function test_polls_page_shows_open_poll_with_vote_form(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Poll Maker']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creator->id, 'question' => 'Best day for the meet-up?',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(),
        ]);
        DB::table('poll_options')->insert([
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Monday', 'votes' => 0],
            ['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Tuesday', 'votes' => 0],
        ]);

        // A non-creator who has not voted sees the vote form (ballot integrity hides totals).
        $this->authenticatedUser(['name' => 'Voter One']);
        $response = $this->get("/{$this->testTenantSlug}/alpha/polls");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.polls.title'));
        $response->assertSee('Best day for the meet-up?');
        $response->assertSee('Monday');
        $response->assertSee('Tuesday');
        $response->assertSee(__('govuk_alpha.polls.vote_button'));
    }

    public function test_poll_vote_records_the_vote(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Poll Maker']);
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creator->id, 'question' => 'Tea or coffee?',
            'is_active' => 1, 'end_date' => null, 'created_at' => now(),
        ]);
        $optionId = DB::table('poll_options')->insertGetId(['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Tea', 'votes' => 0]);

        $voter = $this->authenticatedUser(['name' => 'Voter Two']);
        $response = $this->post("/{$this->testTenantSlug}/alpha/polls/{$pollId}/vote", ['option_id' => $optionId]);

        $response->assertRedirectContains('status=voted');
        $this->assertSame(1, DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->where('option_id', $optionId)
            ->where('user_id', $voter->id)
            ->count());
    }

    public function test_polls_page_shows_closed_poll_results_with_leading_option(): void
    {
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Poll Maker']);
        // end_date in the past → closed → results become visible to everyone.
        $pollId = DB::table('polls')->insertGetId([
            'tenant_id' => $this->testTenantId, 'user_id' => $creator->id, 'question' => 'Which community logo?',
            'is_active' => 1, 'end_date' => now()->subDay(), 'created_at' => now()->subDays(5),
        ]);
        $blue = DB::table('poll_options')->insertGetId(['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Blue', 'votes' => 0]);
        $green = DB::table('poll_options')->insertGetId(['tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'label' => 'Green', 'votes' => 0]);

        // Blue leads 2–1.
        foreach ([$blue, $blue, $green] as $i => $optId) {
            $v = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
            DB::table('poll_votes')->insert([
                'tenant_id' => $this->testTenantId, 'poll_id' => $pollId, 'option_id' => $optId, 'user_id' => $v->id, 'created_at' => now(),
            ]);
        }

        $this->authenticatedUser(['name' => 'Results Viewer']);
        $response = $this->get("/{$this->testTenantSlug}/alpha/polls");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.polls.closed_section_title'));
        $response->assertSee('Which community logo?');
        $response->assertSee('Blue');
        $response->assertSee('Green');
        $response->assertSee(__('govuk_alpha.polls.closed_tag'));
        // Results are visible and the winning option is flagged as leading.
        $response->assertSee(__('govuk_alpha.polls.leading'));
    }

    public function test_timebanking_guide_renders_publicly(): void
    {
        // Public, no auth: newcomers can read how timebanking works before signing up.
        $guest = $this->get("/{$this->testTenantSlug}/alpha/guide");

        $guest->assertOk();
        $guest->assertSee(__('govuk_alpha.guide.title'));
        $guest->assertSee(__('govuk_alpha.guide.equal_title'));
        $guest->assertSee(__('govuk_alpha.guide.step1_title'));
        $guest->assertSee(__('govuk_alpha.guide.step3_title'));
        // Signed-out visitors get a create-account call to action.
        $guest->assertSee(route('govuk-alpha.register', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_explore_hub_and_discovery_pages_render(): void
    {
        $this->authenticatedUser(['name' => 'Explorer']);

        foreach (['explore', 'search', 'groups', 'goals', 'skills', 'organisations'] as $path) {
            $response = $this->get("/{$this->testTenantSlug}/alpha/{$path}");
            $response->assertOk();
        }

        $this->get("/{$this->testTenantSlug}/alpha/explore")->assertSee(__('govuk_alpha.explore.title'));
        $this->get("/{$this->testTenantSlug}/alpha/goals")->assertSee(__('govuk_alpha.goals.create_title'));
        $this->get("/{$this->testTenantSlug}/alpha/organisations")->assertSee(__('govuk_alpha.organisations.register_title'));
    }

    public function test_goal_create_stores_a_goal(): void
    {
        $user = $this->authenticatedUser(['name' => 'Goal Setter']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/goals", [
            'title' => 'Give 20 hours this year',
            'target_value' => 20,
            'description' => 'My volunteering target',
        ]);

        $response->assertRedirectContains('status=goal-created');
        $this->assertSame(1, DB::table('goals')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('title', 'Give 20 hours this year')
            ->count());
    }

    public function test_notifications_inbox_renders(): void
    {
        $this->authenticatedUser(['name' => 'Notified Member']);
        $response = $this->get("/{$this->testTenantSlug}/alpha/notifications");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.notifications.title'));
        $response->assertSee(__('govuk_alpha.notifications.all_filter'));
    }

    public function test_activity_page_renders(): void
    {
        $this->authenticatedUser(['name' => 'Active Member']);
        $response = $this->get("/{$this->testTenantSlug}/alpha/activity");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.activity.title'));
        $response->assertSee(__('govuk_alpha.activity.hours_given'));
    }

    public function test_reviews_page_renders(): void
    {
        $this->authenticatedUser(['name' => 'Reviewed Member']);
        $response = $this->get("/{$this->testTenantSlug}/alpha/reviews");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.reviews_page.title'));
        $response->assertSee(__('govuk_alpha.reviews_page.received_tab'));
    }

    public function test_features_and_faq_render_publicly(): void
    {
        $features = $this->get("/{$this->testTenantSlug}/alpha/features");
        $features->assertOk();
        $features->assertSee(__('govuk_alpha.features.title'));

        $faq = $this->get("/{$this->testTenantSlug}/alpha/faq");
        $faq->assertOk();
        $faq->assertSee(__('govuk_alpha.faq.title'));
        $faq->assertSee(__('govuk_alpha.faq.q1'));
    }

    public function test_achievements_page_renders_level_and_badges(): void
    {
        $this->authenticatedUser(['name' => 'Achiever']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/achievements");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.achievements.title'));
        $response->assertSee(__('govuk_alpha.achievements.level_label'));
        $response->assertSee(__('govuk_alpha.achievements.earned_title'));
    }

    public function test_leaderboard_page_renders_with_metric_filter(): void
    {
        $this->authenticatedUser(['name' => 'Ranked Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/leaderboard");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.leaderboard.title'));
        $response->assertSee(__('govuk_alpha.leaderboard.metric_label'));
        $response->assertSee(__('govuk_alpha.leaderboard.metrics.credits_earned'));
    }

    public function test_nexus_score_page_renders(): void
    {
        $this->authenticatedUser(['name' => 'Scored Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/nexus-score");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.nexus_score.title'));
        $response->assertSee(__('govuk_alpha.nexus_score.description'));
    }

    public function test_wave3_discovery_pages_render(): void
    {
        $this->authenticatedUser(['name' => 'Browser Member']);

        foreach (['saved', 'resources', 'jobs', 'ideation'] as $path) {
            $this->get("/{$this->testTenantSlug}/alpha/{$path}")->assertOk();
        }

        $this->get("/{$this->testTenantSlug}/alpha/jobs")->assertSee(__('govuk_alpha.jobs.title'));
        $this->get("/{$this->testTenantSlug}/alpha/ideation")->assertSee(__('govuk_alpha.ideation.title'));
        $this->get("/{$this->testTenantSlug}/alpha/resources")->assertSee(__('govuk_alpha.resources.title'));
        $this->get("/{$this->testTenantSlug}/alpha/saved")->assertSee(__('govuk_alpha.saved.title'));
    }

    public function test_job_detail_renders_and_application_is_recorded(): void
    {
        $applicant = $this->authenticatedUser(['name' => 'Keen Applicant']);
        // The opportunity must belong to someone else — you cannot apply to your own.
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $jobId = DB::table('job_vacancies')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'title' => 'Community Gardener',
            'description' => 'Help tend the shared allotment each week.',
            'type' => 'volunteer',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$jobId}");
        $detail->assertOk();
        $detail->assertSee('Community Gardener');
        $detail->assertSee(__('govuk_alpha.jobs.apply_button'));

        $apply = $this->post("/{$this->testTenantSlug}/alpha/jobs/{$jobId}/apply", [
            'cover_letter' => 'I would love to help in the garden.',
        ]);
        $apply->assertRedirectContains('status=applied');

        $this->assertSame(1, DB::table('job_vacancy_applications')
            ->where('tenant_id', $this->testTenantId)
            ->where('vacancy_id', $jobId)
            ->where('user_id', $applicant->id)
            ->count());
    }

    public function test_ideation_challenge_detail_submit_and_vote(): void
    {
        $member = $this->authenticatedUser(['name' => 'Idea Author']);

        $challengeId = DB::table('ideation_challenges')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'title' => 'How should we use the old library?',
            'description' => 'Share your ideas for the space.',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $detail = $this->get("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}");
        $detail->assertOk();
        $detail->assertSee('How should we use the old library?');
        $detail->assertSee(__('govuk_alpha.ideation.submit_button'));

        // Submit a new idea.
        $submit = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas", [
            'idea_title' => 'A community makerspace',
            'idea_content' => 'Tools and benches anyone can use.',
        ]);
        $submit->assertRedirectContains('status=idea-submitted');
        $this->assertSame(1, DB::table('challenge_ideas')
            ->where('challenge_id', $challengeId)
            ->where('user_id', $member->id)
            ->where('title', 'A community makerspace')
            ->count());

        // Vote on someone else's idea (you cannot vote on your own).
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $ideaId = DB::table('challenge_ideas')->insertGetId([
            'challenge_id' => $challengeId,
            'user_id' => $other->id,
            'title' => 'A reading garden',
            'description' => 'Quiet outdoor seating with books.',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vote = $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/vote");
        $vote->assertRedirectContains('status=idea-voted');
        $this->assertSame(1, DB::table('challenge_idea_votes')
            ->where('idea_id', $ideaId)
            ->where('user_id', $member->id)
            ->count());
    }

    public function test_wave4_modules_render_when_enabled(): void
    {
        $this->authenticatedUser(['name' => 'Shopper']);
        $this->enableAlphaFeatures(['marketplace', 'courses', 'podcasts', 'merchant_coupons', 'member_premium']);

        $pages = [
            'marketplace' => 'govuk_alpha.marketplace.title',
            'courses' => 'govuk_alpha.courses.title',
            'podcasts' => 'govuk_alpha.podcasts.title',
            'coupons' => 'govuk_alpha.coupons.title',
            'premium' => 'govuk_alpha.premium.title',
            'federation' => 'govuk_alpha.federation.title',
        ];
        foreach ($pages as $path => $key) {
            $res = $this->get("/{$this->testTenantSlug}/alpha/{$path}");
            $res->assertOk();
            $res->assertSee(__($key));
        }
    }

    public function test_marketplace_is_gated_off_by_default(): void
    {
        $this->authenticatedUser();
        $this->get("/{$this->testTenantSlug}/alpha/marketplace")->assertStatus(403);
    }

    public function test_marketplace_item_detail_renders(): void
    {
        $user = $this->authenticatedUser(['name' => 'Buyer']);
        $this->enableAlphaFeatures(['marketplace']);

        $id = DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Vintage Bicycle',
            'description' => 'A lovely old bike in good condition.',
            'price_type' => 'free',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Detail page.
        $res = $this->get("/{$this->testTenantSlug}/alpha/marketplace/{$id}");
        $res->assertOk();
        $res->assertSee('Vintage Bicycle');

        // Index card loop renders the same listing.
        $index = $this->get("/{$this->testTenantSlug}/alpha/marketplace");
        $index->assertOk();
        $index->assertSee('Vintage Bicycle');
    }

    public function test_clubs_directory_lists_a_club(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Club Secretary']);

        DB::table('vol_organizations')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'name' => 'Riverside Chess Club',
            'description' => 'A friendly chess club meeting weekly.',
            'org_type' => 'club',
            'status' => 'active',
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/clubs");
        $res->assertOk();
        $res->assertSee('Riverside Chess Club');
    }

    public function test_podcast_detail_renders_show(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Podcaster']);
        $this->enableAlphaFeatures(['podcasts']);

        $showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_user_id' => $owner->id,
            'title' => 'Community Voices',
            'slug' => 'community-voices-' . $owner->id,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/podcasts/{$showId}");
        $res->assertOk();
        $res->assertSee('Community Voices');
    }

    public function test_course_free_enrolment_records_enrollment(): void
    {
        $learner = $this->authenticatedUser(['name' => 'Learner']);
        $author = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->enableAlphaFeatures(['courses']);

        $courseId = DB::table('courses')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'author_user_id' => $author->id,
            'title' => 'Intro to Beekeeping',
            'slug' => 'intro-to-beekeeping-' . $author->id,
            'level' => 'beginner',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'approved',
            'credit_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Index card loop renders the course.
        $index = $this->get("/{$this->testTenantSlug}/alpha/courses");
        $index->assertOk();
        $index->assertSee('Intro to Beekeeping');

        $detail = $this->get("/{$this->testTenantSlug}/alpha/courses/{$courseId}");
        $detail->assertOk();
        $detail->assertSee('Intro to Beekeeping');
        $detail->assertSee(__('govuk_alpha.courses.enrol_button'));

        $enrol = $this->post("/{$this->testTenantSlug}/alpha/courses/{$courseId}/enrol");
        $enrol->assertRedirectContains('status=enrolled');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertTrue(\App\Services\CourseEnrollmentService::isEnrolled($courseId, $learner->id));
    }

    public function test_job_and_ideation_mutations_work_without_group_exchanges_feature(): void
    {
        // Regression: applyJob/submitIdea/voteIdea must NOT depend on the
        // group_exchanges feature. Disable it while keeping job_vacancies and
        // ideation_challenges on.
        $features = \App\Services\TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['group_exchanges'] = false;
        $features['job_vacancies'] = true;
        $features['ideation_challenges'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($features)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $applicant = $this->authenticatedUser(['name' => 'Applicant No GE']);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $jobId = DB::table('job_vacancies')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'title' => 'Allotment Helper',
            'description' => 'Weekly help on the allotment.',
            'type' => 'volunteer',
            'status' => 'open',
            'created_at' => now(),
        ]);
        // Apply must succeed (not 403) even with group_exchanges off.
        $this->post("/{$this->testTenantSlug}/alpha/jobs/{$jobId}/apply", ['cover_letter' => 'Happy to help.'])
            ->assertRedirectContains('status=applied');

        $challengeId = DB::table('ideation_challenges')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $applicant->id,
            'title' => 'New use for the hall?',
            'description' => 'Ideas welcome.',
            'status' => 'open',
            'created_at' => now(),
        ]);
        $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas", [
            'idea_title' => 'Weekly repair cafe',
            'idea_content' => 'Fix things together.',
        ])->assertRedirectContains('status=idea-submitted');

        $ideaId = DB::table('challenge_ideas')->insertGetId([
            'challenge_id' => $challengeId,
            'user_id' => $owner->id,
            'title' => 'Board game night',
            'description' => 'Monthly games.',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->post("/{$this->testTenantSlug}/alpha/ideation/{$challengeId}/ideas/{$ideaId}/vote")
            ->assertRedirectContains('status=idea-voted');
    }

    /**
     * Enable one or more feature flags on the test tenant (they default off for
     * the commerce modules). Mirrors the DB-update + TenantContext::reset pattern
     * the module-gate tests use, so the next request re-resolves with the flags on.
     *
     * @param array<int,string> $features
     */
    private function enableAlphaFeatures(array $features): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        foreach ($features as $f) {
            $current[$f] = true;
        }
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /**
     * Force the member search down its deterministic SQL fallback by marking
     * Meilisearch unavailable. Meili indexing is non-transactional and async, so
     * across a suite run the index accumulates stale ids from rolled-back tests —
     * which makes any search-result assertion flaky. With Meili off, the SQL LIKE
     * path queries the live (transaction-visible) rows only.
     */
    private function disableMeiliSearch(): void
    {
        $prop = new \ReflectionProperty(\App\Services\SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
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

    private function alphaText(string $locale, string $key, array $replace = []): string
    {
        $value = require base_path("lang/{$locale}/govuk_alpha.php");

        foreach (explode('.', $key) as $part) {
            $value = $value[$part];
        }

        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, $replacement, $value);
        }

        return $value;
    }

    private function ensureListingCategory(): void
    {
        DB::table('categories')->insertOrIgnore([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'name' => 'General',
            'slug' => 'general',
            'type' => 'listing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableExchangeWorkflow(): void
    {
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update([
                'configuration' => json_encode([
                    'broker_controls' => [
                        'exchange_workflow' => [
                            'enabled' => true,
                            'require_broker_approval' => false,
                        ],
                    ],
                ]),
            ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }
}
