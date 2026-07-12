<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Enums\EventStaffRole;
use App\Models\User;
use App\Services\EventRoleService;
use App\Services\EventSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class AccessibleEventAgendaTest extends TestCase
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

    public function test_owner_manages_the_complete_agenda_with_html_first_prg_forms(): void
    {
        $owner = $this->member('Accessible Agenda Owner');
        [$eventId, $start] = $this->event($owner);
        Sanctum::actingAs($owner, ['*']);
        $path = "/{$this->testTenantSlug}/accessible/events/{$eventId}/agenda";

        $initialResponse = $this->get($path);
        $initialResponse->assertOk()
            ->assertSeeText('Event agenda')
            ->assertSeeText('Add a session')
            ->assertSee('type="datetime-local"', false);
        self::assertStringContainsString(
            'no-store',
            (string) $initialResponse->headers->get('Cache-Control'),
        );

        $this->accessiblePost($path, [
            ...$this->payload($start, ['title' => 'Opening workshop']),
            'action' => 'create',
            'idempotency_key' => 'accessible-agenda-create-1',
        ])->assertRedirect($path . '?status=agenda-created');
        $first = DB::table('event_sessions')->where('event_id', $eventId)->first();
        self::assertNotNull($first);

        $this->accessiblePost($path, [
            ...$this->payload($start, ['title' => 'Opening workshop updated']),
            'action' => 'update',
            'session_id' => (string) $first->id,
            'expected_version' => (string) $first->version,
            'idempotency_key' => 'accessible-agenda-update-1',
        ])->assertRedirect($path . '?status=agenda-updated');

        $this->accessiblePost($path, [
            ...$this->payload($start->addHours(2), ['title' => 'Closing panel']),
            'action' => 'create',
            'idempotency_key' => 'accessible-agenda-create-2',
        ])->assertRedirect($path . '?status=agenda-created');
        $second = DB::table('event_sessions')
            ->where('event_id', $eventId)
            ->where('title', 'Closing panel')
            ->first();
        self::assertNotNull($second);
        $agendaVersion = (int) DB::table('events')->where('id', $eventId)->value('agenda_version');

        $this->accessiblePost($path, [
            'action' => 'move_down',
            'session_id' => (string) $first->id,
            'expected_agenda_version' => (string) $agendaVersion,
            'idempotency_key' => 'accessible-agenda-reorder-1',
        ])->assertRedirect($path . '?status=agenda-reordered');
        self::assertSame(
            [(int) $second->id, (int) $first->id],
            DB::table('event_sessions')
                ->where('event_id', $eventId)
                ->where('status', 'scheduled')
                ->orderBy('position')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        $secondVersion = (int) DB::table('event_sessions')->where('id', $second->id)->value('version');
        $this->accessiblePost($path, [
            'action' => 'cancel',
            'session_id' => (string) $second->id,
            'expected_version' => (string) $secondVersion,
            'reason' => 'Speaker unavailable',
            'idempotency_key' => 'accessible-agenda-cancel-1',
        ])->assertRedirect($path . '?status=agenda-cancelled');

        $this->get($path)
            ->assertOk()
            ->assertSeeText('Opening workshop updated')
            ->assertSeeText('Cancelled sessions')
            ->assertSeeText('Speaker unavailable');
        self::assertSame(5, DB::table('event_session_history')->where('event_id', $eventId)->count());
    }

    public function test_accessible_agenda_applies_registered_and_staff_visibility_without_mutation_controls(): void
    {
        $owner = $this->member('Accessible Visibility Owner');
        $member = $this->member('Accessible Visibility Member');
        $registered = $this->member('Accessible Visibility Registered');
        $staff = $this->member('Accessible Visibility Staff');
        [$eventId, $start] = $this->event($owner);
        $service = new EventSessionService();
        foreach ([
            ['Public agenda item', 'public'],
            ['Registered agenda item', 'registered'],
            ['Staff agenda item', 'staff'],
        ] as $index => [$title, $visibility]) {
            $service->create(
                $eventId,
                $owner,
                $this->servicePayload($start->addHours($index), $title, $visibility),
                "accessible-visibility-{$index}",
            );
        }
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $registered->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $owner->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        (new EventRoleService())->grant(
            $eventId,
            (int) $staff->id,
            EventStaffRole::CheckInStaff,
            $owner,
            null,
            'accessible-agenda-staff',
        );
        $path = "/{$this->testTenantSlug}/accessible/events/{$eventId}/agenda";

        Sanctum::actingAs($member, ['*']);
        $this->get($path)
            ->assertOk()
            ->assertSeeText('Public agenda item')
            ->assertDontSeeText('Registered agenda item')
            ->assertDontSeeText('Staff agenda item')
            ->assertDontSeeText('Add a session');

        Sanctum::actingAs($registered, ['*']);
        $this->get($path)
            ->assertOk()
            ->assertSeeText('Public agenda item')
            ->assertSeeText('Registered agenda item')
            ->assertDontSeeText('Staff agenda item');

        Sanctum::actingAs($staff, ['*']);
        $this->get($path)
            ->assertOk()
            ->assertSeeText('Public agenda item')
            ->assertSeeText('Registered agenda item')
            ->assertSeeText('Staff agenda item')
            ->assertDontSeeText('Add a session');
    }

    public function test_invalid_or_stale_accessible_agenda_mutation_fails_closed_without_writing(): void
    {
        $owner = $this->member('Accessible Invalid Owner');
        $member = $this->member('Accessible Invalid Member');
        [$eventId, $start] = $this->event($owner);
        $path = "/{$this->testTenantSlug}/accessible/events/{$eventId}/agenda";

        Sanctum::actingAs($member, ['*']);
        $this->accessiblePost($path, [
            ...$this->payload($start),
            'action' => 'create',
            'idempotency_key' => 'accessible-agenda-denied',
        ])->assertForbidden();
        self::assertSame(0, DB::table('event_sessions')->where('event_id', $eventId)->count());

        Sanctum::actingAs($owner, ['*']);
        $this->accessiblePost($path, [
            ...$this->payload($start, ['start_at' => $start->format('Y-m-d') . 'T02:99']),
            'action' => 'create',
            'idempotency_key' => 'accessible-agenda-invalid-time',
        ])->assertRedirect($path);
        self::assertSame(0, DB::table('event_sessions')->where('event_id', $eventId)->count());

        $created = (new EventSessionService())->create(
            $eventId,
            $owner,
            $this->servicePayload($start, 'Versioned session', 'public'),
            'accessible-agenda-versioned',
        );
        $this->accessiblePost($path, [
            ...$this->payload($start, ['title' => 'Stale title']),
            'action' => 'update',
            'session_id' => (string) $created['session']->id,
            'expected_version' => '99',
            'idempotency_key' => 'accessible-agenda-stale',
        ])->assertRedirect($path);
        self::assertSame('Versioned session', DB::table('event_sessions')
            ->where('id', $created['session']->id)
            ->value('title'));
    }

    public function test_registered_member_uses_no_javascript_session_capacity_and_protected_resources(): void
    {
        $owner = $this->member('Accessible Enterprise Owner');
        $member = $this->member('Accessible Enterprise Member');
        [$eventId, $start] = $this->event($owner);
        $created = (new EventSessionService())->create(
            $eventId,
            $owner,
            [
                ...$this->servicePayload($start, 'Accessible capacity workshop', 'public'),
                'capacity' => 4,
                'resources' => [[
                    'type' => 'stream',
                    'title' => 'Registered live stream',
                    'url' => 'https://events.example.test/live/accessible',
                    'visibility' => 'registered',
                ]],
            ],
            'accessible-agenda-enterprise-session',
        );
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $member->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $owner->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($member, ['*']);
        $path = "/{$this->testTenantSlug}/accessible/events/{$eventId}/agenda";

        $this->get($path)
            ->assertOk()
            ->assertSeeText('0 of 4 registered')
            ->assertSeeText('Registered live stream')
            ->assertSeeText('Register for session');
        $this->accessiblePost($path, [
            'action' => 'register',
            'session_id' => (string) $created['session']->id,
            'expected_version' => '0',
            'idempotency_key' => 'accessible-agenda-enterprise-register',
        ])->assertRedirect($path . '?status=agenda-session-registered');
        $this->get($path)
            ->assertOk()
            ->assertSeeText('1 of 4 registered')
            ->assertSeeText('Withdraw from session');
    }

    private function accessiblePost(string $uri, array $data): TestResponse
    {
        $token = 'accessible-event-agenda-token';
        $this->withSession(['_token' => $token]);

        return $this->post($uri, array_merge(['_token' => $token], $data));
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
            'title' => 'Accessible agenda event',
            'description' => 'Accessible agenda fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(8),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'accessible-agenda:' . bin2hex(random_bytes(8)),
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

    /** @return array<string,mixed> */
    private function payload(CarbonImmutable $start, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Accessible session',
            'description' => 'Accessible session description.',
            'session_type' => 'session',
            'visibility' => 'public',
            'start_at' => $start->format('Y-m-d\TH:i'),
            'end_at' => $start->addHour()->format('Y-m-d\TH:i'),
            'track_name' => '',
            'room_name' => 'Room A',
            'speaker_member_id' => ['', ''],
            'speaker_name' => ['Guest Speaker', ''],
            'speaker_role' => ['Facilitator', ''],
        ], $overrides);
    }

    /** @return array<string,mixed> */
    private function servicePayload(
        CarbonImmutable $start,
        string $title,
        string $visibility,
    ): array {
        return [
            'title' => $title,
            'description' => null,
            'session_type' => 'session',
            'visibility' => $visibility,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->addMinutes(45)->toIso8601String(),
            'timezone' => 'UTC',
            'track_name' => null,
            'room_name' => null,
            'speakers' => [],
        ];
    }
}
