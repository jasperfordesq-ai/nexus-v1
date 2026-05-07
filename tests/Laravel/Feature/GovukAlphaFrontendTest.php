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
        $response->assertSee('name="mode"', false);
        $response->assertSee('name="subtype"', false);
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
        $index->assertSee('Alpha event verification');
        $index->assertSee(route('govuk-alpha.events.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $eventId]), false);

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

    public function test_volunteering_pages_render_opportunity_detail_and_application_flow(): void
    {
        $user = $this->authenticatedUser();
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
            'created_by' => $user->id,
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
        $response->assertSee('Alpha Profile');
        $response->assertSee('Useful neighbour');
        $response->assertSee('Accessible member profile biography.');
        $response->assertSee(__('govuk_alpha.profile.skills_title'));
        $response->assertSee(__('govuk_alpha.profile.activity_title'));
        $response->assertSee('class="govuk-summary-list"', false);
        $response->assertSee('AGPL-3.0-or-later');
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

        $profile->assertOk();
        $profile->assertSee(__('govuk_alpha.nav.profile'));
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
