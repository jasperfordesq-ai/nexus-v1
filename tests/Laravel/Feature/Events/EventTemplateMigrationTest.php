<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventTemplateMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000058_create_event_templates_foundation.php';

    public function test_schema_is_tenant_scoped_versioned_and_append_only(): void
    {
        foreach ([
            'event_templates' => [
                'tenant_id', 'public_id', 'source_event_id', 'current_version', 'status',
                'created_by_user_id', 'archived_by_user_id', 'archived_at', 'archive_reason',
            ],
            'event_template_versions' => [
                'tenant_id', 'template_id', 'source_event_id', 'version_number',
                'schema_version', 'payload', 'payload_hash', 'copied_fields', 'skipped_fields',
                'source_lifecycle_version', 'source_calendar_sequence', 'captured_by_user_id',
                'capture_idempotency_hash', 'capture_request_hash',
            ],
            'event_template_materializations' => [
                'tenant_id', 'template_id', 'template_version_id', 'template_version_number',
                'source_event_id', 'created_event_id', 'materialized_by_user_id',
                'template_payload_hash', 'effective_payload_hash', 'idempotency_hash',
                'request_hash', 'schedule_start_utc', 'schedule_end_utc', 'schedule_timezone',
                'schedule_all_day', 'override_fields', 'federation_normalized',
            ],
            'event_template_audit' => [
                'tenant_id', 'template_id', 'template_version_id', 'template_version_number',
                'source_event_id', 'materialized_event_id', 'action', 'actor_user_id',
                'idempotency_hash', 'request_hash', 'metadata', 'created_at',
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

        self::assertSame(
            ['tenant_id', 'template_id', 'version_number'],
            $this->indexColumns('event_template_versions', 'uq_event_template_version'),
        );
        self::assertSame(
            ['tenant_id', 'idempotency_hash'],
            $this->indexColumns(
                'event_template_materializations',
                'uq_event_template_materialize_key',
            ),
        );
        self::assertSame(
            ['tenant_id', 'created_event_id'],
            $this->indexColumns(
                'event_template_materializations',
                'uq_event_template_materialized_event',
            ),
        );
        foreach ([
            'fk_event_template_source_event',
            'fk_event_template_version_template',
            'fk_event_template_version_source',
            'fk_event_template_material_version',
            'fk_event_template_material_created',
            'fk_event_template_audit_version',
            'fk_event_template_audit_actor',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
        }
        foreach ([
            'chk_event_template_status',
            'chk_event_template_archive',
            'chk_event_template_payload_hash',
            'chk_event_template_material_hashes',
            'chk_event_template_material_safe',
            'chk_event_template_audit_action',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
        }
        foreach ([
            'trg_event_template_update',
            'trg_event_template_no_delete',
            'trg_event_template_version_no_update',
            'trg_event_template_version_no_delete',
            'trg_event_template_materialize_validate',
            'trg_event_template_materialize_no_update',
            'trg_event_template_materialize_no_delete',
            'trg_event_template_audit_no_update',
            'trg_event_template_audit_no_delete',
        ] as $trigger) {
            self::assertTrue($this->triggerExists($trigger), $trigger);
        }
    }

    public function test_database_rejects_template_identity_history_and_version_rewrites(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB immutability triggers are production-driver invariants.');
        }

        DB::beginTransaction();
        try {
            $owner = $this->owner();
            $sourceEventId = $this->sourceEvent((int) $owner->id);
            $capture = (new EventTemplateService())->capture(
                $sourceEventId,
                $owner,
                'migration-immutability-capture',
            );
            $templateId = (int) $capture['template']->id;
            $versionId = (int) $capture['version']->id;
            $auditId = (int) DB::table('event_template_audit')
                ->where('template_id', $templateId)
                ->value('id');

            $this->assertRejected(
                fn () => DB::table('event_templates')
                    ->where('id', $templateId)
                    ->update(['source_event_id' => $sourceEventId + 1]),
                'event_template_identity_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_template_versions')
                    ->where('id', $versionId)
                    ->update(['payload_hash' => str_repeat('a', 64)]),
                'event_template_version_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_template_versions')->where('id', $versionId)->delete(),
                'event_template_version_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_template_audit')->where('id', $auditId)->delete(),
                'event_template_audit_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_templates')->where('id', $templateId)->delete(),
                'event_template_delete_forbidden',
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_rollback_is_refused_after_durable_template_evidence_exists(): void
    {
        DB::beginTransaction();
        try {
            $owner = $this->owner();
            $sourceEventId = $this->sourceEvent((int) $owner->id);
            (new EventTemplateService())->capture(
                $sourceEventId,
                $owner,
                'migration-rollback-capture',
            );

            try {
                $this->migration()->down();
                self::fail('A migration containing template evidence must refuse rollback.');
            } catch (LogicException $exception) {
                self::assertSame(
                    'event_templates_rollback_refused_evidence_exists',
                    $exception->getMessage(),
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    private function owner(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function sourceEvent(int $ownerId): int
    {
        $start = CarbonImmutable::now('UTC')->addMonth()->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Template migration fixture',
            'description' => 'Template migration fixture.',
            'start_time' => $start,
            'end_time' => $start->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'test',
            'all_day' => false,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 1,
            'calendar_sequence' => 1,
            'is_recurring_template' => false,
            'occurrence_key' => 'template-migration:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param callable():mixed $operation */
    private function assertRejected(callable $operation, string $reason): void
    {
        try {
            $operation();
            self::fail("Expected {$reason} to reject the direct write.");
        } catch (QueryException $exception) {
            self::assertStringContainsString($reason, $exception->getMessage());
        }
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

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
