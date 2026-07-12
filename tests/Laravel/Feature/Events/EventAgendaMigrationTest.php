<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventAgendaMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000053_create_event_agenda_sessions.php';

    public function test_schema_exposes_versioned_sessions_speakers_and_immutable_history(): void
    {
        self::assertTrue(Schema::hasColumn('events', 'agenda_version'));

        foreach ([
            'tenant_id',
            'event_id',
            'version',
            'title',
            'description',
            'session_type',
            'visibility',
            'status',
            'starts_at_utc',
            'ends_at_utc',
            'timezone',
            'track_name',
            'room_name',
            'room_key',
            'position',
            'cancellation_reason',
            'created_by',
            'updated_by',
            'cancelled_by',
            'cancelled_at',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_sessions', $column), $column);
        }
        foreach ([
            'tenant_id',
            'event_id',
            'session_id',
            'user_id',
            'display_name',
            'role_label',
            'position',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_session_speakers', $column), $column);
        }
        foreach ([
            'tenant_id',
            'event_id',
            'session_id',
            'actor_user_id',
            'agenda_version',
            'action',
            'idempotency_key',
            'request_hash',
            'changed_fields',
            'affected_session_ids',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('event_session_history', $column), $column);
        }

        self::assertSame(
            ['tenant_id', 'event_id', 'agenda_version'],
            $this->indexColumns('event_session_history', 'uq_event_session_history_version'),
        );
        self::assertSame(
            ['tenant_id', 'idempotency_key'],
            $this->indexColumns('event_session_history', 'uq_event_session_history_key'),
        );
        self::assertSame(
            ['tenant_id', 'session_id', 'user_id'],
            $this->indexColumns('event_session_speakers', 'uq_event_session_speaker_member'),
        );
        self::assertSame(
            ['tenant_id', 'id'],
            $this->indexColumns('events', 'uq_events_tenant_id'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'id'],
            $this->indexColumns('event_sessions', 'uq_event_sessions_tenant_event_id'),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'trg_event_session_history_no_update',
                'trg_event_session_history_no_delete',
            ] as $trigger) {
                self::assertTrue(DB::table('information_schema.TRIGGERS')
                    ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                    ->where('TRIGGER_NAME', $trigger)
                    ->exists());
            }
            foreach ([
                'fk_event_sessions_tenant',
                'fk_event_sessions_event_tenant',
                'fk_event_sessions_creator_tenant',
                'fk_event_session_speakers_tenant',
                'fk_event_session_speakers_event_tenant',
                'fk_event_session_speakers_session_tenant',
                'fk_event_session_speakers_user_tenant',
                'fk_event_session_history_tenant',
                'fk_event_session_history_event_tenant',
                'fk_event_session_history_session_tenant',
                'fk_event_session_history_actor_tenant',
            ] as $constraint) {
                self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
            }
            foreach ([
                'chk_event_sessions_type',
                'chk_event_sessions_visibility',
                'chk_event_sessions_status',
                'chk_event_sessions_time_range',
                'chk_event_sessions_cancellation',
                'chk_event_session_speaker_identity',
            ] as $constraint) {
                self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
            }
        }
    }

    public function test_direct_writes_cannot_bypass_agenda_checks_or_tenant_associations(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB CHECK constraints are verified only on the production driver.');
        }

        DB::beginTransaction();
        try {
            $owner = User::factory()->forTenant($this->testTenantId)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $foreignOwner = User::factory()->forTenant(999)->create([
                'status' => 'active',
                'is_approved' => true,
            ]);
            $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
            $eventId = $this->insertEvent($this->testTenantId, (int) $owner->id, $start);
            $otherEventId = $this->insertEvent($this->testTenantId, (int) $owner->id, $start);
            $foreignEventId = $this->insertEvent(999, (int) $foreignOwner->id, $start);
            $base = $this->sessionRow($eventId, (int) $owner->id, $start);

            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([...$base, 'session_type' => 'unknown']),
                'chk_event_sessions_type',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([...$base, 'visibility' => 'secret']),
                'chk_event_sessions_visibility',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([...$base, 'status' => 'deleted']),
                'chk_event_sessions_status',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([
                    ...$base,
                    'ends_at_utc' => $base['starts_at_utc'],
                ]),
                'chk_event_sessions_time_range',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([
                    ...$base,
                    'status' => 'cancelled',
                    'cancellation_reason' => null,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                ]),
                'chk_event_sessions_cancellation',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([
                    ...$base,
                    'event_id' => $foreignEventId,
                ]),
                'fk_event_sessions_event_tenant',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_sessions')->insert([
                    ...$base,
                    'created_by' => (int) $foreignOwner->id,
                ]),
                'fk_event_sessions_creator_tenant',
            );

            $sessionId = (int) DB::table('event_sessions')->insertGetId($base);
            $otherSessionId = (int) DB::table('event_sessions')->insertGetId([
                ...$base,
                'event_id' => $otherEventId,
            ]);
            $speakerBase = [
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'session_id' => $sessionId,
                'user_id' => null,
                'display_name' => null,
                'role_label' => null,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $this->assertConstraintViolation(
                fn () => DB::table('event_session_speakers')->insert($speakerBase),
                'chk_event_session_speaker_identity',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_session_speakers')->insert([
                    ...$speakerBase,
                    'user_id' => (int) $owner->id,
                    'display_name' => 'Duplicate identity',
                ]),
                'chk_event_session_speaker_identity',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_session_speakers')->insert([
                    ...$speakerBase,
                    'session_id' => $otherSessionId,
                    'display_name' => 'Cross-associated speaker',
                ]),
                'fk_event_session_speakers_session_tenant',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_session_history')->insert([
                    'tenant_id' => $this->testTenantId,
                    'event_id' => $eventId,
                    'session_id' => $otherSessionId,
                    'actor_user_id' => (int) $owner->id,
                    'agenda_version' => 1,
                    'action' => 'updated',
                    'idempotency_key' => 'direct-cross-history',
                    'request_hash' => str_repeat('a', 64),
                    'changed_fields' => '[]',
                    'affected_session_ids' => json_encode([$otherSessionId], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]),
                'fk_event_session_history_session_tenant',
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_empty_migration_roundtrip_is_reversible(): void
    {
        self::assertSame(0, DB::table('event_session_history')->count());
        self::assertSame(0, DB::table('event_session_speakers')->count());
        self::assertSame(0, DB::table('event_sessions')->count());
        self::assertSame(0, DB::table('event_session_resources')->count());
        self::assertSame(0, DB::table('event_session_registrations')->count());
        self::assertSame(0, DB::table('event_session_registration_history')->count());
        self::assertFalse(DB::table('events')->where('agenda_version', '>', 0)->exists());

        $enterprise = $this->enterpriseMigration();
        $migration = $this->migration();
        $enterprise->down();
        try {
            $migration->down();
            try {
                self::assertFalse(Schema::hasTable('event_sessions'));
                self::assertFalse(Schema::hasTable('event_session_speakers'));
                self::assertFalse(Schema::hasTable('event_session_history'));
                self::assertFalse(Schema::hasColumn('events', 'agenda_version'));
                self::assertFalse(Schema::hasIndex('events', 'uq_events_tenant_id'));
            } finally {
                $migration->up();
            }
        } finally {
            $enterprise->up();
        }

        self::assertTrue(Schema::hasTable('event_sessions'));
        self::assertTrue(Schema::hasTable('event_session_speakers'));
        self::assertTrue(Schema::hasTable('event_session_history'));
        self::assertTrue(Schema::hasColumn('events', 'agenda_version'));
        self::assertTrue(Schema::hasIndex('events', 'uq_events_tenant_id'));
        self::assertTrue(Schema::hasTable('event_session_resources'));
        self::assertTrue(Schema::hasTable('event_session_registrations'));
        self::assertTrue(Schema::hasTable('event_session_registration_history'));
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

    private function constraintExists(string $constraint, string $type): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', $type)
            ->exists();
    }

    private function insertEvent(int $tenantId, int $ownerId, CarbonImmutable $start): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Agenda constraint fixture',
            'description' => 'Agenda constraint fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(3),
            'timezone' => 'UTC',
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
    }

    /** @return array<string,mixed> */
    private function sessionRow(int $eventId, int $ownerId, CarbonImmutable $start): array
    {
        return [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'version' => 1,
            'title' => 'Direct session fixture',
            'description' => null,
            'session_type' => 'session',
            'visibility' => 'public',
            'status' => 'scheduled',
            'starts_at_utc' => $start,
            'ends_at_utc' => $start->addHour(),
            'timezone' => 'UTC',
            'track_name' => null,
            'room_name' => null,
            'room_key' => null,
            'position' => 1,
            'cancellation_reason' => null,
            'created_by' => $ownerId,
            'updated_by' => $ownerId,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @param callable():mixed $operation */
    private function assertConstraintViolation(callable $operation, string $constraint): void
    {
        try {
            $operation();
            self::fail("Expected {$constraint} to reject the direct write.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($constraint, $exception->getMessage());
        }
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    private function enterpriseMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path(
            'migrations/2026_07_11_000065_expand_event_agenda_enterprise.php',
        );

        return $migration;
    }
}
