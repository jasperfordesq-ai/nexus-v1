<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventCalendarService;
use App\Services\TenantSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — Events parity coverage.
 *
 * Covers the React-parity gaps added by the EventsParity trait:
 *   - Category toggle-button browse.
 *   - Accessible location map / directions (Maps feature gate).
 *   - Recurring-series occurrence edit with "this / all future" scope.
 *   - Attach / detach polls to an owned event.
 *   - On-demand description translation.
 *   - Privacy-safe calendar exports and owner-scoped personal subscriptions.
 *
 * Uses the Laravel feature base directly so this focused file does not inherit
 * the entire accessible-frontend regression suite. The request-state setup and
 * CSRF-aware POST helper are intentionally kept in this class.
 */
class EventsParityTest extends TestCase
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

    public function post($uri, array $data = [], array $headers = []): TestResponse
    {
        if (is_string($uri) && str_contains($uri, '/accessible')) {
            $token = (string) ($data['_token'] ?? 'govuk-alpha-events-test-token');
            $data['_token'] = $token;
            $this->withSession(['_token' => $token]);
        }

        return parent::post($uri, $data, $headers);
    }

    // -----------------------------------------------------------------
    //  Local helpers (private in the parent — re-declared here)
    // -----------------------------------------------------------------

    private function eventsParityUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function eventsParityEnableMaps(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['events'] = true;
        $current['maps'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function eventsCalendarToken(
        User $owner,
        string $secret,
        ?string $label = 'Accessible calendar',
    ): int {
        return (int) DB::table('event_calendar_feed_tokens')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $owner->getKey(),
            'token_hash' => hash('sha256', $secret),
            'token_prefix' => substr($secret, 0, 12),
            'label' => $label,
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function eventsParitySeedEvent(int $ownerId, array $overrides = []): int
    {
        return DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Parity event',
            'description' => 'An event for the parity build.',
            'location' => 'Parity Hall',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(2),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function eventsParitySeedPoll(int $ownerId, array $overrides = []): int
    {
        // NOTE: the `polls` table has no `updated_at` column.
        return DB::table('polls')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'question' => 'What time suits you best?',
            'created_at' => now(),
        ], $overrides));
    }

    // ================================================================
    //  Category toggle-button browse
    // ================================================================

    public function test_events_browse_page_renders(): void
    {
        $this->eventsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/browse");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.browse.title'));
        $resp->assertSee(__('govuk_alpha_events.browse.all_categories'));
        // The form posts back into the existing events list.
        $resp->assertSee(route('govuk-alpha.events.index', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_events_index_filters_step_free_venues_without_javascript(): void
    {
        $owner = $this->eventsParityUser();
        $this->eventsParitySeedEvent($owner->id, [
            'title' => 'Step-free community hall',
            'accessibility_step_free' => true,
        ]);
        $this->eventsParitySeedEvent($owner->id, [
            'title' => 'Venue with entrance steps',
            'accessibility_step_free' => false,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/events?step_free=yes");

        $response->assertOk()
            ->assertSee('name="step_free"', false)
            ->assertSee('value="yes" selected', false)
            ->assertSee('Step-free community hall')
            ->assertDontSee('Venue with entrance steps');
    }

    // ================================================================
    //  Accessible location map
    // ================================================================

    public function test_events_map_requires_maps_feature(): void
    {
        // Maps defaults OFF — without it the page is forbidden even with events on.
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['events'] = true;
        $current['maps'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, ['latitude' => 53.349805, 'longitude' => -6.26031]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/map");

        $resp->assertForbidden();
    }

    public function test_events_map_renders_with_coordinates(): void
    {
        $this->eventsParityEnableMaps();
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, [
            'latitude' => 53.349805,
            'longitude' => -6.26031,
            'is_online' => 0,
        ]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/map");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.map.title'));
        $resp->assertSee(__('govuk_alpha_events.map.view_on_map_link'));
        $resp->assertSee('openstreetmap.org', false);
    }

    public function test_events_map_unknown_event_returns_404(): void
    {
        $this->eventsParityEnableMaps();
        $this->eventsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/99999999/map");

        $resp->assertNotFound();
    }

    // ================================================================
    //  Shared lifecycle boundary
    // ================================================================

    public function test_accessible_cancel_requires_reason_and_uses_canonical_lifecycle(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent((int) $owner->id, [
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
        ]);

        $missingReason = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/cancel", [
            'reason' => '   ',
        ]);
        $missingReason->assertRedirect(route('govuk-alpha.events.show', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $eventId,
            'status' => 'event-cancel-failed',
        ]));
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
        ]);

        $cancelled = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/cancel", [
            'reason' => 'Venue unavailable',
        ]);
        $cancelled->assertRedirect(route('govuk-alpha.events.show', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $eventId,
            'status' => 'event-cancelled',
        ]));
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'publication_status' => 'published',
            'operational_status' => 'cancelled',
            'lifecycle_version' => 1,
            'cancellation_reason' => 'Venue unavailable',
        ]);
        $this->assertDatabaseHas('event_status_history', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'lifecycle_version' => 1,
            'to_operational_status' => 'cancelled',
        ]);
        $this->assertSame(1, DB::table('event_domain_outbox')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->where('action', 'event.lifecycle.transitioned')
            ->count());
    }

    public function test_accessible_delete_is_archive_first_and_preserves_attendance(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent((int) $owner->id, [
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
        ]);
        DB::table('event_attendance')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $owner->id,
            'checked_in_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $detail = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");
        $detail->assertOk()
            ->assertSee(__('govuk_alpha.events.archive_event'))
            ->assertDontSee('Delete this event')
            ->assertSee('name="idempotency_key"', false);

        $response = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/delete", [
            'reason' => 'Programme complete',
            'idempotency_key' => 'accessible-archive-1',
        ]);

        $response->assertRedirect(route('govuk-alpha.events.index', [
            'tenantSlug' => $this->testTenantSlug,
            'status' => 'event-archived',
        ]));
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'tenant_id' => $this->testTenantId,
            'publication_status' => 'archived',
            'operational_status' => 'cancelled',
            'lifecycle_version' => 1,
            'lifecycle_reason' => 'Programme complete',
        ]);
        $this->assertDatabaseHas('event_attendance', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $owner->id,
        ]);
        $this->assertSame(1, DB::table('event_status_history')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
        $this->assertSame(1, DB::table('event_domain_outbox')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $eventId)
            ->count());
    }

    // ================================================================
    //  Recurring-series occurrence edit with scope
    // ================================================================

    public function test_events_recurring_edit_requires_login(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_events_recurring_edit_forbids_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        // A different, authenticated member is not the organiser.
        $this->eventsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $resp->assertForbidden();
    }

    public function test_events_recurring_edit_renders_for_owner(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.title'));
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.scope_single'));
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.scope_all'));
    }

    public function test_events_recurring_edit_redirects_non_series_to_plain_edit(): void
    {
        $owner = $this->eventsParityUser();
        // Plain (non-recurring) event.
        $eventId = $this->eventsParitySeedEvent($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $resp->assertRedirect(route('govuk-alpha.events.edit', ['tenantSlug' => $this->testTenantSlug, 'id' => $eventId]));
    }

    public function test_events_recurring_update_single_scope_persists(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit", [
            'title' => 'Updated recurring title',
            'description' => 'Updated recurring description.',
            'start_time' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'scope' => 'single',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}?status=event-updated");
        $this->assertSame('Updated recurring title', DB::table('events')->where('id', $eventId)->value('title'));
        // scope=single detaches the occurrence from its parent.
        $this->assertNull(DB::table('events')->where('id', $eventId)->value('parent_event_id'));
    }

    // ================================================================
    //  Attach / detach polls
    // ================================================================

    public function test_events_polls_page_requires_login(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $eventId = $this->eventsParitySeedEvent($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_events_polls_page_forbids_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $eventId = $this->eventsParitySeedEvent($owner->id);

        $this->eventsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls");

        $resp->assertForbidden();
    }

    public function test_events_polls_page_renders_owner_polls(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id);
        $this->eventsParitySeedPoll($owner->id, ['question' => 'Morning or afternoon?']);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.polls.title'));
        $resp->assertSee('Morning or afternoon?');
    }

    public function test_events_polls_update_attaches_and_detaches(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id);
        $pollId = $this->eventsParitySeedPoll($owner->id);

        // Attach.
        $attach = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls", [
            'poll_ids' => [$pollId],
        ]);
        $attach->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls?status=polls-updated");
        $this->assertSame($eventId, (int) DB::table('polls')->where('id', $pollId)->value('event_id'));

        // Detach (empty selection).
        $detach = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls", []);
        $detach->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls?status=polls-updated");
        $this->assertNull(DB::table('polls')->where('id', $pollId)->value('event_id'));
    }

    public function test_events_polls_update_rejects_poll_not_owned(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id);

        // A poll owned by someone else must not be attachable.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $foreignPoll = $this->eventsParitySeedPoll($other->id);

        $resp = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls", [
            'poll_ids' => [$foreignPoll],
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}/polls?status=polls-failed");
        $this->assertNull(DB::table('polls')->where('id', $foreignPoll)->value('event_id'));
    }

    // ================================================================
    //  On-demand description translation
    // ================================================================

    public function test_events_translate_page_requires_login(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $eventId = $this->eventsParitySeedEvent($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/translate");

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_events_translate_page_renders_language_chooser(): void
    {
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/translate");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.translate.title'));
        $resp->assertSee(__('govuk_alpha_events.translate.language_label'));
        $resp->assertSee('name="target_locale"', false);
    }

    public function test_events_translate_unknown_event_returns_404(): void
    {
        $this->eventsParityUser();

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/99999999/translate");

        $resp->assertNotFound();
    }

    // ================================================================
    //  Canonical Events v2 accessible projection
    // ================================================================

    public function test_accessible_calendar_uses_shared_tenant_zone_day_boundaries(): void
    {
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.timezone',
            'America/New_York',
        );
        config()->set('events.calendar.tenant_feed_past_days', 0);
        config()->set('events.calendar.tenant_feed_future_days', 1);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2032-01-01 00:30:00 UTC'));

        try {
            [$from, $until, $timezone] = app(EventCalendarService::class)->tenantFeedRange();

            $this->assertSame('America/New_York', $timezone);
            $this->assertSame('2031-12-31T00:00:00-05:00', $from->format('c'));
            $this->assertSame('2032-01-01T00:00:00-05:00', $until->format('c'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_calendar_subscriptions_require_authentication_and_events_feature(): void
    {
        $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions")
            ->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");

        $this->eventsParityUser();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => false], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions")
            ->assertForbidden();
    }

    public function test_calendar_subscription_management_is_owner_scoped_and_no_store(): void
    {
        $otherOwner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $viewer = $this->eventsParityUser();
        $viewerSecret = 'nxc_' . str_repeat('a', 64);
        $otherSecret = 'nxc_' . str_repeat('b', 64);
        $viewerTokenId = $this->eventsCalendarToken($viewer, $viewerSecret, 'Screen reader calendar');
        $otherTokenId = $this->eventsCalendarToken($otherOwner, $otherSecret, 'Other member calendar');

        $response = $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions")
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Screen reader calendar')
            ->assertDontSee('Other member calendar')
            ->assertDontSee(hash('sha256', $viewerSecret))
            ->assertSee('name="_token"', false);
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions/{$viewerTokenId}/revoke")
            ->assertOk()
            ->assertSee(substr($viewerSecret, 0, 12));
        $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions/{$otherTokenId}/revoke")
            ->assertNotFound();
        $this->post("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions/{$otherTokenId}/revoke", [
            'confirm_revoke' => 'yes',
        ])->assertNotFound();
        $this->assertNull(DB::table('event_calendar_feed_tokens')
            ->where('id', $otherTokenId)
            ->value('revoked_at'));
    }

    public function test_calendar_subscription_create_displays_secret_once_and_hashes_storage(): void
    {
        $viewer = $this->eventsParityUser();

        $created = $this->post("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions", [
            'label' => 'Accessible phone calendar',
        ])->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeaderMissing('Location')
            ->assertSee(__('govuk_alpha.events.calendar_subscription_created'))
            ->assertSee('data-module="govuk-notification-banner"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('readonly', false);
        $cacheControl = (string) $created->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        preg_match('/nxc_[a-f0-9]{64}/', html_entity_decode((string) $created->getContent()), $matches);
        $secret = (string) ($matches[0] ?? '');
        $this->assertNotSame('', $secret);
        $this->assertSame(1, substr_count((string) $created->getContent(), $secret));
        $stored = DB::table('event_calendar_feed_tokens')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', (int) $viewer->getKey())
            ->where('label', 'Accessible phone calendar')
            ->first();
        $this->assertNotNull($stored);
        $this->assertSame(hash('sha256', $secret), $stored->token_hash);
        $this->assertSame(substr($secret, 0, 12), $stored->token_prefix);
        $this->assertStringNotContainsString($secret, json_encode($stored, JSON_THROW_ON_ERROR));
        $created->assertDontSee(hash('sha256', $secret));

        $this->get("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions")
            ->assertOk()
            ->assertDontSee($secret)
            ->assertSee(substr($secret, 0, 12));
    }

    public function test_calendar_subscription_create_validates_label_and_active_limit(): void
    {
        $viewer = $this->eventsParityUser();
        config()->set('events.calendar.max_active_feed_tokens', 1);

        $invalid = $this->post("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions", [
            'label' => str_repeat('x', 101),
        ])->assertUnprocessable()
            ->assertSee(__('govuk_alpha.events.calendar_subscription_label_invalid'))
            ->assertSee('govuk-error-summary', false)
            ->assertSee('data-module="govuk-error-summary"', false)
            ->assertSee('tabindex="-1"', false);
        $invalid->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(0, DB::table('event_calendar_feed_tokens')
            ->where('user_id', (int) $viewer->getKey())
            ->count());

        $this->eventsCalendarToken($viewer, 'nxc_' . str_repeat('c', 64));
        $this->post("/{$this->testTenantSlug}/accessible/events/calendar-subscriptions", [
            'label' => 'One too many',
        ])->assertStatus(409)
            ->assertSee(__('govuk_alpha.events.calendar_subscription_limit'));
        $this->assertSame(1, DB::table('event_calendar_feed_tokens')
            ->where('user_id', (int) $viewer->getKey())
            ->count());
    }

    public function test_calendar_subscription_revoke_requires_confirmation_and_is_immediate(): void
    {
        $viewer = $this->eventsParityUser();
        $secret = 'nxc_' . str_repeat('d', 64);
        $tokenId = $this->eventsCalendarToken($viewer, $secret, 'Tablet calendar');
        $confirmationPath = "/{$this->testTenantSlug}/accessible/events/calendar-subscriptions/{$tokenId}/revoke";

        $this->get($confirmationPath)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Tablet calendar')
            ->assertSee(substr($secret, 0, 12))
            ->assertSee(__('govuk_alpha.events.calendar_subscription_revoke_consequence'))
            ->assertSee('name="confirm_revoke"', false)
            ->assertSee('name="_token"', false);

        $this->post($confirmationPath)
            ->assertUnprocessable()
            ->assertSee(__('govuk_alpha.events.calendar_subscription_confirmation_required'))
            ->assertSee('data-module="govuk-error-summary"', false)
            ->assertSee('tabindex="-1"', false);
        $this->assertNull(DB::table('event_calendar_feed_tokens')
            ->where('id', $tokenId)
            ->value('revoked_at'));

        $this->get("/api/v2/events/calendar/personal/{$this->testTenantSlug}/{$secret}.ics")
            ->assertOk();
        $this->post($confirmationPath, ['confirm_revoke' => 'yes'])
            ->assertRedirect(route('govuk-alpha.events.calendar.subscriptions', [
                'tenantSlug' => $this->testTenantSlug,
                'status' => 'revoked',
            ]))
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNotNull(DB::table('event_calendar_feed_tokens')
            ->where('id', $tokenId)
            ->value('revoked_at'));
        $this->get(route('govuk-alpha.events.calendar.subscriptions', [
            'tenantSlug' => $this->testTenantSlug,
            'status' => 'revoked',
        ]))->assertOk()
            ->assertSee(__('govuk_alpha.events.calendar_subscription_revoked'))
            ->assertSee('data-module="govuk-notification-banner"', false)
            ->assertSee('tabindex="-1"', false);
        $this->get($confirmationPath)->assertNotFound();
        $this->get("/api/v2/events/calendar/personal/{$this->testTenantSlug}/{$secret}.ics")
            ->assertNotFound();
    }

    public function test_event_detail_uses_shared_contract_axes_identity_category_and_timezone(): void
    {
        config()->set('events.online_access.reveal_before_minutes', 30);
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.timezone',
            'Europe/Dublin'
        );

        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Actual',
            'last_name' => 'Organizer',
            'status' => 'active',
            'is_approved' => true,
        ]);
        $viewer = $this->eventsParityUser([
            'first_name' => 'Contract',
            'last_name' => 'Viewer',
        ]);
        $categoryId = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Repair workshops',
            'slug' => 'repair-workshops-' . uniqid(),
            'type' => 'event',
            'color' => '#2563eb',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $joinUrl = 'https://meet.example.test/accessible-contract-secret';
        $eventId = $this->eventsParitySeedEvent($organizer->id, [
            'category_id' => $categoryId,
            'is_online' => 1,
            'allow_remote_attendance' => 1,
            'online_link' => $joinUrl,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(4),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $viewer->id,
            'status' => 'interested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_waitlist')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $viewer->id,
            'position' => 3,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_attendance')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $viewer->id,
            'checked_in_at' => now()->subMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fixture = json_decode(
            (string) file_get_contents(base_path('contracts/events/v2/event-detail.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $response = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");

        $response->assertOk();
        $response->assertSee('data-events-contract-version="2"', false);
        $response->assertSee(
            'data-event-engagement-state="' . $fixture['relationship']['engagement']['state'] . '"',
            false
        );
        $response->assertSee(
            'data-event-registration-state="' . $fixture['relationship']['registration']['state'] . '"',
            false
        );
        $response->assertSee(
            'data-event-attendance-state="' . $fixture['relationship']['attendance']['state'] . '"',
            false
        );
        $response->assertSee('data-event-online-access-state="restricted"', false);
        $response->assertSee('data-event-timezone="Europe/Dublin"', false);
        $response->assertSee('Actual Organizer');
        $response->assertSee('Repair workshops');
        $response->assertDontSee($joinUrl, false);
    }

    public function test_event_detail_reveals_online_access_only_when_canonical_contract_allows_it(): void
    {
        config()->set('events.online_access.reveal_before_minutes', 30);
        config()->set('events.online_access.grace_after_minutes', 120);

        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $outsider = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $joinUrl = 'https://meet.example.test/reveal-window-secret';
        $eventId = $this->eventsParitySeedEvent($organizer->id, [
            'is_online' => 1,
            'online_link' => $joinUrl,
            'start_time' => now()->addMinutes(10),
            'end_time' => now()->addHours(1),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($outsider, ['*']);
        $restricted = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");
        $restricted->assertOk();
        $restricted->assertSee('data-event-online-access-state="restricted"', false);
        $restricted->assertDontSee($joinUrl, false);

        Sanctum::actingAs($attendee, ['*']);
        $available = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");
        $available->assertOk();
        $available->assertSee('data-event-registration-state="confirmed"', false);
        $available->assertSee('data-event-online-access-state="available"', false);
        $available->assertSee($joinUrl, false);

        Sanctum::actingAs($organizer, ['*']);
        $manager = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");
        $manager->assertOk();
        $manager->assertSee('data-event-online-access-state="available"', false);
        $manager->assertSee($joinUrl, false);
    }
}
