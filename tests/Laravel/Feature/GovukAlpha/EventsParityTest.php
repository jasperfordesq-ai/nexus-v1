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
 * Accessible (GOV.UK) frontend — Events parity coverage.
 *
 * Covers the React-parity gaps added by the EventsParity trait:
 *   - Category toggle-button browse.
 *   - Accessible location map / directions (Maps feature gate).
 *   - Recurring-series occurrence edit with "this / all future" scope.
 *   - Attach / detach polls to an owned event.
 *   - On-demand description translation.
 *
 * Extends the same base as GovukAlphaFrontendTest so it inherits the tenant
 * setup, superglobal scrubbing and cache flush. Private helpers are re-declared
 * here (PHP cannot call a parent's private methods).
 */
class EventsParityTest extends GovukAlphaFrontendTest
{
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
        $resp = $this->get("/{$this->testTenantSlug}/accessible/events/browse");

        $resp->assertOk();
        $resp->assertSee(__('govuk_alpha_events.browse.title'));
        $resp->assertSee(__('govuk_alpha_events.browse.all_categories'));
        // The form posts back into the existing events list.
        $resp->assertSee(route('govuk-alpha.events.index', ['tenantSlug' => $this->testTenantSlug]), false);
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
}
