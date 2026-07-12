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
    private const ACCESS_UPDATE_TRIGGER = 'trg_event_waitlist_envelope_access_no_update';
    private const ACCESS_DELETE_TRIGGER = 'trg_event_waitlist_envelope_access_no_delete';

    public function up(): void
    {
        if (! Schema::hasTable('event_waitlist_entries')
            || ! Schema::hasTable('event_domain_outbox')) {
            return;
        }

        if (! Schema::hasTable('event_waitlist_offer_envelopes')) {
            Schema::create('event_waitlist_offer_envelopes', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->unsignedBigInteger('waitlist_entry_id');
                $table->unsignedBigInteger('outbox_id');
                $table->unsignedBigInteger('queue_version');
                $table->string('action', 80);
                $table->string('cipher_version', 32);
                $table->string('key_version', 64);
                $table->char('key_fingerprint', 64);
                $table->char('aad_hash', 64);
                $table->longText('token_ciphertext')->nullable();
                $table->string('status', 32)->default('sealed');
                $table->unsignedBigInteger('envelope_version')->default(1);
                $table->char('claim_token_hash', 64)->nullable();
                $table->string('claimed_by', 191)->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('handed_off_at')->nullable();
                $table->timestamp('erased_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'outbox_id'],
                    'uq_event_waitlist_offer_envelope_outbox',
                );
                $table->unique(
                    ['tenant_id', 'waitlist_entry_id', 'queue_version'],
                    'uq_event_waitlist_offer_envelope_version',
                );
                $table->index(
                    ['tenant_id', 'status', 'expires_at', 'id'],
                    'idx_event_waitlist_offer_envelope_status',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'waitlist_entry_id', 'id'],
                    'idx_event_waitlist_offer_envelope_entry',
                );
            });
        }

        if (! Schema::hasTable('event_waitlist_offer_envelope_access')) {
            Schema::create('event_waitlist_offer_envelope_access', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->unsignedBigInteger('envelope_id');
                $table->unsignedBigInteger('waitlist_entry_id');
                $table->unsignedBigInteger('outbox_id');
                $table->unsignedBigInteger('queue_version');
                $table->string('operation', 32);
                $table->string('consumer', 191)->nullable();
                $table->char('claim_id_hash', 64)->nullable();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('idempotency_key', 191);
                $table->json('metadata');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(
                    ['tenant_id', 'idempotency_key'],
                    'uq_event_waitlist_envelope_access_key',
                );
                $table->index(
                    ['tenant_id', 'envelope_id', 'created_at', 'id'],
                    'idx_event_waitlist_envelope_access_envelope',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'operation', 'created_at', 'id'],
                    'idx_event_waitlist_envelope_access_event',
                );
            });
        }

        $this->installImmutabilityTriggers();
    }

    public function down(): void
    {
        foreach ([
            'event_waitlist_offer_envelopes',
            'event_waitlist_offer_envelope_access',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \LogicException(
                    'Event waitlist offer delivery-envelope records exist and cannot be rolled back.'
                );
            }
        }

        $this->dropImmutabilityTriggers();
        Schema::dropIfExists('event_waitlist_offer_envelope_access');
        Schema::dropIfExists('event_waitlist_offer_envelopes');
    }

    private function installImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql'
            || ! Schema::hasTable('event_waitlist_offer_envelope_access')) {
            return;
        }

        $this->createTrigger(
            self::ACCESS_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_waitlist_offer_envelope_access` FOR EACH ROW',
        );
        $this->createTrigger(
            self::ACCESS_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_waitlist_offer_envelope_access` FOR EACH ROW',
        );
    }

    private function createTrigger(string $name, string $timing): void
    {
        if (DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $name)
            ->exists()) {
            return;
        }

        DB::unprepared(
            "CREATE TRIGGER `{$name}` {$timing} "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = "
            . "'event_waitlist_offer_envelope_access_immutable'"
        );
    }

    private function dropImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::ACCESS_UPDATE_TRIGGER . '`');
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::ACCESS_DELETE_TRIGGER . '`');
    }
};
