<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use App\Services\EventReminderPreferenceService;
use App\Services\EventReminderScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class EventReminderScheduleServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventReminderScheduleService $schedules;
    private EventReminderPreferenceService $preferences;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2035-05-10 12:00:00 UTC');
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->schedules = app(EventReminderScheduleService::class);
        $this->preferences = app(EventReminderPreferenceService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_confirmed_registration_builds_default_schedules_idempotently(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));

        $first = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );
        $second = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );

        self::assertSame([1440, 60], array_column($first, 'offset_minutes'));
        self::assertSame(
            array_column($first, 'id'),
            array_column($second, 'id'),
        );
        self::assertSame(2, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->count());
        self::assertSame(['pending'], DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->distinct()
            ->pluck('status')
            ->all());
    }

    public function test_registration_exit_cancels_but_reconfirmation_creates_new_versions(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));
        $this->schedules->reconcileConfirmedRegistration((int) $event->id, (int) $user->id, $registrationId, 1);

        self::assertSame(2, $this->schedules->cancelForRegistrationExit(
            (int) $event->id,
            (int) $user->id,
            'registration_cancelled',
        ));
        DB::table('event_registrations')->where('id', $registrationId)->update([
            'registration_state' => 'confirmed',
            'registration_version' => 2,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'updated_at' => now(),
        ]);
        $rebuilt = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            2,
        );

        self::assertSame([2, 2], array_column($rebuilt, 'schedule_version'));
        self::assertSame(2, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('status', 'cancelled')
            ->count());
        self::assertSame(2, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_calendar_reschedule_supersedes_pending_versions_but_preserves_delivered_evidence(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));
        $initial = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );
        DB::table('event_reminder_schedules')
            ->where('id', (int) $initial[0]['id'])
            ->update(['status' => 'delivered', 'delivered_at' => now(), 'updated_at' => now()]);
        DB::table('events')->where('id', $event->id)->update([
            'start_time' => now()->addDays(10),
            'end_time' => now()->addDays(10)->addHour(),
            'calendar_sequence' => 1,
            'updated_at' => now(),
        ]);

        $rescheduled = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );

        self::assertSame([2, 2], array_column($rescheduled, 'schedule_version'));
        self::assertSame([1, 1], array_column($rescheduled, 'event_calendar_sequence'));
        self::assertDatabaseHas('event_reminder_schedules', [
            'id' => (int) $initial[0]['id'],
            'status' => 'delivered',
        ]);
        self::assertDatabaseHas('event_reminder_schedules', [
            'id' => (int) $initial[1]['id'],
            'status' => 'superseded',
            'reason_code' => 'schedule_generation_superseded',
        ]);
    }

    public function test_rule_edit_does_not_repeat_an_already_delivered_occurrence_offset(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));
        $preference = $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [['offset_minutes' => 60, 'email_enabled' => true]],
            0,
        );
        $initial = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );
        DB::table('event_reminder_schedules')->where('id', $initial[0]['id'])->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'updated_at' => now(),
        ]);
        $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [['offset_minutes' => 60, 'email_enabled' => false]],
            $preference['revision'],
        );

        $reconciled = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );

        self::assertSame($initial[0]['id'], $reconciled[0]['id']);
        self::assertSame('delivered', $reconciled[0]['status']);
        self::assertSame(1, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('offset_minutes', 60)
            ->count());
    }

    public function test_custom_past_due_rule_is_caught_up_only_inside_the_recovery_horizon(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(2));
        $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [
                ['offset_minutes' => 1440],
                ['offset_minutes' => 10080],
            ],
            0,
        );

        $rows = collect($this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        ))->keyBy('offset_minutes');

        self::assertSame('pending', $rows->get(1440)['status']);
        self::assertNull($rows->get(1440)['reason_code']);
        self::assertSame('suppressed', $rows->get(10080)['status']);
        self::assertSame('outside_recovery_horizon', $rows->get(10080)['reason_code']);

        DB::table('events')->where('id', $event->id)->update([
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'calendar_sequence' => 1,
            'updated_at' => now(),
        ]);
        $catchUp = collect($this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        ))->keyBy('offset_minutes');
        self::assertSame('pending', $catchUp->get(1440)['status']);
        self::assertSame('catch_up_due', $catchUp->get(1440)['reason_code']);
    }

    public function test_explicit_reminder_opt_out_closes_existing_schedule_without_erasing_rules(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));
        $created = $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [['offset_minutes' => 60]],
            0,
        );
        $this->schedules->reconcileConfirmedRegistration((int) $event->id, (int) $user->id, $registrationId, 1);
        $disabled = $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => false],
            [['offset_minutes' => 60]],
            $created['revision'],
        );

        self::assertSame([], $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        ));
        self::assertSame(1, DB::table('event_reminder_rules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->count());
        self::assertDatabaseHas('event_reminder_schedules', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'cancelled',
            'reason_code' => 'reminders_disabled',
        ]);
        self::assertSame(2, $disabled['revision']);
    }

    public function test_reenabling_reminders_rebuilds_a_cancelled_generation(): void
    {
        [$user, $event, $registrationId] = $this->subject(now()->addDays(3));
        $enabled = $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [['offset_minutes' => 60]],
            0,
        );
        $first = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );
        $disabled = $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => false],
            [['offset_minutes' => 60]],
            $enabled['revision'],
        );
        $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );
        $this->preferences->replaceEventPreferences(
            (int) $event->id,
            (int) $user->id,
            ['reminders_enabled' => true],
            [['offset_minutes' => 60]],
            $disabled['revision'],
        );

        $rebuilt = $this->schedules->reconcileConfirmedRegistration(
            (int) $event->id,
            (int) $user->id,
            $registrationId,
            1,
        );

        self::assertSame(2, $rebuilt[0]['schedule_version']);
        self::assertSame('pending', $rebuilt[0]['status']);
        self::assertNotSame($first[0]['id'], $rebuilt[0]['id']);
        self::assertSame(1, DB::table('event_reminder_schedules')
            ->where('tenant_id', $this->testTenantId)
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', 'cancelled')
            ->count());
    }

    /** @return array{User,Event,int} */
    private function subject(Carbon $start): array
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'calendar_sequence' => 0,
            'is_recurring_template' => false,
        ]);
        $registrationId = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $user->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $event, $registrationId];
    }
}
