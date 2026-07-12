<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\EventRegistrationService;
use App\Services\EventWaitlistService;
use App\Services\GamificationService;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Laravel\TestCase;

final class AccessibleEventCanonicalMutationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.key', 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=');
        Config::set('events.notification_delivery.mode', 'direct');
        Config::set('events.registration.default_capacity_pool_key', 'event');
        Config::set('events.registration.legacy_dual_read', true);
        Config::set('events.registration.legacy_dual_write', true);
        Config::set('events.registration.timed_waitlist_offers_enabled', false);
        Config::set('event_waitlist.envelope.active_key_version', 'accessible-test-v1');
        Config::set('event_waitlist.envelope.active_key', null);
        Config::set('event_waitlist.envelope.fallback_to_app_key', true);
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_accessible_going_form_writes_one_canonical_history_outbox_and_xp_claim(): void
    {
        $organizer = $this->member('Accessible RSVP Organizer');
        $member = $this->member('Accessible RSVP Member', ['xp' => 0]);
        $eventId = $this->event((int) $organizer->id, 5);
        Sanctum::actingAs($member, ['*']);

        $uri = "/{$this->testTenantSlug}/accessible/events/{$eventId}/rsvp";
        $this->accessiblePost($uri, ['status' => 'going'])
            ->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}?status=rsvp-updated");
        $this->accessiblePost($uri, ['status' => 'going'])
            ->assertRedirect("/{$this->testTenantSlug}/accessible/events/{$eventId}?status=rsvp-updated");

        self::assertSame('confirmed', DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $member->id)
            ->value('registration_state'));
        self::assertSame(1, DB::table('event_registration_history')
            ->where('event_id', $eventId)
            ->where('user_id', $member->id)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->whereIn('action', ['event.registration.confirmed', 'event.registration.canonicalized'])
            ->count());
        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $member->id)
            ->value('status'));
        self::assertSame(1, DB::table('user_xp_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $member->id)
            ->where('action', 'attend_event')
            ->where('source_reference', 'event:' . $eventId)
            ->count());
        self::assertSame(
            GamificationService::XP_VALUES['attend_event'],
            (int) DB::table('users')->where('id', $member->id)->value('xp'),
        );
    }

    public function test_accessible_full_rsvp_and_waitlist_forms_use_canonical_queue_cycles(): void
    {
        $organizer = $this->member('Accessible Waitlist Organizer');
        $holder = $this->member('Accessible Capacity Holder');
        $waiter = $this->member('Accessible Waitlist Member');
        $eventId = $this->event((int) $organizer->id, 1);
        (new EventRegistrationService())->confirm(
            $eventId,
            (int) $holder->id,
            $holder,
            'accessible-fill-capacity',
        );
        Sanctum::actingAs($waiter, ['*']);

        $this->accessiblePost(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}/rsvp",
            ['status' => 'going'],
        )->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=waitlist-joined",
        );
        $joinUri = "/{$this->testTenantSlug}/accessible/events/{$eventId}/waitlist";
        $this->accessiblePost($joinUri)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=waitlist-joined",
        );
        self::assertSame(1, DB::table('event_waitlist_entry_history')
            ->where('event_id', $eventId)
            ->where('user_id', $waiter->id)
            ->where('action', 'joined')
            ->count());

        $leaveUri = "/{$this->testTenantSlug}/accessible/events/{$eventId}/waitlist/leave";
        $this->accessiblePost($leaveUri)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=waitlist-left",
        );
        $this->accessiblePost($leaveUri)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=waitlist-left",
        );
        self::assertSame('cancelled', DB::table('event_waitlist_entries')
            ->where('event_id', $eventId)
            ->where('user_id', $waiter->id)
            ->value('queue_state'));
        self::assertSame(1, DB::table('event_waitlist_entry_history')
            ->where('event_id', $eventId)
            ->where('user_id', $waiter->id)
            ->where('action', 'withdrawn')
            ->count());
        self::assertSame(2, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->whereIn('action', ['event.waitlist.joined', 'event.waitlist.withdrawn'])
            ->count());
    }

    public function test_accessible_member_can_accept_a_live_waitlist_offer_without_conflicting_rsvp_controls(): void
    {
        Config::set('events.notification_delivery.mode', 'outbox_authoritative');
        Config::set('events.notification_delivery.consumer_enabled', true);
        Config::set('events.notification_delivery.channels', ['in_app']);
        Config::set('events.registration.timed_waitlist_offers_enabled', false);

        $organizer = $this->member('Accessible Offer Organizer');
        $holder = $this->member('Accessible Offer Holder');
        $waiter = $this->member('Accessible Offer Waiter');
        $eventId = $this->event((int) $organizer->id, 1);
        $registrations = new EventRegistrationService();
        $waitlist = app(EventWaitlistService::class);
        $registrations->confirm($eventId, (int) $holder->id, $holder, 'accessible-offer-holder');
        $waitlist->join($eventId, (int) $waiter->id, $waiter, 'accessible-offer-waiter');
        $registrations->withdraw($eventId, (int) $holder->id, $holder, 'accessible-offer-release');
        Config::set('events.registration.timed_waitlist_offers_enabled', true);
        self::assertNotNull($waitlist->offerNext($eventId, $organizer, 'accessible-offer-create'));
        Sanctum::actingAs($waiter, ['*']);

        $detail = $this->get("/{$this->testTenantSlug}/accessible/events/{$eventId}");
        $detail->assertOk()
            ->assertSeeText('A place is available for you')
            ->assertSeeText('Accept place')
            ->assertSeeText('Decline place')
            ->assertDontSee('name="status"', false);

        $this->accessiblePost(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}/waitlist/accept",
            ['idempotency_key' => 'accessible-offer-accept'],
        )->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=waitlist-offer-accepted",
        );

        self::assertSame('confirmed', DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $waiter->id)
            ->value('registration_state'));
        self::assertSame('accepted', DB::table('event_waitlist_entries')
            ->where('event_id', $eventId)
            ->where('user_id', $waiter->id)
            ->value('queue_state'));
    }

    public function test_accessible_checkin_uses_canonical_attendance_ledger_idempotently(): void
    {
        $organizer = $this->member('Accessible Checkin Organizer');
        $attendee = $this->member('Accessible Checkin Attendee');
        $eventId = $this->event((int) $organizer->id, 5, [
            'start_time' => now()->subMinutes(10),
            'end_time' => now()->addHour(),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($organizer, ['*']);
        $uri = "/{$this->testTenantSlug}/accessible/events/{$eventId}/attendees/{$attendee->id}/check-in";

        $this->accessiblePost($uri)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=checkin-success",
        );
        $this->accessiblePost($uri)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=checkin-success",
        );
        self::assertSame(1, DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        self::assertSame(1, DB::table('event_attendance_activity')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());
        self::assertSame('attended', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
    }

    public function test_accessible_rsvp_maps_policy_unavailable_to_existing_banner(): void
    {
        $organizer = $this->member('Accessible Policy Organizer');
        $member = $this->member('Accessible Policy Member');
        $eventId = $this->event((int) $organizer->id, 5);
        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->andThrow(new SafeguardingPolicyException('SAFEGUARDING_POLICY_UNAVAILABLE'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);
        Sanctum::actingAs($member, ['*']);

        $this->accessiblePost(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}/rsvp",
            ['status' => 'going'],
        )->assertRedirect(
            "/{$this->testTenantSlug}/accessible/events/{$eventId}?status=rsvp-policy-unavailable",
        );
        self::assertFalse(DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $member->id)
            ->exists());
    }

    private function accessiblePost(string $uri, array $data = []): TestResponse
    {
        $token = 'accessible-event-canonical-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, array_merge(['_token' => $token], $data));
    }

    /** @param array<string,mixed> $overrides */
    private function member(string $name, array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    /** @param array<string,mixed> $overrides */
    private function event(int $organizerId, int $capacity, array $overrides = []): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Accessible canonical mutation fixture',
            'description' => 'Accessible form parity fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'accessible-canonical:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'max_attendees' => $capacity,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
