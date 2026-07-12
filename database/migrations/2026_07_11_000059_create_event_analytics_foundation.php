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
        'trg_event_analytics_fact_update',
        'trg_event_analytics_fact_no_delete',
        'trg_event_analytics_withdraw_no_update',
        'trg_event_analytics_withdraw_no_delete',
        'trg_event_analytics_access_no_update',
        'trg_event_analytics_access_no_delete',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('event_analytics_optional_facts')) {
            Schema::create('event_analytics_optional_facts', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->string('occurrence_key', 191)->nullable();
                $table->string('metric', 64);
                $table->char('deduplication_hash', 64);
                $table->char('request_hash', 64);
                $table->char('subject_hash', 64)->nullable();
                $table->char('pseudonym_key_version', 16)->nullable();
                $table->unsignedBigInteger('consent_record_id')->nullable();
                $table->string('consent_version', 20)->nullable();
                $table->string('source_surface', 32);
                $table->string('client_platform', 32);
                $table->json('dimensions');
                $table->boolean('is_late')->default(false);
                $table->dateTime('occurred_at', 6);
                $table->dateTime('received_at', 6);
                $table->dateTime('retention_due_at', 6);
                $table->string('status', 16)->default('active');
                $table->dateTime('withdrawn_at', 6)->nullable();

                $table->unique(
                    ['tenant_id', 'deduplication_hash'],
                    'uq_event_analytics_fact_dedup',
                );
                $table->unique(
                    ['tenant_id', 'event_id', 'id'],
                    'uq_event_analytics_fact_scope',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'metric', 'status', 'occurred_at', 'id'],
                    'idx_event_analytics_fact_event',
                );
                $table->index(
                    ['tenant_id', 'consent_record_id', 'status', 'id'],
                    'idx_event_analytics_fact_consent',
                );
                $table->index(
                    ['tenant_id', 'retention_due_at', 'status', 'id'],
                    'idx_event_analytics_fact_retention',
                );

                $table->foreign('tenant_id', 'fk_event_analytics_fact_tenant')
                    ->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign(
                    ['tenant_id', 'event_id'],
                    'fk_event_analytics_fact_event',
                )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('event_analytics_withdrawal_runs')) {
            Schema::create('event_analytics_withdrawal_runs', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('actor_user_id');
                $table->char('idempotency_hash', 64);
                $table->char('request_hash', 64);
                $table->unsignedInteger('consent_count');
                $table->unsignedInteger('fact_count');
                $table->dateTime('created_at', 6);

                $table->unique(
                    ['tenant_id', 'idempotency_hash'],
                    'uq_event_analytics_withdraw_key',
                );
                $table->index(
                    ['tenant_id', 'actor_user_id', 'created_at', 'id'],
                    'idx_event_analytics_withdraw_actor',
                );
                $table->foreign('tenant_id', 'fk_event_analytics_withdraw_tenant')
                    ->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign(
                    ['actor_user_id', 'tenant_id'],
                    'fk_event_analytics_withdraw_actor',
                )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('event_analytics_access_audits')) {
            Schema::create('event_analytics_access_audits', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->integer('actor_user_id');
                $table->string('access_scope', 32);
                $table->string('purpose_code', 64);
                $table->char('query_hash', 64);
                $table->unsignedInteger('result_count');
                $table->unsignedInteger('suppressed_count')->default(0);
                $table->unsignedSmallInteger('privacy_threshold')->default(5);
                $table->dateTime('created_at', 6);

                $table->index(
                    ['tenant_id', 'event_id', 'created_at', 'id'],
                    'idx_event_analytics_access_event',
                );
                $table->index(
                    ['tenant_id', 'actor_user_id', 'created_at', 'id'],
                    'idx_event_analytics_access_actor',
                );
                $table->foreign('tenant_id', 'fk_event_analytics_access_tenant')
                    ->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign(
                    ['tenant_id', 'event_id'],
                    'fk_event_analytics_access_event',
                )->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
                $table->foreign(
                    ['actor_user_id', 'tenant_id'],
                    'fk_event_analytics_access_actor',
                )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            });
        }

        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        foreach ([
            'event_analytics_access_audits',
            'event_analytics_withdrawal_runs',
            'event_analytics_optional_facts',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('event_analytics_rollback_refused_durable_evidence');
            }
        }

        $this->dropTriggers();
        Schema::dropIfExists('event_analytics_access_audits');
        Schema::dropIfExists('event_analytics_withdrawal_runs');
        Schema::dropIfExists('event_analytics_optional_facts');
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_analytics_optional_facts' => [
                'chk_event_analytics_fact_metric' => "`metric` IN ('event_viewed','registration_started')",
                'chk_event_analytics_fact_hashes' => "`deduplication_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
                'chk_event_analytics_fact_time' => '`occurred_at` <= DATE_ADD(`received_at`, INTERVAL 5 MINUTE) AND `retention_due_at` > `occurred_at`',
                'chk_event_analytics_fact_state' => "((`status` = 'active' AND `subject_hash` IS NOT NULL AND `pseudonym_key_version` IS NOT NULL AND `consent_record_id` IS NOT NULL AND `consent_version` IS NOT NULL AND `withdrawn_at` IS NULL) OR (`status` = 'withdrawn' AND `subject_hash` IS NULL AND `pseudonym_key_version` IS NULL AND `consent_record_id` IS NULL AND `consent_version` IS NULL AND `withdrawn_at` IS NOT NULL))",
                'chk_event_analytics_fact_subject' => "`status` = 'withdrawn' OR (`subject_hash` REGEXP '^[0-9a-f]{64}$' AND `pseudonym_key_version` REGEXP '^[0-9a-f]{16}$')",
                'chk_event_analytics_fact_dimensions' => "(`status` = 'withdrawn' AND JSON_LENGTH(`dimensions`) = 0) OR (`status` = 'active' AND JSON_LENGTH(`dimensions`) = 2 AND JSON_CONTAINS_PATH(`dimensions`, 'all', '$.source_surface', '$.client_platform') AND JSON_UNQUOTE(JSON_EXTRACT(`dimensions`, '$.source_surface')) IN ('event_list','event_detail','calendar','search','notification','direct','registration') AND JSON_UNQUOTE(JSON_EXTRACT(`dimensions`, '$.client_platform')) IN ('react_web','accessible_web','native_mobile'))",
            ],
            'event_analytics_withdrawal_runs' => [
                'chk_event_analytics_withdraw_hash' => "`idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_analytics_access_audits' => [
                'chk_event_analytics_access_scope' => "`access_scope` IN ('organizer_summary','tenant_summary','csv_export')",
                'chk_event_analytics_access_threshold' => '`privacy_threshold` >= 5 AND `suppressed_count` <= `result_count`',
                'chk_event_analytics_access_hash' => "`query_hash` REGEXP '^[0-9a-f]{64}$'",
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
            'trg_event_analytics_fact_update',
            'BEFORE UPDATE ON `event_analytics_optional_facts` FOR EACH ROW BEGIN '
                . "IF OLD.`status` <> 'active' OR NEW.`status` <> 'withdrawn' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_analytics_fact_immutable'; END IF; "
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`metric` <=> NEW.`metric`) OR NOT (OLD.`deduplication_hash` <=> NEW.`deduplication_hash`) OR NOT (OLD.`request_hash` <=> NEW.`request_hash`) OR NOT (OLD.`source_surface` <=> NEW.`source_surface`) OR NOT (OLD.`client_platform` <=> NEW.`client_platform`) OR NOT (OLD.`is_late` <=> NEW.`is_late`) OR NOT (OLD.`occurred_at` <=> NEW.`occurred_at`) OR NOT (OLD.`received_at` <=> NEW.`received_at`) OR NOT (OLD.`retention_due_at` <=> NEW.`retention_due_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_analytics_fact_evidence_immutable'; END IF; "
                . "IF NEW.`subject_hash` IS NOT NULL OR NEW.`pseudonym_key_version` IS NOT NULL OR NEW.`consent_record_id` IS NOT NULL OR NEW.`consent_version` IS NOT NULL OR NEW.`withdrawn_at` IS NULL OR JSON_LENGTH(NEW.`dimensions`) <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_analytics_fact_withdrawal_invalid'; END IF; END",
        );

        foreach ([
            'trg_event_analytics_fact_no_delete' => ['event_analytics_optional_facts', 'DELETE', 'event_analytics_fact_delete_forbidden'],
            'trg_event_analytics_withdraw_no_update' => ['event_analytics_withdrawal_runs', 'UPDATE', 'event_analytics_withdrawal_immutable'],
            'trg_event_analytics_withdraw_no_delete' => ['event_analytics_withdrawal_runs', 'DELETE', 'event_analytics_withdrawal_immutable'],
            'trg_event_analytics_access_no_update' => ['event_analytics_access_audits', 'UPDATE', 'event_analytics_access_immutable'],
            'trg_event_analytics_access_no_delete' => ['event_analytics_access_audits', 'DELETE', 'event_analytics_access_immutable'],
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
};
