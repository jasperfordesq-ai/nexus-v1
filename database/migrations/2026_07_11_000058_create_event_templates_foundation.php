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
    /** @var list<string> */
    private const TRIGGERS = [
        'trg_event_template_update',
        'trg_event_template_no_delete',
        'trg_event_template_version_no_update',
        'trg_event_template_version_no_delete',
        'trg_event_template_materialize_validate',
        'trg_event_template_materialize_no_update',
        'trg_event_template_materialize_no_delete',
        'trg_event_template_audit_no_update',
        'trg_event_template_audit_no_delete',
    ];

    /** @var list<string> */
    private const TABLES = [
        'event_template_audit',
        'event_template_materializations',
        'event_template_versions',
        'event_templates',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('events')) {
            return;
        }

        $this->createTemplates();
        $this->createVersions();
        $this->createMaterializations();
        $this->createAudit();
        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        if ($this->hasDependentSchema()) {
            throw new LogicException('event_templates_rollback_refused_dependents_exist');
        }
        if ($this->containsDurableEvidence()) {
            throw new LogicException('event_templates_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createTemplates(): void
    {
        if (Schema::hasTable('event_templates')) {
            return;
        }

        Schema::create('event_templates', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->uuid('public_id');
            $table->integer('source_event_id');
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status', 16)->default('active');
            $table->integer('created_by_user_id');
            $table->integer('archived_by_user_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('archive_reason', 500)->nullable();
            $table->timestamps();

            $table->unique('public_id', 'uq_event_template_public');
            $table->unique(['tenant_id', 'id'], 'uq_event_template_tenant_id');
            $table->index(
                ['tenant_id', 'status', 'updated_at', 'id'],
                'idx_event_template_status',
            );
            $table->index(
                ['tenant_id', 'source_event_id', 'created_at', 'id'],
                'idx_event_template_source',
            );

            $table->foreign('tenant_id', 'fk_event_template_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'source_event_id'],
                'fk_event_template_source_event',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['created_by_user_id', 'tenant_id'],
                'fk_event_template_creator',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['archived_by_user_id', 'tenant_id'],
                'fk_event_template_archiver',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createVersions(): void
    {
        if (Schema::hasTable('event_template_versions')) {
            return;
        }

        Schema::create('event_template_versions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->unsignedBigInteger('template_id');
            $table->integer('source_event_id');
            $table->unsignedInteger('version_number');
            $table->unsignedSmallInteger('schema_version');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->json('copied_fields');
            $table->json('skipped_fields');
            $table->unsignedBigInteger('source_lifecycle_version')->default(0);
            $table->unsignedBigInteger('source_calendar_sequence')->default(0);
            $table->timestamp('source_updated_at')->nullable();
            $table->integer('captured_by_user_id');
            $table->char('capture_idempotency_hash', 64);
            $table->char('capture_request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'template_id', 'version_number'],
                'uq_event_template_version',
            );
            $table->unique(
                ['tenant_id', 'capture_idempotency_hash'],
                'uq_event_template_capture_key',
            );
            $table->unique(
                ['tenant_id', 'template_id', 'id', 'version_number', 'source_event_id'],
                'uq_event_template_version_provenance',
            );
            $table->index(
                ['tenant_id', 'source_event_id', 'created_at', 'id'],
                'idx_event_template_version_source',
            );

            $table->foreign('tenant_id', 'fk_event_template_version_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'template_id'],
                'fk_event_template_version_template',
            )->references(['tenant_id', 'id'])->on('event_templates')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'source_event_id'],
                'fk_event_template_version_source',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['captured_by_user_id', 'tenant_id'],
                'fk_event_template_version_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createMaterializations(): void
    {
        if (Schema::hasTable('event_template_materializations')) {
            return;
        }

        Schema::create('event_template_materializations', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('template_version_id');
            $table->unsignedInteger('template_version_number');
            $table->integer('source_event_id');
            $table->integer('created_event_id');
            $table->integer('materialized_by_user_id');
            $table->unsignedSmallInteger('schema_version');
            $table->char('template_payload_hash', 64);
            $table->char('effective_payload_hash', 64);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->dateTime('schedule_start_utc');
            $table->dateTime('schedule_end_utc')->nullable();
            $table->string('schedule_timezone', 64);
            $table->boolean('schedule_all_day')->default(false);
            $table->json('override_fields');
            $table->boolean('federation_normalized')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_template_materialize_key',
            );
            $table->unique(
                ['tenant_id', 'created_event_id'],
                'uq_event_template_materialized_event',
            );
            $table->index(
                ['tenant_id', 'template_id', 'template_version_number', 'created_at', 'id'],
                'idx_event_template_materialized_version',
            );

            $table->foreign('tenant_id', 'fk_event_template_material_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'template_id'],
                'fk_event_template_material_template',
            )->references(['tenant_id', 'id'])->on('event_templates')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'template_id',
                    'template_version_id',
                    'template_version_number',
                    'source_event_id',
                ],
                'fk_event_template_material_version',
            )->references([
                'tenant_id',
                'template_id',
                'id',
                'version_number',
                'source_event_id',
            ])->on('event_template_versions')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'source_event_id'],
                'fk_event_template_material_source',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'created_event_id'],
                'fk_event_template_material_created',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['materialized_by_user_id', 'tenant_id'],
                'fk_event_template_material_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createAudit(): void
    {
        if (Schema::hasTable('event_template_audit')) {
            return;
        }

        Schema::create('event_template_audit', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('template_version_id')->nullable();
            $table->unsignedInteger('template_version_number');
            $table->integer('source_event_id');
            $table->integer('materialized_event_id')->nullable();
            $table->string('action', 24);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_template_audit_key',
            );
            $table->index(
                ['tenant_id', 'template_id', 'created_at', 'id'],
                'idx_event_template_audit_template',
            );
            $table->index(
                ['tenant_id', 'source_event_id', 'created_at', 'id'],
                'idx_event_template_audit_source',
            );

            $table->foreign('tenant_id', 'fk_event_template_audit_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'template_id'],
                'fk_event_template_audit_template',
            )->references(['tenant_id', 'id'])->on('event_templates')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'template_id',
                    'template_version_id',
                    'template_version_number',
                    'source_event_id',
                ],
                'fk_event_template_audit_version',
            )->references([
                'tenant_id',
                'template_id',
                'id',
                'version_number',
                'source_event_id',
            ])->on('event_template_versions')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'source_event_id'],
                'fk_event_template_audit_source',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'materialized_event_id'],
                'fk_event_template_audit_created',
            )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['actor_user_id', 'tenant_id'],
                'fk_event_template_audit_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_templates' => [
                'chk_event_template_version' => '`current_version` > 0',
                'chk_event_template_status' => "`status` IN ('active','archived')",
                'chk_event_template_archive' => "((`status` = 'active' AND `archived_by_user_id` IS NULL AND `archived_at` IS NULL AND `archive_reason` IS NULL) OR (`status` = 'archived' AND `archived_by_user_id` IS NOT NULL AND `archived_at` IS NOT NULL AND `archive_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`archive_reason`)) > 0))",
            ],
            'event_template_versions' => [
                'chk_event_template_version_number' => '`version_number` > 0 AND `schema_version` > 0',
                'chk_event_template_payload_hash' => "`payload_hash` REGEXP '^[0-9a-f]{64}$'",
                'chk_event_template_capture_hashes' => "`capture_idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `capture_request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_template_materializations' => [
                'chk_event_template_material_hashes' => "`template_payload_hash` REGEXP '^[0-9a-f]{64}$' AND `effective_payload_hash` REGEXP '^[0-9a-f]{64}$' AND `idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
                'chk_event_template_material_schedule' => '`schedule_end_utc` IS NULL OR `schedule_end_utc` > `schedule_start_utc`',
                'chk_event_template_material_safe' => '`template_version_number` > 0 AND `schema_version` > 0 AND `federation_normalized` = 1',
            ],
            'event_template_audit' => [
                'chk_event_template_audit_action' => "`action` IN ('captured','revised','archived','materialized')",
                'chk_event_template_audit_hashes' => "`idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
                'chk_event_template_audit_version' => '`template_version_number` > 0',
            ],
        ];

        foreach ($checks as $table => $tableChecks) {
            foreach ($tableChecks as $name => $expression) {
                if (! $this->constraintExists($table, $name)) {
                    DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
                }
            }
        }
    }

    private function installTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createTrigger(
            'trg_event_template_update',
            'BEFORE UPDATE ON `event_templates` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`public_id` <=> NEW.`public_id`) OR NOT (OLD.`source_event_id` <=> NEW.`source_event_id`) OR NOT (OLD.`created_by_user_id` <=> NEW.`created_by_user_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_identity_immutable'; END IF; "
                . "IF OLD.`status` = 'archived' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_archived_immutable'; END IF; "
                . "IF NEW.`current_version` < OLD.`current_version` OR NEW.`current_version` > OLD.`current_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_version_transition_invalid'; END IF; "
                . "IF NEW.`status` = 'active' AND (NEW.`current_version` <> OLD.`current_version` + 1 OR NOT (OLD.`archived_by_user_id` <=> NEW.`archived_by_user_id`) OR NOT (OLD.`archived_at` <=> NEW.`archived_at`) OR NOT (OLD.`archive_reason` <=> NEW.`archive_reason`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_revision_transition_invalid'; END IF; "
                . "IF NEW.`status` = 'archived' AND (NEW.`current_version` <> OLD.`current_version` OR OLD.`status` <> 'active') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_archive_transition_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_template_materialize_validate',
            'BEFORE INSERT ON `event_template_materializations` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`created_event_id` AND `user_id` = NEW.`materialized_by_user_id` AND `status` = 'draft' AND `publication_status` = 'draft' AND `operational_status` = 'scheduled' AND `is_recurring_template` = 0 AND `parent_event_id` IS NULL AND `series_id` IS NULL AND `federated_visibility` = 'none') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_template_materialized_event_invalid'; END IF; END",
        );

        foreach ([
            'trg_event_template_no_delete' => ['event_templates', 'DELETE', 'event_template_delete_forbidden'],
            'trg_event_template_version_no_update' => ['event_template_versions', 'UPDATE', 'event_template_version_immutable'],
            'trg_event_template_version_no_delete' => ['event_template_versions', 'DELETE', 'event_template_version_immutable'],
            'trg_event_template_materialize_no_update' => ['event_template_materializations', 'UPDATE', 'event_template_materialization_immutable'],
            'trg_event_template_materialize_no_delete' => ['event_template_materializations', 'DELETE', 'event_template_materialization_immutable'],
            'trg_event_template_audit_no_update' => ['event_template_audit', 'UPDATE', 'event_template_audit_immutable'],
            'trg_event_template_audit_no_delete' => ['event_template_audit', 'DELETE', 'event_template_audit_immutable'],
        ] as $name => [$table, $operation, $message]) {
            $this->createTrigger(
                $name,
                "BEFORE {$operation} ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
            );
        }
    }

    private function createTrigger(string $name, string $definition): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$definition}");
        }
    }

    private function dropTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    private function triggerExists(string $name): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $name)
            ->exists();
    }

    private function constraintExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }

    private function containsDurableEvidence(): bool
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasDependentSchema(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->whereIn('REFERENCED_TABLE_NAME', self::TABLES)
            ->whereNotIn('TABLE_NAME', self::TABLES)
            ->exists();
    }
};
