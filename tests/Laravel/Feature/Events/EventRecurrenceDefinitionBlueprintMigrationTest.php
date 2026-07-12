<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Services\EventRecurrenceDefinitionBlueprintService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventRecurrenceDefinitionBlueprintMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_12_000071_add_event_recurrence_definition_blueprints.php';

    public function test_schema_is_immutable_tenant_safe_and_actor_erasure_safe(): void
    {
        self::assertTrue(Schema::hasTable(EventRecurrenceDefinitionBlueprintService::BLUEPRINTS));
        self::assertTrue(Schema::hasTable(EventRecurrenceDefinitionBlueprintService::APPLICATIONS));
        self::assertTrue(app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable());

        foreach ([
            'uq_ev_rec_def_bp_version' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'uq_ev_rec_def_bp_scope' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'uq_ev_rec_def_bp_idempotency' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'idx_ev_rec_def_bp_effective' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'uq_ev_rec_def_app_event' => EventRecurrenceDefinitionBlueprintService::APPLICATIONS,
            'uq_ev_rec_def_app_recurrence' => EventRecurrenceDefinitionBlueprintService::APPLICATIONS,
            'idx_ev_rec_def_app_root' => EventRecurrenceDefinitionBlueprintService::APPLICATIONS,
        ] as $index => $table) {
            self::assertTrue(DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists(), "Missing {$index}");
        }
        foreach ([
            'trg_ev_rec_def_bp_no_update' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'trg_ev_rec_def_bp_no_delete' => EventRecurrenceDefinitionBlueprintService::BLUEPRINTS,
            'trg_ev_rec_def_app_no_update' => EventRecurrenceDefinitionBlueprintService::APPLICATIONS,
            'trg_ev_rec_def_app_no_delete' => EventRecurrenceDefinitionBlueprintService::APPLICATIONS,
        ] as $trigger => $table) {
            self::assertTrue(DB::table('information_schema.triggers')
                ->where('trigger_schema', DB::getDatabaseName())
                ->where('trigger_name', $trigger)
                ->where('event_object_table', $table)
                ->exists(), "Missing {$trigger}");
        }

        self::assertSame(0, DB::table('information_schema.key_column_usage')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where(static function ($actor): void {
                $actor->where(function ($blueprint): void {
                    $blueprint->where('table_name', EventRecurrenceDefinitionBlueprintService::BLUEPRINTS)
                        ->where('column_name', 'captured_by_user_id');
                })->orWhere(function ($application): void {
                    $application->where('table_name', EventRecurrenceDefinitionBlueprintService::APPLICATIONS)
                        ->where('column_name', 'applied_by_user_id');
                });
            })
            ->whereNotNull('referenced_table_name')
            ->count());
    }

    public function test_partial_runtime_artifact_fails_closed_and_migration_refuses_to_guess(): void
    {
        // This source-identity check was intentionally outside the original
        // minimal probe; removing it proves the complete artifact contract is
        // now enforced rather than accepting a superficially complete table.
        DB::statement(
            'ALTER TABLE `event_recurrence_definition_blueprints` '
            . 'DROP CONSTRAINT `chk_ev_rec_def_bp_source_id`',
        );
        try {
            self::assertFalse(app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable());
            $this->migration()->up();
            self::fail('A partial blueprint schema was silently completed.');
        } catch (LogicException $exception) {
            self::assertSame(
                'event_recurrence_definition_preflight_partial_artifacts',
                $exception->getMessage(),
            );
        } finally {
            DB::statement(
                'ALTER TABLE `event_recurrence_definition_blueprints` '
                . 'ADD CONSTRAINT `chk_ev_rec_def_bp_source_id` '
                . 'CHECK (`source_recurrence_id` REGEXP "^[0-9]{8}T[0-9]{6}Z$")',
            );
        }
        self::assertTrue(app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable());

        $decoy = 'event_recurrence_definition_probe_decoy';
        Schema::create($decoy, static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('blueprint_version');
            $table->index('blueprint_version', 'idx_ev_rec_def_bp_effective');
        });
        DB::statement(
            'ALTER TABLE `event_recurrence_definition_blueprints` '
            . 'DROP INDEX `idx_ev_rec_def_bp_effective`',
        );
        try {
            self::assertFalse(app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable());
            $this->migration()->up();
            self::fail('An index on the wrong table satisfied the blueprint schema probe.');
        } catch (LogicException $exception) {
            self::assertSame(
                'event_recurrence_definition_preflight_partial_artifacts',
                $exception->getMessage(),
            );
        } finally {
            DB::statement(
                'ALTER TABLE `event_recurrence_definition_blueprints` '
                . 'ADD INDEX `idx_ev_rec_def_bp_effective` '
                . '(`tenant_id`, `root_event_id`, `effective_from_recurrence_id`, `blueprint_version`)',
            );
            Schema::dropIfExists($decoy);
        }
        self::assertTrue(app(EventRecurrenceDefinitionBlueprintService::class)->schemaAvailable());
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}
