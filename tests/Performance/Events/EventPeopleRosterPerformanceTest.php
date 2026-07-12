<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Performance\Events;

use App\Core\TenantContext;
use App\Models\Event;
use App\Models\User;
use App\Services\EventPeopleService;
use App\Support\Events\EventPeopleQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Opt-in EVT-902 scale contract for the canonical Event People workspace.
 *
 * This test intentionally lives outside tests/Laravel so the normal Laravel
 * suite cannot absorb its 10,000-person fixture by directory discovery. Run it
 * only through scripts/test-events.mjs --php-only --php-batch=performance.
 */
final class EventPeopleRosterPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private const ROSTER_SIZE = 10_000;
    private const INSERT_CHUNK_SIZE = 500;
    private const PAGE_SIZE = 100;
    private const QUERY_BUDGET = 6;
    private const PAGE_RESPONSE_BUDGET_BYTES = 256 * 1024;
    private const SEARCH_RESPONSE_BUDGET_BYTES = 16 * 1024;
    private const NEEDLE_INDEX = 7_321;

    /** @var list<string> */
    private const REGISTRATION_STATES = [
        'confirmed',
        'pending',
        'invited',
        'declined',
        'cancelled',
    ];

    /** @var list<string> */
    private const ATTENDANCE_STATES = [
        'checked_in',
        'checked_out',
        'attended',
        'no_show',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
    }

    public function test_ten_thousand_person_roster_stays_inside_query_and_payload_budgets(): void
    {
        $organizer = $this->member('EVT-902 Scale Organizer');
        $event = $this->event($organizer, now()->addDay());
        $fixture = $this->seedProductionRoster($event, $organizer);

        $firstPage = $this->measureRoster(
            $event,
            new EventPeopleQuery(page: 1, perPage: self::PAGE_SIZE),
        );
        $deepPage = $this->measureRoster(
            $event,
            new EventPeopleQuery(page: 100, perPage: self::PAGE_SIZE),
        );
        $search = $this->measureRoster(
            $event,
            new EventPeopleQuery(
                page: 1,
                perPage: self::PAGE_SIZE,
                search: 'Needle Search',
            ),
        );

        self::assertSame(self::ROSTER_SIZE, $firstPage['payload']['total']);
        self::assertSame(self::ROSTER_SIZE, $deepPage['payload']['total']);
        self::assertCount(self::PAGE_SIZE, $firstPage['payload']['items']);
        self::assertCount(self::PAGE_SIZE, $deepPage['payload']['items']);
        self::assertSame(
            'EVT-902 Roster Member 00000',
            $firstPage['payload']['items'][0]['member']['display_name'],
        );
        self::assertSame(
            'EVT-902 Roster Member 09900',
            $deepPage['payload']['items'][0]['member']['display_name'],
        );
        self::assertSame(
            'EVT-902 Roster Member 09999',
            $deepPage['payload']['items'][99]['member']['display_name'],
        );
        self::assertSame([
            'confirmed' => 2_000,
            'waitlisted' => 1_000,
            'checked_in' => 250,
            'checked_out' => 250,
            'no_show' => 250,
            'attended' => 250,
        ], $firstPage['payload']['metrics']);

        self::assertSame(1, $search['payload']['total']);
        self::assertCount(1, $search['payload']['items']);
        self::assertSame(
            $fixture['needle_id'],
            $search['payload']['items'][0]['member']['id'],
        );
        self::assertSame('pending', $search['payload']['items'][0]['registration']['state']);
        self::assertSame('waiting', $search['payload']['items'][0]['waitlist']['state']);

        foreach ([
            'first page' => $firstPage,
            'deep page' => $deepPage,
            'search' => $search,
        ] as $surface => $measurement) {
            self::assertLessThanOrEqual(
                self::QUERY_BUDGET,
                $measurement['queries'],
                "The 10,000-person {$surface} used {$measurement['queries']} queries; "
                    . 'the EVT-902 budget is ' . self::QUERY_BUDGET . '.',
            );
        }
        self::assertLessThanOrEqual(
            self::PAGE_RESPONSE_BUDGET_BYTES,
            $firstPage['bytes'],
            'The first People page serialized more than the bounded page payload budget.',
        );
        self::assertLessThanOrEqual(
            self::PAGE_RESPONSE_BUDGET_BYTES,
            $deepPage['bytes'],
            'The deep People page serialized more than the bounded page payload budget.',
        );
        self::assertLessThanOrEqual(
            self::SEARCH_RESPONSE_BUDGET_BYTES,
            $search['bytes'],
            'The one-result People search serialized more than the bounded search payload budget.',
        );

        $this->assertRosterIndexesAndExplainPlan((int) $event->id);
    }

    /**
     * @return array{
     *   payload:array<string,mixed>,
     *   queries:int,
     *   bytes:int
     * }
     */
    private function measureRoster(Event $event, EventPeopleQuery $query): array
    {
        DB::disableQueryLog();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $payload = app(EventPeopleService::class)->paginate($event, $query);
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'payload' => $payload,
            'queries' => $queryCount,
            'bytes' => strlen($json),
        ];
    }

    /** @return array{needle_id:int} */
    private function seedProductionRoster(Event $event, User $organizer): array
    {
        $prefix = bin2hex(random_bytes(8));
        $now = now();
        for ($offset = 0; $offset < self::ROSTER_SIZE; $offset += self::INSERT_CHUNK_SIZE) {
            $users = [];
            for ($index = $offset; $index < $offset + self::INSERT_CHUNK_SIZE; $index++) {
                $displayName = sprintf('EVT-902 Roster Member %05d', $index);
                if ($index === self::NEEDLE_INDEX) {
                    $displayName .= ' Needle Search';
                }
                $users[] = [
                    'tenant_id' => $this->testTenantId,
                    'name' => $displayName,
                    'first_name' => $displayName,
                    'username' => sprintf('evt902-%s-%05d', $prefix, $index),
                    'email' => sprintf('evt902-%s-%05d@example.test', $prefix, $index),
                    'status' => 'active',
                    'is_approved' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($users);
        }

        /** @var array<int,int> $memberIds */
        $memberIds = [];
        $members = DB::table('users')
            ->where('tenant_id', $this->testTenantId)
            ->where('email', 'like', "evt902-{$prefix}-%@example.test")
            ->get(['id', 'email']);
        foreach ($members as $member) {
            $matched = preg_match('/-(\d{5})@example\.test$/', (string) $member->email, $matches);
            self::assertSame(1, $matched, 'A scale-fixture email did not contain its roster ordinal.');
            $memberIds[(int) $matches[1]] = (int) $member->id;
        }
        ksort($memberIds);
        self::assertCount(self::ROSTER_SIZE, $memberIds);

        $queueSequence = 0;
        foreach (array_chunk($memberIds, self::INSERT_CHUNK_SIZE, true) as $memberChunk) {
            $registrations = [];
            $waitlistEntries = [];
            $attendanceRows = [];
            foreach ($memberChunk as $index => $userId) {
                $registrationState = self::REGISTRATION_STATES[
                    $index % count(self::REGISTRATION_STATES)
                ];
                $registration = [
                    'tenant_id' => $this->testTenantId,
                    'event_id' => (int) $event->id,
                    'user_id' => $userId,
                    'capacity_pool_key' => 'event',
                    'registration_state' => $registrationState,
                    'registration_version' => 1,
                    'state_changed_at' => $now,
                    'state_changed_by' => (int) $organizer->id,
                    'invited_at' => null,
                    'pending_at' => null,
                    'confirmed_at' => null,
                    'declined_at' => null,
                    'cancelled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $registration["{$registrationState}_at"] = $now;
                $registrations[] = $registration;

                if ($index % 10 === 1) {
                    $queueSequence++;
                    $waitlistEntries[] = [
                        'tenant_id' => $this->testTenantId,
                        'event_id' => (int) $event->id,
                        'user_id' => $userId,
                        'capacity_pool_key' => 'event',
                        'queue_state' => 'waiting',
                        'queue_version' => 1,
                        'queue_sequence' => $queueSequence,
                        'state_changed_at' => $now,
                        'state_changed_by' => (int) $organizer->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($index % 10 === 0) {
                    $attendanceState = self::ATTENDANCE_STATES[
                        intdiv($index, 10) % count(self::ATTENDANCE_STATES)
                    ];
                    $attendanceRows[] = [
                        'tenant_id' => $this->testTenantId,
                        'event_id' => (int) $event->id,
                        'user_id' => $userId,
                        'attendance_status' => $attendanceState,
                        'attendance_version' => 1,
                        'status_changed_at' => $now,
                        'status_changed_by' => (int) $organizer->id,
                        'checked_in_at' => $attendanceState === 'no_show' ? null : $now,
                        'checked_in_by' => $attendanceState === 'no_show'
                            ? null
                            : (int) $organizer->id,
                        'checked_out_at' => $attendanceState === 'checked_out' ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::table('event_registrations')->insert($registrations);
            DB::table('event_waitlist_entries')->insert($waitlistEntries);
            DB::table('event_attendance')->insert($attendanceRows);
        }

        return ['needle_id' => $memberIds[self::NEEDLE_INDEX]];
    }

    private function assertRosterIndexesAndExplainPlan(int $eventId): void
    {
        self::assertSame(
            ['tenant_id', 'event_id', 'capacity_pool_key', 'registration_state', 'id'],
            $this->indexColumns('event_registrations', 'idx_event_registration_capacity'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'capacity_pool_key', 'queue_state', 'queue_sequence', 'id'],
            $this->indexColumns('event_waitlist_entries', 'idx_event_waitlist_queue'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'attendance_status', 'id'],
            $this->indexColumns('event_attendance', 'idx_event_attendance_tenant_event_status'),
        );
        self::assertSame(
            ['tenant_id', 'name'],
            $this->indexColumns('users', 'idx_users_name_tenant'),
        );

        $plan = DB::select(
            <<<'SQL'
EXPLAIN SELECT user_id
FROM event_registrations
WHERE tenant_id = ?
  AND event_id = ?
  AND capacity_pool_key = ?
  AND registration_state = ?
ORDER BY id
LIMIT 100
SQL,
            [$this->testTenantId, $eventId, 'event', 'confirmed'],
        );
        self::assertCount(1, $plan);
        self::assertContains(
            (string) ($plan[0]->key ?? ''),
            ['idx_event_registration_capacity', 'uq_event_registration_subject'],
            'The optimizer abandoned the tenant/event registration indexes used by People.',
        );
        self::assertContains(
            strtolower((string) ($plan[0]->type ?? '')),
            ['ref', 'range'],
            'The indexed registration probe regressed to a full table scan.',
        );
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        usort(
            $rows,
            static fn (object $left, object $right): int =>
                (int) $left->Seq_in_index <=> (int) $right->Seq_in_index,
        );

        return array_map(
            static fn (object $row): string => (string) $row->Column_name,
            $rows,
        );
    }

    private function member(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(User $organizer, Carbon $start): Event
    {
        $id = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $organizer->id,
            'title' => 'EVT-902 production-scale People fixture',
            'description' => 'Query-count and response-size regression fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'evt-902:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Event::withoutGlobalScopes()->findOrFail($id);
    }
}
