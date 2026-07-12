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
    private const EVENT_OCCURRENCE_INDEX = 'uq_events_checkin_occurrence';
    private const REGISTRATION_SCOPE_INDEX = 'uq_event_registrations_checkin_scope';
    private const ATTENDANCE_ACTIVITY_SCOPE_INDEX = 'uq_event_attendance_activity_checkin_scope';
    private const MANIFEST_VERSION_INDEX = 'idx_events_checkin_manifest_version';

    /** @var list<string> */
    private const TRIGGERS = [
        'trg_event_qr_credential_validate',
        'trg_event_qr_credential_update',
        'trg_event_qr_credential_no_delete',
        'trg_event_checkin_device_validate',
        'trg_event_checkin_device_update',
        'trg_event_checkin_device_no_delete',
        'trg_event_offline_batch_update',
        'trg_event_offline_batch_no_delete',
        'trg_event_offline_item_no_update',
        'trg_event_offline_item_no_delete',
        'trg_event_offline_decision_validate',
        'trg_event_offline_decision_no_update',
        'trg_event_offline_decision_no_delete',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('event_registrations')
            || ! Schema::hasTable('event_attendance_activity')) {
            return;
        }

        $this->addCompositeParentIndexes();
        $this->addManifestVersion();
        $this->createCredentials();
        $this->createDevices();
        $this->createSyncBatches();
        $this->createSyncItems();
        $this->createSyncDecisions();
        $this->installCheckConstraints();
        $this->installValidationAndImmutabilityTriggers();
    }

    public function down(): void
    {
        if ($this->hasDependentSchema()) {
            throw new LogicException('event_offline_checkin_rollback_refused_dependents_exist');
        }
        if ($this->containsDurableEvidence()) {
            throw new LogicException('event_offline_checkin_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        Schema::dropIfExists('event_offline_sync_decisions');
        Schema::dropIfExists('event_offline_sync_items');
        Schema::dropIfExists('event_offline_sync_batches');
        Schema::dropIfExists('event_checkin_devices');
        Schema::dropIfExists('event_checkin_credentials');

        if (Schema::hasTable('events')
            && Schema::hasIndex('events', self::MANIFEST_VERSION_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->dropIndex(self::MANIFEST_VERSION_INDEX);
            });
        }
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'checkin_manifest_version')) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->dropColumn('checkin_manifest_version');
            });
        }

        $this->dropCompositeParentIndexes();
    }

    private function addCompositeParentIndexes(): void
    {
        if (! Schema::hasIndex('events', self::EVENT_OCCURRENCE_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'id', 'occurrence_key'],
                    self::EVENT_OCCURRENCE_INDEX,
                );
            });
        }

        if (! Schema::hasIndex('event_registrations', self::REGISTRATION_SCOPE_INDEX)) {
            Schema::table('event_registrations', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'event_id', 'id', 'user_id'],
                    self::REGISTRATION_SCOPE_INDEX,
                );
            });
        }

        if (! Schema::hasIndex(
            'event_attendance_activity',
            self::ATTENDANCE_ACTIVITY_SCOPE_INDEX,
        )) {
            Schema::table('event_attendance_activity', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'event_id', 'id'],
                    self::ATTENDANCE_ACTIVITY_SCOPE_INDEX,
                );
            });
        }
    }

    private function dropCompositeParentIndexes(): void
    {
        if (Schema::hasTable('event_attendance_activity')
            && Schema::hasIndex(
                'event_attendance_activity',
                self::ATTENDANCE_ACTIVITY_SCOPE_INDEX,
            )) {
            Schema::table('event_attendance_activity', static function (Blueprint $table): void {
                $table->dropUnique(self::ATTENDANCE_ACTIVITY_SCOPE_INDEX);
            });
        }

        if (Schema::hasTable('event_registrations')
            && Schema::hasIndex('event_registrations', self::REGISTRATION_SCOPE_INDEX)) {
            Schema::table('event_registrations', static function (Blueprint $table): void {
                $table->dropUnique(self::REGISTRATION_SCOPE_INDEX);
            });
        }

        if (Schema::hasTable('events') && Schema::hasIndex('events', self::EVENT_OCCURRENCE_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->dropUnique(self::EVENT_OCCURRENCE_INDEX);
            });
        }
    }

    private function addManifestVersion(): void
    {
        if (! Schema::hasColumn('events', 'checkin_manifest_version')) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->unsignedBigInteger('checkin_manifest_version')->default(0)
                    ->comment('Monotonic version of the revocable offline check-in projection');
            });
        }

        if (! Schema::hasIndex('events', self::MANIFEST_VERSION_INDEX)) {
            Schema::table('events', static function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'checkin_manifest_version', 'id'],
                    self::MANIFEST_VERSION_INDEX,
                );
            });
        }
    }

    private function createCredentials(): void
    {
        if (Schema::hasTable('event_checkin_credentials')) {
            return;
        }

        Schema::create('event_checkin_credentials', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('credential_version');
            $table->string('status', 16)->default('active');
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->char('token_hash', 64);
            $table->char('token_fingerprint', 16);
            $table->char('issue_idempotency_hash', 64);
            $table->integer('issued_by_user_id');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->integer('revoked_by_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique('token_hash', 'uq_event_qr_credential_hash');
            $table->unique(
                ['tenant_id', 'issue_idempotency_hash'],
                'uq_event_qr_credential_idempotency',
            );
            $table->unique(
                ['tenant_id', 'registration_id', 'credential_version'],
                'uq_event_qr_credential_version',
            );
            $table->unique(
                ['tenant_id', 'registration_id', 'active_slot'],
                'uq_event_qr_credential_active',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_qr_credential_scope_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'status', 'expires_at', 'id'],
                'idx_event_qr_credential_manifest',
            );
            $table->index(
                ['tenant_id', 'event_id', 'token_fingerprint'],
                'idx_event_qr_credential_fingerprint',
            );

            $table->foreign('tenant_id', 'fk_event_qr_credential_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_qr_credential_occurrence',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id', 'user_id'],
                'fk_event_qr_credential_registration',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(
                ['issued_by_user_id', 'tenant_id'],
                'fk_event_qr_credential_issuer_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['revoked_by_user_id', 'tenant_id'],
                'fk_event_qr_credential_revoker_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'superseded_by_id'],
                'fk_event_qr_credential_successor',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_checkin_credentials')->restrictOnDelete();
        });
    }

    private function createDevices(): void
    {
        if (Schema::hasTable('event_checkin_devices')) {
            return;
        }

        Schema::create('event_checkin_devices', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->uuid('public_id');
            $table->string('label', 120);
            $table->integer('registered_by_user_id');
            $table->unsignedBigInteger('device_version')->default(1);
            $table->string('status', 16)->default('active');
            $table->char('secret_hash', 64);
            $table->char('secret_fingerprint', 16);
            $table->char('registration_idempotency_hash', 64);
            $table->char('last_rotation_idempotency_hash', 64)->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('expires_at');
            $table->timestamp('rotated_at')->nullable();
            $table->integer('revoked_by_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique('public_id', 'uq_event_checkin_device_public');
            $table->unique('secret_hash', 'uq_event_checkin_device_secret');
            $table->unique(
                ['tenant_id', 'registration_idempotency_hash'],
                'uq_event_checkin_device_idempotency',
            );
            $table->unique(
                ['tenant_id', 'last_rotation_idempotency_hash'],
                'uq_event_checkin_device_rotation',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_checkin_device_scope_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'status', 'expires_at', 'id'],
                'idx_event_checkin_device_event',
            );
            $table->index(
                ['tenant_id', 'event_id', 'secret_fingerprint'],
                'idx_event_checkin_device_fingerprint',
            );

            $table->foreign('tenant_id', 'fk_event_checkin_device_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_checkin_device_occurrence',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['registered_by_user_id', 'tenant_id'],
                'fk_event_checkin_device_actor_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['revoked_by_user_id', 'tenant_id'],
                'fk_event_checkin_device_revoker_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createSyncBatches(): void
    {
        if (Schema::hasTable('event_offline_sync_batches')) {
            return;
        }

        Schema::create('event_offline_sync_batches', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('device_id');
            $table->integer('submitted_by_user_id');
            $table->string('client_batch_id', 100);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('manifest_version');
            $table->unsignedSmallInteger('item_count');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('claim_attempts')->default(0);
            $table->timestamp('available_at');
            $table->char('claim_token_hash', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('last_claimed_at')->nullable();
            $table->timestamp('last_released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->string('terminal_code', 64)->nullable();
            $table->string('terminal_reason', 500)->nullable();
            $table->integer('terminal_by_user_id')->nullable();
            $table->unsignedSmallInteger('accepted_count')->default(0);
            $table->unsignedSmallInteger('conflict_count')->default(0);
            $table->unsignedSmallInteger('rejected_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'device_id', 'client_batch_id'],
                'uq_event_offline_batch_client',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'device_id', 'id'],
                'uq_event_offline_batch_scope_id',
            );
            $table->index(
                ['status', 'available_at', 'claim_expires_at', 'id'],
                'idx_event_offline_batch_claim',
            );
            $table->index(
                ['tenant_id', 'event_id', 'status', 'id'],
                'idx_event_offline_batch_event',
            );

            $table->foreign('tenant_id', 'fk_event_offline_batch_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_offline_batch_occurrence',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'device_id'],
                'fk_event_offline_batch_device',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_checkin_devices')->restrictOnDelete();
            $table->foreign(
                ['submitted_by_user_id', 'tenant_id'],
                'fk_event_offline_batch_actor_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['terminal_by_user_id', 'tenant_id'],
                'fk_event_offline_batch_terminal_actor_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createSyncItems(): void
    {
        if (Schema::hasTable('event_offline_sync_items')) {
            return;
        }

        Schema::create('event_offline_sync_items', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedSmallInteger('item_position');
            $table->string('client_nonce', 100);
            $table->string('operation', 16);
            $table->dateTime('observed_at');
            $table->unsignedBigInteger('expected_attendance_version');
            $table->char('credential_fingerprint', 16);
            $table->char('credential_hash_reference', 64);
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('submitted_reason', 500)->nullable();
            $table->char('submitted_payload_hash', 64);
            $table->string('initial_outcome', 16)->default('pending');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'device_id', 'client_nonce'],
                'uq_event_offline_item_nonce',
            );
            $table->unique(
                ['tenant_id', 'batch_id', 'item_position'],
                'uq_event_offline_item_position',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'batch_id', 'id'],
                'uq_event_offline_item_scope_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'credential_fingerprint', 'id'],
                'idx_event_offline_item_credential',
            );
            $table->index(
                ['tenant_id', 'event_id', 'user_id', 'observed_at', 'id'],
                'idx_event_offline_item_subject',
            );

            $table->foreign('tenant_id', 'fk_event_offline_item_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'device_id', 'batch_id'],
                'fk_event_offline_item_batch',
            )->references(['tenant_id', 'event_id', 'device_id', 'id'])
                ->on('event_offline_sync_batches')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'credential_id'],
                'fk_event_offline_item_credential',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_checkin_credentials')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id', 'user_id'],
                'fk_event_offline_item_registration',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_registrations')->restrictOnDelete();
        });
    }

    private function createSyncDecisions(): void
    {
        if (Schema::hasTable('event_offline_sync_decisions')) {
            return;
        }

        Schema::create('event_offline_sync_decisions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('decision_version');
            $table->string('outcome', 16);
            $table->string('decision_code', 64);
            $table->string('decision_reason', 500)->nullable();
            $table->unsignedBigInteger('attendance_version_before')->nullable();
            $table->unsignedBigInteger('attendance_version_after')->nullable();
            $table->unsignedBigInteger('attendance_activity_id')->nullable();
            $table->integer('decided_by_user_id');
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'item_id', 'decision_version'],
                'uq_event_offline_decision_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_key_hash'],
                'uq_event_offline_decision_idempotency',
            );
            $table->index(
                ['tenant_id', 'event_id', 'batch_id', 'outcome', 'id'],
                'idx_event_offline_decision_batch',
            );

            $table->foreign('tenant_id', 'fk_event_offline_decision_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'batch_id', 'item_id'],
                'fk_event_offline_decision_item',
            )->references(['tenant_id', 'event_id', 'batch_id', 'id'])
                ->on('event_offline_sync_items')->restrictOnDelete();
            $table->foreign(
                ['decided_by_user_id', 'tenant_id'],
                'fk_event_offline_decision_actor_tenant',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'attendance_activity_id'],
                'fk_event_offline_decision_attendance',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_attendance_activity')->restrictOnDelete();
        });
    }

    private function installCheckConstraints(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_checkin_credentials' => [
                'chk_event_qr_credential_hash' => "`token_hash` REGEXP '^[0-9a-f]{64}$' AND `token_fingerprint` = LEFT(`token_hash`, 16)",
                'chk_event_qr_credential_version' => '`credential_version` > 0',
                'chk_event_qr_credential_expiry' => '`issued_at` < `expires_at`',
                'chk_event_qr_credential_state' => "((`status` = 'active' AND `active_slot` = 1 AND `superseded_by_id` IS NULL AND `rotated_at` IS NULL AND `revoked_by_user_id` IS NULL AND `revoked_at` IS NULL AND `revocation_reason` IS NULL AND `expired_at` IS NULL) OR (`status` = 'rotated' AND `active_slot` IS NULL AND `rotated_at` IS NOT NULL AND `revoked_by_user_id` IS NULL AND `revoked_at` IS NULL AND `revocation_reason` IS NULL AND `expired_at` IS NULL) OR (`status` = 'revoked' AND `active_slot` IS NULL AND `superseded_by_id` IS NULL AND `revoked_at` IS NOT NULL AND `revoked_by_user_id` IS NOT NULL AND `revocation_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`revocation_reason`)) > 0 AND `rotated_at` IS NULL AND `expired_at` IS NULL) OR (`status` = 'expired' AND `active_slot` IS NULL AND `superseded_by_id` IS NULL AND `expired_at` IS NOT NULL AND `rotated_at` IS NULL AND `revoked_by_user_id` IS NULL AND `revoked_at` IS NULL AND `revocation_reason` IS NULL))",
            ],
            'event_checkin_devices' => [
                'chk_event_checkin_device_hash' => "`secret_hash` REGEXP '^[0-9a-f]{64}$' AND `secret_fingerprint` = LEFT(`secret_hash`, 16)",
                'chk_event_checkin_device_version' => '`device_version` > 0',
                'chk_event_checkin_device_expiry' => '`registered_at` < `expires_at`',
                'chk_event_checkin_device_state' => "((`status` = 'active' AND `revoked_by_user_id` IS NULL AND `revoked_at` IS NULL AND `revocation_reason` IS NULL AND `expired_at` IS NULL) OR (`status` = 'revoked' AND `revoked_at` IS NOT NULL AND `revoked_by_user_id` IS NOT NULL AND `revocation_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`revocation_reason`)) > 0 AND `expired_at` IS NULL) OR (`status` = 'expired' AND `expired_at` IS NOT NULL AND `revoked_by_user_id` IS NULL AND `revoked_at` IS NULL AND `revocation_reason` IS NULL))",
            ],
            'event_offline_sync_batches' => [
                'chk_event_offline_batch_count' => '`item_count` BETWEEN 1 AND 500',
                'chk_event_offline_batch_status' => "`status` IN ('pending','processing','completed','dead_letter')",
                'chk_event_offline_batch_attempts' => '`claim_attempts` <= 100',
                'chk_event_offline_batch_claim' => "((`status` = 'processing' AND `claim_token_hash` IS NOT NULL AND `claimed_at` IS NOT NULL AND `claim_expires_at` IS NOT NULL) OR (`status` <> 'processing' AND `claim_token_hash` IS NULL AND `claimed_at` IS NULL AND `claim_expires_at` IS NULL))",
                'chk_event_offline_batch_outcomes' => '(`accepted_count` + `conflict_count` + `rejected_count` <= `item_count`) AND (`status` <> \'completed\' OR (`accepted_count` + `conflict_count` + `rejected_count` = `item_count` AND `completed_at` IS NOT NULL)) AND (`status` = \'completed\' OR `completed_at` IS NULL)',
                'chk_event_offline_batch_terminal' => "((`status` = 'dead_letter' AND `dead_lettered_at` IS NOT NULL AND `terminal_code` IS NOT NULL AND CHAR_LENGTH(TRIM(`terminal_code`)) > 0 AND `completed_at` IS NULL) OR (`status` <> 'dead_letter' AND `dead_lettered_at` IS NULL AND `terminal_code` IS NULL AND `terminal_reason` IS NULL AND `terminal_by_user_id` IS NULL))",
            ],
            'event_offline_sync_items' => [
                'chk_event_offline_item_operation' => "`operation` IN ('check_in','check_out','no_show','undo')",
                'chk_event_offline_item_hash' => "`credential_hash_reference` REGEXP '^[0-9a-f]{64}$' AND `credential_fingerprint` = LEFT(`credential_hash_reference`, 16)",
                'chk_event_offline_item_outcome' => "`initial_outcome` = 'pending'",
                'chk_event_offline_item_subject' => '((`credential_id` IS NULL AND `registration_id` IS NULL AND `user_id` IS NULL) OR (`credential_id` IS NOT NULL AND `registration_id` IS NOT NULL AND `user_id` IS NOT NULL))',
            ],
            'event_offline_sync_decisions' => [
                'chk_event_offline_decision_outcome' => "`outcome` IN ('accepted','conflict','rejected')",
                'chk_event_offline_decision_version' => '`decision_version` > 0',
                'chk_event_offline_decision_attendance' => "((`outcome` = 'accepted' AND `attendance_version_before` IS NOT NULL AND `attendance_version_after` IS NOT NULL AND `attendance_version_after` > `attendance_version_before` AND `attendance_activity_id` IS NOT NULL) OR (`outcome` IN ('conflict','rejected') AND `attendance_version_after` IS NULL AND `attendance_activity_id` IS NULL))",
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

    private function installValidationAndImmutabilityTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createTrigger(
            'trg_event_qr_credential_validate',
            'BEFORE INSERT ON `event_checkin_credentials` FOR EACH ROW '
                . "BEGIN IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_concrete_occurrence_required'; END IF; "
                . "IF (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id` AND `registration_state` = 'confirmed') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_confirmed_registration_required'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_checkin_device_validate',
            'BEFORE INSERT ON `event_checkin_devices` FOR EACH ROW '
                . "BEGIN IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_device_concrete_occurrence_required'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_qr_credential_update',
            'BEFORE UPDATE ON `event_checkin_credentials` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`credential_version` <=> NEW.`credential_version`) OR NOT (OLD.`token_hash` <=> NEW.`token_hash`) OR NOT (OLD.`token_fingerprint` <=> NEW.`token_fingerprint`) OR NOT (OLD.`issue_idempotency_hash` <=> NEW.`issue_idempotency_hash`) OR NOT (OLD.`issued_by_user_id` <=> NEW.`issued_by_user_id`) OR NOT (OLD.`issued_at` <=> NEW.`issued_at`) OR NOT (OLD.`expires_at` <=> NEW.`expires_at`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_credential_identity_immutable'; END IF; "
                . "IF OLD.`status` = 'active' AND NEW.`status` = 'active' AND NOT (OLD.`superseded_by_id` <=> NEW.`superseded_by_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_credential_successor_invalid'; END IF; "
                . "IF OLD.`status` = 'active' AND NEW.`status` = 'rotated' AND NEW.`superseded_by_id` IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_credential_successor_invalid'; END IF; "
                . "IF OLD.`status` <> 'active' AND NOT (OLD.`status` = 'rotated' AND NEW.`status` = 'rotated' AND OLD.`superseded_by_id` IS NULL AND NEW.`superseded_by_id` IS NOT NULL AND OLD.`active_slot` <=> NEW.`active_slot` AND OLD.`rotated_at` <=> NEW.`rotated_at` AND OLD.`revoked_by_user_id` <=> NEW.`revoked_by_user_id` AND OLD.`revoked_at` <=> NEW.`revoked_at` AND OLD.`revocation_reason` <=> NEW.`revocation_reason` AND OLD.`expired_at` <=> NEW.`expired_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_qr_credential_terminal_immutable'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_checkin_device_update',
            'BEFORE UPDATE ON `event_checkin_devices` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`public_id` <=> NEW.`public_id`) OR NOT (OLD.`label` <=> NEW.`label`) OR NOT (OLD.`registered_by_user_id` <=> NEW.`registered_by_user_id`) OR NOT (OLD.`registration_idempotency_hash` <=> NEW.`registration_idempotency_hash`) OR NOT (OLD.`registered_at` <=> NEW.`registered_at`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_identity_immutable'; END IF; "
                . "IF OLD.`status` <> 'active' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_terminal_immutable'; END IF; "
                . "IF NEW.`device_version` < OLD.`device_version` OR NEW.`device_version` > OLD.`device_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_version_invalid'; END IF; "
                . "IF (NOT (OLD.`secret_hash` <=> NEW.`secret_hash`) OR NOT (OLD.`secret_fingerprint` <=> NEW.`secret_fingerprint`)) AND (NEW.`status` <> 'active' OR NEW.`device_version` <> OLD.`device_version` + 1 OR NEW.`last_rotation_idempotency_hash` IS NULL OR NEW.`last_rotation_idempotency_hash` <=> OLD.`last_rotation_idempotency_hash` OR NEW.`rotated_at` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_rotation_invalid'; END IF; "
                . "IF OLD.`secret_hash` <=> NEW.`secret_hash` AND OLD.`secret_fingerprint` <=> NEW.`secret_fingerprint` AND NEW.`status` = 'active' AND (NEW.`device_version` <> OLD.`device_version` OR NOT (OLD.`last_rotation_idempotency_hash` <=> NEW.`last_rotation_idempotency_hash`) OR NOT (OLD.`expires_at` <=> NEW.`expires_at`) OR NOT (OLD.`rotated_at` <=> NEW.`rotated_at`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_rotation_invalid'; END IF; "
                . "IF NEW.`status` <> OLD.`status` AND NEW.`device_version` <> OLD.`device_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_checkin_device_version_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_offline_batch_update',
            'BEFORE UPDATE ON `event_offline_sync_batches` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`device_id` <=> NEW.`device_id`) OR NOT (OLD.`submitted_by_user_id` <=> NEW.`submitted_by_user_id`) OR NOT (OLD.`client_batch_id` <=> NEW.`client_batch_id`) OR NOT (OLD.`payload_hash` <=> NEW.`payload_hash`) OR NOT (OLD.`manifest_version` <=> NEW.`manifest_version`) OR NOT (OLD.`item_count` <=> NEW.`item_count`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_batch_evidence_immutable'; END IF; "
                . "IF OLD.`status` IN ('completed','dead_letter') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_batch_terminal_immutable'; END IF; "
                . "IF NEW.`claim_attempts` < OLD.`claim_attempts` OR NEW.`claim_attempts` > OLD.`claim_attempts` + 1 OR NEW.`accepted_count` < OLD.`accepted_count` OR NEW.`conflict_count` < OLD.`conflict_count` OR NEW.`rejected_count` < OLD.`rejected_count` THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_batch_progress_invalid'; END IF; "
                . "IF NEW.`status` = 'processing' AND ((OLD.`status` = 'pending' AND NEW.`claim_attempts` <> OLD.`claim_attempts` + 1) OR (OLD.`status` = 'processing' AND NOT ((NEW.`claim_attempts` = OLD.`claim_attempts` AND NEW.`claim_token_hash` <=> OLD.`claim_token_hash`) OR (NEW.`claim_attempts` = OLD.`claim_attempts` + 1 AND NOT (NEW.`claim_token_hash` <=> OLD.`claim_token_hash`))))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_batch_claim_invalid'; END IF; "
                . "IF (OLD.`status` = 'pending' AND NEW.`status` NOT IN ('processing','dead_letter')) OR (OLD.`status` = 'processing' AND NEW.`status` NOT IN ('processing','pending','completed','dead_letter')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_batch_transition_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_offline_decision_validate',
            'BEFORE INSERT ON `event_offline_sync_decisions` FOR EACH ROW BEGIN '
                . "IF NEW.`outcome` = 'accepted' AND (SELECT COUNT(*) FROM `event_offline_sync_items` AS i INNER JOIN `event_attendance_activity` AS a ON a.`tenant_id` = i.`tenant_id` AND a.`event_id` = i.`event_id` AND a.`user_id` = i.`user_id` WHERE i.`tenant_id` = NEW.`tenant_id` AND i.`event_id` = NEW.`event_id` AND i.`batch_id` = NEW.`batch_id` AND i.`id` = NEW.`item_id` AND a.`id` = NEW.`attendance_activity_id` AND a.`attendance_version` = NEW.`attendance_version_after`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_offline_decision_attendance_mismatch'; END IF; END",
        );

        foreach ([
            'trg_event_qr_credential_no_delete' => ['event_checkin_credentials', 'DELETE', 'event_qr_credential_delete_forbidden'],
            'trg_event_checkin_device_no_delete' => ['event_checkin_devices', 'DELETE', 'event_checkin_device_delete_forbidden'],
            'trg_event_offline_batch_no_delete' => ['event_offline_sync_batches', 'DELETE', 'event_offline_batch_delete_forbidden'],
            'trg_event_offline_item_no_update' => ['event_offline_sync_items', 'UPDATE', 'event_offline_item_immutable'],
            'trg_event_offline_item_no_delete' => ['event_offline_sync_items', 'DELETE', 'event_offline_item_immutable'],
            'trg_event_offline_decision_no_update' => ['event_offline_sync_decisions', 'UPDATE', 'event_offline_decision_immutable'],
            'trg_event_offline_decision_no_delete' => ['event_offline_sync_decisions', 'DELETE', 'event_offline_decision_immutable'],
        ] as $name => [$table, $operation, $message]) {
            $this->createTrigger(
                $name,
                "BEFORE {$operation} ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
            );
        }
    }

    private function createTrigger(string $name, string $definition): void
    {
        if ($this->triggerExists($name)) {
            return;
        }

        DB::unprepared("CREATE TRIGGER `{$name}` {$definition}");
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

    private function containsDurableEvidence(): bool
    {
        foreach ([
            'event_offline_sync_decisions',
            'event_offline_sync_items',
            'event_offline_sync_batches',
            'event_checkin_devices',
            'event_checkin_credentials',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('events')
            && Schema::hasColumn('events', 'checkin_manifest_version')
            && DB::table('events')->where('checkin_manifest_version', '>', 0)->exists();
    }

    private function hasDependentSchema(): bool
    {
        // Migration 000056 and future slices may legitimately reuse these
        // composite parent keys. Refuse an out-of-order rollback before any
        // table/column is removed; normal reverse-order rollback drops the
        // dependent slice first and then proceeds here.
        if (Schema::hasTable('event_registration_settings')) {
            return true;
        }

        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $ownedChildren = [
            'event_checkin_credentials',
            'event_checkin_devices',
            'event_offline_sync_batches',
            'event_offline_sync_items',
            'event_offline_sync_decisions',
        ];

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->whereNotIn('TABLE_NAME', $ownedChildren)
            ->where(static function ($query): void {
                $query->where(static function ($event): void {
                    $event->where('REFERENCED_TABLE_NAME', 'events')
                        ->where('UNIQUE_CONSTRAINT_NAME', self::EVENT_OCCURRENCE_INDEX);
                })->orWhere(static function ($registration): void {
                    $registration->where('REFERENCED_TABLE_NAME', 'event_registrations')
                        ->where('UNIQUE_CONSTRAINT_NAME', self::REGISTRATION_SCOPE_INDEX);
                })->orWhere(static function ($attendance): void {
                    $attendance->where('REFERENCED_TABLE_NAME', 'event_attendance_activity')
                        ->where('UNIQUE_CONSTRAINT_NAME', self::ATTENDANCE_ACTIVITY_SCOPE_INDEX);
                });
            })
            ->exists();
    }
};
