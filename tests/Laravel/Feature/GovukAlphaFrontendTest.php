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
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class GovukAlphaFrontendTest extends TestCase
{
    use DatabaseTransactions;

    public function test_root_renders_accessible_tenant_chooser(): void
    {
        \App\Core\TenantContext::reset();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.tenant_chooser.title'));
        $response->assertSee($this->testTenantSlug);
        $response->assertSee("/{$this->testTenantSlug}/alpha", false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_home_login_and_register_pages_render_for_tenant(): void
    {
        foreach (['/alpha', '/alpha/login', '/alpha/register'] as $path) {
            $response = $this->get("/{$this->testTenantSlug}{$path}");

            $response->assertOk();
            $response->assertHeader('content-type', 'text/html; charset=UTF-8');
            $response->assertSee('Project NEXUS Accessible');
            $response->assertSee('class="govuk-skip-link"', false);
            $response->assertSee('class="govuk-phase-banner"', false);
            $response->assertSee('AGPL-3.0-or-later');
        }
    }

    public function test_accessible_login_persists_token_cookie_for_server_rendered_pages(): void
    {
        $email = 'alpha-login-' . bin2hex(random_bytes(4)) . '@example.test';

        User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('CorrectPassword123'),
            'status' => 'active',
            'is_approved' => true,
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
        $response->assertSee('class="govuk-tag govuk-tag--grey"', false);
        $response->assertSee('Alpha feed verification post');
    }

    public function test_feed_page_has_html_auth_required_state_when_unauthenticated(): void
    {
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
        $response->assertSee('class="govuk-tag govuk-tag--blue"', false);
        $response->assertSee('Alpha listing verification');
        $response->assertSee(__('govuk_alpha.actions.view_details'));
        $response->assertDontSee('Other tenant alpha listing');
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
        $response->assertSee("/{$this->testTenantSlug}/profile/", false);
        $response->assertDontSee('Other Tenant Member');
        $response->assertSee('AGPL-3.0-or-later');
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
}
