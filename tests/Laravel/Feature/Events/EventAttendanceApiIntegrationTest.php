<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventAttendanceApiIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function event(User $organizer, array $overrides = []): int
    {
        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $organizer->id,
            'title' => 'Attendance API integration fixture',
            'description' => 'Canonical attendance API integration fixture.',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHours(2),
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'is_recurring_template' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function rsvp(int $eventId, User $attendee, string $status = 'going'): void
    {
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_single_routes_share_one_idempotent_ledger_and_explicit_replay_dto(): void
    {
        $organizer = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);
        Sanctum::actingAs($organizer, ['*']);

        $first = $this->apiPost(
            "/v2/events/{$eventId}/attendees/{$attendee->id}/check-in",
            [
                'notes' => 'Arrived at the main desk.',
                'idempotency_key' => 'body-key-must-not-win',
            ],
            ['Idempotency-Key' => 'scanner-request-1'],
        );
        $first->assertOk()
            ->assertJsonPath('data.event_id', $eventId)
            ->assertJsonPath('data.user_id', (int) $attendee->id)
            ->assertJsonPath('data.attendee_id', (int) $attendee->id)
            ->assertJsonPath('data.outcome', 'checked_in')
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.marked', true)
            ->assertJsonPath('data.already_checked_in', false)
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.credit_status', 'disabled')
            ->assertJsonPath('data.hours_credited', null)
            ->assertJsonPath('data.attendance_version', 1);

        $attendanceId = (int) $first->json('data.attendance_id');
        $attendance = DB::table('event_attendance')->where('id', $attendanceId)->first();
        self::assertNotNull($attendance);
        self::assertSame((int) $organizer->id, (int) $attendance->checked_in_by);
        self::assertSame('checked_in', $attendance->attendance_status);
        self::assertSame('attended', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));

        $expectedKey = substr(hash(
            'sha256',
            "event-attendance:v1:{$this->testTenantId}:{$eventId}:{$attendee->id}:check-in:scanner-request-1",
        ), 0, 64);
        self::assertSame($expectedKey, DB::table('event_attendance_activity')
            ->where('attendance_id', $attendanceId)
            ->value('idempotency_key'));

        $replay = $this->apiPost(
            "/v2/events/{$eventId}/attendance",
            [
                'user_id' => $attendee->id,
                'idempotency_key' => 'another-client-request',
                'notes' => 'Must not overwrite the first immutable fact.',
            ],
        );
        $replay->assertOk()
            ->assertJsonPath('data.attendance_id', $attendanceId)
            ->assertJsonPath('data.outcome', 'already_checked_in')
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.marked', false)
            ->assertJsonPath('data.already_checked_in', true)
            ->assertJsonPath('data.replayed', true);

        self::assertSame(1, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());
        $stored = DB::table('event_attendance')->where('id', $attendanceId)->first();
        self::assertSame($attendance->checked_in_at, $stored->checked_in_at);
        self::assertSame($attendance->notes, $stored->notes);
    }

    public function test_attendance_exceptions_map_to_existing_translated_api_errors(): void
    {
        $organizer = $this->user();
        $outsider = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);

        Sanctum::actingAs($outsider, ['*']);
        $this->apiPost("/v2/events/{$eventId}/attendance", ['user_id' => $attendee->id])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN')
            ->assertJsonPath('errors.0.message', __('api.event_attendance_forbidden'));

        Sanctum::actingAs($organizer, ['*']);
        $earlyEventId = $this->event($organizer, [
            'title' => 'Too early attendance fixture',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(4),
        ]);
        $this->rsvp($earlyEventId, $attendee);
        $this->apiPost("/v2/events/{$earlyEventId}/attendance", ['user_id' => $attendee->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'TOO_EARLY')
            ->assertJsonPath('errors.0.message', __('api.event_too_early_checkin'));

        $unregistered = $this->user();
        $this->apiPost("/v2/events/{$eventId}/attendance", ['user_id' => $unregistered->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.message', __('api.event_not_rsvped'));

        $this->apiPost(
            "/v2/events/{$eventId}/attendance",
            ['user_id' => $attendee->id],
            ['Idempotency-Key' => str_repeat('x', 192)],
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.message', __('api.invalid_input'));

        $this->apiPost("/v2/events/{$eventId}/attendance", [
            'user_id' => $attendee->id,
            'hours' => 'not-a-number',
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
            ->assertJsonPath('errors.0.message', __('api.invalid_amount'));

        $this->apiPost("/v2/events/{$eventId}/attendance", [
            'user_id' => $attendee->id,
            'hours' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'HOURS_OVERRIDE_UNAVAILABLE')
            ->assertJsonPath('errors.0.field', 'hours');

        $this->apiPost("/v2/events/{$eventId}/attendance", ['user_id' => 999999999])
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'NOT_FOUND')
            ->assertJsonPath('errors.0.message', __('api.user_not_found'));

        DB::table('events')->where('id', $eventId)->update([
            'status' => 'cancelled',
            'operational_status' => 'cancelled',
        ]);
        $this->apiPost("/v2/events/{$eventId}/attendance", ['user_id' => $attendee->id])
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'EVENT_UNAVAILABLE')
            ->assertJsonPath('errors.0.message', __('api.event_checkin_failed'));

        self::assertSame(0, DB::table('event_attendance')->count());
        self::assertSame(0, DB::table('event_attendance_activity')->count());
    }

    public function test_bulk_returns_bounded_deduplicated_per_item_outcomes_without_ambiguity(): void
    {
        $organizer = $this->user();
        $newAttendee = $this->user();
        $existingAttendee = $this->user();
        $unregistered = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $newAttendee);
        $this->rsvp($eventId, $existingAttendee);
        Sanctum::actingAs($organizer, ['*']);

        $this->apiPost(
            "/v2/events/{$eventId}/attendance",
            ['user_id' => $existingAttendee->id, 'idempotency_key' => 'existing-checkin'],
        )->assertOk();

        $bulk = $this->apiPost(
            "/v2/events/{$eventId}/attendance/bulk",
            [
                'user_ids' => [
                    $newAttendee->id,
                    (string) $newAttendee->id,
                    $existingAttendee->id,
                    $unregistered->id,
                ],
                'idempotency_key' => 'bulk-scanner-batch-1',
            ],
        );
        $bulk->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.processed', 3)
            ->assertJsonPath('data.successful', 2)
            ->assertJsonPath('data.marked', 1)
            ->assertJsonPath('data.already_checked_in', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.complete', false)
            ->assertJsonPath('data.partial_success', true)
            ->assertJsonCount(3, 'data.outcomes')
            ->assertJsonPath('data.outcomes.0.user_id', (int) $newAttendee->id)
            ->assertJsonPath('data.outcomes.0.success', true)
            ->assertJsonPath('data.outcomes.0.outcome', 'checked_in')
            ->assertJsonPath('data.outcomes.1.user_id', (int) $existingAttendee->id)
            ->assertJsonPath('data.outcomes.1.outcome', 'already_checked_in')
            ->assertJsonPath('data.outcomes.1.replayed', true)
            ->assertJsonPath('data.outcomes.2.user_id', (int) $unregistered->id)
            ->assertJsonPath('data.outcomes.2.success', false)
            ->assertJsonPath('data.outcomes.2.error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.outcomes.2.http_status', 422);

        self::assertSame(2, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(2, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame(2, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());

        $repeat = $this->apiPost(
            "/v2/events/{$eventId}/attendance/bulk",
            [
                'user_ids' => [$newAttendee->id, $existingAttendee->id, $unregistered->id],
                'idempotency_key' => 'bulk-scanner-batch-1',
            ],
        );
        $repeat->assertOk()
            ->assertJsonPath('data.marked', 0)
            ->assertJsonPath('data.already_checked_in', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.partial_success', true);
        self::assertSame(2, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
    }

    public function test_event_service_compatibility_seam_delegates_without_direct_attendance_writes(): void
    {
        $organizer = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);

        $first = TenantContext::runForTenant(
            $this->testTenantId,
            fn (): bool => EventService::markAttended(
                $eventId,
                (int) $attendee->id,
                (int) $organizer->id,
                null,
                null,
                'accessible-checkin-1',
            ),
        );
        self::assertTrue($first);
        self::assertSame('checked_in', EventService::getLastAttendanceResult()?->outcome);

        $replay = TenantContext::runForTenant(
            $this->testTenantId,
            fn (): bool => EventService::markAttended(
                $eventId,
                (int) $attendee->id,
                (int) $organizer->id,
                null,
                null,
                'accessible-checkin-retry',
            ),
        );
        self::assertTrue($replay);
        self::assertSame('already_checked_in', EventService::getLastAttendanceResult()?->outcome);
        self::assertSame(1, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());

        $secondAttendee = $this->user();
        $unregistered = $this->user();
        $this->rsvp($eventId, $secondAttendee);
        $bulk = TenantContext::runForTenant(
            $this->testTenantId,
            fn (): array => EventService::bulkMarkAttended(
                $eventId,
                [(int) $attendee->id, (int) $secondAttendee->id, (int) $unregistered->id],
                (int) $organizer->id,
                null,
                null,
                'accessible-bulk-1',
            ),
        );
        self::assertSame(3, $bulk['total']);
        self::assertSame(2, $bulk['successful']);
        self::assertSame(1, $bulk['marked']);
        self::assertSame(1, $bulk['already_checked_in']);
        self::assertSame(1, $bulk['failed']);
        self::assertTrue($bulk['partial_success']);
        self::assertCount(3, $bulk['outcomes']);
        self::assertSame('VALIDATION_ERROR', $bulk['outcomes'][2]['error']['code']);
        self::assertSame(2, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame(2, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());

        $serviceSource = file_get_contents(base_path('app/Services/EventService.php'));
        $controllerSource = file_get_contents(base_path('app/Http/Controllers/Api/EventsController.php'));
        self::assertIsString($serviceSource);
        self::assertIsString($controllerSource);
        self::assertStringNotContainsString('INSERT INTO event_attendance', $serviceSource);
        self::assertStringNotContainsString("DB::table('event_attendance')->insert", $serviceSource);
        self::assertStringNotContainsString('event_checkins', $serviceSource);
        self::assertStringNotContainsString('INSERT INTO event_attendance', $controllerSource);
        self::assertStringNotContainsString("DB::table('event_attendance')->insert", $controllerSource);
        self::assertStringNotContainsString('event_checkins', $controllerSource);
    }
}
