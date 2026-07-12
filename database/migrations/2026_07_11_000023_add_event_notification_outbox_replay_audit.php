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
    private const UPDATE_TRIGGER = 'trg_event_outbox_replay_no_update';
    private const DELETE_TRIGGER = 'trg_event_outbox_replay_no_delete';

    public function up(): void
    {
        if (Schema::hasTable('event_notification_outbox_replays')
            || ! Schema::hasTable('event_domain_outbox')) {
            return;
        }

        Schema::create('event_notification_outbox_replays', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->unsignedBigInteger('outbox_id');
            $table->string('requested_by', 191);
            $table->string('reason', 1000);
            $table->unsignedSmallInteger('previous_attempts');
            $table->char('previous_error_fingerprint', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at', 'id'], 'idx_event_outbox_replay_tenant');
            $table->index(['outbox_id', 'created_at', 'id'], 'idx_event_outbox_replay_outbox');
            $table->foreign('outbox_id', 'fk_event_outbox_replay_outbox')
                ->references('id')
                ->on('event_domain_outbox')
                ->restrictOnDelete();
        });

        $this->installImmutabilityTriggers();
    }

    public function down(): void
    {
        if (Schema::hasTable('event_notification_outbox_replays')
            && DB::table('event_notification_outbox_replays')->exists()) {
            throw new \LogicException('Event notification replay audit records exist and cannot be rolled back.');
        }
        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('event_notification_outbox_replays');
    }

    private function installImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ([
            self::UPDATE_TRIGGER => 'BEFORE UPDATE',
            self::DELETE_TRIGGER => 'BEFORE DELETE',
        ] as $name => $timing) {
            if (DB::table('information_schema.TRIGGERS')
                ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                ->where('TRIGGER_NAME', $name)
                ->exists()) {
                continue;
            }
            DB::unprepared(
                "CREATE TRIGGER `{$name}` {$timing} ON `event_notification_outbox_replays` FOR EACH ROW "
                . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_notification_outbox_replay_immutable'"
            );
        }
    }

    private function dropImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::UPDATE_TRIGGER . '`');
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::DELETE_TRIGGER . '`');
    }
};
