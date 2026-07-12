<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventSessionException;
use App\Models\User;
use App\Services\EventSessionService;
use App\Support\Events\EventSessionContractMapper;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventAgendaEnterpriseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['events' => true], JSON_THROW_ON_ERROR),
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_session_registration_is_capacity_safe_idempotent_and_domain_isolated(): void
    {
        $owner = $this->member('Agenda Enterprise Owner');
        $first = $this->member('Agenda Enterprise First');
        $second = $this->member('Agenda Enterprise Second');
        [$eventId, $start] = $this->event($owner);
        $firstEventRegistrationId = $this->confirmEventRegistration($eventId, $first, $owner);
        $this->confirmEventRegistration($eventId, $second, $owner);
        $service = new EventSessionService();
        $created = $service->create(
            $eventId,
            $owner,
            $this->sessionPayload($start),
            'agenda-enterprise-create',
        );
        $sessionId = (int) $created['session']->id;
        $eventRegistrationBefore = (array) DB::table('event_registrations')
            ->where('id', $firstEventRegistrationId)
            ->first();
        $ticketsBefore = DB::table('event_ticket_entitlements')->count();
        $attendanceBefore = DB::table('event_attendance_activity')->count();

        $registered = $service->registerSession(
            $eventId,
            $sessionId,
            $first,
            0,
            'agenda-enterprise-register-first',
        );
        self::assertTrue($registered['changed']);
        self::assertSame(1, $registered['registration_version']);
        $replay = $service->registerSession(
            $eventId,
            $sessionId,
            $first,
            0,
            'agenda-enterprise-register-first',
        );
        self::assertFalse($replay['changed']);
        self::assertSame($registered['history_id'], $replay['history_id']);
        self::assertSame(
            $eventRegistrationBefore,
            (array) DB::table('event_registrations')->where('id', $firstEventRegistrationId)->first(),
        );
        self::assertSame($ticketsBefore, DB::table('event_ticket_entitlements')->count());
        self::assertSame($attendanceBefore, DB::table('event_attendance_activity')->count());

        try {
            $service->registerSession(
                $eventId,
                $sessionId,
                $second,
                0,
                'agenda-enterprise-register-full',
            );
            self::fail('Expected the session capacity boundary to reject a second member.');
        } catch (EventSessionException $exception) {
            self::assertSame('event_agenda_session_capacity_full', $exception->reasonCode);
        }

        $withdrawn = $service->withdrawSession(
            $eventId,
            $sessionId,
            $first,
            1,
            'agenda-enterprise-withdraw-first',
        );
        self::assertTrue($withdrawn['changed']);
        self::assertSame(2, $withdrawn['registration_version']);
        self::assertSame(2, DB::table('event_session_registration_history')->count());
    }

    public function test_mapper_reveals_encrypted_media_only_to_registered_or_staff_viewers(): void
    {
        $owner = $this->member('Agenda Resource Owner');
        $publicViewer = $this->member('Agenda Public Viewer');
        $registeredViewer = $this->member('Agenda Registered Viewer');
        [$eventId, $start] = $this->event($owner);
        $this->confirmEventRegistration($eventId, $registeredViewer, $owner);
        $service = new EventSessionService();
        $created = $service->create(
            $eventId,
            $owner,
            $this->sessionPayload($start),
            'agenda-enterprise-resources',
        );
        $ciphertext = (string) DB::table('event_session_resources')
            ->where('session_id', $created['session']->id)
            ->where('resource_type', 'stream')
            ->value('url_ciphertext');
        self::assertNotSame('https://events.example.test/live/private', $ciphertext);
        self::assertStringNotContainsString('events.example.test/live/private', $ciphertext);

        $public = $service->readAgenda($eventId, $publicViewer);
        $publicContract = EventSessionContractMapper::agenda(
            $public['event'],
            $public['sessions'],
            ['can_manage' => $public['can_manage']],
        );
        self::assertSame(['slides'], array_column($publicContract['sessions'][0]['resources'], 'type'));

        $registered = $service->readAgenda($eventId, $registeredViewer);
        $registeredContract = EventSessionContractMapper::agenda(
            $registered['event'],
            $registered['sessions'],
            ['can_manage' => $registered['can_manage']],
        );
        self::assertSame(
            ['slides', 'stream'],
            array_column($registeredContract['sessions'][0]['resources'], 'type'),
        );
        self::assertSame(
            'https://events.example.test/live/private',
            $registeredContract['sessions'][0]['resources'][1]['url'],
        );
    }

    public function test_api_exposes_versioned_registration_mutations_with_private_cache_policy(): void
    {
        $owner = $this->member('Agenda API Owner');
        $member = $this->member('Agenda API Member');
        [$eventId, $start] = $this->event($owner);
        $this->confirmEventRegistration($eventId, $member, $owner);
        $created = (new EventSessionService())->create(
            $eventId,
            $owner,
            $this->sessionPayload($start),
            'agenda-enterprise-api-session',
        );
        $sessionId = (int) $created['session']->id;
        Sanctum::actingAs($member, ['*']);

        $response = $this->apiPost(
            "/v2/events/{$eventId}/agenda/sessions/{$sessionId}/registration",
            ['expected_version' => 0],
            ['Idempotency-Key' => 'agenda-enterprise-api-register'],
        );
        $response->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.registration_version', 1)
            ->assertJsonPath('data.session.registration.state', 'registered')
            ->assertJsonPath('data.session.capacity.registered', 1)
            ->assertJsonCount(2, 'data.session.resources')
            ->assertJsonPath('data.session.resources.0.type', 'slides')
            ->assertJsonPath('data.session.resources.1.type', 'stream')
            ->assertJsonMissing(['title' => 'Staff run sheet']);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->apiPost(
            "/v2/events/{$eventId}/agenda/sessions/{$sessionId}/registration/withdraw",
            ['expected_version' => 1],
            ['Idempotency-Key' => 'agenda-enterprise-api-withdraw'],
        )->assertOk()
            ->assertJsonPath('data.session.registration.state', 'withdrawn')
            ->assertJsonPath('data.registration_version', 2);
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

    /** @return array{int,CarbonImmutable} */
    private function event(User $owner): array
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $owner->id,
            'title' => 'Enterprise agenda event',
            'description' => 'Enterprise agenda fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(8),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'agenda-enterprise:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'agenda_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$eventId, $start];
    }

    private function confirmEventRegistration(int $eventId, User $member, User $actor): int
    {
        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $actor->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function sessionPayload(CarbonImmutable $start): array
    {
        return [
            'title' => 'Capacity-limited workshop',
            'description' => 'Session registration fixture.',
            'session_type' => 'workshop',
            'visibility' => 'public',
            'capacity' => 1,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->addHour()->toIso8601String(),
            'timezone' => 'UTC',
            'track_name' => null,
            'room_name' => 'Workshop room',
            'speakers' => [],
            'resources' => [
                [
                    'type' => 'slides',
                    'title' => 'Public slides',
                    'url' => 'https://events.example.test/slides/public',
                    'visibility' => 'public',
                ],
                [
                    'type' => 'stream',
                    'title' => 'Private stream',
                    'url' => 'https://events.example.test/live/private',
                    'visibility' => 'registered',
                ],
                [
                    'type' => 'document',
                    'title' => 'Staff run sheet',
                    'url' => 'https://events.example.test/staff/run-sheet',
                    'visibility' => 'staff',
                ],
            ],
        ];
    }
}
