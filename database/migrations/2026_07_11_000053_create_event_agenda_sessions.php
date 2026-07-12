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
    private const HISTORY_UPDATE_TRIGGER = 'trg_event_session_history_no_update';
    private const HISTORY_DELETE_TRIGGER = 'trg_event_session_history_no_delete';
    private const EVENT_TENANT_INDEX = 'uq_events_tenant_id';

    public function up(): void
    {
        foreach (['tenants', 'events', 'users'] as $required) {
            if (! Schema::hasTable($required)) {
                throw new LogicException("event_agenda_prerequisite_missing:{$required}");
            }
        }

        $this->addCompositeParentIndexes();
        $this->addAgendaVersion();
        $this->createSessions();
        $this->createSpeakers();
        $this->createHistory();
        $this->installCheckConstraints();
        $this->installHistoryImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->hasDependentSchema()) {
            throw new LogicException('event_agenda_rollback_refused_dependents_exist');
        }
        if ($this->containsDurableRecords()) {
            throw new LogicException('event_agenda_rollback_refused_records_exist');
        }

        $this->dropHistoryImmutabilityTriggers();
        Schema::dropIfExists('event_session_history');
        Schema::dropIfExists('event_session_speakers');
        Schema::dropIfExists('event_sessions');

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'agenda_version')) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->dropColumn('agenda_version');
            });
        }
        $this->dropCompositeParentIndexes();
    }

    private function addCompositeParentIndexes(): void
    {
        if (! Schema::hasIndex('events', self::EVENT_TENANT_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->unique(['tenant_id', 'id'], self::EVENT_TENANT_INDEX);
            });
        }
    }

    private function dropCompositeParentIndexes(): void
    {
        if (Schema::hasTable('events') && Schema::hasIndex('events', self::EVENT_TENANT_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->dropUnique(self::EVENT_TENANT_INDEX);
            });
        }
    }

    private function addAgendaVersion(): void
    {
        if (Schema::hasColumn('events', 'agenda_version')) {
            return;
        }

        Schema::table('events', static function (Blueprint $table): void {
            $table->unsignedBigInteger('agenda_version')->default(0)->after('calendar_sequence')
                ->comment('Monotonic version for the ordered per-event agenda aggregate');
        });
    }

    private function createSessions(): void
    {
        if (Schema::hasTable('event_sessions')) {
            return;
        }

        Schema::create('event_sessions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('session_type', 32)->default('session');
            $table->string('visibility', 24)->default('public');
            $table->string('status', 16)->default('scheduled');
            $table->dateTime('starts_at_utc');
            $table->dateTime('ends_at_utc');
            $table->string('timezone', 64);
            $table->string('track_name', 120)->nullable();
            $table->string('room_name', 120)->nullable();
            $table->char('room_key', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('cancellation_reason', 500)->nullable();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'event_id', 'status', 'starts_at_utc', 'position', 'id'],
                'idx_event_sessions_event_time',
            );
            $table->index(
                ['tenant_id', 'event_id', 'room_key', 'status', 'starts_at_utc', 'ends_at_utc'],
                'idx_event_sessions_room_time',
            );
            $table->index(
                ['tenant_id', 'starts_at_utc', 'ends_at_utc', 'status'],
                'idx_event_sessions_tenant_time',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_sessions_tenant_event_id',
            );

            $table->foreign('tenant_id', 'fk_event_sessions_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_event_sessions_event_tenant')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_event_sessions_creator_tenant')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_event_sessions_updater_tenant')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['cancelled_by', 'tenant_id'], 'fk_event_sessions_canceller_tenant')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createSpeakers(): void
    {
        if (Schema::hasTable('event_session_speakers')) {
            return;
        }

        Schema::create('event_session_speakers', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('session_id');
            $table->integer('user_id')->nullable();
            $table->string('display_name', 191)->nullable();
            $table->string('role_label', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'session_id', 'user_id'],
                'uq_event_session_speaker_member',
            );
            $table->index(
                ['tenant_id', 'event_id', 'session_id', 'position', 'id'],
                'idx_event_session_speakers_order',
            );
            $table->index(
                ['tenant_id', 'user_id', 'event_id', 'session_id'],
                'idx_event_session_speakers_user',
            );

            $table->foreign('tenant_id', 'fk_event_session_speakers_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_event_session_speakers_event_tenant')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'session_id'],
                'fk_event_session_speakers_session_tenant',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_sessions')->restrictOnDelete();
            $table->foreign(['user_id', 'tenant_id'], 'fk_event_session_speakers_user_tenant')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createHistory(): void
    {
        if (Schema::hasTable('event_session_history')) {
            return;
        }

        Schema::create('event_session_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('agenda_version');
            $table->string('action', 32);
            $table->string('idempotency_key', 191);
            $table->char('request_hash', 64);
            $table->json('changed_fields');
            $table->json('affected_session_ids');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'event_id', 'agenda_version'],
                'uq_event_session_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'uq_event_session_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_session_history_event',
            );
            $table->index(
                ['tenant_id', 'session_id', 'created_at', 'id'],
                'idx_event_session_history_session',
            );

            $table->foreign('tenant_id', 'fk_event_session_history_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_event_session_history_event_tenant')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'session_id'],
                'fk_event_session_history_session_tenant',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_sessions')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_session_history_actor_tenant')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installCheckConstraints(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_sessions' => [
                'chk_event_sessions_type' => "`session_type` IN ('session','keynote','workshop','panel','break','networking','other')",
                'chk_event_sessions_visibility' => "`visibility` IN ('public','registered','staff')",
                'chk_event_sessions_status' => "`status` IN ('scheduled','cancelled')",
                'chk_event_sessions_time_range' => '`starts_at_utc` < `ends_at_utc`',
                'chk_event_sessions_cancellation' => "((`status` = 'scheduled' AND `cancellation_reason` IS NULL AND `cancelled_by` IS NULL AND `cancelled_at` IS NULL) OR (`status` = 'cancelled' AND `cancellation_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`cancellation_reason`)) > 0 AND `cancelled_by` IS NOT NULL AND `cancelled_at` IS NOT NULL))",
            ],
            'event_session_speakers' => [
                'chk_event_session_speaker_identity' => '((`user_id` IS NOT NULL AND `display_name` IS NULL) OR (`user_id` IS NULL AND `display_name` IS NOT NULL AND CHAR_LENGTH(TRIM(`display_name`)) > 0))',
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

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    private function installHistoryImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('event_session_history')) {
            return;
        }

        $this->createTrigger(
            self::HISTORY_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_session_history` FOR EACH ROW',
        );
        $this->createTrigger(
            self::HISTORY_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_session_history` FOR EACH ROW',
        );
    }

    private function createTrigger(string $name, string $timing): void
    {
        if ($this->triggerExists($name)) {
            return;
        }

        DB::unprepared(
            "CREATE TRIGGER `{$name}` {$timing} "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_session_history_immutable'",
        );
    }

    private function dropHistoryImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::HISTORY_UPDATE_TRIGGER . '`');
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::HISTORY_DELETE_TRIGGER . '`');
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    private function containsDurableRecords(): bool
    {
        foreach (['event_session_history', 'event_session_speakers', 'event_sessions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('events')
            && Schema::hasColumn('events', 'agenda_version')
            && DB::table('events')->where('agenda_version', '>', 0)->exists();
    }

    private function hasDependentSchema(): bool
    {
        // Migration 000065 and future enterprise agenda slices extend the
        // session aggregate through strict composite foreign keys. Refuse an
        // out-of-order rollback before dropping their parent key; normal
        // reverse-order rollback removes each dependent slice first.
        foreach ([
            'event_session_resources',
            'event_session_registrations',
            'event_session_registration_history',
        ] as $table) {
            if (Schema::hasTable($table)) {
                return true;
            }
        }

        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->whereNotIn('TABLE_NAME', [
                'event_sessions',
                'event_session_speakers',
                'event_session_history',
            ])
            ->where('REFERENCED_TABLE_NAME', 'event_sessions')
            ->where('UNIQUE_CONSTRAINT_NAME', 'uq_event_sessions_tenant_event_id')
            ->exists();
    }
};
