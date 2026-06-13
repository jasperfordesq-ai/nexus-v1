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
        $response->assertDontSee('type="submit"', false);
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
        $response->assertSee('<nav class="nexus-alpha-footer__links"', false);
        $response->assertSee('<a class="govuk-link" href="' . $contactUrl . '">' . __('govuk_alpha.footer.links.contact') . '</a>', false);
        $response->assertDontSee(__('govuk_alpha.footer.links.logout'));
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
        $response->assertSee('<nav class="nexus-alpha-footer__links"', false);
        // Sign-out changes state, so it is a POST form (with CSRF), not a GET link.
        $response->assertSee('<form method="post" action="' . $logoutUrl . '"', false);
        $response->assertSee(__('govuk_alpha.footer.links.logout'));
        $response->assertDontSee('<a class="govuk-link" href="' . $logoutUrl . '">', false);

        // The GET method is no longer routable for the state-changing sign-out.
        $this->get("/{$this->testTenantSlug}/alpha/logout")->assertStatus(405);

        $logout = $this->post("/{$this->testTenantSlug}/alpha/logout");
        $logout->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=signed-out");
        $logout->assertCookieExpired('auth_token');
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
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");

        $response->assertOk();
        $response->assertSee('Alpha detail listing');
        $response->assertSee('class="govuk-back-link"', false);
        $response->assertSee('class="govuk-summary-list"', false);
        $response->assertSee('class="govuk-tag govuk-tag--purple"', false);
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

        $archive = $this->post("/{$this->testTenantSlug}/alpha/messages/{$recipient->id}/archive");
        $archive->assertRedirect("/{$this->testTenantSlug}/alpha/messages?status=conversation-archived");

        $archived = $this->get("/{$this->testTenantSlug}/alpha/messages?archived=1");
        $archived->assertOk();
        $archived->assertSee(__('govuk_alpha.actions.restore_conversation'));
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
        $profile->assertSee(__('govuk_alpha.nav.profile'));
        $profile->assertSee('class="nexus-alpha-header__link" href="' . $profileUrl . '"', false);
        $profile->assertDontSee('class="govuk-service-navigation__link" href="' . $profileUrl . '"', false);
        $profile->assertSee(__('govuk_alpha.header.back_to_main_site'));
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

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
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
