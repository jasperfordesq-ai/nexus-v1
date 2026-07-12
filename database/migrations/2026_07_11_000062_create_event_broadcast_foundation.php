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
    private const TABLES = [
        'event_broadcast_delivery_attempts',
        'event_broadcast_deliveries',
        'event_broadcast_history',
        'event_broadcasts',
    ];

    /** @var list<string> */
    private const TRIGGERS = [
        'trg_event_broadcast_no_delete',
        'trg_event_broadcast_lifecycle_guard',
        'trg_event_broadcast_history_no_update',
        'trg_event_broadcast_history_no_delete',
        'trg_event_broadcast_delivery_lifecycle_guard',
        'trg_event_broadcast_delivery_no_delete',
        'trg_event_broadcast_attempt_no_update',
        'trg_event_broadcast_attempt_no_delete',
    ];

    public function up(): void
    {
        foreach ([
            'tenants',
            'users',
            'events',
            'event_registrations',
            'event_waitlist_entries',
            'event_attendance',
        ] as $required) {
            if (! Schema::hasTable($required)) {
                throw new LogicException("event_broadcast_prerequisite_missing:{$required}");
            }
        }
        foreach ([
            'tenants' => ['id'],
            'users' => ['id', 'tenant_id'],
            'events' => ['id', 'tenant_id', 'occurrence_key', 'is_recurring_template'],
            'event_registrations' => ['tenant_id', 'event_id', 'user_id', 'registration_state'],
            'event_waitlist_entries' => ['tenant_id', 'event_id', 'user_id', 'queue_state', 'offer_expires_at'],
            'event_attendance' => ['tenant_id', 'event_id', 'user_id', 'attendance_status'],
        ] as $table => $columns) {
            if (! Schema::hasColumns($table, $columns)) {
                throw new LogicException("event_broadcast_prerequisite_columns_missing:{$table}");
            }
        }

        $this->createBroadcasts();
        $this->createHistory();
        $this->createDeliveries();
        $this->createAttempts();
        $this->installChecks();
        $this->installImmutabilityTriggers();
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('event_broadcast_rollback_refused_evidence_exists');
            }
        }

        $this->dropTriggers();
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createBroadcasts(): void
    {
        if (Schema::hasTable('event_broadcasts')) {
            return;
        }

        Schema::create('event_broadcasts', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->string('variant', 32);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('broadcast_version')->default(1);
            $table->json('audience_segments');
            $table->json('channels');
            $table->longText('body');
            $table->char('content_hash', 64);
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivery_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('suppressed_count')->default(0);
            $table->unsignedInteger('dead_letter_count')->default(0);
            $table->integer('created_by_user_id');
            $table->integer('updated_by_user_id');
            $table->integer('scheduled_by_user_id')->nullable();
            $table->integer('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_broadcast_scope_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'status', 'created_at', 'id'],
                'idx_event_broadcast_event_status',
            );
            $table->index(
                ['status', 'scheduled_at', 'id'],
                'idx_event_broadcast_schedule',
            );

            $table->foreign('tenant_id', 'fk_event_broadcast_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_broadcast_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['created_by_user_id', 'tenant_id'],
                'fk_event_broadcast_creator',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['updated_by_user_id', 'tenant_id'],
                'fk_event_broadcast_updater',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['scheduled_by_user_id', 'tenant_id'],
                'fk_event_broadcast_scheduler',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['cancelled_by_user_id', 'tenant_id'],
                'fk_event_broadcast_canceller',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createHistory(): void
    {
        if (Schema::hasTable('event_broadcast_history')) {
            return;
        }

        Schema::create('event_broadcast_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedInteger('broadcast_version');
            $table->string('action', 16);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->integer('actor_user_id')->nullable();
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->char('content_hash', 64);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'broadcast_id', 'broadcast_version'],
                'uq_event_broadcast_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_broadcast_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'broadcast_id', 'created_at', 'id'],
                'idx_event_broadcast_history_event',
            );

            $table->foreign(
                ['tenant_id', 'event_id', 'broadcast_id'],
                'fk_event_broadcast_history_parent',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_broadcasts')->restrictOnDelete();
            $table->foreign(
                ['actor_user_id', 'tenant_id'],
                'fk_event_broadcast_history_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createDeliveries(): void
    {
        if (Schema::hasTable('event_broadcast_deliveries')) {
            return;
        }

        Schema::create('event_broadcast_deliveries', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedInteger('frozen_broadcast_version');
            $table->integer('recipient_user_id');
            $table->string('channel', 16);
            $table->char('delivery_key', 64);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('next_attempt_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('preference_reason', 100)->nullable();
            $table->string('suppression_reason', 100)->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_evidence_id', 255)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'delivery_key'],
                'uq_event_broadcast_delivery_key',
            );
            $table->unique(
                ['broadcast_id', 'recipient_user_id', 'channel'],
                'uq_event_broadcast_recipient_channel',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'broadcast_id', 'id'],
                'uq_event_broadcast_delivery_scope',
            );
            $table->index(
                ['status', 'available_at', 'next_attempt_at', 'id'],
                'idx_event_broadcast_delivery_claim',
            );
            $table->index(
                ['tenant_id', 'broadcast_id', 'status', 'id'],
                'idx_event_broadcast_delivery_status',
            );

            $table->foreign(
                ['tenant_id', 'event_id', 'broadcast_id'],
                'fk_event_broadcast_delivery_parent',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_broadcasts')->restrictOnDelete();
            $table->foreign(
                ['recipient_user_id', 'tenant_id'],
                'fk_event_broadcast_delivery_recipient',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createAttempts(): void
    {
        if (Schema::hasTable('event_broadcast_delivery_attempts')) {
            return;
        }

        Schema::create('event_broadcast_delivery_attempts', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('broadcast_id');
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->string('outcome', 16);
            $table->string('provider', 50)->nullable();
            $table->string('provider_evidence_id', 255)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'delivery_id', 'attempt_number', 'outcome'],
                'uq_event_broadcast_attempt_outcome',
            );
            $table->index(
                ['tenant_id', 'broadcast_id', 'created_at', 'id'],
                'idx_event_broadcast_attempt_parent',
            );

            $table->foreign(
                ['tenant_id', 'event_id', 'broadcast_id', 'delivery_id'],
                'fk_event_broadcast_attempt_delivery',
            )->references(['tenant_id', 'event_id', 'broadcast_id', 'id'])
                ->on('event_broadcast_deliveries')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            ['event_broadcasts', 'chk_event_broadcast_status', "`status` IN ('draft','scheduled','sending','sent','cancelled','failed')"],
            ['event_broadcasts', 'chk_event_broadcast_variant', "`variant` IN ('announcement','follow_up','review_request')"],
            ['event_broadcast_history', 'chk_event_broadcast_history_action', "`action` IN ('created','revised','scheduled','sending','sent','cancelled','failed','retried')"],
            ['event_broadcast_deliveries', 'chk_event_broadcast_delivery_channel', "`channel` IN ('email','in_app','push')"],
            ['event_broadcast_deliveries', 'chk_event_broadcast_delivery_status', "`status` IN ('pending','processing','retry','delivered','suppressed','dead_letter','cancelled')"],
            ['event_broadcast_delivery_attempts', 'chk_event_broadcast_attempt_outcome', "`outcome` IN ('processing','delivered','suppressed','retry','dead_letter','cancelled')"],
        ];

        foreach ($checks as [$table, $name, $expression]) {
            if (! $this->constraintExists($table, $name)) {
                DB::statement(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})",
                );
            }
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }

    private function installImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $definitions = [
            'trg_event_broadcast_no_delete' => 'BEFORE DELETE ON `event_broadcasts`',
            'trg_event_broadcast_lifecycle_guard' => 'BEFORE UPDATE ON `event_broadcasts`',
            'trg_event_broadcast_history_no_update' => 'BEFORE UPDATE ON `event_broadcast_history`',
            'trg_event_broadcast_history_no_delete' => 'BEFORE DELETE ON `event_broadcast_history`',
            'trg_event_broadcast_delivery_lifecycle_guard' => 'BEFORE UPDATE ON `event_broadcast_deliveries`',
            'trg_event_broadcast_delivery_no_delete' => 'BEFORE DELETE ON `event_broadcast_deliveries`',
            'trg_event_broadcast_attempt_no_update' => 'BEFORE UPDATE ON `event_broadcast_delivery_attempts`',
            'trg_event_broadcast_attempt_no_delete' => 'BEFORE DELETE ON `event_broadcast_delivery_attempts`',
        ];

        foreach ($definitions as $name => $timing) {
            if (! DB::table('information_schema.TRIGGERS')
                ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                ->where('TRIGGER_NAME', $name)
                ->exists()) {
                $body = match ($name) {
                    'trg_event_broadcast_lifecycle_guard' => $this->broadcastLifecycleTriggerBody(),
                    'trg_event_broadcast_delivery_lifecycle_guard' => $this->deliveryLifecycleTriggerBody(),
                    default => "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = "
                        . "'event_broadcast_evidence_immutable'",
                };
                DB::unprepared("CREATE TRIGGER `{$name}` {$timing} FOR EACH ROW {$body}");
            }
        }
    }

    private function broadcastLifecycleTriggerBody(): string
    {
        return "BEGIN "
            . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) "
            . "OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) "
            . "OR NOT (OLD.`created_by_user_id` <=> NEW.`created_by_user_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_identity_immutable'; END IF; "
            . "IF OLD.`status` IN ('sent','cancelled') "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_terminal_immutable'; END IF; "
            . "IF OLD.`status` <> 'draft' AND (NOT (OLD.`variant` <=> NEW.`variant`) "
            . "OR NOT (OLD.`audience_segments` <=> NEW.`audience_segments`) OR NOT (OLD.`channels` <=> NEW.`channels`) "
            . "OR NOT (OLD.`body` <=> NEW.`body`) OR NOT (OLD.`content_hash` <=> NEW.`content_hash`)) "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_content_frozen'; END IF; "
            . "IF NOT ((OLD.`status` = NEW.`status`) "
            . "OR (OLD.`status` = 'draft' AND NEW.`status` IN ('scheduled','cancelled')) "
            . "OR (OLD.`status` = 'scheduled' AND NEW.`status` IN ('sending','cancelled')) "
            . "OR (OLD.`status` = 'sending' AND NEW.`status` IN ('sent','failed')) "
            . "OR (OLD.`status` = 'failed' AND NEW.`status` = 'scheduled')) "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_transition_invalid'; END IF; "
            . "IF NEW.`broadcast_version` < OLD.`broadcast_version` OR NEW.`broadcast_version` > OLD.`broadcast_version` + 1 "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_version_invalid'; END IF; "
            . "IF (NOT (OLD.`status` <=> NEW.`status`) OR NOT (OLD.`variant` <=> NEW.`variant`) "
            . "OR NOT (OLD.`audience_segments` <=> NEW.`audience_segments`) OR NOT (OLD.`channels` <=> NEW.`channels`) "
            . "OR NOT (OLD.`body` <=> NEW.`body`) OR NOT (OLD.`content_hash` <=> NEW.`content_hash`)) "
            . "AND NEW.`broadcast_version` <> OLD.`broadcast_version` + 1 "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_version_required'; END IF; "
            . "END";
    }

    private function deliveryLifecycleTriggerBody(): string
    {
        return "BEGIN "
            . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) "
            . "OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`broadcast_id` <=> NEW.`broadcast_id`) "
            . "OR NOT (OLD.`frozen_broadcast_version` <=> NEW.`frozen_broadcast_version`) "
            . "OR NOT (OLD.`recipient_user_id` <=> NEW.`recipient_user_id`) OR NOT (OLD.`channel` <=> NEW.`channel`) "
            . "OR NOT (OLD.`delivery_key` <=> NEW.`delivery_key`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_delivery_identity_immutable'; END IF; "
            . "IF OLD.`status` IN ('delivered','suppressed','cancelled') "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_delivery_terminal_immutable'; END IF; "
            . "IF NOT ((OLD.`status` = NEW.`status`) "
            . "OR (OLD.`status` IN ('pending','retry') AND NEW.`status` IN ('processing','cancelled')) "
            . "OR (OLD.`status` = 'processing' AND NEW.`status` IN ('delivered','suppressed','retry','dead_letter')) "
            . "OR (OLD.`status` = 'dead_letter' AND NEW.`status` = 'retry')) "
            . "THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_broadcast_delivery_transition_invalid'; END IF; "
            . "END";
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
};
