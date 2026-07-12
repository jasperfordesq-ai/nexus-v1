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
    private const ACCESS_UPDATE_TRIGGER = 'trg_event_guardian_delivery_access_no_update';
    private const ACCESS_DELETE_TRIGGER = 'trg_event_guardian_delivery_access_no_delete';
    private const RECIPIENT_XOR_CHECK = 'chk_event_delivery_recipient_xor';
    private const EXTERNAL_HASH_CHECK = 'chk_event_delivery_external_hash';

    public function up(): void
    {
        if (! Schema::hasTable('event_notification_deliveries')
            || ! Schema::hasTable('event_guardian_consents')
            || ! Schema::hasTable('event_domain_outbox')) {
            return;
        }

        if (! Schema::hasIndex(
            'event_guardian_consents',
            'uq_event_guardian_consent_scope_id',
        )) {
            Schema::table('event_guardian_consents', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'event_id', 'id'],
                    'uq_event_guardian_consent_scope_id',
                );
            });
        }
        if (! Schema::hasIndex('event_domain_outbox', 'uq_event_outbox_scope_id')) {
            Schema::table('event_domain_outbox', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'event_id', 'id'],
                    'uq_event_outbox_scope_id',
                );
            });
        }

        Schema::table('event_notification_deliveries', function (Blueprint $table): void {
            $table->integer('recipient_user_id')->nullable()->change();
            if (! Schema::hasColumn('event_notification_deliveries', 'external_recipient_hash')) {
                $table->char('external_recipient_hash', 64)
                    ->nullable()
                    ->after('recipient_user_id');
            }
        });
        if (! Schema::hasIndex(
            'event_notification_deliveries',
            'uq_event_delivery_external_recipient_channel',
        )) {
            Schema::table('event_notification_deliveries', function (Blueprint $table): void {
                $table->unique(
                    ['outbox_id', 'external_recipient_hash', 'channel'],
                    'uq_event_delivery_external_recipient_channel',
                );
            });
        }
        $this->installRecipientChecks();

        if (! Schema::hasColumn('event_guardian_consents', 'guardian_locale')) {
            Schema::table('event_guardian_consents', function (Blueprint $table): void {
                $table->string('guardian_locale', 15)
                    ->nullable()
                    ->after('relationship_code');
            });
        }

        if (! Schema::hasTable('event_guardian_consent_delivery_envelopes')) {
            Schema::create('event_guardian_consent_delivery_envelopes', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->unsignedBigInteger('consent_id');
                $table->unsignedBigInteger('outbox_id');
                $table->unsignedBigInteger('consent_version');
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
                    'uq_event_guardian_delivery_envelope_outbox',
                );
                $table->unique(
                    ['tenant_id', 'consent_id', 'consent_version'],
                    'uq_event_guardian_delivery_envelope_version',
                );
                $table->unique(
                    [
                        'tenant_id',
                        'event_id',
                        'id',
                        'consent_id',
                        'outbox_id',
                        'consent_version',
                    ],
                    'uq_event_guardian_delivery_envelope_scope',
                );
                $table->index(
                    ['tenant_id', 'status', 'expires_at', 'id'],
                    'idx_event_guardian_delivery_envelope_status',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'consent_id', 'id'],
                    'idx_event_guardian_delivery_envelope_consent',
                );
                $table->foreign(
                    ['tenant_id', 'event_id', 'consent_id'],
                    'fk_event_guardian_delivery_consent',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_guardian_consents')
                    ->restrictOnDelete();
                $table->foreign(
                    ['tenant_id', 'event_id', 'outbox_id'],
                    'fk_event_guardian_delivery_outbox',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_domain_outbox')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('event_guardian_consent_delivery_access')) {
            Schema::create('event_guardian_consent_delivery_access', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->integer('tenant_id');
                $table->integer('event_id');
                $table->unsignedBigInteger('envelope_id');
                $table->unsignedBigInteger('consent_id');
                $table->unsignedBigInteger('outbox_id');
                $table->unsignedBigInteger('consent_version');
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
                    'uq_event_guardian_delivery_access_key',
                );
                $table->index(
                    ['tenant_id', 'envelope_id', 'created_at', 'id'],
                    'idx_event_guardian_delivery_access_envelope',
                );
                $table->index(
                    ['tenant_id', 'event_id', 'operation', 'created_at', 'id'],
                    'idx_event_guardian_delivery_access_event',
                );
                $table->foreign(
                    [
                        'tenant_id',
                        'event_id',
                        'envelope_id',
                        'consent_id',
                        'outbox_id',
                        'consent_version',
                    ],
                    'fk_event_guardian_delivery_access_envelope',
                )->references([
                    'tenant_id',
                    'event_id',
                    'id',
                    'consent_id',
                    'outbox_id',
                    'consent_version',
                ])
                    ->on('event_guardian_consent_delivery_envelopes')
                    ->restrictOnDelete();
                $table->foreign(
                    ['tenant_id', 'event_id', 'consent_id'],
                    'fk_event_guardian_delivery_access_consent',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_guardian_consents')
                    ->restrictOnDelete();
                $table->foreign(
                    ['tenant_id', 'event_id', 'outbox_id'],
                    'fk_event_guardian_delivery_access_outbox',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_domain_outbox')
                    ->restrictOnDelete();
            });
        }

        $this->installAccessImmutabilityTriggers();
    }

    public function down(): void
    {
        foreach ([
            'event_guardian_consent_delivery_access',
            'event_guardian_consent_delivery_envelopes',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException(
                    'Event guardian consent delivery evidence exists and cannot be rolled back.',
                );
            }
        }
        if (Schema::hasColumn('event_notification_deliveries', 'external_recipient_hash')
            && DB::table('event_notification_deliveries')
                ->whereNotNull('external_recipient_hash')
                ->exists()) {
            throw new LogicException(
                'External Event delivery evidence exists and cannot be rolled back.',
            );
        }

        $this->dropAccessImmutabilityTriggers();
        Schema::dropIfExists('event_guardian_consent_delivery_access');
        Schema::dropIfExists('event_guardian_consent_delivery_envelopes');
        $this->dropRecipientChecks();

        if (Schema::hasIndex(
            'event_notification_deliveries',
            'uq_event_delivery_external_recipient_channel',
        )) {
            Schema::table('event_notification_deliveries', function (Blueprint $table): void {
                $table->dropUnique('uq_event_delivery_external_recipient_channel');
            });
        }
        if (Schema::hasColumn('event_notification_deliveries', 'external_recipient_hash')) {
            Schema::table('event_notification_deliveries', function (Blueprint $table): void {
                $table->dropColumn('external_recipient_hash');
                $table->integer('recipient_user_id')->nullable(false)->change();
            });
        }
        if (Schema::hasColumn('event_guardian_consents', 'guardian_locale')) {
            Schema::table('event_guardian_consents', function (Blueprint $table): void {
                $table->dropColumn('guardian_locale');
            });
        }
        if (Schema::hasIndex('event_domain_outbox', 'uq_event_outbox_scope_id')) {
            Schema::table('event_domain_outbox', function (Blueprint $table): void {
                $table->dropUnique('uq_event_outbox_scope_id');
            });
        }
        if (Schema::hasIndex(
            'event_guardian_consents',
            'uq_event_guardian_consent_scope_id',
        )) {
            Schema::table('event_guardian_consents', function (Blueprint $table): void {
                $table->dropUnique('uq_event_guardian_consent_scope_id');
            });
        }
    }

    private function installRecipientChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! $this->constraintExists(self::RECIPIENT_XOR_CHECK)) {
            DB::statement(
                'ALTER TABLE `event_notification_deliveries` ADD CONSTRAINT `'
                . self::RECIPIENT_XOR_CHECK
                . '` CHECK ((`recipient_user_id` IS NOT NULL AND `external_recipient_hash` IS NULL)'
                . ' OR (`recipient_user_id` IS NULL AND `external_recipient_hash` IS NOT NULL))',
            );
        }
        if (! $this->constraintExists(self::EXTERNAL_HASH_CHECK)) {
            DB::statement(
                'ALTER TABLE `event_notification_deliveries` ADD CONSTRAINT `'
                . self::EXTERNAL_HASH_CHECK
                . '` CHECK (`external_recipient_hash` IS NULL'
                . " OR `external_recipient_hash` REGEXP '^[0-9a-f]{64}$')",
            );
        }
    }

    private function dropRecipientChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ([self::RECIPIENT_XOR_CHECK, self::EXTERNAL_HASH_CHECK] as $name) {
            if ($this->constraintExists($name)) {
                DB::statement(
                    'ALTER TABLE `event_notification_deliveries` DROP CONSTRAINT `' . $name . '`',
                );
            }
        }
    }

    private function constraintExists(string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'event_notification_deliveries')
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }

    private function installAccessImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql'
            || ! Schema::hasTable('event_guardian_consent_delivery_access')) {
            return;
        }
        $this->createTrigger(
            self::ACCESS_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_guardian_consent_delivery_access` FOR EACH ROW',
        );
        $this->createTrigger(
            self::ACCESS_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_guardian_consent_delivery_access` FOR EACH ROW',
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
            . "'event_guardian_delivery_access_immutable'",
        );
    }

    private function dropAccessImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::ACCESS_UPDATE_TRIGGER . '`');
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::ACCESS_DELETE_TRIGGER . '`');
    }
};
