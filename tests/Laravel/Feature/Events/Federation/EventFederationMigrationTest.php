<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events\Federation;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventFederationMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000054_create_event_federation_reliability_foundation.php';

    public function test_schema_is_idempotent_scoped_and_independent_from_notification_state(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        self::assertTrue(Schema::hasColumn('events', 'federation_version'));
        self::assertTrue(Schema::hasTable('event_federation_deliveries'));
        foreach ([
            'tenant_id', 'event_id', 'external_partner_id', 'payload_schema_version',
            'event_aggregate_version', 'event_calendar_version', 'action',
            'idempotency_key', 'payload_hash', 'payload', 'status', 'attempts',
            'available_at', 'next_attempt_at', 'claim_token', 'claimed_at',
            'last_attempt_at', 'delivered_at', 'dead_lettered_at',
            'last_error_code', 'last_error',
        ] as $column) {
            self::assertTrue(
                Schema::hasColumn('event_federation_deliveries', $column),
                "Missing event_federation_deliveries.{$column}",
            );
        }
        self::assertFalse(Schema::hasColumn('event_federation_deliveries', 'outbox_id'));
        self::assertFalse(Schema::hasColumn('event_federation_deliveries', 'notification_delivery_id'));
        self::assertSame(
            ['tenant_id', 'external_partner_id', 'idempotency_key'],
            $this->indexColumns('event_federation_deliveries', 'uq_event_fed_delivery_idempotency'),
        );
        self::assertSame(
            [
                'tenant_id', 'event_id', 'external_partner_id', 'payload_schema_version',
                'event_aggregate_version', 'event_calendar_version',
            ],
            $this->indexColumns('event_federation_deliveries', 'uq_event_fed_delivery_version'),
        );
        self::assertSame(
            ['status', 'available_at', 'next_attempt_at', 'id'],
            $this->indexColumns('event_federation_deliveries', 'idx_event_fed_delivery_claim'),
        );

        foreach ([
            'payload_schema_version', 'source_aggregate_version', 'source_calendar_version',
            'source_action', 'source_payload_hash', 'source_occurred_at', 'is_tombstone',
            'tombstoned_at', 'tombstone_reason', 'last_received_at', 'replay_count',
            'last_replayed_at', 'stale_count', 'last_stale_at', 'last_stale_hash',
            'conflict_count', 'last_conflict_at', 'last_conflict_hash',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('federation_events', $column), "Missing federation_events.{$column}");
        }
        self::assertSame([], $this->indexColumns('federation_events', 'uq_federation_events_tenant_partner_ext'));
        self::assertSame(
            ['tenant_id', 'is_tombstone', 'starts_at', 'id'],
            $this->indexColumns('federation_events', 'idx_federation_events_current'),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'event_federation_deliveries' => [
                    'chk_event_fed_delivery_action',
                    'chk_event_fed_delivery_status',
                    'chk_event_fed_delivery_attempts',
                    'chk_event_fed_delivery_claim',
                    'chk_event_fed_delivery_terminal',
                ],
                'federation_events' => [
                    'chk_federation_events_source_action',
                    'chk_federation_events_tombstone',
                ],
            ] as $table => $checks) {
                $actual = $this->checkConstraints($table);
                foreach ($checks as $check) {
                    self::assertContains($check, $actual);
                }
            }
        }
    }

    public function test_existing_events_receive_a_monotonic_federation_version_idempotently(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $organizer->id,
            'lifecycle_version' => 7,
            'calendar_sequence' => 9,
            'federation_version' => 0,
            'is_recurring_template' => false,
        ]);

        $this->migration()->up();
        $this->migration()->up();

        self::assertSame(9, (int) DB::table('events')->where('id', $event->id)->value('federation_version'));
    }

    public function test_legacy_projection_defaults_are_non_tombstoned_and_explicitly_unversioned(): void
    {
        $id = DB::table('federation_events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 987654,
            'external_id' => 'legacy-event-' . uniqid(),
            'title' => 'Legacy event',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('federation_events')->where('id', $id)->first();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row->payload_schema_version);
        self::assertSame(0, (int) $row->source_aggregate_version);
        self::assertSame(0, (int) $row->source_calendar_version);
        self::assertSame('upsert', $row->source_action);
        self::assertSame(0, (int) $row->is_tombstone);
        self::assertNull($row->source_payload_hash);
    }

    public function test_database_rejects_invalid_action_status_claim_and_tombstone_shapes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('Database CHECK enforcement is verified on MariaDB/MySQL.');
        }

        $base = [
            'tenant_id' => $this->testTenantId,
            'event_id' => 77001,
            'external_partner_id' => 77002,
            'payload_schema_version' => 1,
            'event_aggregate_version' => 1,
            'event_calendar_version' => 1,
            'action' => 'upsert',
            'idempotency_key' => hash('sha256', uniqid('delivery-', true)),
            'payload_hash' => hash('sha256', '{}'),
            'payload' => '{}',
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->assertQueryRejected(static fn () => DB::table('event_federation_deliveries')->insert([
            ...$base,
            'action' => 'delete',
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_federation_deliveries')->insert([
            ...$base,
            'idempotency_key' => hash('sha256', 'bad-status'),
            'status' => 'sent',
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_federation_deliveries')->insert([
            ...$base,
            'idempotency_key' => hash('sha256', 'bad-claim'),
            'status' => 'processing',
        ]));
        $this->assertQueryRejected(static fn () => DB::table('event_federation_deliveries')->insert([
            ...$base,
            'idempotency_key' => hash('sha256', 'too-many-attempts'),
            'attempts' => 6,
        ]));
        $this->assertQueryRejected(fn () => DB::table('federation_events')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 77003,
            'external_id' => 'bad-tombstone-' . uniqid(),
            'title' => 'Bad tombstone',
            'source_action' => 'tombstone',
            'is_tombstone' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertQueryRejected(fn () => DB::table('federation_events')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 77004,
            'external_id' => 'bad-upsert-evidence-' . uniqid(),
            'title' => 'Bad upsert evidence',
            'source_action' => 'upsert',
            'is_tombstone' => false,
            'tombstoned_at' => now(),
            'tombstone_reason' => 'deleted',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertQueryRejected(fn () => DB::table('federation_events')->insert([
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => 77005,
            'external_id' => 'bad-tombstone-reason-' . uniqid(),
            'title' => 'Bad tombstone reason',
            'source_action' => 'tombstone',
            'is_tombstone' => true,
            'tombstoned_at' => now(),
            'tombstone_reason' => 'other',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    /** @param callable():mixed $write */
    private function assertQueryRejected(callable $write): void
    {
        try {
            $write();
            self::fail('Invalid direct event federation write was accepted.');
        } catch (QueryException) {
            self::assertTrue(true);
        }
    }

    /** @return list<string> */
    private function checkConstraints(string $table): array
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->orderBy('CONSTRAINT_NAME')
            ->pluck('CONSTRAINT_NAME')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->orderBy('SEQ_IN_INDEX')
            ->pluck('COLUMN_NAME')
            ->map(static fn (mixed $column): string => (string) $column)
            ->all();
    }
}
