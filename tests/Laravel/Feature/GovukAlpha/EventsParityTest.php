<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventCalendarService;
use App\Services\EventRecurrenceCapabilityService;
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

    /** @return array{root:int,occurrences:list<int>} */
    private function eventsParityCreateSeries(User $organizer, int $count = 3): array
    {
        config()->set('events.recurrence.engine_v2_enabled', true);
        config()->set('events.recurrence.revisions.preview_ttl_seconds', 60);
        config()->set('events.recurrence.revisions.max_affected_occurrences', 1000);
        config()->set('events.notification_delivery.mode', 'outbox_authoritative');
        Sanctum::actingAs($organizer, ['*']);
        $start = CarbonImmutable::now('UTC')->addYear()->startOfDay()->setTime(9, 0);
        $response = $this->apiPost('/v2/events/recurring', [
            'title' => 'Accessible recurring fixture',
            'description' => 'Effective-dated accessible recurrence fixture.',
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $start->addHour()->format('Y-m-d H:i:s'),
            'timezone' => 'Europe/Dublin',
            'all_day' => false,
            'location' => 'Community hall',
            'is_online' => false,
            'allow_remote_attendance' => false,
            'federated_visibility' => 'none',
            'recurrence_frequency' => 'daily',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => $count,
        ])->assertCreated();
        $root = (int) $response->json('data.template.id');
        $occurrences = DB::table('events')
            ->where('tenant_id', $this->testTenantId)
            ->where('parent_event_id', $root)
            ->orderBy('recurrence_id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return ['root' => $root, 'occurrences' => $occurrences];
    }

    /** @return array<string,mixed> */
    private function eventsParityV2Capabilities(int $maxOccurrences = 366): array
    {
        return [
            'contract_version' => 1,
            'engine' => 'v2',
            'structured_input' => true,
            'supported_frequencies' => ['daily', 'weekly', 'monthly', 'yearly'],
            'max_occurrences' => $maxOccurrences,
            'supported_end_types' => ['after_count', 'on_date', 'never'],
            'supports_rolling_never' => true,
            'supports_effective_revisions' => true,
            'supports_definition_blueprints' => false,
            'schema_ready' => true,
            'rollout_state' => 'v2_rolling',
        ];
    }

    private function eventsParityMockV2Capabilities(int $maxOccurrences = 366): void
    {
        $capabilities = $this->eventsParityV2Capabilities($maxOccurrences);
        $this->mock(EventRecurrenceCapabilityService::class, static function ($mock) use ($capabilities): void {
            $mock->shouldReceive('capabilities')->andReturn($capabilities);
        });
    }

    private function eventsParityMockBlueprintCapabilities(): void
    {
        $capabilities = array_merge($this->eventsParityV2Capabilities(), [
            'supports_definition_blueprints' => true,
        ]);
        $this->mock(EventRecurrenceCapabilityService::class, static function ($mock) use ($capabilities): void {
            $mock->shouldReceive('capabilities')->andReturn($capabilities);
        });
    }

    private function eventsParityHiddenValue(TestResponse $response, string $name): string
    {
        $pattern = '/name="' . preg_quote($name, '/') . '" value="([^"]*)"/';
        self::assertSame(1, preg_match($pattern, (string) $response->getContent(), $matches));

        return html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
    //  Runtime recurrence capability negotiation
    // ================================================================

    public function test_accessible_create_uses_legacy_runtime_limit_and_hides_rolling_never(): void
    {
        config()->set('events.recurrence.engine_v2_enabled', false);
        $this->eventsParityUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/events/new");

        $response->assertOk()
            ->assertSee('max="52"', false)
            ->assertSee(__('govuk_alpha.events.polish_events.recurrence_count_hint', ['max' => 52]))
            ->assertDontSee('value="never"', false);
    }

    public function test_accessible_create_rejects_recurrence_values_outside_runtime_contract(): void
    {
        config()->set('events.recurrence.engine_v2_enabled', false);
        $this->eventsParityUser();
        $base = [
            'is_recurring' => '1',
            'recurrence_frequency' => 'weekly',
            'recurrence_ends_type' => 'after_count',
            'recurrence_ends_after_count' => '53',
        ];

        $this->post("/{$this->testTenantSlug}/accessible/events/new", $base)
            ->assertSessionHasErrors('recurrence_ends_after_count');
        $this->post("/{$this->testTenantSlug}/accessible/events/new", [
            ...$base,
            'recurrence_ends_type' => 'never',
        ])->assertSessionHasErrors('recurrence_ends_type');
    }

    public function test_accessible_create_renders_rolling_capabilities_without_javascript(): void
    {
        $this->eventsParityMockV2Capabilities(366);
        $this->eventsParityUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/events/new");

        $response->assertOk()
            ->assertSee('max="366"', false)
            ->assertSee(__('govuk_alpha.events.polish_events.recurrence_count_hint', ['max' => 366]))
            ->assertSee('value="never"', false);
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
        config()->set('events.recurrence.engine_v2_enabled', false);
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.title'));
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.scope_single'));
        $resp->assertDontSee('value="all"', false);
        $resp->assertSee(__('govuk_alpha_events.recurring_edit.unavailable'));
    }

    public function test_events_recurring_edit_exposes_effective_scope_only_when_runtime_supports_it(): void
    {
        $this->eventsParityMockV2Capabilities();
        $owner = $this->eventsParityUser();
        $eventId = $this->eventsParitySeedEvent($owner->id, ['is_recurring_template' => 1]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit");

        $response->assertOk()
            ->assertSee(__('govuk_alpha_events.recurring_edit.scope_all'))
            ->assertSee('value="all"', false);
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
        $series = $this->eventsParityCreateSeries($owner, 2);
        $eventId = $series['occurrences'][0];
        $event = DB::table('events')->where('id', $eventId)->first();
        self::assertNotNull($event);
        $start = CarbonImmutable::parse((string) $event->start_time, 'UTC')->setTimezone('Europe/Dublin');

        $resp = $this->post("/{$this->testTenantSlug}/accessible/events/{$eventId}/recurring-edit", [
            'title' => 'Updated recurring title',
            'description' => 'Updated recurring description.',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $start->addHour()->format('Y-m-d\TH:i'),
            'timezone' => 'Europe/Dublin',
            'scope' => 'single',
        ]);

        $resp->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}?status=event-updated");
        $this->assertSame('Updated recurring title', DB::table('events')->where('id', $eventId)->value('title'));
        $stored = DB::table('events')->where('id', $eventId)->first();
        self::assertSame($series['root'], (int) $stored->parent_event_id);
        self::assertSame(1, (int) $stored->is_recurrence_exception);
        self::assertContains('title', json_decode((string) $stored->recurrence_override_fields, true));
    }

    public function test_accessible_effective_preview_is_non_mutating_and_commit_preserves_every_submitted_field(): void
    {
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 3);
        $this->eventsParityMockV2Capabilities();
        $selectedId = $series['occurrences'][1];
        DB::table('events')->whereIn('id', array_slice($series['occurrences'], 1))
            ->update(['accessibility_hearing_loop' => 1]);
        $selected = DB::table('events')->where('id', $selectedId)->first();
        self::assertNotNull($selected);
        $start = CarbonImmutable::parse((string) $selected->start_time, 'UTC')->setTimezone('Europe/Dublin');
        $end = CarbonImmutable::parse((string) $selected->end_time, 'UTC')->setTimezone('Europe/Dublin');

        $preview = $this->post("/{$this->testTenantSlug}/accessible/events/{$selectedId}/recurring-edit", [
            'title' => 'Accessible effective title',
            'description' => 'Effective-dated accessible recurrence fixture.',
            'location' => 'Accessible community centre',
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $end->format('Y-m-d\TH:i'),
            'timezone' => 'Europe/Dublin',
            'is_online' => '1',
            'online_link' => 'https://events.example.test/join',
            'allow_remote_attendance' => '1',
            'video_url' => 'https://video.example.test/room',
            'max_attendees' => '44',
            'accessibility_step_free' => 'yes',
            'accessibility_toilet' => 'no',
            'accessibility_hearing_loop' => 'unknown',
            'accessibility_quiet_space' => 'yes',
            'accessibility_seating' => 'yes',
            'accessibility_parking' => 'no',
            'accessibility_parking_details' => 'Drop-off beside the main entrance.',
            'accessibility_transit_details' => 'Bus stop 20 metres from the entrance.',
            'accessibility_assistance_contact' => 'access@example.test',
            'accessibility_notes' => 'Ask the organiser for a quiet arrival.',
            'scope' => 'all',
        ]);

        $preview->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertSee(__('govuk_alpha_events.recurring_edit.confirm_title'));
        self::assertStringContainsString('private', (string) $preview->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));
        $patchJson = $this->eventsParityHiddenValue($preview, 'patch_json');
        $patch = json_decode($patchJson, true, 32, JSON_THROW_ON_ERROR);
        self::assertSame('Accessible effective title', $patch['title']);
        self::assertSame(44, $patch['max_attendees']);
        self::assertTrue($patch['is_online']);
        self::assertTrue($patch['allow_remote_attendance']);
        self::assertTrue($patch['accessibility_step_free']);
        self::assertFalse($patch['accessibility_toilet']);
        self::assertNull($patch['accessibility_hearing_loop']);
        self::assertSame('Ask the organiser for a quiet arrival.', $patch['accessibility_notes']);
        self::assertSame('Accessible recurring fixture', DB::table('events')->where('id', $selectedId)->value('title'));
        self::assertNull(DB::table('events')->where('id', $selectedId)->value('accessibility_notes'));

        $commit = $this->post("/{$this->testTenantSlug}/accessible/events/{$selectedId}/recurring-edit/commit", [
            'preview_token' => $this->eventsParityHiddenValue($preview, 'preview_token'),
            'patch_json' => $patchJson,
            'idempotency_key' => $this->eventsParityHiddenValue($preview, 'idempotency_key'),
        ]);

        $commit->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$selectedId}?status=event-updated");
        self::assertSame('Accessible recurring fixture', DB::table('events')
            ->where('id', $series['occurrences'][0])->value('title'));
        foreach (array_slice($series['occurrences'], 1) as $eventId) {
            $row = DB::table('events')->where('id', $eventId)->first();
            self::assertSame('Accessible effective title', $row->title);
            self::assertSame(44, (int) $row->max_attendees);
            self::assertSame(1, (int) $row->is_online);
            self::assertSame(1, (int) $row->allow_remote_attendance);
            self::assertSame(1, (int) $row->accessibility_step_free);
            self::assertSame(0, (int) $row->accessibility_toilet);
            self::assertNull($row->accessibility_hearing_loop);
            self::assertSame('Ask the organiser for a quiet arrival.', $row->accessibility_notes);
        }
    }

    public function test_accessible_effective_scope_rejects_date_moves_before_preview(): void
    {
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 2);
        $this->eventsParityMockV2Capabilities();
        $selectedId = $series['occurrences'][0];
        $selected = DB::table('events')->where('id', $selectedId)->first();
        self::assertNotNull($selected);
        $start = CarbonImmutable::parse((string) $selected->start_time, 'UTC')->setTimezone('Europe/Dublin');
        $end = CarbonImmutable::parse((string) $selected->end_time, 'UTC')->setTimezone('Europe/Dublin');

        $response = $this->post("/{$this->testTenantSlug}/accessible/events/{$selectedId}/recurring-edit", [
            'title' => 'Moved series',
            'description' => 'Effective-dated accessible recurrence fixture.',
            'location' => 'Community hall',
            'start_time' => $start->addDay()->format('Y-m-d\TH:i'),
            'end_time' => $end->addDay()->format('Y-m-d\TH:i'),
            'timezone' => 'Europe/Dublin',
            'scope' => 'all',
        ]);

        $response->assertRedirect(route('govuk-alpha.events.recurring.edit', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $selectedId,
        ]))->assertSessionHasErrors('scope');
        self::assertSame('Accessible recurring fixture', DB::table('events')->where('id', $selectedId)->value('title'));
    }

    public function test_recurrence_definition_manager_entry_requires_concrete_identity_and_capability(): void
    {
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 2);
        $occurrenceId = $series['occurrences'][0];

        $this->eventsParityMockBlueprintCapabilities();
        $this->get("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}")
            ->assertOk()
            ->assertSee(__('event_recurrence_blueprints.tab'))
            ->assertSee(route('govuk-alpha.events.recurrence-definitions.index', [
                'tenantSlug' => $this->testTenantSlug,
                'id' => $occurrenceId,
            ]), false);
        $this->get("/{$this->testTenantSlug}/accessible/events/{$series['root']}/recurrence-definition-blueprints")
            ->assertNotFound();
    }

    public function test_recurrence_definition_manager_fails_closed_when_capability_is_off(): void
    {
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 2);
        $this->eventsParityMockV2Capabilities();

        $this->get("/{$this->testTenantSlug}/accessible/events/{$series['occurrences'][0]}/recurrence-definition-blueprints")
            ->assertNotFound();
    }

    public function test_accessible_recurrence_definition_preview_and_idempotent_commit_are_private_and_explicit(): void
    {
        config()->set('events.recurrence.materialization.enabled', true);
        config()->set('events.recurrence.definition_blueprints.enabled', true);
        $this->eventsParityMockBlueprintCapabilities();
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 2);
        $occurrenceId = $series['occurrences'][0];

        $manager = $this->get("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints");
        $manager->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertSee(__('event_recurrence_blueprints.definition_only_description'))
            ->assertSee('name="sections[]"', false)
            ->assertDontSee('participant_email', false)
            ->assertDontSee('delivery_history', false);
        self::assertStringContainsString('private', (string) $manager->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $manager->headers->get('Cache-Control'));

        $preview = $this->post("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints/preview", [
            'sections' => ['safety'],
        ]);
        $preview->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertSee(__('event_recurrence_blueprints.preview_title'))
            ->assertSee('name="confirm_definition_version"', false)
            ->assertDontSee('manifest_hash', false)
            ->assertDontSee('captured_by_user_id', false);
        $this->assertDatabaseCount('event_recurrence_definition_blueprints', 0);
        $token = $this->eventsParityHiddenValue($preview, 'preview_token');
        $idempotencyKey = $this->eventsParityHiddenValue($preview, 'idempotency_key');

        $commitPayload = [
            'sections' => ['safety'],
            'preview_token' => $token,
            'idempotency_key' => $idempotencyKey,
            'confirm_definition_version' => '1',
        ];
        $commit = $this->post("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints/commit", $commitPayload);
        $commit->assertRedirect(route('govuk-alpha.events.recurrence-definitions.index', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $occurrenceId,
            'status' => 'created',
            'version' => 1,
        ]));
        $this->assertDatabaseCount('event_recurrence_definition_blueprints', 1);

        $replay = $this->post("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints/commit", $commitPayload);
        $replay->assertRedirect(route('govuk-alpha.events.recurrence-definitions.index', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $occurrenceId,
            'status' => 'replayed',
            'version' => 1,
        ]));
        $this->assertDatabaseCount('event_recurrence_definition_blueprints', 1);
        $storedSections = json_decode((string) DB::table('event_recurrence_definition_blueprints')
            ->value('selected_sections'), true, 32, JSON_THROW_ON_ERROR);
        $expectedSections = [
            'agenda' => false,
            'ticket_types' => false,
            'registration' => false,
            'safety' => true,
            'staff' => false,
        ];
        ksort($expectedSections);
        ksort($storedSections);
        self::assertSame($expectedSections, $storedSections);
    }

    public function test_accessible_recurrence_definition_history_uses_canonical_before_version_pagination(): void
    {
        $this->eventsParityMockBlueprintCapabilities();
        $owner = $this->eventsParityUser();
        $series = $this->eventsParityCreateSeries($owner, 2);
        $occurrenceId = $series['occurrences'][0];
        $source = DB::table('events')->where('id', $occurrenceId)->first();
        self::assertNotNull($source);
        $sections = json_encode([
            'agenda' => false,
            'ticket_types' => false,
            'registration' => false,
            'safety' => true,
            'staff' => false,
        ], JSON_THROW_ON_ERROR);
        $manifest = json_encode([
            'schema_version' => 1,
            'definitions' => [
                'agenda' => [],
                'ticket_types' => [],
                'registration' => [],
                'safety' => [],
                'staff' => [],
            ],
        ], JSON_THROW_ON_ERROR);
        for ($version = 1; $version <= 11; $version++) {
            DB::table('event_recurrence_definition_blueprints')->insert([
                'tenant_id' => $this->testTenantId,
                'root_event_id' => $series['root'],
                'source_event_id' => $occurrenceId,
                'source_recurrence_id' => (string) $source->recurrence_id,
                'source_occurrence_key' => (string) $source->occurrence_key,
                'blueprint_version' => $version,
                'schema_version' => 1,
                'effective_from_recurrence_id' => (string) $source->recurrence_id,
                'selected_sections' => $sections,
                'manifest' => $manifest,
                'manifest_hash' => hash('sha256', "manifest-{$version}"),
                'idempotency_hash' => hash('sha256', "idempotency-{$version}"),
                'request_hash' => hash('sha256', "request-{$version}"),
                'captured_by_user_id' => $owner->id,
                'created_at' => now()->addSeconds($version),
            ]);
        }

        $historyHeading = static fn (int $version): string => sprintf(
            '<h3 class="govuk-summary-card__title">%s</h3>',
            e(__('event_recurrence_blueprints.history_version', ['version' => $version])),
        );
        $first = $this->get("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints");
        $first->assertOk()
            ->assertSee($historyHeading(11), false)
            ->assertSee($historyHeading(2), false)
            ->assertDontSee($historyHeading(1), false)
            ->assertSee('before_version=2', false);

        $second = $this->get("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints?before_version=2");
        $second->assertOk()
            ->assertSee($historyHeading(1), false)
            ->assertDontSee('rel="next"', false);
        $this->get("/{$this->testTenantSlug}/accessible/events/{$occurrenceId}/recurrence-definition-blueprints?before_version=02")
            ->assertStatus(422);
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
