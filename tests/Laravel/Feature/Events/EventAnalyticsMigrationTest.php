<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Laravel\TestCase;

final class EventAnalyticsMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000059_create_event_analytics_foundation.php';

    public function test_schema_has_consent_pseudonym_retention_and_audit_evidence(): void
    {
        foreach ([
            'tenant_id',
            'event_id',
            'occurrence_key',
            'metric',
            'deduplication_hash',
            'request_hash',
            'subject_hash',
            'pseudonym_key_version',
            'consent_record_id',
            'consent_version',
            'source_surface',
            'client_platform',
            'dimensions',
            'is_late',
            'occurred_at',
            'received_at',
            'retention_due_at',
            'status',
            'withdrawn_at',
        ] as $column) {
            self::assertTrue(
                Schema::hasColumn('event_analytics_optional_facts', $column),
                $column,
            );
        }

        self::assertSame(
            ['tenant_id', 'deduplication_hash'],
            $this->indexColumns(
                'event_analytics_optional_facts',
                'uq_event_analytics_fact_dedup',
            ),
        );
        self::assertSame(
            ['tenant_id', 'idempotency_hash'],
            $this->indexColumns(
                'event_analytics_withdrawal_runs',
                'uq_event_analytics_withdraw_key',
            ),
        );

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'chk_event_analytics_fact_metric',
                'chk_event_analytics_fact_hashes',
                'chk_event_analytics_fact_time',
                'chk_event_analytics_fact_state',
                'chk_event_analytics_fact_subject',
                'chk_event_analytics_fact_dimensions',
                'chk_event_analytics_access_threshold',
            ] as $constraint) {
                self::assertTrue($this->constraintExists($constraint), $constraint);
            }
            foreach ([
                'trg_event_analytics_fact_update',
                'trg_event_analytics_fact_no_delete',
                'trg_event_analytics_withdraw_no_update',
                'trg_event_analytics_withdraw_no_delete',
                'trg_event_analytics_access_no_update',
                'trg_event_analytics_access_no_delete',
            ] as $trigger) {
                self::assertTrue($this->triggerExists($trigger), $trigger);
            }
        }
    }

    public function test_database_rejects_unknown_dimensions_and_evidence_rewrites(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB constraints are verified on the production driver.');
        }

        DB::beginTransaction();
        try {
            [$user, $event, $consentId] = $this->subjects();
            $row = $this->factRow((int) $event->id, $consentId);
            $this->assertConstraintViolation(
                fn () => DB::table('event_analytics_optional_facts')->insert([
                    ...$row,
                    'dimensions' => json_encode([
                        'source_surface' => 'event_detail',
                        'client_platform' => 'react_web',
                        'member_email' => 'must-not-be-stored@example.test',
                    ], JSON_THROW_ON_ERROR),
                ]),
                'chk_event_analytics_fact_dimensions',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_analytics_optional_facts')->insert([
                    ...$row,
                    'metric' => 'registration_completed',
                ]),
                'chk_event_analytics_fact_metric',
            );

            $factId = (int) DB::table('event_analytics_optional_facts')->insertGetId($row);
            $this->assertConstraintViolation(
                fn () => DB::table('event_analytics_optional_facts')
                    ->where('id', $factId)
                    ->update(['source_surface' => 'search']),
                'event_analytics_fact_immutable',
            );
            $this->assertConstraintViolation(
                fn () => DB::table('event_analytics_optional_facts')
                    ->where('id', $factId)
                    ->delete(),
                'event_analytics_fact_delete_forbidden',
            );

            self::assertSame((int) $user->id, (int) DB::table('cookie_consents')
                ->where('id', $consentId)
                ->value('user_id'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_rollback_refuses_durable_evidence_before_mutating_schema(): void
    {
        DB::beginTransaction();
        try {
            [, $event, $consentId] = $this->subjects();
            DB::table('event_analytics_optional_facts')->insert(
                $this->factRow((int) $event->id, $consentId),
            );

            try {
                $this->migration()->down();
                self::fail('Durable analytics evidence was rolled back.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'event_analytics_rollback_refused_durable_evidence',
                    $exception->getMessage(),
                );
            }
            self::assertTrue(Schema::hasTable('event_analytics_optional_facts'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_empty_migration_roundtrip_is_reversible(): void
    {
        self::assertSame(0, DB::table('event_analytics_optional_facts')->count());
        self::assertSame(0, DB::table('event_analytics_withdrawal_runs')->count());
        self::assertSame(0, DB::table('event_analytics_access_audits')->count());

        $migration = $this->migration();
        $migration->down();
        try {
            self::assertFalse(Schema::hasTable('event_analytics_optional_facts'));
            self::assertFalse(Schema::hasTable('event_analytics_withdrawal_runs'));
            self::assertFalse(Schema::hasTable('event_analytics_access_audits'));
        } finally {
            $migration->up();
        }

        self::assertTrue(Schema::hasTable('event_analytics_optional_facts'));
        self::assertTrue(Schema::hasTable('event_analytics_withdrawal_runs'));
        self::assertTrue(Schema::hasTable('event_analytics_access_audits'));
    }

    /** @return array{User,Event,int} */
    private function subjects(): array
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $event = Event::factory()->forTenant($this->testTenantId)->create([
            'user_id' => (int) $user->id,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'is_recurring_template' => false,
        ]);
        $consentId = (int) DB::table('cookie_consents')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'session_id' => 'analytics-migration-' . bin2hex(random_bytes(6)),
            'essential' => 1,
            'analytics' => 1,
            'functional' => 0,
            'marketing' => 0,
            'consent_version' => '1.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $event, $consentId];
    }

    /** @return array<string,mixed> */
    private function factRow(int $eventId, int $consentId): array
    {
        $now = now()->startOfSecond();

        return [
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'occurrence_key' => null,
            'metric' => 'event_viewed',
            'deduplication_hash' => hash('sha256', uniqid('analytics-dedup-', true)),
            'request_hash' => hash('sha256', uniqid('analytics-request-', true)),
            'subject_hash' => str_repeat('a', 64),
            'pseudonym_key_version' => str_repeat('b', 16),
            'consent_record_id' => $consentId,
            'consent_version' => '1.0',
            'source_surface' => 'event_detail',
            'client_platform' => 'react_web',
            'dimensions' => json_encode([
                'source_surface' => 'event_detail',
                'client_platform' => 'react_web',
            ], JSON_THROW_ON_ERROR),
            'is_late' => false,
            'occurred_at' => $now,
            'received_at' => $now,
            'retention_due_at' => $now->copy()->addYear(),
            'status' => 'active',
            'withdrawn_at' => null,
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

    private function constraintExists(string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $constraint)
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
            self::fail("Expected {$needle} to reject the write.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($needle, $exception->getMessage());
        }
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
