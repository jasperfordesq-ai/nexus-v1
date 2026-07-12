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
    private const ATTENDANCE_INDEX = 'idx_event_attendance_tenant_event_status';
    private const ACTIVITY_UPDATE_TRIGGER = 'trg_event_attendance_activity_no_update';
    private const ACTIVITY_DELETE_TRIGGER = 'trg_event_attendance_activity_no_delete';
    private const CLAIM_DELETE_TRIGGER = 'trg_event_attendance_claim_no_delete';

    public function up(): void
    {
        if (! Schema::hasTable('event_attendance')) {
            return;
        }

        $this->expandAttendanceFact();
        $this->createAttendanceActivity();
        $this->createCreditClaims();
        $this->backfillAttendanceState();
        $this->installImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->ledgerContainsRecords()) {
            throw new \LogicException('Event attendance ledgers contain durable records and cannot be rolled back.');
        }

        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('event_attendance_credit_claims');
        Schema::dropIfExists('event_attendance_activity');

        if (! Schema::hasTable('event_attendance')) {
            return;
        }

        if (Schema::hasIndex('event_attendance', self::ATTENDANCE_INDEX)) {
            Schema::table('event_attendance', function (Blueprint $table): void {
                $table->dropIndex(self::ATTENDANCE_INDEX);
            });
        }

        $columns = array_values(array_filter(
            ['attendance_status', 'attendance_version', 'status_changed_at', 'status_changed_by'],
            static fn (string $column): bool => Schema::hasColumn('event_attendance', $column),
        ));
        if ($columns !== []) {
            Schema::table('event_attendance', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function expandAttendanceFact(): void
    {
        $missing = array_values(array_filter(
            ['attendance_status', 'attendance_version', 'status_changed_at', 'status_changed_by'],
            static fn (string $column): bool => ! Schema::hasColumn('event_attendance', $column),
        ));

        if ($missing !== []) {
            Schema::table('event_attendance', function (Blueprint $table) use ($missing): void {
                if (in_array('attendance_status', $missing, true)) {
                    $table->string('attendance_status', 32)->nullable()->after('tenant_id')
                        ->comment('Canonical attendance state; nullable during rolling deployment');
                }
                if (in_array('attendance_version', $missing, true)) {
                    $table->unsignedBigInteger('attendance_version')->nullable()->after('attendance_status')
                        ->comment('Monotonic attendance activity version');
                }
                if (in_array('status_changed_at', $missing, true)) {
                    $table->timestamp('status_changed_at')->nullable()->after('attendance_version');
                }
                if (in_array('status_changed_by', $missing, true)) {
                    $table->integer('status_changed_by')->nullable()->after('status_changed_at');
                }
            });
        }

        if (! Schema::hasIndex('event_attendance', self::ATTENDANCE_INDEX)) {
            Schema::table('event_attendance', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'event_id', 'attendance_status', 'id'],
                    self::ATTENDANCE_INDEX,
                );
            });
        }
    }

    private function createAttendanceActivity(): void
    {
        if (Schema::hasTable('event_attendance_activity')) {
            return;
        }

        Schema::create('event_attendance_activity', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('attendance_id');
            $table->integer('user_id');
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('attendance_version');
            $table->string('action', 50);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('idempotency_key', 191);
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_key'], 'uq_event_attendance_activity_key');
            $table->unique(
                ['tenant_id', 'attendance_id', 'attendance_version'],
                'uq_event_attendance_activity_version',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_attendance_activity_event',
            );
            $table->index(
                ['tenant_id', 'user_id', 'created_at'],
                'idx_event_attendance_activity_user',
            );
        });
    }

    private function createCreditClaims(): void
    {
        if (Schema::hasTable('event_attendance_credit_claims')) {
            return;
        }

        Schema::create('event_attendance_credit_claims', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('attendance_id');
            $table->integer('user_id');
            $table->string('claim_type', 50);
            $table->string('idempotency_key', 191);
            $table->string('funding_source_type', 32);
            $table->integer('funding_source_id')->nullable();
            $table->integer('payer_user_id')->nullable();
            $table->integer('payee_user_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('unit', 32)->default('time_credit');
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('parent_claim_id')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('reversal_code', 100)->nullable();
            $table->json('metadata');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'claim_type'],
                'uq_event_credit_claim_subject',
            );
            $table->unique(['tenant_id', 'idempotency_key'], 'uq_event_credit_claim_key');
            $table->unique('transaction_id', 'uq_event_credit_claim_transaction');
            $table->index(
                ['tenant_id', 'status', 'created_at', 'id'],
                'idx_event_credit_claim_status',
            );
            $table->index(
                ['tenant_id', 'event_id', 'attendance_id'],
                'idx_event_credit_claim_attendance',
            );
        });
    }

    private function backfillAttendanceState(): void
    {
        DB::table('event_attendance')
            ->whereNull('attendance_status')
            ->whereNotNull('checked_out_at')
            ->update([
                'attendance_status' => 'checked_out',
                'attendance_version' => DB::raw('COALESCE(attendance_version, 1)'),
                'status_changed_at' => DB::raw('COALESCE(checked_out_at, updated_at, created_at)'),
                'status_changed_by' => DB::raw('COALESCE(checked_in_by, user_id)'),
            ]);

        DB::table('event_attendance')
            ->whereNull('attendance_status')
            ->update([
                'attendance_status' => 'checked_in',
                'attendance_version' => DB::raw('COALESCE(attendance_version, 1)'),
                'status_changed_at' => DB::raw('COALESCE(checked_in_at, updated_at, created_at)'),
                'status_changed_by' => DB::raw('COALESCE(checked_in_by, user_id)'),
            ]);
    }

    private function installImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('event_attendance_activity')) {
            $this->createTrigger(
                self::ACTIVITY_UPDATE_TRIGGER,
                'BEFORE UPDATE ON `event_attendance_activity` FOR EACH ROW',
                'event_attendance_activity_immutable',
            );
            $this->createTrigger(
                self::ACTIVITY_DELETE_TRIGGER,
                'BEFORE DELETE ON `event_attendance_activity` FOR EACH ROW',
                'event_attendance_activity_immutable',
            );
        }

        if (Schema::hasTable('event_attendance_credit_claims')) {
            $this->createTrigger(
                self::CLAIM_DELETE_TRIGGER,
                'BEFORE DELETE ON `event_attendance_credit_claims` FOR EACH ROW',
                'event_attendance_credit_claim_immutable',
            );
        }
    }

    private function createTrigger(string $name, string $timing, string $message): void
    {
        if ($this->triggerExists($name)) {
            return;
        }

        DB::unprepared(
            "CREATE TRIGGER `{$name}` {$timing} "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
        );
    }

    private function dropImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            self::ACTIVITY_UPDATE_TRIGGER,
            self::ACTIVITY_DELETE_TRIGGER,
            self::CLAIM_DELETE_TRIGGER,
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    private function ledgerContainsRecords(): bool
    {
        foreach (['event_attendance_activity', 'event_attendance_credit_claims'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }
};
