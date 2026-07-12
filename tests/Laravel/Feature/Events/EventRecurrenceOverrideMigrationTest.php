<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventRecurrenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventRecurrenceOverrideMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_12_000068_add_event_recurrence_override_fields.php';

    public function test_expand_schema_is_nullable_tenant_unique_and_actor_type_matches_users(): void
    {
        foreach ([
            'recurrence_id',
            'is_recurrence_exception',
            'recurrence_override_fields',
            'recurrence_override_version',
            'recurrence_override_updated_at',
            'recurrence_override_updated_by',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('events', $column), "Missing events.{$column}");
        }
        $identity = DB::selectOne("SHOW COLUMNS FROM `events` WHERE Field = 'recurrence_id'");
        self::assertNotNull($identity);
        self::assertSame('YES', $identity->{'Null'});
        self::assertSame('varchar(32)', strtolower((string) $identity->{'Type'}));
        $actor = DB::selectOne("SHOW COLUMNS FROM `events` WHERE Field = 'recurrence_override_updated_by'");
        self::assertNotNull($actor);
        self::assertStringStartsWith('int', strtolower((string) $actor->{'Type'}));
        self::assertStringNotContainsString('unsigned', strtolower((string) $actor->{'Type'}));

        $index = DB::select(
            "SHOW INDEX FROM `events` WHERE Key_name = 'uq_events_tenant_parent_recurrence_id'",
        );
        self::assertSame(
            ['tenant_id', 'parent_event_id', 'recurrence_id'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $index),
        );
        self::assertSame([0, 0, 0], array_map(static fn (object $row): int => (int) $row->Non_unique, $index));
    }

    public function test_partial_rerun_backfills_only_identity_proven_rows_and_is_idempotent(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $templateId = $this->insertEvent((int) $organizer->id, [
            'is_recurring_template' => 1,
            'occurrence_key' => null,
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);
        $service = app(EventRecurrenceService::class);
        $safeRecurrenceId = '20300110T090000Z';
        $safeId = $this->insertEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'start_time' => '2030-01-10 09:00:00',
            'end_time' => '2030-01-10 10:00:00',
            'occurrence_date' => '2030-01-10',
            'occurrence_key' => $service->occurrenceKey($this->testTenantId, $templateId, $safeRecurrenceId),
            'recurrence_id' => null,
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);
        $originalMovedRecurrenceId = '20300117T090000Z';
        $movedId = $this->insertEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'start_time' => '2030-01-18 09:00:00',
            'end_time' => '2030-01-18 10:00:00',
            'occurrence_date' => '2030-01-18',
            'occurrence_key' => $service->occurrenceKey(
                $this->testTenantId,
                $templateId,
                $originalMovedRecurrenceId,
            ),
            'recurrence_id' => null,
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);

        $this->migration()->up();
        $this->migration()->up();

        self::assertSame($safeRecurrenceId, DB::table('events')->where('id', $safeId)->value('recurrence_id'));
        self::assertNull(DB::table('events')->where('id', $movedId)->value('recurrence_id'));
    }

    public function test_database_rejects_noncanonical_or_mutated_recurrence_identity(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $templateId = $this->insertEvent((int) $organizer->id, [
            'is_recurring_template' => 1,
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);

        try {
            $this->insertEvent((int) $organizer->id, [
                'parent_event_id' => $templateId,
                'occurrence_key' => 'invalid-recurrence-id-fixture',
                'recurrence_id' => '2030-01-10T09:00:00Z',
            ]);
            self::fail('A noncanonical recurrence identity was accepted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_recurrence_id_invalid', $exception->getMessage());
        }

        $eventId = $this->insertEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'occurrence_key' => 'immutable-recurrence-id-fixture',
            'recurrence_id' => '20300110T090000Z',
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);
        try {
            DB::table('events')->where('id', $eventId)->update(['recurrence_id' => '20300117T090000Z']);
            self::fail('A persisted recurrence identity was mutable.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_recurrence_id_immutable', $exception->getMessage());
        }
    }

    public function test_preflight_rejects_partial_artifacts_before_attempting_ddl(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Artifact preflight covers the MariaDB production contract.');
        }

        DB::statement('ALTER TABLE `events` DROP INDEX `idx_events_recurrence_exception`');
        try {
            $this->migration()->up();
            self::fail('A partial recurrence override migration was silently completed.');
        } catch (LogicException $exception) {
            self::assertSame('event_recurrence_override_preflight_partial_artifacts', $exception->getMessage());
        } finally {
            DB::statement(
                'ALTER TABLE `events` ADD INDEX `idx_events_recurrence_exception` '
                . '(`tenant_id`, `parent_event_id`, `is_recurrence_exception`)',
            );
        }
    }

    public function test_database_rejects_inconsistent_override_evidence(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $templateId = $this->insertEvent((int) $organizer->id, ['is_recurring_template' => 1]);
        $eventId = $this->insertEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'occurrence_key' => 'override-evidence-fixture',
        ]);

        try {
            DB::table('events')->where('id', $eventId)->update([
                'is_recurrence_exception' => 1,
                'recurrence_override_fields' => json_encode(['title'], JSON_THROW_ON_ERROR),
                'recurrence_override_version' => 1,
            ]);
            self::fail('Incomplete override evidence was accepted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_recurrence_override_evidence_invalid', $exception->getMessage());
        }

        $v2TemplateId = $this->insertEvent((int) $organizer->id, [
            'is_recurring_template' => 1,
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);
        $v2OccurrenceId = $this->insertEvent((int) $organizer->id, [
            'parent_event_id' => $v2TemplateId,
            'occurrence_key' => 'override-allowlist-fixture',
            'recurrence_id' => '20300110T090000Z',
            'recurrence_engine' => EventRecurrenceService::ENGINE,
            'recurrence_engine_version' => EventRecurrenceService::ENGINE_VERSION,
        ]);
        try {
            DB::table('events')->where('id', $v2OccurrenceId)->update([
                'is_recurrence_exception' => 1,
                'recurrence_override_fields' => json_encode(['server_owned_field'], JSON_THROW_ON_ERROR),
                'recurrence_override_version' => 1,
                'recurrence_override_updated_at' => now(),
                'recurrence_override_updated_by' => $organizer->id,
            ]);
            self::fail('A non-allowlisted recurrence override field was accepted.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('event_recurrence_override_evidence_invalid', $exception->getMessage());
        }
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }

    /** @param array<string,mixed> $overrides */
    private function insertEvent(int $organizerId, array $overrides = []): int
    {
        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Recurrence identity migration fixture',
            'description' => 'Safe partial rollout and recurrence identity fixture.',
            'start_time' => '2030-01-03 09:00:00',
            'end_time' => '2030-01-03 10:00:00',
            'status' => 'draft',
            'publication_status' => 'draft',
            'operational_status' => 'scheduled',
            'is_recurring_template' => 0,
            'is_recurrence_exception' => 0,
            'recurrence_override_fields' => null,
            'recurrence_override_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
