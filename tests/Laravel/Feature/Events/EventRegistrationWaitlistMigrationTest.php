<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventRegistrationWaitlistMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000017_add_event_registration_waitlist_foundation.php';

    public function test_schema_exposes_scoped_facts_versions_sequences_and_single_use_offer_evidence(): void
    {
        foreach ([
            'event_registrations' => [
                'tenant_id', 'event_id', 'user_id', 'capacity_pool_key', 'allocation_key',
                'registration_state', 'registration_version', 'state_changed_at', 'state_changed_by',
                'invited_at', 'pending_at', 'confirmed_at', 'declined_at', 'cancelled_at',
            ],
            'event_registration_history' => [
                'registration_id', 'registration_version', 'action', 'from_state', 'to_state',
                'idempotency_key', 'metadata',
            ],
            'event_waitlist_entries' => [
                'tenant_id', 'event_id', 'user_id', 'capacity_pool_key', 'allocation_key',
                'queue_state', 'queue_version', 'queue_sequence', 'offered_at', 'offer_expires_at',
                'offer_token_hash', 'offer_token_used_at', 'accepted_at',
                'accepted_registration_id', 'expired_at', 'cancelled_at',
            ],
            'event_waitlist_entry_history' => [
                'waitlist_entry_id', 'queue_version', 'queue_sequence', 'action', 'from_state',
                'to_state', 'idempotency_key', 'metadata',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), "Missing {$table}");
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }

        self::assertSame(
            ['tenant_id', 'event_id', 'user_id', 'capacity_pool_key'],
            $this->indexColumns('event_registrations', 'uq_event_registration_subject'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'user_id', 'capacity_pool_key'],
            $this->indexColumns('event_waitlist_entries', 'uq_event_waitlist_entry_subject'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'capacity_pool_key', 'queue_sequence'],
            $this->indexColumns('event_waitlist_entries', 'uq_event_waitlist_entry_sequence'),
        );
        self::assertSame(
            ['offer_token_hash'],
            $this->indexColumns('event_waitlist_entries', 'uq_event_waitlist_offer_token'),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'trg_event_registration_history_no_update',
                'trg_event_registration_history_no_delete',
                'trg_event_waitlist_history_no_update',
                'trg_event_waitlist_history_no_delete',
            ] as $trigger) {
                self::assertTrue(DB::table('information_schema.TRIGGERS')
                    ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                    ->where('TRIGGER_NAME', $trigger)
                    ->exists(), "Missing trigger {$trigger}");
            }
        }
    }

    public function test_histories_are_immutable_for_update_and_delete(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->event((int) $user->id);
        $now = now();
        $registrationId = (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $user->id,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $registrationHistoryId = (int) DB::table('event_registration_history')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'registration_id' => $registrationId,
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_version' => 1,
            'action' => 'confirmed',
            'to_state' => 'confirmed',
            'idempotency_key' => hash('sha256', 'immutable-registration-' . uniqid()),
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
        $entryId = (int) DB::table('event_waitlist_entries')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'waiting',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $waitlistHistoryId = (int) DB::table('event_waitlist_entry_history')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'waitlist_entry_id' => $entryId,
            'user_id' => $user->id,
            'actor_user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'action' => 'joined',
            'to_state' => 'waiting',
            'idempotency_key' => hash('sha256', 'immutable-waitlist-' . uniqid()),
            'metadata' => json_encode(['schema_version' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        foreach ([
            ['event_registration_history', $registrationHistoryId, 'event_registration_history_immutable'],
            ['event_waitlist_entry_history', $waitlistHistoryId, 'event_waitlist_entry_history_immutable'],
        ] as [$table, $id, $message]) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    if ($operation === 'update') {
                        DB::table($table)->where('id', $id)->update(['action' => 'tampered']);
                    } else {
                        DB::table($table)->where('id', $id)->delete();
                    }
                    self::fail("{$operation} bypassed {$table} immutability");
                } catch (QueryException $exception) {
                    self::assertStringContainsString($message, $exception->getMessage());
                }
            }
        }
    }

    public function test_rollback_refuses_any_operational_registration_or_waitlist_record(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->event((int) $user->id);
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'pending',
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $user->id,
            'pending_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Event registration or waitlist records exist and cannot be rolled back.');
        $this->migration()->down();
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return array_map(static fn (object $row): string => (string) $row->Column_name, $rows);
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    private function event(int $organizerId): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Registration migration fixture',
            'description' => 'Registration migration fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
