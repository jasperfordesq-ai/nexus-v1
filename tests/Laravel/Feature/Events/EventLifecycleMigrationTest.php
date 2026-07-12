<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\EventStatusHistory;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventLifecycleMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000014_add_event_lifecycle_foundation.php';

    public function test_expand_only_schema_and_tenant_scoped_audit_indexes_are_present(): void
    {
        foreach ([
            'publication_status',
            'operational_status',
            'lifecycle_version',
            'publication_status_changed_at',
            'publication_status_changed_by',
            'operational_status_changed_at',
            'operational_status_changed_by',
            'lifecycle_reason',
            'moderation_submitted_at',
            'moderation_submitted_by',
            'moderated_at',
            'moderated_by',
            'moderation_reason',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('events', $column), "Missing events.{$column}");
            $definition = DB::selectOne('SHOW COLUMNS FROM `events` WHERE Field = ?', [$column]);
            self::assertNotNull($definition);
            self::assertSame('YES', $definition->{'Null'}, "events.{$column} must remain nullable");
        }

        self::assertTrue(Schema::hasTable('event_status_history'));
        $versionIndex = DB::select(
            "SHOW INDEX FROM `event_status_history` WHERE Key_name = 'uq_event_status_history_version'"
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'lifecycle_version'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $versionIndex),
        );
        self::assertSame([0, 0, 0], array_map(
            static fn (object $row): int => (int) $row->Non_unique,
            $versionIndex,
        ));

        $lifecycleIndex = DB::select(
            "SHOW INDEX FROM `events` WHERE Key_name = 'idx_events_tenant_lifecycle_start'"
        );
        self::assertSame(
            ['tenant_id', 'publication_status', 'operational_status', 'start_time', 'id'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $lifecycleIndex),
        );
    }

    public function test_backfill_maps_every_known_legacy_state_without_replacing_event_identity(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $ids = [
            'draft' => $this->insertEvent((int) $organizer->id, 'draft'),
            'active' => $this->insertEvent((int) $organizer->id, 'active'),
            'null' => $this->insertEvent((int) $organizer->id, null),
            'cancelled' => $this->insertEvent((int) $organizer->id, 'cancelled'),
            'completed' => $this->insertEvent((int) $organizer->id, 'completed'),
        ];

        $this->migration()->up();
        $this->migration()->up();

        $expected = [
            'draft' => ['draft', 'scheduled'],
            'active' => ['published', 'scheduled'],
            'null' => ['published', 'scheduled'],
            'cancelled' => ['published', 'cancelled'],
            'completed' => ['published', 'completed'],
        ];
        foreach ($ids as $legacy => $id) {
            $event = DB::table('events')->where('id', $id)->first();
            self::assertNotNull($event);
            self::assertSame($id, (int) $event->id);
            self::assertSame($expected[$legacy][0], $event->publication_status);
            self::assertSame($expected[$legacy][1], $event->operational_status);
            self::assertSame(0, (int) $event->lifecycle_version);
        }

        self::assertNull(DB::table('events')->where('id', $ids['null'])->value('status'));
        self::assertSame(0, DB::table('event_status_history')->whereIn('event_id', $ids)->count());
    }

    public function test_history_is_immutable_through_eloquent_and_direct_database_writes(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->insertEvent((int) $organizer->id, 'active', [
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
        ]);
        $historyId = $this->insertHistory($eventId, (int) $organizer->id);

        TenantContext::setById($this->testTenantId);
        $history = EventStatusHistory::query()->findOrFail($historyId);
        $history->forceFill(['reason' => 'mutation-attempt']);
        try {
            $history->save();
            self::fail('Eloquent was able to rewrite lifecycle history.');
        } catch (LogicException $exception) {
            self::assertSame('event_status_history_immutable', $exception->getMessage());
        }

        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    DB::table('event_status_history')->where('id', $historyId)->update([
                        'reason' => 'direct-mutation-attempt',
                    ]);
                } else {
                    DB::table('event_status_history')->where('id', $historyId)->delete();
                }
                self::fail("Direct {$operation} bypassed lifecycle history immutability.");
            } catch (QueryException $exception) {
                self::assertStringContainsString(
                    'event_status_history_immutable',
                    $exception->getMessage(),
                );
            }
        }

        self::assertSame(1, DB::table('event_status_history')->where('id', $historyId)->count());
        self::assertSame('initial', DB::table('event_status_history')->where('id', $historyId)->value('reason'));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    /** @param array<string,mixed> $overrides */
    private function insertEvent(int $organizerId, ?string $status, array $overrides = []): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Lifecycle migration fixture',
            'description' => 'Lifecycle migration fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => $status,
            'publication_status' => null,
            'operational_status' => null,
            'lifecycle_version' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertHistory(int $eventId, int $actorId): int
    {
        return (int) DB::table('event_status_history')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'actor_user_id' => $actorId,
            'lifecycle_version' => 1,
            'from_publication_status' => 'published',
            'to_publication_status' => 'published',
            'from_operational_status' => 'scheduled',
            'to_operational_status' => 'postponed',
            'from_legacy_status' => 'active',
            'to_legacy_status' => 'cancelled',
            'reason' => 'initial',
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
