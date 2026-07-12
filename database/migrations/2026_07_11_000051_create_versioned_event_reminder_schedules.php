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
    private const OFFSET_CHECK = 'chk_event_reminder_schedule_offset';
    private const STATUS_CHECK = 'chk_event_reminder_schedule_status';
    private const TERMINAL_TIMESTAMPS_CHECK = 'chk_event_reminder_schedule_terminal_timestamps';

    public function up(): void
    {
        if (! Schema::hasTable('event_reminder_schedules')
            && (
                ! Schema::hasTable('event_reminder_rules')
                || ! Schema::hasTable('event_registrations')
                || ! Schema::hasTable('event_domain_outbox')
            )) {
            return;
        }

        if (! Schema::hasTable('event_reminder_schedules')) {
            Schema::create('event_reminder_schedules', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->integer('user_id');
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->unsignedInteger('offset_minutes');
                $table->unsignedBigInteger('rule_version')->default(0);
                $table->unsignedBigInteger('registration_version')->default(0);
                $table->unsignedBigInteger('event_calendar_sequence')->default(0);
                $table->unsignedBigInteger('schedule_version');
                $table->timestamp('scheduled_for');
                $table->timestamp('deliver_until')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('reason_code', 100)->nullable();
                $table->unsignedBigInteger('outbox_id')->nullable();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('superseded_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'event_id', 'user_id', 'offset_minutes', 'schedule_version'],
                    'uq_event_reminder_schedule_version',
                );
                $table->index(
                    ['tenant_id', 'status', 'scheduled_for', 'id'],
                    'idx_event_reminder_schedule_due',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'user_id', 'event_calendar_sequence', 'status'],
                    'idx_event_reminder_schedule_reconcile',
                );
                $table->index(
                    ['tenant_id', 'registration_id', 'registration_version'],
                    'idx_event_reminder_schedule_registration',
                );

                $table->foreign('tenant_id', 'fk_event_reminder_schedule_tenant')
                    ->references('id')->on('tenants')->restrictOnDelete();
                $table->foreign('event_id', 'fk_event_reminder_schedule_event')
                    ->references('id')->on('events')->restrictOnDelete();
                $table->foreign('user_id', 'fk_event_reminder_schedule_user')
                    ->references('id')->on('users')->restrictOnDelete();
                $table->foreign('rule_id', 'fk_event_reminder_schedule_rule')
                    ->references('id')->on('event_reminder_rules')->restrictOnDelete();
                $table->foreign('registration_id', 'fk_event_reminder_schedule_registration')
                    ->references('id')->on('event_registrations')->restrictOnDelete();
                $table->foreign('outbox_id', 'fk_event_reminder_schedule_outbox')
                    ->references('id')->on('event_domain_outbox')->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->addCheckIfMissing(self::OFFSET_CHECK, '(`offset_minutes` > 0)');
            $this->addCheckIfMissing(
                self::STATUS_CHECK,
                '(`status` IN (\'pending\', \'queued\', \'delivered\', \'cancelled\','
                    . ' \'superseded\', \'suppressed\', \'failed_terminal\'))',
            );
            $this->addCheckIfMissing(
                self::TERMINAL_TIMESTAMPS_CHECK,
                '((`status` <> \'delivered\' OR `delivered_at` IS NOT NULL)'
                    . ' AND (`status` <> \'cancelled\' OR `cancelled_at` IS NOT NULL)'
                    . ' AND (`status` <> \'superseded\' OR `superseded_at` IS NOT NULL))',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_schedules');
    }

    private function addCheckIfMissing(string $name, string $expression): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'event_reminder_schedules')
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
        if (! $exists) {
            DB::statement(
                "ALTER TABLE `event_reminder_schedules` ADD CONSTRAINT `{$name}` CHECK {$expression}",
            );
        }
    }
};
