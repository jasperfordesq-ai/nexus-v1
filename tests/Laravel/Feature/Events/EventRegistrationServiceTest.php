<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Enums\EventCapacityRegistrationState;
use App\Exceptions\EventRegistrationException;
use App\Models\User;
use App\Services\EventRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventRegistrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('events.notification_delivery.mode', 'direct');
        Config::set('events.registration.default_capacity_pool_key', 'event');
        Config::set('events.registration.allow_allocation_keys', false);
        Config::set('events.registration.legacy_dual_read', true);
        Config::set('events.registration.legacy_dual_write', true);
        Config::set('events.registration.timed_waitlist_offers_enabled', false);
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'events.notification_delivery_mode')
            ->delete();
        $this->service = new EventRegistrationService();
    }

    public function test_confirmation_writes_one_capacity_fact_history_outbox_and_safe_legacy_projection(): void
    {
        $organizer = $this->member();
        $attendee = $this->member();
        $eventId = $this->event($organizer, ['max_attendees' => 2]);

        $first = $this->service->confirm(
            $eventId,
            (int) $attendee->id,
            $attendee,
            'registration-request-1',
        );
        $replay = $this->service->confirm(
            $eventId,
            (int) $attendee->id,
            $attendee,
            'registration-request-1',
        );

        self::assertTrue($first->changed);
        self::assertFalse($first->replayed);
        self::assertFalse($replay->changed);
        self::assertTrue($replay->replayed);
        self::assertSame(EventCapacityRegistrationState::Confirmed, $first->registration->registration_state);
        self::assertNotNull($first->registration->confirmed_at);
        self::assertSame(1, (int) $first->registration->registration_version);
        self::assertSame(1, DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        self::assertSame(1, DB::table('event_registration_history')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.registration.confirmed')
            ->count());
        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
    }

    public function test_interest_is_engagement_only_and_never_consumes_capacity(): void
    {
        $organizer = $this->member();
        $interested = $this->member();
        $attendee = $this->member();
        $eventId = $this->event($organizer, ['max_attendees' => 1]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $interested->id,
            'status' => 'interested',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertNull($this->service->stateFor($eventId, (int) $interested->id));
        $confirmed = $this->service->confirm(
            $eventId,
            (int) $attendee->id,
            $attendee,
            'interest-does-not-reserve',
        );

        self::assertTrue($confirmed->changed);
        self::assertSame('interested', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $interested->id)
            ->value('status'));
    }

    public function test_row_locked_capacity_rejects_a_second_confirmation(): void
    {
        $organizer = $this->member();
        $first = $this->member();
        $second = $this->member();
        $eventId = $this->event($organizer, ['max_attendees' => 1]);
        $this->service->confirm($eventId, (int) $first->id, $first, 'capacity-first');

        $this->assertRejected(
            'event_registration_capacity_full',
            fn () => $this->service->confirm(
                $eventId,
                (int) $second->id,
                $second,
                'capacity-second',
            ),
        );

        self::assertSame(1, DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('registration_state', 'confirmed')
            ->count());
    }

    public function test_stale_legacy_going_never_overrides_terminal_canonical_capacity(): void
    {
        $organizer = $this->member();
        foreach (['cancelled', 'declined'] as $terminalState) {
            $stale = $this->member();
            $next = $this->member();
            $eventId = $this->event($organizer, ['max_attendees' => 1]);
            if ($terminalState === 'cancelled') {
                $this->service->confirm(
                    $eventId,
                    (int) $stale->id,
                    $stale,
                    "stale-{$terminalState}-confirm",
                );
                $this->service->withdraw(
                    $eventId,
                    (int) $stale->id,
                    $stale,
                    "stale-{$terminalState}-transition",
                );
            } else {
                $this->service->transition(
                    $eventId,
                    (int) $stale->id,
                    EventCapacityRegistrationState::Invited,
                    $organizer,
                    'stale-declined-invite',
                );
                $this->service->transition(
                    $eventId,
                    (int) $stale->id,
                    EventCapacityRegistrationState::Declined,
                    $stale,
                    'stale-declined-transition',
                );
            }
            DB::table('event_rsvps')
                ->where('event_id', $eventId)
                ->where('user_id', $stale->id)
                ->update(['status' => 'going']);

            $confirmed = $this->service->confirm(
                $eventId,
                (int) $next->id,
                $next,
                "canonical-precedence-{$terminalState}",
            );
            self::assertTrue($confirmed->changed);
            self::assertSame(1, DB::table('event_registrations')
                ->where('event_id', $eventId)
                ->where('registration_state', 'confirmed')
                ->count());
        }
    }

    public function test_all_canonical_states_dual_write_only_supported_legacy_values(): void
    {
        $organizer = $this->member();
        $attendee = $this->member();
        $eventId = $this->event($organizer);

        $this->service->transition(
            $eventId,
            (int) $attendee->id,
            EventCapacityRegistrationState::Invited,
            $organizer,
            'invite',
        );
        self::assertSame('invited', $this->legacyStatus($eventId, (int) $attendee->id));
        $this->service->transition(
            $eventId,
            (int) $attendee->id,
            EventCapacityRegistrationState::Pending,
            $attendee,
            'pending',
        );
        self::assertSame('invited', $this->legacyStatus($eventId, (int) $attendee->id));
        $this->service->confirm($eventId, (int) $attendee->id, $attendee, 'confirm');
        self::assertSame('going', $this->legacyStatus($eventId, (int) $attendee->id));
        $this->service->withdraw($eventId, (int) $attendee->id, $attendee, 'withdraw');
        self::assertSame('cancelled', $this->legacyStatus($eventId, (int) $attendee->id));
        $this->service->transition(
            $eventId,
            (int) $attendee->id,
            EventCapacityRegistrationState::Invited,
            $organizer,
            'reinvite',
        );
        $this->service->transition(
            $eventId,
            (int) $attendee->id,
            EventCapacityRegistrationState::Declined,
            $attendee,
            'decline',
        );

        self::assertSame('declined', $this->legacyStatus($eventId, (int) $attendee->id));
        self::assertSame(6, DB::table('event_registration_history')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        self::assertSame(6, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'like', 'event.registration.%')
            ->count());
    }

    public function test_draft_template_past_and_cancelled_events_are_ineligible_without_writes(): void
    {
        $organizer = $this->member();
        $attendee = $this->member();
        $cases = [
            'draft' => [
                ['publication_status' => 'draft', 'status' => 'draft'],
                'event_registration_event_unavailable',
            ],
            'template' => [
                ['is_recurring_template' => 1, 'occurrence_key' => null],
                'event_registration_concrete_occurrence_required',
            ],
            'past' => [
                ['start_time' => now()->subHours(2), 'end_time' => now()->subHour()],
                'event_registration_event_started',
            ],
            'cancelled' => [
                ['operational_status' => 'cancelled', 'status' => 'cancelled'],
                'event_registration_event_unavailable',
            ],
        ];

        $eventIds = [];
        foreach ($cases as $name => [$overrides, $reason]) {
            $eventId = $this->event($organizer, $overrides);
            $eventIds[] = $eventId;
            $this->assertRejected(
                $reason,
                fn () => $this->service->confirm(
                    $eventId,
                    (int) $attendee->id,
                    $attendee,
                    "ineligible-{$name}",
                ),
            );
        }

        self::assertSame(0, DB::table('event_registrations')->whereIn('event_id', $eventIds)->count());
        self::assertSame(0, DB::table('event_registration_history')->whereIn('event_id', $eventIds)->count());
    }

    public function test_leaving_confirmed_cancels_pending_reminders_and_audits_the_count(): void
    {
        $organizer = $this->member();
        $confirmed = $this->member();
        $pending = $this->member();
        $eventId = $this->event($organizer, ['max_attendees' => 3]);
        $this->service->confirm(
            $eventId,
            (int) $confirmed->id,
            $confirmed,
            'reminder-confirmed',
        );
        $this->service->transition(
            $eventId,
            (int) $pending->id,
            EventCapacityRegistrationState::Pending,
            $pending,
            'reminder-pending',
        );
        foreach ([60, 1440] as $minutes) {
            DB::table('event_reminders')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'user_id' => $confirmed->id,
                'remind_before_minutes' => $minutes,
                'reminder_type' => 'both',
                'scheduled_for' => now()->addDay(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('event_reminders')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $pending->id,
            'remind_before_minutes' => 60,
            'reminder_type' => 'both',
            'scheduled_for' => now()->addDay(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->transition(
            $eventId,
            (int) $pending->id,
            EventCapacityRegistrationState::Declined,
            $organizer,
            'reminder-pending-rejected',
            null,
            null,
            'Registration not approved',
        );
        self::assertSame('pending', DB::table('event_reminders')
            ->where('event_id', $eventId)
            ->where('user_id', $pending->id)
            ->value('status'));
        $pendingMetadata = json_decode((string) DB::table('event_registration_history')
            ->where('event_id', $eventId)
            ->where('user_id', $pending->id)
            ->where('action', 'declined')
            ->value('metadata'), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(0, $pendingMetadata['cancelled_pending_reminders']);

        Config::set('events.registration.timed_waitlist_offers_enabled', true);
        $withdrawn = $this->service->withdraw(
            $eventId,
            (int) $confirmed->id,
            $confirmed,
            'reminder-confirmed-withdrawn',
        );
        self::assertTrue($withdrawn->releasedCapacity);
        self::assertSame(2, DB::table('event_reminders')
            ->where('event_id', $eventId)
            ->where('user_id', $confirmed->id)
            ->where('status', 'cancelled')
            ->count());
        $historyMetadata = json_decode((string) DB::table('event_registration_history')
            ->where('id', $withdrawn->historyId)
            ->value('metadata'), true, 16, JSON_THROW_ON_ERROR);
        $outboxPayload = json_decode((string) DB::table('event_domain_outbox')
            ->where('id', $withdrawn->outboxId)
            ->value('payload'), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(2, $historyMetadata['cancelled_pending_reminders']);
        self::assertSame(2, $outboxPayload['cancelled_pending_reminders']);
    }

    public function test_domain_authorization_reasons_and_manager_consent_cannot_be_bypassed(): void
    {
        $organizer = $this->member();
        $subject = $this->member();
        $decliningSubject = $this->member();
        $outsider = $this->member();
        $eventId = $this->event($organizer, ['max_attendees' => 5]);

        $this->assertRejected(
            'event_registration_authorization_denied',
            fn () => $this->service->transition(
                $eventId,
                (int) $subject->id,
                EventCapacityRegistrationState::Invited,
                $outsider,
                'outsider-cannot-invite',
            ),
        );
        self::assertFalse(DB::table('event_registration_history')
            ->where('event_id', $eventId)
            ->where('user_id', $subject->id)
            ->exists());

        $this->service->transition(
            $eventId,
            (int) $subject->id,
            EventCapacityRegistrationState::Invited,
            $organizer,
            'manager-invite',
        );
        $this->service->confirm(
            $eventId,
            (int) $subject->id,
            $organizer,
            'manager-confirms-invite',
        );
        $this->assertRejected(
            'event_registration_reason_required',
            fn () => $this->service->withdraw(
                $eventId,
                (int) $subject->id,
                $organizer,
                'manager-cancel-missing-reason',
            ),
        );
        $cancelled = $this->service->withdraw(
            $eventId,
            (int) $subject->id,
            $organizer,
            'manager-cancel-with-reason',
            null,
            'Registration requirements changed',
        );
        $cancelReplay = $this->service->withdraw(
            $eventId,
            (int) $subject->id,
            $organizer,
            'manager-cancel-with-reason',
            null,
            'Registration requirements changed',
        );
        self::assertTrue($cancelled->changed);
        self::assertTrue($cancelReplay->replayed);
        $this->assertRejected(
            'event_registration_idempotency_conflict',
            fn () => $this->service->withdraw(
                $eventId,
                (int) $subject->id,
                $organizer,
                'manager-cancel-with-reason',
            ),
        );
        $this->assertRejected(
            'event_registration_transition_invalid',
            fn () => $this->service->confirm(
                $eventId,
                (int) $subject->id,
                $organizer,
                'manager-cannot-reenrol-cancelled',
            ),
        );
        self::assertTrue($this->service->confirm(
            $eventId,
            (int) $subject->id,
            $subject,
            'subject-reenrols-self',
        )->changed);

        $this->service->transition(
            $eventId,
            (int) $decliningSubject->id,
            EventCapacityRegistrationState::Pending,
            $organizer,
            'manager-seeds-pending',
        );
        $this->assertRejected(
            'event_registration_reason_required',
            fn () => $this->service->transition(
                $eventId,
                (int) $decliningSubject->id,
                EventCapacityRegistrationState::Declined,
                $organizer,
                'manager-decline-missing-reason',
            ),
        );
        $this->service->transition(
            $eventId,
            (int) $decliningSubject->id,
            EventCapacityRegistrationState::Declined,
            $organizer,
            'manager-decline-with-reason',
            null,
            null,
            'Registration criteria not met',
        );
        $this->assertRejected(
            'event_registration_transition_invalid',
            fn () => $this->service->confirm(
                $eventId,
                (int) $decliningSubject->id,
                $organizer,
                'manager-cannot-reenrol-declined',
            ),
        );
        self::assertTrue($this->service->confirm(
            $eventId,
            (int) $decliningSubject->id,
            $decliningSubject,
            'declined-subject-reenrols-self',
        )->changed);
    }

    private function member(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function event(User $organizer, array $overrides = []): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizer->id,
            'title' => 'Canonical registration fixture',
            'description' => 'Canonical registration fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'registration:' . bin2hex(random_bytes(10)),
            'is_recurring_template' => 0,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function legacyStatus(int $eventId, int $userId): ?string
    {
        $status = DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->value('status');

        return is_string($status) ? $status : null;
    }

    private function assertRejected(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected registration rejection {$reason}.");
        } catch (EventRegistrationException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }
}
