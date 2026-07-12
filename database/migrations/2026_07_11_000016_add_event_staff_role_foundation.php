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
    private const HISTORY_UPDATE_TRIGGER = 'trg_event_staff_history_no_update';
    private const HISTORY_DELETE_TRIGGER = 'trg_event_staff_history_no_delete';

    public function up(): void
    {
        if (!Schema::hasTable('events') || !Schema::hasTable('users')) {
            return;
        }

        $this->createAssignments();
        $this->createHistory();
        $this->installHistoryImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->containsDurableRecords()) {
            throw new LogicException('event_staff_role_rollback_refused_records_exist');
        }

        $this->dropHistoryImmutabilityTriggers();
        Schema::dropIfExists('event_staff_assignment_history');
        Schema::dropIfExists('event_staff_assignments');
    }

    private function createAssignments(): void
    {
        if (Schema::hasTable('event_staff_assignments')) {
            return;
        }

        Schema::create('event_staff_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('user_id');
            $table->string('role', 40);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('assignment_version')->default(1);
            $table->timestamp('granted_at');
            $table->integer('granted_by');
            $table->timestamp('revoked_at')->nullable();
            $table->integer('revoked_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'role'],
                'uq_event_staff_assignment_subject',
            );
            $table->index(
                ['tenant_id', 'event_id', 'status', 'expires_at', 'id'],
                'idx_event_staff_assignment_event',
            );
            $table->index(
                ['tenant_id', 'user_id', 'status', 'expires_at'],
                'idx_event_staff_assignment_user',
            );

            $table->foreign('event_id', 'fk_event_staff_assignment_event')
                ->references('id')->on('events')->restrictOnDelete();
            $table->foreign('user_id', 'fk_event_staff_assignment_user')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('granted_by', 'fk_event_staff_assignment_grantor')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by', 'fk_event_staff_assignment_revoker')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    private function createHistory(): void
    {
        if (Schema::hasTable('event_staff_assignment_history')) {
            return;
        }

        Schema::create('event_staff_assignment_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('assignment_id');
            $table->integer('user_id');
            $table->string('role', 40);
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('assignment_version');
            $table->string('action', 32);
            $table->string('idempotency_key', 191)->nullable();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->timestamp('previous_expires_at')->nullable();
            $table->timestamp('new_expires_at')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'assignment_id', 'assignment_version'],
                'uq_event_staff_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'uq_event_staff_history_idempotency',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_staff_history_event',
            );
            $table->index(
                ['tenant_id', 'user_id', 'created_at'],
                'idx_event_staff_history_user',
            );

            $table->foreign('assignment_id', 'fk_event_staff_history_assignment')
                ->references('id')->on('event_staff_assignments')->restrictOnDelete();
            $table->foreign('actor_user_id', 'fk_event_staff_history_actor')
                ->references('id')->on('users')->restrictOnDelete();
        });
    }

    private function installHistoryImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('event_staff_assignment_history')) {
            return;
        }

        $this->createTrigger(
            self::HISTORY_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_staff_assignment_history` FOR EACH ROW',
        );
        $this->createTrigger(
            self::HISTORY_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_staff_assignment_history` FOR EACH ROW',
        );
    }

    private function createTrigger(string $name, string $timing): void
    {
        if ($this->triggerExists($name)) {
            return;
        }

        DB::unprepared(
            "CREATE TRIGGER `{$name}` {$timing} "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_staff_assignment_history_immutable'"
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
        foreach (['event_staff_assignments', 'event_staff_assignment_history'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }
};
