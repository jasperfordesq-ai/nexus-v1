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
use LogicException;
use Tests\Laravel\TestCase;

final class EventOfflineCheckinMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000055_create_event_offline_checkin_foundation.php';

    /**
     * Every later migration, in normal up order.
     *
     * A rollback of 000055 is only valid after Laravel has unwound the complete later
     * migration graph. Several later aggregates reference the same composite event or
     * registration keys, and MariaDB may select the offline-owned unique index even
     * when a migration does not name that index explicitly.
     *
     * @var list<string>
     */
    private const DEPENDENT_MIGRATIONS = [
        '2026_07_11_000056_create_event_registration_forms_and_invitations.php',
        '2026_07_11_000057_create_event_ticketing_foundation.php',
        '2026_07_11_000058_create_event_templates_foundation.php',
        '2026_07_11_000059_create_event_analytics_foundation.php',
        '2026_07_11_000060_create_event_safety_foundation.php',
        '2026_07_11_000061_add_event_guardian_delivery_boundary.php',
        '2026_07_11_000062_create_event_broadcast_foundation.php',
        '2026_07_11_000063_expand_event_registration_forms_and_invitations_phase_b.php',
        '2026_07_11_000064_add_event_venue_accessibility.php',
        '2026_07_11_000065_expand_event_agenda_enterprise.php',
        '2026_07_11_000066_add_event_context_to_notification_queue.php',
    ];

    public function test_schema_is_tenant_scoped_versioned_bounded_and_append_only(): void
    {
        self::assertTrue(Schema::hasColumn('events', 'checkin_manifest_version'));
        self::assertSame(
            ['tenant_id', 'id', 'occurrence_key'],
            $this->indexColumns('events', 'uq_events_checkin_occurrence'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'id', 'user_id'],
            $this->indexColumns('event_registrations', 'uq_event_registrations_checkin_scope'),
        );
        self::assertSame(
            ['tenant_id', 'event_id', 'id'],
            $this->indexColumns(
                'event_attendance_activity',
                'uq_event_attendance_activity_checkin_scope',
            ),
        );

        foreach ([
            'event_checkin_credentials' => [
                'tenant_id', 'event_id', 'occurrence_key', 'registration_id', 'user_id',
                'credential_version', 'status', 'active_slot', 'token_hash',
                'token_fingerprint', 'issue_idempotency_hash', 'expires_at',
            ],
            'event_checkin_devices' => [
                'tenant_id', 'event_id', 'occurrence_key', 'public_id', 'registered_by_user_id',
                'device_version', 'status', 'secret_hash', 'secret_fingerprint', 'expires_at',
            ],
            'event_offline_sync_batches' => [
                'tenant_id', 'event_id', 'device_id', 'client_batch_id', 'payload_hash',
                'manifest_version', 'item_count', 'status', 'claim_token_hash',
                'claimed_at', 'claim_expires_at', 'last_released_at', 'dead_lettered_at',
                'terminal_code', 'terminal_reason', 'terminal_by_user_id',
            ],
            'event_offline_sync_items' => [
                'tenant_id', 'event_id', 'batch_id', 'device_id', 'client_nonce', 'operation',
                'observed_at', 'expected_attendance_version', 'credential_fingerprint',
                'credential_hash_reference', 'submitted_payload_hash', 'initial_outcome',
            ],
            'event_offline_sync_decisions' => [
                'tenant_id', 'event_id', 'batch_id', 'item_id', 'decision_version', 'outcome',
                'decision_code', 'attendance_version_before', 'attendance_version_after',
                'idempotency_key_hash', 'request_hash',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), $table);
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ([
            'fk_event_qr_credential_occurrence',
            'fk_event_qr_credential_registration',
            'fk_event_checkin_device_occurrence',
            'fk_event_offline_batch_device',
            'fk_event_offline_item_batch',
            'fk_event_offline_item_registration',
            'fk_event_offline_decision_item',
            'fk_event_offline_decision_actor_tenant',
            'fk_event_offline_decision_attendance',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
        }
        foreach ([
            'chk_event_qr_credential_hash',
            'chk_event_qr_credential_state',
            'chk_event_checkin_device_state',
            'chk_event_offline_batch_count',
            'chk_event_offline_batch_attempts',
            'chk_event_offline_batch_claim',
            'chk_event_offline_batch_terminal',
            'chk_event_offline_item_operation',
            'chk_event_offline_item_hash',
            'chk_event_offline_decision_outcome',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
        }
        foreach ([
            'trg_event_qr_credential_validate',
            'trg_event_qr_credential_update',
            'trg_event_qr_credential_no_delete',
            'trg_event_checkin_device_validate',
            'trg_event_checkin_device_update',
            'trg_event_offline_batch_update',
            'trg_event_offline_batch_no_delete',
            'trg_event_offline_item_no_update',
            'trg_event_offline_item_no_delete',
            'trg_event_offline_decision_validate',
            'trg_event_offline_decision_no_update',
            'trg_event_offline_decision_no_delete',
        ] as $trigger) {
            self::assertTrue($this->triggerExists($trigger), $trigger);
        }
    }

    public function test_direct_writes_cannot_cross_tenants_issue_for_templates_or_bypass_confirmation(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB constraints and triggers are verified on the production driver.');
        }

        DB::beginTransaction();
        try {
            $owner = $this->user($this->testTenantId);
            $attendee = $this->user($this->testTenantId);
            $foreignOwner = $this->user(999);
            $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
            $eventId = $this->event($this->testTenantId, (int) $owner->id, $start, false);
            $templateId = $this->event($this->testTenantId, (int) $owner->id, $start, true);
            $foreignEventId = $this->event(999, (int) $foreignOwner->id, $start, false);
            $registrationId = $this->registration(
                $this->testTenantId,
                $eventId,
                (int) $attendee->id,
                'confirmed',
            );
            $pendingId = $this->registration(
                $this->testTenantId,
                $eventId,
                (int) $owner->id,
                'pending',
            );
            $hash = hash('sha256', 'opaque-migration-fixture');
            $base = $this->credentialRow(
                $this->testTenantId,
                $eventId,
                "occurrence:{$eventId}",
                $registrationId,
                (int) $attendee->id,
                (int) $owner->id,
                $hash,
            );

            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')->insert([
                    ...$base,
                    'token_fingerprint' => str_repeat('f', 16),
                ]),
                'chk_event_qr_credential_hash',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')->insert([
                    ...$base,
                    'event_id' => $templateId,
                    'occurrence_key' => "template:{$templateId}",
                    'issue_idempotency_hash' => hash('sha256', 'template-write'),
                    'token_hash' => hash('sha256', 'template-secret'),
                    'token_fingerprint' => substr(hash('sha256', 'template-secret'), 0, 16),
                ]),
                'event_qr_concrete_occurrence_required',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')->insert([
                    ...$base,
                    'registration_id' => $pendingId,
                    'user_id' => (int) $owner->id,
                    'issue_idempotency_hash' => hash('sha256', 'pending-write'),
                    'token_hash' => hash('sha256', 'pending-secret'),
                    'token_fingerprint' => substr(hash('sha256', 'pending-secret'), 0, 16),
                ]),
                'event_qr_confirmed_registration_required',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')->insert([
                    ...$base,
                    'event_id' => $foreignEventId,
                    'occurrence_key' => "occurrence:{$foreignEventId}",
                    'issue_idempotency_hash' => hash('sha256', 'cross-tenant-write'),
                    'token_hash' => hash('sha256', 'cross-tenant-secret'),
                    'token_fingerprint' => substr(hash('sha256', 'cross-tenant-secret'), 0, 16),
                ]),
                'event_qr_concrete_occurrence_required',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_devices')->insert($this->deviceRow(
                    $this->testTenantId,
                    $templateId,
                    "template:{$templateId}",
                    (int) $owner->id,
                )),
                'event_device_concrete_occurrence_required',
            );

            $credentialId = (int) DB::table('event_checkin_credentials')->insertGetId($base);
            $deviceId = (int) DB::table('event_checkin_devices')->insertGetId($this->deviceRow(
                $this->testTenantId,
                $eventId,
                "occurrence:{$eventId}",
                (int) $owner->id,
            ));
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')
                    ->where('id', $credentialId)
                    ->update([
                        'token_hash' => hash('sha256', 'rewritten-credential'),
                        'token_fingerprint' => substr(
                            hash('sha256', 'rewritten-credential'),
                            0,
                            16,
                        ),
                    ]),
                'event_qr_credential_identity_immutable',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_credentials')
                    ->where('id', $credentialId)
                    ->update(['superseded_by_id' => $credentialId]),
                'event_qr_credential_successor_invalid',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_devices')
                    ->where('id', $deviceId)
                    ->update(['registered_by_user_id' => (int) $attendee->id]),
                'event_checkin_device_identity_immutable',
            );
            $deviceRewrite = hash('sha256', 'rewritten-device-secret');
            $this->assertConstraintViolation(
                fn () => DB::table('event_checkin_devices')
                    ->where('id', $deviceId)
                    ->update([
                        'secret_hash' => $deviceRewrite,
                        'secret_fingerprint' => substr($deviceRewrite, 0, 16),
                    ]),
                'event_checkin_device_rotation_invalid',
            );
            $batchId = (int) DB::table('event_offline_sync_batches')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'event_id' => $eventId,
                'occurrence_key' => "occurrence:{$eventId}",
                'device_id' => $deviceId,
                'submitted_by_user_id' => (int) $owner->id,
                'client_batch_id' => 'direct-batch-immutable',
                'payload_hash' => hash('sha256', 'direct-batch-payload'),
                'manifest_version' => 0,
                'item_count' => 1,
                'status' => 'pending',
                'claim_attempts' => 0,
                'available_at' => now(),
                'accepted_count' => 0,
                'conflict_count' => 0,
                'rejected_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->assertConstraintViolation(
                fn () => DB::table('event_offline_sync_batches')
                    ->where('id', $batchId)
                    ->update(['payload_hash' => hash('sha256', 'rewritten-batch-payload')]),
                'event_offline_batch_evidence_immutable',
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_up_is_idempotent_and_empty_roundtrip_is_reversible(): void
    {
        $migration = $this->migration();
        $migration->up();
        self::assertTrue(Schema::hasTable('event_checkin_credentials'));
        self::assertTrue(Schema::hasTable('event_offline_sync_decisions'));

        foreach ([
            'event_checkin_credentials',
            'event_checkin_devices',
            'event_offline_sync_batches',
            'event_offline_sync_items',
            'event_offline_sync_decisions',
        ] as $table) {
            self::assertSame(0, DB::table($table)->count(), $table);
        }
        self::assertFalse(DB::table('events')->where('checkin_manifest_version', '>', 0)->exists());

        try {
            $migration->down();
            self::fail('Out-of-order rollback removed schema required by a later migration.');
        } catch (LogicException $exception) {
            self::assertSame(
                'event_offline_checkin_rollback_refused_dependents_exist',
                $exception->getMessage(),
            );
        }
        self::assertTrue(Schema::hasColumn('events', 'checkin_manifest_version'));
        self::assertTrue(Schema::hasTable('event_checkin_credentials'));

        $this->runDependentMigrationsDown();
        try {
            $migration->down();
            try {
                self::assertFalse(Schema::hasTable('event_checkin_credentials'));
                self::assertFalse(Schema::hasTable('event_offline_sync_decisions'));
                self::assertFalse(Schema::hasColumn('events', 'checkin_manifest_version'));
                self::assertFalse(Schema::hasIndex('events', 'idx_events_checkin_manifest_version'));
                self::assertFalse(Schema::hasIndex('events', 'uq_events_checkin_occurrence'));
                self::assertFalse(Schema::hasIndex(
                    'event_registrations',
                    'uq_event_registrations_checkin_scope',
                ));
                self::assertFalse(Schema::hasIndex(
                    'event_attendance_activity',
                    'uq_event_attendance_activity_checkin_scope',
                ));
            } finally {
                $migration->up();
            }
        } finally {
            $this->runDependentMigrationsUp();
        }

        self::assertTrue(Schema::hasTable('event_checkin_credentials'));
        self::assertTrue(Schema::hasTable('event_offline_sync_decisions'));
        self::assertTrue(Schema::hasColumn('events', 'checkin_manifest_version'));
        self::assertTrue(Schema::hasIndex(
            'event_attendance_activity',
            'uq_event_attendance_activity_checkin_scope',
        ));
    }

    public function test_rollback_refuses_durable_security_evidence(): void
    {
        $this->runDependentMigrationsDown();
        try {
            DB::beginTransaction();
            try {
                $owner = $this->user($this->testTenantId);
                $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();
                $eventId = $this->event($this->testTenantId, (int) $owner->id, $start, false);
                DB::table('event_checkin_devices')->insert($this->deviceRow(
                    $this->testTenantId,
                    $eventId,
                    "occurrence:{$eventId}",
                    (int) $owner->id,
                ));

                $this->expectException(LogicException::class);
                $this->expectExceptionMessage('event_offline_checkin_rollback_refused_evidence_exists');
                $this->migration()->down();
            } finally {
                DB::rollBack();
            }
        } finally {
            $this->runDependentMigrationsUp();
        }
    }

    private function user(int $tenantId): User
    {
        return User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(
        int $tenantId,
        int $ownerId,
        CarbonImmutable $start,
        bool $template,
    ): int {
        $id = (int) DB::table('events')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'title' => 'Offline check-in migration fixture',
            'description' => 'Offline check-in migration fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(4),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => $template,
            'checkin_manifest_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $id)->update([
            'occurrence_key' => $template ? "template:{$id}" : "occurrence:{$id}",
        ]);

        return $id;
    }

    private function registration(int $tenantId, int $eventId, int $userId, string $state): int
    {
        return (int) DB::table('event_registrations')->insertGetId([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'capacity_pool_key' => 'event',
            'registration_state' => $state,
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => $userId,
            'confirmed_at' => $state === 'confirmed' ? now() : null,
            'pending_at' => $state === 'pending' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function credentialRow(
        int $tenantId,
        int $eventId,
        string $occurrenceKey,
        int $registrationId,
        int $userId,
        int $actorId,
        string $hash,
    ): array {
        return [
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'occurrence_key' => $occurrenceKey,
            'registration_id' => $registrationId,
            'user_id' => $userId,
            'credential_version' => 1,
            'status' => 'active',
            'active_slot' => 1,
            'token_hash' => $hash,
            'token_fingerprint' => substr($hash, 0, 16),
            'issue_idempotency_hash' => hash('sha256', 'migration-idempotency'),
            'issued_by_user_id' => $actorId,
            'issued_at' => now(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @return array<string,mixed> */
    private function deviceRow(
        int $tenantId,
        int $eventId,
        string $occurrenceKey,
        int $actorId,
    ): array {
        $hash = hash('sha256', "migration-device-{$eventId}");

        return [
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'occurrence_key' => $occurrenceKey,
            'public_id' => '00000000-0000-4000-8000-' . str_pad((string) $eventId, 12, '0', STR_PAD_LEFT),
            'label' => 'Migration device',
            'registered_by_user_id' => $actorId,
            'device_version' => 1,
            'status' => 'active',
            'secret_hash' => $hash,
            'secret_fingerprint' => substr($hash, 0, 16),
            'registration_idempotency_hash' => hash('sha256', "migration-device-idem-{$eventId}"),
            'registered_at' => now(),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
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

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    /** @param callable():mixed $operation */
    private function assertConstraintViolation(callable $operation, string $needle): void
    {
        try {
            $operation();
            self::fail("Expected constraint violation {$needle}.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($needle, $exception->getMessage());
        }
    }

    private function migration(): Migration
    {
        $migration = require database_path('migrations/' . self::MIGRATION);
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function runDependentMigrationsDown(): void
    {
        foreach (array_reverse(self::DEPENDENT_MIGRATIONS) as $migrationFile) {
            $this->dependentMigration($migrationFile)->down();
        }
    }

    private function runDependentMigrationsUp(): void
    {
        foreach (self::DEPENDENT_MIGRATIONS as $migrationFile) {
            $this->dependentMigration($migrationFile)->up();
        }
    }

    private function dependentMigration(string $migrationFile): Migration
    {
        $migration = require database_path('migrations/' . $migrationFile);
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }
}
