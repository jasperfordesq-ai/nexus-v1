<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Enums\EventStaffRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventPeopleOperationsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('events.attendance_credit_mode', 'off');
        Config::set('events.registration.default_capacity_pool_key', 'event');
        Config::set('events.registration.legacy_dual_write', true);
    }

    public function test_full_people_api_is_strict_bounded_and_returns_structured_errors(): void
    {
        $organizer = $this->member('API People Organizer');
        $invitee = $this->member('API People Invitee');
        $eventId = $this->event($organizer);
        Sanctum::actingAs($organizer, ['*']);

        $this->apiGet("/v2/events/{$eventId}/people?unexpected=true")
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'EVENT_PEOPLE_QUERY_INVALID');

        $this->apiPost("/v2/events/{$eventId}/people/bulk", [
            'operations' => [[
                'user_id' => (int) $invitee->id,
                'action' => 'invite',
                'expected_version' => 0,
                'idempotency_key' => 'people-api-invite',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.requested', 1)
            ->assertJsonPath('data.succeeded', 1)
            ->assertJsonPath('data.results.0.success', true)
            ->assertJsonPath('data.results.0.mutation.state', 'invited');

        $this->apiPost("/v2/events/{$eventId}/people/bulk", [
            'operations' => [[
                'user_id' => (int) $invitee->id,
                'action' => 'approve',
                'expected_version' => 99,
                'idempotency_key' => 'people-api-stale-approve',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.results.0.success', false)
            ->assertJsonPath('data.results.0.error.code', 'EVENT_REGISTRATION_CONFLICT')
            ->assertJsonPath(
                'data.results.0.error.message',
                __('event_registration.request_conflict'),
            )
            ->assertJsonMissingPath('data.results.0.error.reason');

        $this->apiPost("/v2/events/{$eventId}/people/bulk", [
            'operations' => [[
                'user_id' => (int) $invitee->id,
                'action' => 'approve',
                'expected_version' => 1,
                'idempotency_key' => 'people-api-extra-key',
                'unexpected' => true,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'EVENT_PEOPLE_VALIDATION_FAILED');

        $this->apiGet("/v2/events/{$eventId}/people/{$invitee->id}/history")
            ->assertOk()
            ->assertJsonPath('meta.projection', 'full')
            ->assertJsonPath('data.0.axis', 'registration')
            ->assertJsonMissingPath('data.0.metadata');

        $export = $this->apiGet("/v2/events/{$eventId}/people/export.csv");
        $export->assertOk();
        self::assertStringContainsString('text/csv', (string) $export->headers->get('content-type'));
        $csv = $export->streamedContent();
        self::assertStringContainsString('API People Invitee', $csv);
        self::assertSame([
            __('event_registration.people_export.member_id'),
            __('event_registration.people_export.member_name'),
            __('event_registration.people_export.engagement'),
            __('event_registration.people_export.registration'),
            __('event_registration.people_export.registration_changed'),
            __('event_registration.people_export.waitlist'),
            __('event_registration.people_export.queue_position'),
            __('event_registration.people_export.queue_sequence'),
            __('event_registration.people_export.attendance'),
            __('event_registration.people_export.attendance_changed'),
            __('event_registration.people_export.checked_in'),
            __('event_registration.people_export.checked_out'),
        ], str_getcsv((string) strtok($csv, "\r\n")));
        self::assertStringNotContainsString((string) $invitee->email, $csv);
        self::assertStringNotContainsString('form_answer', strtolower($csv));
        self::assertStringNotContainsString('incident', strtolower($csv));
        self::assertStringNotContainsString('safeguard', strtolower($csv));
        self::assertStringNotContainsString('support_note', strtolower($csv));
    }

    public function test_check_in_projection_cannot_enumerate_waitlist_or_use_manager_surfaces(): void
    {
        $organizer = $this->member('Attendance API Organizer');
        $staff = $this->member('Attendance API Staff');
        $confirmed = $this->member('Attendance API Confirmed');
        $waitlisted = $this->member('Attendance API Waitlisted');
        $unrelated = $this->member('Attendance API Unrelated');
        $eventId = $this->event($organizer, true);
        $this->confirmed($eventId, $confirmed, $organizer);
        $this->waitlisted($eventId, $waitlisted, $organizer);
        $this->assignStaff($eventId, $staff, EventStaffRole::CheckInStaff, $organizer);
        Sanctum::actingAs($staff, ['*']);

        $people = $this->apiGet("/v2/events/{$eventId}/people");
        $people->assertOk()
            ->assertJsonPath('meta.projection', 'attendance')
            ->assertJsonPath('meta.capabilities.manage_attendance', true)
            ->assertJsonPath('meta.capabilities.manage_registration', false)
            ->assertJsonPath('meta.capabilities.view_waitlist', false)
            ->assertJsonPath('meta.capabilities.export_people', false)
            ->assertJsonPath('data.0.member.id', (int) $confirmed->id)
            ->assertJsonMissingPath('data.0.waitlist')
            ->assertJsonMissingPath('data.0.engagement')
            ->assertJsonMissingPath('meta.metrics.waitlisted');
        self::assertStringNotContainsString('Attendance API Waitlisted', $people->getContent());

        $this->apiGet("/v2/events/{$eventId}/people?waitlist_state=active")
            ->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'EVENT_PEOPLE_QUERY_INVALID');
        $this->apiGet("/v2/events/{$eventId}/people/export.csv")
            ->assertForbidden();
        $this->apiPost("/v2/events/{$eventId}/people/bulk", [
            'operations' => [[
                'user_id' => (int) $confirmed->id,
                'action' => 'check_in',
                'expected_version' => 0,
                'idempotency_key' => 'staff-forbidden-bulk',
            ]],
        ])->assertForbidden();

        $this->apiPost(
            "/v2/events/{$eventId}/people/{$unrelated->id}/attendance",
            [
                'action' => 'check_in',
                'expected_version' => 0,
                'idempotency_key' => 'staff-unrelated-check-in',
            ],
            ['Idempotency-Key' => 'staff-unrelated-check-in'],
        )->assertNotFound()
            ->assertJsonPath('errors.0.code', 'EVENT_REGISTRATION_MEMBER_NOT_FOUND');

        $this->apiPost(
            "/v2/events/{$eventId}/people/{$confirmed->id}/attendance",
            [
                'action' => 'check_in',
                'expected_version' => 0,
                'idempotency_key' => 'staff-confirmed-check-in',
            ],
            ['Idempotency-Key' => 'different-key'],
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'EVENT_REGISTRATION_IDEMPOTENCY_INVALID');

        $this->apiPost(
            "/v2/events/{$eventId}/people/{$confirmed->id}/attendance",
            [
                'action' => 'check_in',
                'expected_version' => 0,
                'idempotency_key' => 'staff-confirmed-check-in',
            ],
            ['Idempotency-Key' => 'staff-confirmed-check-in'],
        )->assertOk()
            ->assertJsonPath('data.mutation.to_state', 'checked_in')
            ->assertJsonPath('data.mutation.attendance_version', 1)
            ->assertJsonPath('data.mutation.changed', true);

        $this->apiPost(
            "/v2/events/{$eventId}/people/{$confirmed->id}/attendance",
            [
                'action' => 'check_in',
                'expected_version' => 0,
                'idempotency_key' => 'staff-confirmed-check-in',
            ],
            ['Idempotency-Key' => 'staff-confirmed-check-in'],
        )->assertOk()
            ->assertJsonPath('data.mutation.changed', false)
            ->assertJsonPath('data.mutation.idempotent_replay', true);

        $this->apiPost(
            "/v2/events/{$eventId}/people/{$confirmed->id}/attendance",
            [
                'action' => 'check_out',
                'expected_version' => 0,
                'idempotency_key' => 'staff-stale-check-out',
            ],
            ['Idempotency-Key' => 'staff-stale-check-out'],
        )->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'EVENT_REGISTRATION_CONFLICT');

        $history = $this->apiGet(
            "/v2/events/{$eventId}/people/{$confirmed->id}/history",
        );
        $history->assertOk()
            ->assertJsonPath('meta.projection', 'attendance')
            ->assertJsonPath('data.0.axis', 'attendance');
        self::assertStringNotContainsString('"axis":"registration"', $history->getContent());
        $this->apiGet("/v2/events/{$eventId}/people/{$waitlisted->id}/history")
            ->assertNotFound();
    }

    public function test_people_bulk_has_an_isolated_but_enforced_rate_limit(): void
    {
        $organizer = $this->member('People Bulk Rate Limit Organizer');
        $eventId = $this->event($organizer);
        Sanctum::actingAs($organizer, ['*']);

        $configuredLimiter = RateLimiter::limiter('events-people-bulk');
        self::assertNotNull($configuredLimiter);
        $limiterRequest = Request::create("/api/v2/events/{$eventId}/people/bulk", 'POST');
        $limiterRequest->setUserResolver(static fn (): User => $organizer);
        $configuredLimit = $configuredLimiter($limiterRequest);
        self::assertInstanceOf(Limit::class, $configuredLimit);
        self::assertSame(30, $configuredLimit->maxAttempts);
        self::assertSame(
            "events:people-bulk:tenant:{$this->testTenantId}:user:{$organizer->id}",
            $configuredLimit->key,
        );

        // Keep this behavioural regression fast while still exercising the real
        // route middleware and its separation from Laravel's numeric bucket.
        RateLimiter::for('events-people-bulk', static fn (Request $request): Limit => Limit::perMinute(2)->by(
            'test:events-people-bulk:user:' . $request->user()?->getAuthIdentifier()
        ));
        Route::get('/api/_test/events/unrelated-numeric-throttle', static fn () => response()->json([
            'ok' => true,
        ]))->middleware(['api', 'auth:sanctum', 'throttle:2,1']);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->apiGet('/_test/events/unrelated-numeric-throttle')->assertOk();
        }
        $this->apiGet('/_test/events/unrelated-numeric-throttle')->assertTooManyRequests();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->apiPost("/v2/events/{$eventId}/people/bulk", [])
                ->assertUnprocessable();
        }

        $limited = $this->apiPost("/v2/events/{$eventId}/people/bulk", [])
            ->assertTooManyRequests();
        self::assertGreaterThan(0, (int) $limited->json('retry_after'));
    }

    private function member(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(User $organizer, bool $started = false): int
    {
        $start = $started ? now()->subMinutes(15) : now()->addWeek();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $organizer->id,
            'title' => 'Event People API fixture',
            'description' => 'Least-privilege API fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'people-api:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'max_attendees' => 20,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function confirmed(int $eventId, User $member, User $actor): void
    {
        $now = now();
        $registrationId = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => (int) $actor->id,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_registration_history')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'registration_id' => $registrationId,
            'user_id' => (int) $member->id,
            'capacity_pool_key' => 'event',
            'registration_version' => 1,
            'action' => 'confirmed',
            'from_state' => null,
            'to_state' => 'confirmed',
            'actor_user_id' => (int) $actor->id,
            'idempotency_key' => 'people-api-confirmed-' . $member->id,
            'reason' => null,
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'status' => 'going',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function waitlisted(int $eventId, User $member, User $actor): void
    {
        DB::table('event_waitlist_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'waiting',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignStaff(
        int $eventId,
        User $staff,
        EventStaffRole $role,
        User $grantor,
    ): void {
        DB::table('event_staff_assignments')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $staff->id,
            'role' => $role->value,
            'status' => 'active',
            'assignment_version' => 1,
            'granted_at' => now(),
            'granted_by' => (int) $grantor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
