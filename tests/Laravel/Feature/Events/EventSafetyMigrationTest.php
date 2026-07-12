<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventSafetyAcknowledgementService;
use App\Services\EventSafetyRequirementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventSafetyMigrationTest extends TestCase
{
    private const MIGRATION = '2026_07_11_000060_create_event_safety_foundation.php';

    public function test_schema_is_tenant_scoped_versioned_append_only_and_privacy_safe(): void
    {
        foreach ([
            'event_safety_requirements' => [
                'tenant_id', 'event_id', 'occurrence_key', 'revision', 'current_version',
                'published_version', 'status', 'created_by_user_id', 'updated_by_user_id',
                'published_by_user_id', 'published_at', 'archived_by_user_id', 'archived_at',
            ],
            'event_safety_requirement_versions' => [
                'tenant_id', 'event_id', 'requirements_id', 'version_number', 'minimum_age',
                'guardian_consent_required', 'minor_age_threshold', 'code_of_conduct_required',
                'code_of_conduct_text', 'code_of_conduct_text_version',
                'code_of_conduct_text_hash', 'eligibility_policy_metadata',
                'eligibility_policy_hash', 'captured_by_user_id', 'idempotency_hash',
                'request_hash',
            ],
            'event_safety_requirement_history' => [
                'tenant_id', 'event_id', 'requirements_id', 'requirements_revision',
                'requirements_version_id', 'requirements_version_number', 'action',
                'actor_user_id', 'idempotency_hash', 'request_hash', 'metadata',
            ],
            'event_safety_code_acknowledgements' => [
                'tenant_id', 'event_id', 'requirements_id', 'requirements_version_id',
                'requirements_version_number', 'user_id', 'evidence_sequence', 'action',
                'referenced_acknowledgement_id', 'text_version', 'text_hash',
                'acknowledged_at', 'actor_user_id', 'idempotency_hash', 'request_hash',
            ],
            'event_guardian_consents' => [
                'tenant_id', 'event_id', 'occurrence_key', 'requirements_id',
                'requirements_version_id', 'requirements_version_number', 'minor_user_id',
                'guardian_email_ciphertext', 'guardian_identity_ciphertext',
                'guardian_email_blind_hash', 'relationship_code', 'consent_text',
                'consent_text_version', 'consent_text_hash', 'policy_binding_hash',
                'token_hash', 'status', 'active_slot', 'consent_version',
                'requested_by_user_id', 'request_idempotency_hash', 'request_hash',
                'token_consumed_at', 'granted_at', 'withdrawn_by_user_id', 'withdrawn_at',
            ],
            'event_guardian_consent_history' => [
                'tenant_id', 'event_id', 'consent_id', 'minor_user_id', 'consent_version',
                'status', 'action', 'actor_type', 'actor_user_id', 'idempotency_hash',
                'request_hash', 'evidence',
            ],
            'event_participation_denials' => [
                'tenant_id', 'event_id', 'occurrence_key', 'user_id', 'decision',
                'reason_code', 'status', 'active_slot', 'decision_version',
                'reviewed_by_user_id', 'effective_from', 'effective_until',
                'create_idempotency_hash', 'create_request_hash', 'withdrawn_by_user_id',
                'withdrawn_at', 'expired_by_user_id', 'expired_at',
            ],
            'event_participation_denial_history' => [
                'tenant_id', 'event_id', 'denial_id', 'user_id', 'decision_version',
                'decision', 'reason_code', 'status', 'action', 'reviewer_user_id',
                'effective_from', 'effective_until', 'idempotency_hash', 'request_hash',
            ],
        ] as $table => $columns) {
            self::assertTrue(Schema::hasTable($table), $table);
            foreach ($columns as $column) {
                self::assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
        }

        self::assertFalse(Schema::hasTable('event_safeguarding_incidents'));
        self::assertFalse(Schema::hasTable('event_safeguarding_incident_notes'));

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'fk_event_safety_requirements_event',
            'fk_event_safety_version_requirements',
            'fk_event_safety_history_version',
            'fk_event_safety_coc_reference',
            'fk_event_guardian_consent_event',
            'fk_event_guardian_consent_version',
            'fk_event_guardian_history_consent',
            'fk_event_participation_denial_event',
            'fk_event_denial_history_denial',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'FOREIGN KEY'), $constraint);
        }
        foreach ([
            'chk_event_safety_requirements_state',
            'chk_event_safety_version_ages',
            'chk_event_safety_version_coc',
            'chk_event_safety_coc_action',
            'chk_event_guardian_consent_state',
            'chk_event_guardian_history_actor',
            'chk_event_participation_denial_state',
            'chk_event_denial_history_reason',
        ] as $constraint) {
            self::assertTrue($this->constraintExists($constraint, 'CHECK'), $constraint);
        }
        foreach ([
            'trg_event_safety_requirements_insert',
            'trg_event_safety_requirements_update',
            'trg_event_safety_requirements_no_delete',
            'trg_event_safety_version_insert',
            'trg_event_safety_version_no_update',
            'trg_event_safety_version_no_delete',
            'trg_event_safety_history_no_update',
            'trg_event_safety_history_no_delete',
            'trg_event_safety_coc_insert',
            'trg_event_safety_coc_no_update',
            'trg_event_safety_coc_no_delete',
            'trg_event_guardian_consent_update',
            'trg_event_guardian_consent_no_delete',
            'trg_event_guardian_history_no_update',
            'trg_event_guardian_history_no_delete',
            'trg_event_participation_denial_insert',
            'trg_event_participation_denial_update',
            'trg_event_participation_denial_no_delete',
            'trg_event_denial_history_no_update',
            'trg_event_denial_history_no_delete',
        ] as $trigger) {
            self::assertTrue($this->triggerExists($trigger), $trigger);
        }

        $indexedColumns = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'event_guardian_consents')
            ->pluck('COLUMN_NAME')
            ->map(static fn (mixed $column): string => (string) $column)
            ->all();
        self::assertNotContains('guardian_email_ciphertext', $indexedColumns);
        self::assertNotContains('guardian_identity_ciphertext', $indexedColumns);
    }

    public function test_database_rejects_safety_evidence_rewrites_and_recurring_templates(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB constraints and immutability triggers are production invariants.');
        }

        DB::beginTransaction();
        try {
            $owner = $this->user();
            $participant = $this->user();
            $eventId = $this->event((int) $owner->id);
            $published = $this->publishedRequirements($eventId, $owner);
            $acknowledgement = (new EventSafetyAcknowledgementService())->acknowledge(
                $eventId,
                $participant,
                (string) $published['version']->code_of_conduct_text_version,
                (string) $published['version']->code_of_conduct_text_hash,
                'safety-migration-acknowledge',
            );

            $this->assertRejected(
                fn () => DB::table('event_safety_requirement_versions')
                    ->where('id', (int) $published['version']->id)
                    ->update(['minimum_age' => 99]),
                'event_safety_requirement_version_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_safety_code_acknowledgements')
                    ->where('id', (int) $acknowledgement['evidence']->id)
                    ->update(['text_hash' => str_repeat('a', 64)]),
                'event_safety_code_acknowledgement_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_safety_code_acknowledgements')
                    ->where('id', (int) $acknowledgement['evidence']->id)
                    ->delete(),
                'event_safety_code_acknowledgement_immutable',
            );
            $this->assertRejected(
                fn () => DB::table('event_safety_requirements')
                    ->where('id', (int) $published['requirements']->id)
                    ->delete(),
                'event_safety_requirements_delete_forbidden',
            );

            $recurringId = $this->event((int) $owner->id, true);
            $recurring = DB::table('events')->where('id', $recurringId)->first();
            $this->assertRejected(
                fn () => DB::table('event_safety_requirements')->insert([
                    'tenant_id' => $this->testTenantId,
                    'event_id' => $recurringId,
                    'occurrence_key' => (string) $recurring->occurrence_key,
                    'revision' => 1,
                    'current_version' => 1,
                    'status' => 'draft',
                    'created_by_user_id' => (int) $owner->id,
                    'updated_by_user_id' => (int) $owner->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
                'event_safety_concrete_event_required',
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_rollback_is_refused_after_durable_safety_evidence_exists(): void
    {
        DB::beginTransaction();
        try {
            $owner = $this->user();
            $eventId = $this->event((int) $owner->id);
            $this->publishedRequirements($eventId, $owner);

            $migration = $this->migration();
            $evidence = new \ReflectionMethod($migration, 'containsDurableEvidence');
            self::assertTrue($evidence->invoke($migration));

            try {
                $migration->down();
                self::fail('A migration containing event-safety evidence must refuse rollback.');
            } catch (LogicException $exception) {
                self::assertSame(
                    'event_safety_rollback_refused_dependents_exist',
                    $exception->getMessage(),
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    private function user(array $overrides = [], int $tenantId = 2): User
    {
        return User::factory()->forTenant($tenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function event(int $ownerId, bool $recurring = false): int
    {
        $start = CarbonImmutable::now('UTC')->addMonths(2)->startOfHour();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Safety migration fixture',
            'description' => 'Safety migration fixture.',
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
            'is_recurring_template' => $recurring,
            'occurrence_key' => 'safety-migration:' . bin2hex(random_bytes(12)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{requirements:\App\Models\EventSafetyRequirement,version:\App\Models\EventSafetyRequirementVersion,changed:bool} */
    private function publishedRequirements(int $eventId, User $owner): array
    {
        $service = new EventSafetyRequirementService();
        $draft = $service->saveDraft(
            $eventId,
            $owner,
            [
                'minimum_age' => null,
                'guardian_consent_required' => false,
                'minor_age_threshold' => null,
                'code_of_conduct_required' => true,
                'code_of_conduct_text' => 'Treat every participant with dignity.',
                'code_of_conduct_text_version' => 'migration-v1',
            ],
            0,
            'safety-migration-draft:' . $eventId,
        );

        return $service->publish(
            $eventId,
            $owner,
            (int) $draft['requirements']->revision,
            (int) $draft['version']->version_number,
            'safety-migration-publish:' . $eventId,
        );
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
