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
    private const REGISTRATION_HISTORY_UPDATE_TRIGGER = 'trg_event_registration_history_no_update';
    private const REGISTRATION_HISTORY_DELETE_TRIGGER = 'trg_event_registration_history_no_delete';
    private const WAITLIST_HISTORY_UPDATE_TRIGGER = 'trg_event_waitlist_history_no_update';
    private const WAITLIST_HISTORY_DELETE_TRIGGER = 'trg_event_waitlist_history_no_delete';

    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('users')) {
            return;
        }

        $this->createRegistrations();
        $this->createRegistrationHistory();
        $this->createWaitlistEntries();
        $this->createWaitlistHistory();
        $this->installImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->containsOperationalRecords()) {
            throw new \LogicException(
                'Event registration or waitlist records exist and cannot be rolled back.'
            );
        }

        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('event_waitlist_entry_history');
        Schema::dropIfExists('event_waitlist_entries');
        Schema::dropIfExists('event_registration_history');
        Schema::dropIfExists('event_registrations');
    }

    private function createRegistrations(): void
    {
        if (Schema::hasTable('event_registrations')) {
            return;
        }

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('user_id');
            $table->string('capacity_pool_key', 100)->default('event');
            $table->string('allocation_key', 191)->nullable();
            $table->string('registration_state', 32);
            $table->unsignedBigInteger('registration_version')->default(1);
            $table->timestamp('state_changed_at');
            $table->integer('state_changed_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'capacity_pool_key'],
                'uq_event_registration_subject',
            );
            $table->index(
                ['tenant_id', 'event_id', 'capacity_pool_key', 'registration_state', 'id'],
                'idx_event_registration_capacity',
            );
            $table->index(
                ['tenant_id', 'user_id', 'registration_state', 'event_id'],
                'idx_event_registration_user',
            );
        });
    }

    private function createRegistrationHistory(): void
    {
        if (Schema::hasTable('event_registration_history')) {
            return;
        }

        Schema::create('event_registration_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->integer('actor_user_id')->nullable();
            $table->string('capacity_pool_key', 100);
            $table->string('allocation_key', 191)->nullable();
            $table->unsignedBigInteger('registration_version');
            $table->string('action', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('idempotency_key', 191);
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'registration_id', 'registration_version'],
                'uq_event_registration_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'uq_event_registration_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'capacity_pool_key', 'created_at', 'id'],
                'idx_event_registration_history_event',
            );
            $table->index(
                ['tenant_id', 'user_id', 'created_at', 'id'],
                'idx_event_registration_history_user',
            );
        });
    }

    private function createWaitlistEntries(): void
    {
        if (Schema::hasTable('event_waitlist_entries')) {
            return;
        }

        Schema::create('event_waitlist_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->integer('user_id');
            $table->string('capacity_pool_key', 100)->default('event');
            $table->string('allocation_key', 191)->nullable();
            $table->string('queue_state', 32);
            $table->unsignedBigInteger('queue_version')->default(1);
            $table->unsignedBigInteger('queue_sequence');
            $table->timestamp('state_changed_at');
            $table->integer('state_changed_by')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->char('offer_token_hash', 64)->nullable();
            $table->timestamp('offer_token_used_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_registration_id')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'capacity_pool_key'],
                'uq_event_waitlist_entry_subject',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'capacity_pool_key', 'queue_sequence'],
                'uq_event_waitlist_entry_sequence',
            );
            $table->unique('offer_token_hash', 'uq_event_waitlist_offer_token');
            $table->index(
                ['tenant_id', 'event_id', 'capacity_pool_key', 'queue_state', 'queue_sequence', 'id'],
                'idx_event_waitlist_queue',
            );
            $table->index(
                ['queue_state', 'offer_expires_at', 'id'],
                'idx_event_waitlist_expiry',
            );
            $table->index(
                ['tenant_id', 'user_id', 'queue_state', 'event_id'],
                'idx_event_waitlist_user',
            );
        });
    }

    private function createWaitlistHistory(): void
    {
        if (Schema::hasTable('event_waitlist_entry_history')) {
            return;
        }

        Schema::create('event_waitlist_entry_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('waitlist_entry_id');
            $table->integer('user_id');
            $table->integer('actor_user_id')->nullable();
            $table->string('capacity_pool_key', 100);
            $table->string('allocation_key', 191)->nullable();
            $table->unsignedBigInteger('queue_version');
            $table->unsignedBigInteger('queue_sequence');
            $table->string('action', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('idempotency_key', 191);
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'waitlist_entry_id', 'queue_version'],
                'uq_event_waitlist_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'uq_event_waitlist_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'capacity_pool_key', 'created_at', 'id'],
                'idx_event_waitlist_history_event',
            );
            $table->index(
                ['tenant_id', 'user_id', 'created_at', 'id'],
                'idx_event_waitlist_history_user',
            );
        });
    }

    private function installImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('event_registration_history')) {
            $this->createTrigger(
                self::REGISTRATION_HISTORY_UPDATE_TRIGGER,
                'BEFORE UPDATE ON `event_registration_history` FOR EACH ROW',
                'event_registration_history_immutable',
            );
            $this->createTrigger(
                self::REGISTRATION_HISTORY_DELETE_TRIGGER,
                'BEFORE DELETE ON `event_registration_history` FOR EACH ROW',
                'event_registration_history_immutable',
            );
        }
        if (Schema::hasTable('event_waitlist_entry_history')) {
            $this->createTrigger(
                self::WAITLIST_HISTORY_UPDATE_TRIGGER,
                'BEFORE UPDATE ON `event_waitlist_entry_history` FOR EACH ROW',
                'event_waitlist_entry_history_immutable',
            );
            $this->createTrigger(
                self::WAITLIST_HISTORY_DELETE_TRIGGER,
                'BEFORE DELETE ON `event_waitlist_entry_history` FOR EACH ROW',
                'event_waitlist_entry_history_immutable',
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
            self::REGISTRATION_HISTORY_UPDATE_TRIGGER,
            self::REGISTRATION_HISTORY_DELETE_TRIGGER,
            self::WAITLIST_HISTORY_UPDATE_TRIGGER,
            self::WAITLIST_HISTORY_DELETE_TRIGGER,
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

    private function containsOperationalRecords(): bool
    {
        foreach ([
            'event_registrations',
            'event_registration_history',
            'event_waitlist_entries',
            'event_waitlist_entry_history',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }
};
