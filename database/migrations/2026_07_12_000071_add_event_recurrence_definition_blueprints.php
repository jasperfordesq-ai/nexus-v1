<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BLUEPRINTS = 'event_recurrence_definition_blueprints';
    private const APPLICATIONS = 'event_recurrence_definition_applications';

    /** @var list<string> */
    private const TRIGGERS = [
        'trg_ev_rec_def_bp_no_update',
        'trg_ev_rec_def_bp_no_delete',
        'trg_ev_rec_def_app_no_update',
        'trg_ev_rec_def_app_no_delete',
    ];

    /**
     * Expand-only, immutable definition manifests and per-occurrence evidence.
     * A partial MariaDB DDL attempt is never guessed or silently completed.
     */
    public function up(): void
    {
        foreach (['tenants', 'events', 'event_recurrence_rules'] as $required) {
            if (! Schema::hasTable($required)) {
                throw new LogicException("event_recurrence_definition_prerequisite_missing:{$required}");
            }
        }

        $existingTables = array_values(array_filter(
            [self::BLUEPRINTS, self::APPLICATIONS],
            static fn (string $table): bool => Schema::hasTable($table),
        ));
        $existingTriggers = array_values(array_filter(
            self::TRIGGERS,
            fn (string $trigger): bool => $this->triggerExists($trigger),
        ));
        if ($existingTables !== [] || $existingTriggers !== []) {
            if (count($existingTables) === 2
                && count($existingTriggers) === count(self::TRIGGERS)
                && $this->completeArtifacts()) {
                return;
            }

            throw new LogicException('event_recurrence_definition_preflight_partial_artifacts');
        }

        Schema::create(self::BLUEPRINTS, static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('root_event_id');
            $table->integer('source_event_id');
            $table->string('source_recurrence_id', 32);
            $table->string('source_occurrence_key', 191);
            $table->unsignedInteger('blueprint_version');
            $table->unsignedSmallInteger('schema_version');
            $table->string('effective_from_recurrence_id', 32);
            $table->json('selected_sections');
            $table->json('manifest');
            $table->char('manifest_hash', 64);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            // Deliberately no FK: immutable audit evidence must remain valid
            // after a user's erasure, without retaining profile data.
            $table->integer('captured_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'root_event_id', 'blueprint_version'],
                'uq_ev_rec_def_bp_version',
            );
            $table->unique(
                ['tenant_id', 'root_event_id', 'id'],
                'uq_ev_rec_def_bp_scope',
            );
            $table->unique(
                ['tenant_id', 'root_event_id', 'idempotency_hash'],
                'uq_ev_rec_def_bp_idempotency',
            );
            $table->index(
                ['tenant_id', 'root_event_id', 'effective_from_recurrence_id', 'blueprint_version'],
                'idx_ev_rec_def_bp_effective',
            );
            $table->foreign('tenant_id', 'fk_ev_rec_def_bp_tenant')
                ->references('id')->on('tenants');
            $table->foreign(
                ['tenant_id', 'root_event_id'],
                'fk_ev_rec_def_bp_root',
            )->references(['tenant_id', 'id'])->on('events');
            $table->foreign(
                ['tenant_id', 'source_event_id'],
                'fk_ev_rec_def_bp_source',
            )->references(['tenant_id', 'id'])->on('events');
        });

        Schema::create(self::APPLICATIONS, static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('root_event_id');
            $table->integer('event_id');
            $table->string('recurrence_id', 32);
            $table->unsignedBigInteger('blueprint_id');
            $table->unsignedInteger('blueprint_version');
            $table->char('manifest_hash', 64);
            $table->char('application_hash', 64);
            $table->json('applied_counts');
            $table->string('status', 16)->default('applied');
            // Erasure-safe pseudonymous evidence; intentionally no user FK.
            $table->integer('applied_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'event_id'], 'uq_ev_rec_def_app_event');
            $table->unique(
                ['tenant_id', 'root_event_id', 'recurrence_id'],
                'uq_ev_rec_def_app_recurrence',
            );
            $table->index(
                ['tenant_id', 'root_event_id', 'blueprint_version', 'created_at'],
                'idx_ev_rec_def_app_root',
            );
            $table->foreign('tenant_id', 'fk_ev_rec_def_app_tenant')
                ->references('id')->on('tenants');
            $table->foreign(
                ['tenant_id', 'root_event_id'],
                'fk_ev_rec_def_app_root',
            )->references(['tenant_id', 'id'])->on('events');
            $table->foreign(
                ['tenant_id', 'event_id'],
                'fk_ev_rec_def_app_event',
            )->references(['tenant_id', 'id'])->on('events');
            $table->foreign(
                ['tenant_id', 'root_event_id', 'blueprint_id'],
                'fk_ev_rec_def_app_blueprint',
            )->references(['tenant_id', 'root_event_id', 'id'])->on(self::BLUEPRINTS);
        });

        DB::statement(
            'ALTER TABLE `' . self::BLUEPRINTS . '` '
            . 'ADD CONSTRAINT `chk_ev_rec_def_bp_versions` CHECK (`blueprint_version` > 0 AND `schema_version` > 0), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_bp_source_id` CHECK (`source_recurrence_id` REGEXP "^[0-9]{8}T[0-9]{6}Z$"), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_bp_effective_id` CHECK (`effective_from_recurrence_id` REGEXP "^[0-9]{8}T[0-9]{6}Z$"), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_bp_hashes` CHECK (`manifest_hash` REGEXP "^[0-9a-f]{64}$" '
            . 'AND `idempotency_hash` REGEXP "^[0-9a-f]{64}$" AND `request_hash` REGEXP "^[0-9a-f]{64}$")',
        );
        DB::statement(
            'ALTER TABLE `' . self::APPLICATIONS . '` '
            . 'ADD CONSTRAINT `chk_ev_rec_def_app_version` CHECK (`blueprint_version` > 0), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_app_recurrence` CHECK (`recurrence_id` REGEXP "^[0-9]{8}T[0-9]{6}Z$"), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_app_hashes` CHECK (`manifest_hash` REGEXP "^[0-9a-f]{64}$" '
            . 'AND `application_hash` REGEXP "^[0-9a-f]{64}$"), '
            . 'ADD CONSTRAINT `chk_ev_rec_def_app_status` CHECK (`status` = "applied")',
        );

        $this->createImmutableTrigger(
            'trg_ev_rec_def_bp_no_update',
            self::BLUEPRINTS,
            'UPDATE',
            'event_recurrence_definition_blueprint_immutable',
        );
        $this->createImmutableTrigger(
            'trg_ev_rec_def_bp_no_delete',
            self::BLUEPRINTS,
            'DELETE',
            'event_recurrence_definition_blueprint_immutable',
        );
        $this->createImmutableTrigger(
            'trg_ev_rec_def_app_no_update',
            self::APPLICATIONS,
            'UPDATE',
            'event_recurrence_definition_application_immutable',
        );
        $this->createImmutableTrigger(
            'trg_ev_rec_def_app_no_delete',
            self::APPLICATIONS,
            'DELETE',
            'event_recurrence_definition_application_immutable',
        );
    }

    public function down(): void
    {
        foreach ([self::APPLICATIONS, self::BLUEPRINTS] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('event_recurrence_definition_rollback_refused_evidence_exists');
            }
        }
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
        Schema::dropIfExists(self::APPLICATIONS);
        Schema::dropIfExists(self::BLUEPRINTS);
    }

    private function completeArtifacts(): bool
    {
        $requiredColumns = [
            self::BLUEPRINTS => [
                'id', 'tenant_id', 'root_event_id', 'source_event_id',
                'source_recurrence_id', 'source_occurrence_key',
                'blueprint_version', 'schema_version',
                'effective_from_recurrence_id', 'selected_sections',
                'manifest', 'manifest_hash', 'idempotency_hash', 'request_hash',
                'captured_by_user_id', 'created_at',
            ],
            self::APPLICATIONS => [
                'id', 'tenant_id', 'root_event_id', 'event_id', 'recurrence_id',
                'blueprint_id', 'blueprint_version', 'manifest_hash',
                'application_hash', 'applied_counts', 'status',
                'applied_by_user_id', 'created_at',
            ],
        ];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return false;
                }
            }
        }

        $indexes = [
            'uq_ev_rec_def_bp_version' => [self::BLUEPRINTS, 0],
            'uq_ev_rec_def_bp_scope' => [self::BLUEPRINTS, 0],
            'uq_ev_rec_def_bp_idempotency' => [self::BLUEPRINTS, 0],
            'idx_ev_rec_def_bp_effective' => [self::BLUEPRINTS, 1],
            'uq_ev_rec_def_app_event' => [self::APPLICATIONS, 0],
            'uq_ev_rec_def_app_recurrence' => [self::APPLICATIONS, 0],
            'idx_ev_rec_def_app_root' => [self::APPLICATIONS, 1],
        ];
        foreach ($indexes as $index => [$table, $nonUnique]) {
            if (! DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->where('non_unique', $nonUnique)
                ->exists()) {
                return false;
            }
        }

        $constraints = [
            'fk_ev_rec_def_bp_tenant' => [self::BLUEPRINTS, 'FOREIGN KEY'],
            'fk_ev_rec_def_bp_root' => [self::BLUEPRINTS, 'FOREIGN KEY'],
            'fk_ev_rec_def_bp_source' => [self::BLUEPRINTS, 'FOREIGN KEY'],
            'fk_ev_rec_def_app_tenant' => [self::APPLICATIONS, 'FOREIGN KEY'],
            'fk_ev_rec_def_app_root' => [self::APPLICATIONS, 'FOREIGN KEY'],
            'fk_ev_rec_def_app_event' => [self::APPLICATIONS, 'FOREIGN KEY'],
            'fk_ev_rec_def_app_blueprint' => [self::APPLICATIONS, 'FOREIGN KEY'],
            'chk_ev_rec_def_bp_versions' => [self::BLUEPRINTS, 'CHECK'],
            'chk_ev_rec_def_bp_source_id' => [self::BLUEPRINTS, 'CHECK'],
            'chk_ev_rec_def_bp_effective_id' => [self::BLUEPRINTS, 'CHECK'],
            'chk_ev_rec_def_bp_hashes' => [self::BLUEPRINTS, 'CHECK'],
            'chk_ev_rec_def_app_version' => [self::APPLICATIONS, 'CHECK'],
            'chk_ev_rec_def_app_recurrence' => [self::APPLICATIONS, 'CHECK'],
            'chk_ev_rec_def_app_hashes' => [self::APPLICATIONS, 'CHECK'],
            'chk_ev_rec_def_app_status' => [self::APPLICATIONS, 'CHECK'],
        ];
        foreach ($constraints as $constraint => [$table, $type]) {
            if (! DB::table('information_schema.table_constraints')
                ->where('constraint_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('constraint_name', $constraint)
                ->where('constraint_type', $type)
                ->exists()) {
                return false;
            }
        }

        $triggers = [
            'trg_ev_rec_def_bp_no_update' => [self::BLUEPRINTS, 'UPDATE'],
            'trg_ev_rec_def_bp_no_delete' => [self::BLUEPRINTS, 'DELETE'],
            'trg_ev_rec_def_app_no_update' => [self::APPLICATIONS, 'UPDATE'],
            'trg_ev_rec_def_app_no_delete' => [self::APPLICATIONS, 'DELETE'],
        ];
        foreach ($triggers as $trigger => [$table, $operation]) {
            if (! DB::table('information_schema.triggers')
                ->where('trigger_schema', DB::getDatabaseName())
                ->where('trigger_name', $trigger)
                ->where('event_object_table', $table)
                ->where('event_manipulation', $operation)
                ->exists()) {
                return false;
            }
        }

        return true;
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.triggers')
            ->where('trigger_schema', DB::getDatabaseName())
            ->where('trigger_name', $trigger)
            ->exists();
    }

    private function createImmutableTrigger(
        string $name,
        string $table,
        string $operation,
        string $message,
    ): void {
        DB::unprepared(
            "CREATE TRIGGER `{$name}` BEFORE {$operation} ON `{$table}` "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
        );
    }
};
