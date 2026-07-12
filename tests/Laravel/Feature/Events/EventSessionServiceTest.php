<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventSessionStatus;
use App\Enums\EventSessionType;
use App\Enums\EventSessionVisibility;
use App\Enums\EventStaffRole;
use App\Exceptions\EventSessionException;
use App\Models\EventSession;
use App\Models\EventSessionHistory;
use App\Models\User;
use App\Services\EventSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Laravel\TestCase;

final class EventSessionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->service = new EventSessionService();
    }

    public function test_owner_creates_an_idempotent_versioned_session_with_safe_speakers(): void
    {
        $owner = $this->user();
        $speaker = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $payload = $this->payload($start, [
            'title' => '<strong>Opening workshop</strong>',
            'description' => '<p>Practical repair skills.</p>',
            'session_type' => EventSessionType::Workshop->value,
            'visibility' => EventSessionVisibility::Registered->value,
            'room_name' => ' Main Hall ',
            'speakers' => [
                ['user_id' => (int) $speaker->id, 'role_label' => 'Facilitator'],
                ['display_name' => 'Guest Expert', 'role_label' => 'Speaker'],
            ],
        ]);

        $created = $this->service->create($eventId, $owner, $payload, 'agenda-create-1');
        $replay = $this->service->create($eventId, $owner, $payload, 'agenda-create-1');

        self::assertTrue($created['changed']);
        self::assertFalse($replay['changed']);
        self::assertSame($created['history_id'], $replay['history_id']);
        self::assertSame(1, $created['agenda_version']);
        self::assertSame('Opening workshop', $created['session']->title);
        self::assertSame(EventSessionType::Workshop, $created['session']->session_type);
        self::assertSame(EventSessionVisibility::Registered, $created['session']->visibility);
        self::assertSame(EventSessionStatus::Scheduled, $created['session']->status);
        self::assertSame(1, $created['session']->version);
        self::assertCount(2, $created['session']->speakers);
        self::assertSame(1, DB::table('event_sessions')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_session_history')->where('event_id', $eventId)->count());
        self::assertSame(1, (int) DB::table('events')->where('id', $eventId)->value('agenda_version'));

        $history = DB::table('event_session_history')->where('id', $created['history_id'])->first();
        self::assertNotNull($history);
        self::assertSame('created', $history->action);
        self::assertSame(64, strlen((string) $history->request_hash));
        self::assertStringNotContainsString('Guest Expert', (string) $history->changed_fields);

        $conflictPayload = [...$payload, 'title' => 'Different operation'];
        $this->assertReason(
            'event_agenda_idempotency_conflict',
            fn () => $this->service->create($eventId, $owner, $conflictPayload, 'agenda-create-1'),
        );
    }

    public function test_visibility_is_filtered_for_public_registered_and_staff_viewers(): void
    {
        $owner = $this->user();
        $publicViewer = $this->user();
        $registeredViewer = $this->user();
        $staffViewer = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);

        $this->service->create(
            $eventId,
            $owner,
            $this->payload($start, ['title' => 'Public', 'visibility' => 'public']),
            'agenda-public',
        );
        $this->service->create(
            $eventId,
            $owner,
            $this->payload($start->addHour(), ['title' => 'Registered', 'visibility' => 'registered']),
            'agenda-registered',
        );
        $this->service->create(
            $eventId,
            $owner,
            $this->payload($start->addHours(2), ['title' => 'Staff', 'visibility' => 'staff']),
            'agenda-staff',
        );
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $registeredViewer->id,
            'capacity_pool_key' => 'event',
            'allocation_key' => null,
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $owner->id,
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_staff_assignments')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $staffViewer->id,
            'role' => EventStaffRole::CommunicationsManager->value,
            'status' => 'active',
            'assignment_version' => 1,
            'granted_at' => now(),
            'granted_by' => (int) $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(
            ['Public'],
            $this->service->list($eventId, $publicViewer)->pluck('title')->all(),
        );
        self::assertSame(
            ['Public', 'Registered'],
            $this->service->list($eventId, $registeredViewer)->pluck('title')->all(),
        );
        self::assertSame(
            ['Public', 'Registered', 'Staff'],
            $this->service->list($eventId, $staffViewer)->pluck('title')->all(),
        );
    }

    public function test_update_reorder_and_cancel_are_optimistic_monotonic_and_soft(): void
    {
        $owner = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $first = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start, ['title' => 'First']),
            'agenda-flow-create-1',
        );
        $second = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start->addHour(), ['title' => 'Second']),
            'agenda-flow-create-2',
        );

        $updated = $this->service->update(
            $eventId,
            (int) $first['session']->id,
            $owner,
            ['title' => 'First updated', 'track_name' => 'Track A'],
            1,
            'agenda-flow-update',
        );
        self::assertTrue($updated['changed']);
        self::assertSame(2, $updated['session']->version);
        self::assertSame(3, $updated['agenda_version']);
        $this->assertReason(
            'event_agenda_version_conflict',
            fn () => $this->service->update(
                $eventId,
                (int) $first['session']->id,
                $owner,
                ['title' => 'Stale change'],
                1,
                'agenda-flow-stale',
            ),
        );

        $reordered = $this->service->reorder(
            $eventId,
            $owner,
            [(int) $second['session']->id, (int) $first['session']->id],
            3,
            'agenda-flow-reorder',
        );
        self::assertTrue($reordered['changed']);
        self::assertSame(4, $reordered['agenda_version']);
        self::assertSame(
            [(int) $second['session']->id, (int) $first['session']->id],
            $reordered['sessions']->pluck('id')->all(),
        );

        $cancelled = $this->service->cancel(
            $eventId,
            (int) $second['session']->id,
            $owner,
            'Speaker unavailable',
            2,
            'agenda-flow-cancel',
        );
        self::assertTrue($cancelled['changed']);
        self::assertSame(EventSessionStatus::Cancelled, $cancelled['session']->status);
        self::assertSame(5, $cancelled['agenda_version']);
        self::assertSame(2, DB::table('event_sessions')->where('event_id', $eventId)->count());
        self::assertSame(
            ['created', 'created', 'updated', 'reordered', 'cancelled'],
            DB::table('event_session_history')
                ->where('event_id', $eventId)
                ->orderBy('agenda_version')
                ->pluck('action')
                ->all(),
        );
        self::assertCount(1, $this->service->list($eventId, $owner));
        self::assertCount(2, $this->service->list($eventId, $owner, true));

        $session = EventSession::withoutGlobalScopes()->findOrFail($second['session']->id);
        try {
            $session->delete();
            self::fail('Session model allowed destructive deletion.');
        } catch (LogicException $exception) {
            self::assertSame('event_session_delete_forbidden', $exception->getMessage());
        }
    }

    public function test_room_speaker_bounds_and_timezone_conflicts_fail_closed(): void
    {
        $owner = $this->user();
        $speaker = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $this->service->create(
            $eventId,
            $owner,
            $this->payload($start, [
                'room_name' => 'Main Hall',
                'speakers' => [['user_id' => (int) $speaker->id]],
            ]),
            'agenda-conflict-base',
        );

        $this->assertReason(
            'event_agenda_room_conflict',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start->addMinutes(15), ['room_name' => ' main   hall ']),
                'agenda-room-conflict',
            ),
        );
        $this->assertReason(
            'event_agenda_speaker_conflict',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start->addMinutes(15), [
                    'room_name' => 'Other room',
                    'speakers' => [['user_id' => (int) $speaker->id]],
                ]),
                'agenda-speaker-conflict',
            ),
        );
        $adjacent = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start->addHour(), ['room_name' => 'MAIN HALL']),
            'agenda-adjacent',
        );
        self::assertTrue($adjacent['changed']);
        $this->assertReason(
            'event_agenda_outside_event_bounds',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start->subMinute()),
                'agenda-bounds',
            ),
        );

        [$dstEventId] = $this->event(
            (int) $owner->id,
            $this->testTenantId,
            CarbonImmutable::parse('2027-03-14T05:00:00Z'),
            CarbonImmutable::parse('2027-03-14T10:00:00Z'),
            'America/New_York',
        );
        $this->assertReason(
            'event_agenda_timezone_offset_mismatch',
            fn () => $this->service->create($dstEventId, $owner, [
                'title' => 'DST gap',
                'start_at' => '2027-03-14T02:30:00-05:00',
                'end_at' => '2027-03-14T03:30:00-04:00',
                'timezone' => 'America/New_York',
            ], 'agenda-dst-gap'),
        );
    }

    public function test_authorization_tenant_lifecycle_and_template_boundaries_fail_closed(): void
    {
        $owner = $this->user();
        $member = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $this->assertReason(
            'event_agenda_authorization_denied',
            fn () => $this->service->create(
                $eventId,
                $member,
                $this->payload($start),
                'agenda-denied',
            ),
        );

        $directTemplateSession = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start),
            'agenda-before-template',
        );
        DB::table('events')->where('id', $eventId)->update(['is_recurring_template' => true]);
        self::assertCount(0, $this->service->list($eventId, $owner, true));
        $this->assertReason(
            'event_agenda_template_unsupported',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start),
                'agenda-template',
            ),
        );
        self::assertNotNull($directTemplateSession['session']->id);
        DB::table('events')->where('id', $eventId)->update([
            'is_recurring_template' => false,
            'publication_status' => 'archived',
        ]);
        $this->assertReason(
            'event_agenda_event_read_only',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start),
                'agenda-archived',
            ),
        );

        $foreignOwner = $this->user([], 999);
        [$foreignEventId, $foreignStart] = $this->event((int) $foreignOwner->id, 999);
        TenantContext::setById($this->testTenantId);
        $this->assertReason(
            'event_agenda_event_not_found',
            fn () => $this->service->create(
                $foreignEventId,
                $owner,
                $this->payload($foreignStart),
                'agenda-foreign',
            ),
        );
    }

    public function test_unknown_and_ambiguous_input_fields_are_rejected(): void
    {
        $owner = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $this->assertReason(
            'event_agenda_fields_unknown',
            fn () => $this->service->create(
                $eventId,
                $owner,
                [...$this->payload($start), 'unexpected' => true],
                'agenda-unknown-create',
            ),
        );
        $this->assertReason(
            'event_agenda_fields_ambiguous',
            fn () => $this->service->create(
                $eventId,
                $owner,
                [...$this->payload($start), 'starts_at' => $start->toIso8601String()],
                'agenda-ambiguous-create',
            ),
        );
        $this->assertReason(
            'event_agenda_speaker_fields_unknown',
            fn () => $this->service->create(
                $eventId,
                $owner,
                $this->payload($start, [
                    'speakers' => [['display_name' => 'Guest', 'private_note' => 'not allowed']],
                ]),
                'agenda-unknown-speaker',
            ),
        );

        $created = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start),
            'agenda-known-create',
        );
        $this->assertReason(
            'event_agenda_fields_unknown',
            fn () => $this->service->update(
                $eventId,
                (int) $created['session']->id,
                $owner,
                ['title' => 'Updated', 'status' => 'cancelled'],
                1,
                'agenda-unknown-update',
            ),
        );
        self::assertSame(1, DB::table('event_sessions')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_session_history')->where('event_id', $eventId)->count());
    }

    public function test_history_is_immutable_in_database_and_model(): void
    {
        $owner = $this->user();
        [$eventId, $start] = $this->event((int) $owner->id);
        $created = $this->service->create(
            $eventId,
            $owner,
            $this->payload($start),
            'agenda-history',
        );

        try {
            DB::table('event_session_history')
                ->where('id', $created['history_id'])
                ->update(['action' => 'tampered']);
            self::fail('History update trigger did not fire.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_session_history_immutable', $exception->getMessage());
        }

        $history = EventSessionHistory::withoutGlobalScopes()->findOrFail($created['history_id']);
        $history->forceFill(['action' => 'tampered']);
        try {
            $history->save();
            self::fail('History model allowed mutation.');
        } catch (LogicException $exception) {
            self::assertSame('event_session_history_immutable', $exception->getMessage());
        }
    }

    private function user(array $overrides = [], int $tenantId = 2): User
    {
        $user = User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
        TenantContext::setById($this->testTenantId);

        return $user;
    }

    /** @return array{int,CarbonImmutable,CarbonImmutable} */
    private function event(
        int $ownerId,
        int $tenantId = 2,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
        string $timezone = 'UTC',
    ): array {
        $start ??= CarbonImmutable::now('UTC')->addMonth()->startOfHour();
        $end ??= $start->addHours(6);
        $eventId = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Agenda service fixture',
            'description' => 'Agenda service fixture.',
            'start_time' => $start->utc(),
            'end_time' => $end->utc(),
            'timezone' => $timezone,
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
            'agenda_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$eventId, $start, $end];
    }

    /** @return array<string,mixed> */
    private function payload(CarbonImmutable $start, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Agenda session',
            'description' => 'A useful session.',
            'session_type' => EventSessionType::Session->value,
            'visibility' => EventSessionVisibility::Public->value,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->addHour()->toIso8601String(),
            'timezone' => $start->getTimezone()->getName() === 'Z'
                ? 'UTC'
                : $start->getTimezone()->getName(),
            'track_name' => null,
            'room_name' => null,
            'speakers' => [],
        ], $overrides);
    }

    /** @param callable():mixed $operation */
    private function assertReason(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected {$reason}.");
        } catch (EventSessionException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }
}
