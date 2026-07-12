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
        'event_participation_denial_history',
        'event_participation_denials',
        'event_guardian_consent_history',
        'event_guardian_consents',
        'event_safety_code_acknowledgements',
        'event_safety_requirement_history',
        'event_safety_requirement_versions',
        'event_safety_requirements',
    ];

    /** @var list<string> */
    private const TRIGGERS = [
        'trg_event_safety_requirements_insert',
        'trg_event_safety_requirements_update',
        'trg_event_safety_requirements_no_delete',
        'trg_event_safety_version_insert',
        'trg_event_safety_version_no_update',
        'trg_event_safety_version_no_delete',
        'trg_event_safety_history_no_update',
        'trg_event_safety_history_no_delete',
        'trg_event_safety_coc_insert',
        'trg_event_safety_coc_no_update',
        'trg_event_safety_coc_no_delete',
        'trg_event_guardian_consent_update',
        'trg_event_guardian_consent_no_delete',
        'trg_event_guardian_history_no_update',
        'trg_event_guardian_history_no_delete',
        'trg_event_participation_denial_insert',
        'trg_event_participation_denial_update',
        'trg_event_participation_denial_no_delete',
        'trg_event_denial_history_no_update',
        'trg_event_denial_history_no_delete',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tenants')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('events')
            || ! Schema::hasTable('user_blocks')
            || ! Schema::hasColumn('user_blocks', 'tenant_id')
            || ! Schema::hasColumn('users', 'date_of_birth')) {
            return;
        }

        $this->createRequirements();
        $this->createRequirementVersions();
        $this->createRequirementHistory();
        $this->createCodeAcknowledgements();
        $this->createGuardianConsents();
        $this->createGuardianConsentHistory();
        $this->createParticipationDenials();
        $this->createParticipationDenialHistory();
        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        if ($this->hasDependentSchema()) {
            throw new LogicException('event_safety_rollback_refused_dependents_exist');
        }
        if ($this->containsDurableEvidence()) {
            throw new LogicException('event_safety_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createRequirements(): void
    {
        if (Schema::hasTable('event_safety_requirements')) {
            return;
        }

        Schema::create('event_safety_requirements', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('revision')->default(1);
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('published_version')->nullable();
            $table->string('status', 16)->default('draft');
            $table->integer('created_by_user_id');
            $table->integer('updated_by_user_id');
            $table->integer('published_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('archived_by_user_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id'], 'uq_event_safety_requirements_event');
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_safety_requirements_id',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'uq_event_safety_requirements_occurrence',
            );
            $table->index(
                ['tenant_id', 'status', 'updated_at', 'event_id'],
                'idx_event_safety_requirements_status',
            );

            $table->foreign('tenant_id', 'fk_event_safety_requirements_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_safety_requirements_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['created_by_user_id', 'tenant_id'],
                'fk_event_safety_requirements_creator',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['updated_by_user_id', 'tenant_id'],
                'fk_event_safety_requirements_updater',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['published_by_user_id', 'tenant_id'],
                'fk_event_safety_requirements_publisher',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['archived_by_user_id', 'tenant_id'],
                'fk_event_safety_requirements_archiver',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRequirementVersions(): void
    {
        if (Schema::hasTable('event_safety_requirement_versions')) {
            return;
        }

        Schema::create('event_safety_requirement_versions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('requirements_id');
            $table->unsignedInteger('version_number');
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->boolean('guardian_consent_required')->default(false);
            $table->unsignedTinyInteger('minor_age_threshold')->nullable();
            $table->boolean('code_of_conduct_required')->default(false);
            $table->longText('code_of_conduct_text')->nullable();
            $table->string('code_of_conduct_text_version', 64)->nullable();
            $table->char('code_of_conduct_text_hash', 64)->nullable();
            $table->json('eligibility_policy_metadata');
            $table->char('eligibility_policy_hash', 64);
            $table->integer('captured_by_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'requirements_id', 'version_number'],
                'uq_event_safety_requirement_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_safety_requirement_version_key',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'requirements_id', 'id', 'version_number'],
                'uq_event_safety_requirement_version_id',
            );

            $table->foreign('tenant_id', 'fk_event_safety_version_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'requirements_id'],
                'fk_event_safety_version_requirements',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_safety_requirements')->restrictOnDelete();
            $table->foreign(
                ['captured_by_user_id', 'tenant_id'],
                'fk_event_safety_version_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRequirementHistory(): void
    {
        if (Schema::hasTable('event_safety_requirement_history')) {
            return;
        }

        Schema::create('event_safety_requirement_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('requirements_id');
            $table->unsignedBigInteger('requirements_revision');
            $table->unsignedBigInteger('requirements_version_id');
            $table->unsignedInteger('requirements_version_number');
            $table->string('action', 16);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'requirements_id', 'requirements_revision'],
                'uq_event_safety_requirement_history_revision',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_safety_requirement_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_safety_requirement_history_event',
            );

            $table->foreign('tenant_id', 'fk_event_safety_history_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'requirements_id'],
                'fk_event_safety_history_requirements',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_safety_requirements')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'event_id',
                    'requirements_id',
                    'requirements_version_id',
                    'requirements_version_number',
                ],
                'fk_event_safety_history_version',
            )->references([
                'tenant_id',
                'event_id',
                'requirements_id',
                'id',
                'version_number',
            ])->on('event_safety_requirement_versions')->restrictOnDelete();
            $table->foreign(
                ['actor_user_id', 'tenant_id'],
                'fk_event_safety_history_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createCodeAcknowledgements(): void
    {
        if (Schema::hasTable('event_safety_code_acknowledgements')) {
            return;
        }

        Schema::create('event_safety_code_acknowledgements', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('requirements_id');
            $table->unsignedBigInteger('requirements_version_id');
            $table->unsignedInteger('requirements_version_number');
            $table->integer('user_id');
            $table->unsignedBigInteger('evidence_sequence');
            $table->string('action', 16);
            $table->unsignedBigInteger('referenced_acknowledgement_id')->nullable();
            $table->string('text_version', 64);
            $table->char('text_hash', 64);
            $table->timestamp('acknowledged_at');
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('recorded_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'evidence_sequence'],
                'uq_event_safety_coc_sequence',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_safety_coc_key',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'id'],
                'uq_event_safety_coc_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'user_id', 'action', 'recorded_at', 'id'],
                'idx_event_safety_coc_current',
            );

            $table->foreign('tenant_id', 'fk_event_safety_coc_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'event_id',
                    'requirements_id',
                    'requirements_version_id',
                    'requirements_version_number',
                ],
                'fk_event_safety_coc_version',
            )->references([
                'tenant_id',
                'event_id',
                'requirements_id',
                'id',
                'version_number',
            ])->on('event_safety_requirement_versions')->restrictOnDelete();
            $table->foreign(
                ['user_id', 'tenant_id'],
                'fk_event_safety_coc_user',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['actor_user_id', 'tenant_id'],
                'fk_event_safety_coc_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'user_id', 'referenced_acknowledgement_id'],
                'fk_event_safety_coc_reference',
            )->references(['tenant_id', 'event_id', 'user_id', 'id'])
                ->on('event_safety_code_acknowledgements')->restrictOnDelete();
        });
    }

    private function createGuardianConsents(): void
    {
        if (Schema::hasTable('event_guardian_consents')) {
            return;
        }

        Schema::create('event_guardian_consents', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('requirements_id');
            $table->unsignedBigInteger('requirements_version_id');
            $table->unsignedInteger('requirements_version_number');
            $table->integer('minor_user_id');
            $table->longText('guardian_email_ciphertext');
            $table->longText('guardian_identity_ciphertext');
            $table->char('guardian_email_blind_hash', 64);
            $table->string('relationship_code', 32);
            $table->longText('consent_text');
            $table->string('consent_text_version', 64);
            $table->char('consent_text_hash', 64);
            $table->char('policy_binding_hash', 64);
            $table->char('token_hash', 64);
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->unsignedBigInteger('consent_version')->default(1);
            $table->integer('requested_by_user_id');
            $table->char('request_idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->timestamp('token_consumed_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->integer('withdrawn_by_user_id')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->integer('expired_by_user_id')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique('token_hash', 'uq_event_guardian_consent_token');
            $table->unique(
                ['tenant_id', 'request_idempotency_hash'],
                'uq_event_guardian_consent_request_key',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'minor_user_id', 'active_slot'],
                'uq_event_guardian_consent_active',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id', 'minor_user_id'],
                'uq_event_guardian_consent_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'minor_user_id', 'status', 'expires_at', 'id'],
                'idx_event_guardian_consent_minor',
            );

            $table->foreign('tenant_id', 'fk_event_guardian_consent_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_guardian_consent_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                [
                    'tenant_id',
                    'event_id',
                    'requirements_id',
                    'requirements_version_id',
                    'requirements_version_number',
                ],
                'fk_event_guardian_consent_version',
            )->references([
                'tenant_id',
                'event_id',
                'requirements_id',
                'id',
                'version_number',
            ])->on('event_safety_requirement_versions')->restrictOnDelete();
            $table->foreign(
                ['minor_user_id', 'tenant_id'],
                'fk_event_guardian_consent_minor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['requested_by_user_id', 'tenant_id'],
                'fk_event_guardian_consent_requester',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['withdrawn_by_user_id', 'tenant_id'],
                'fk_event_guardian_consent_withdrawer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['expired_by_user_id', 'tenant_id'],
                'fk_event_guardian_consent_expirer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createGuardianConsentHistory(): void
    {
        if (Schema::hasTable('event_guardian_consent_history')) {
            return;
        }

        Schema::create('event_guardian_consent_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('consent_id');
            $table->integer('minor_user_id');
            $table->unsignedBigInteger('consent_version');
            $table->string('status', 16);
            $table->string('action', 16);
            $table->string('actor_type', 24);
            $table->integer('actor_user_id')->nullable();
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('evidence');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'consent_id', 'consent_version'],
                'uq_event_guardian_consent_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_guardian_consent_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'minor_user_id', 'created_at', 'id'],
                'idx_event_guardian_consent_history_minor',
            );

            $table->foreign('tenant_id', 'fk_event_guardian_history_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'consent_id', 'minor_user_id'],
                'fk_event_guardian_history_consent',
            )->references(['tenant_id', 'event_id', 'id', 'minor_user_id'])
                ->on('event_guardian_consents')->restrictOnDelete();
            $table->foreign(
                ['actor_user_id', 'tenant_id'],
                'fk_event_guardian_history_actor',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createParticipationDenials(): void
    {
        if (Schema::hasTable('event_participation_denials')) {
            return;
        }

        Schema::create('event_participation_denials', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->integer('user_id');
            $table->string('decision', 16);
            $table->string('reason_code', 32);
            $table->string('status', 16)->default('active');
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
            $table->unsignedBigInteger('decision_version')->default(1);
            $table->integer('reviewed_by_user_id');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->char('create_idempotency_hash', 64);
            $table->char('create_request_hash', 64);
            $table->integer('withdrawn_by_user_id')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->integer('expired_by_user_id')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'user_id', 'active_slot'],
                'uq_event_participation_denial_active',
            );
            $table->unique(
                ['tenant_id', 'create_idempotency_hash'],
                'uq_event_participation_denial_create_key',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id', 'user_id'],
                'uq_event_participation_denial_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'user_id', 'status', 'effective_from', 'effective_until'],
                'idx_event_participation_denial_effective',
            );

            $table->foreign('tenant_id', 'fk_event_participation_denial_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_participation_denial_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(
                ['user_id', 'tenant_id'],
                'fk_event_participation_denial_user',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['reviewed_by_user_id', 'tenant_id'],
                'fk_event_participation_denial_reviewer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['withdrawn_by_user_id', 'tenant_id'],
                'fk_event_participation_denial_withdrawer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(
                ['expired_by_user_id', 'tenant_id'],
                'fk_event_participation_denial_expirer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createParticipationDenialHistory(): void
    {
        if (Schema::hasTable('event_participation_denial_history')) {
            return;
        }

        Schema::create('event_participation_denial_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('denial_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('decision_version');
            $table->string('decision', 16);
            $table->string('reason_code', 32);
            $table->string('status', 16);
            $table->string('action', 16);
            $table->integer('reviewer_user_id');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'denial_id', 'decision_version'],
                'uq_event_participation_denial_history_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_participation_denial_history_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'user_id', 'created_at', 'id'],
                'idx_event_participation_denial_history_user',
            );

            $table->foreign('tenant_id', 'fk_event_denial_history_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'denial_id', 'user_id'],
                'fk_event_denial_history_denial',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_participation_denials')->restrictOnDelete();
            $table->foreign(
                ['reviewer_user_id', 'tenant_id'],
                'fk_event_denial_history_reviewer',
            )->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_safety_requirements' => [
                'chk_event_safety_requirements_versions' => '`revision` > 0 AND `current_version` > 0 AND (`published_version` IS NULL OR (`published_version` > 0 AND `published_version` <= `current_version`))',
                'chk_event_safety_requirements_state' => "((`status` = 'draft' AND `published_version` IS NULL AND `published_by_user_id` IS NULL AND `published_at` IS NULL AND `archived_by_user_id` IS NULL AND `archived_at` IS NULL) OR (`status` = 'published' AND `published_version` = `current_version` AND `published_by_user_id` IS NOT NULL AND `published_at` IS NOT NULL AND `archived_by_user_id` IS NULL AND `archived_at` IS NULL) OR (`status` = 'archived' AND `archived_by_user_id` IS NOT NULL AND `archived_at` IS NOT NULL AND ((`published_version` IS NULL AND `published_by_user_id` IS NULL AND `published_at` IS NULL) OR (`published_version` IS NOT NULL AND `published_by_user_id` IS NOT NULL AND `published_at` IS NOT NULL))))",
            ],
            'event_safety_requirement_versions' => [
                'chk_event_safety_version_number' => '`version_number` > 0',
                'chk_event_safety_version_ages' => '(`minimum_age` IS NULL OR `minimum_age` <= 125) AND ((`guardian_consent_required` = 0 AND `minor_age_threshold` IS NULL) OR (`guardian_consent_required` = 1 AND `minor_age_threshold` BETWEEN 1 AND 125))',
                'chk_event_safety_version_coc' => "((`code_of_conduct_required` = 0 AND `code_of_conduct_text` IS NULL AND `code_of_conduct_text_version` IS NULL AND `code_of_conduct_text_hash` IS NULL) OR (`code_of_conduct_required` = 1 AND `code_of_conduct_text` IS NOT NULL AND CHAR_LENGTH(TRIM(`code_of_conduct_text`)) > 0 AND `code_of_conduct_text_version` IS NOT NULL AND CHAR_LENGTH(TRIM(`code_of_conduct_text_version`)) > 0 AND `code_of_conduct_text_hash` REGEXP '^[0-9a-f]{64}$'))",
                'chk_event_safety_version_hashes' => "`eligibility_policy_hash` REGEXP '^[0-9a-f]{64}$' AND `idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_safety_requirement_history' => [
                'chk_event_safety_history_action' => "`action` IN ('saved','published','archived')",
                'chk_event_safety_history_versions' => '`requirements_revision` > 0 AND `requirements_version_number` > 0',
                'chk_event_safety_history_hashes' => "`idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_safety_code_acknowledgements' => [
                'chk_event_safety_coc_action' => "`actor_user_id` = `user_id` AND ((`action` = 'acknowledged' AND `referenced_acknowledgement_id` IS NULL) OR (`action` IN ('withdrawn','replaced') AND `referenced_acknowledgement_id` IS NOT NULL))",
                'chk_event_safety_coc_versions' => '`requirements_version_number` > 0 AND `evidence_sequence` > 0',
                'chk_event_safety_coc_hashes' => "`text_hash` REGEXP '^[0-9a-f]{64}$' AND `idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_guardian_consents' => [
                'chk_event_guardian_consent_relationship' => "`relationship_code` IN ('parent','guardian','legal_guardian','carer')",
                'chk_event_guardian_consent_version' => '`requirements_version_number` > 0 AND `consent_version` > 0',
                'chk_event_guardian_consent_hashes' => "`guardian_email_blind_hash` REGEXP '^[0-9a-f]{64}$' AND `consent_text_hash` REGEXP '^[0-9a-f]{64}$' AND `policy_binding_hash` REGEXP '^[0-9a-f]{64}$' AND `token_hash` REGEXP '^[0-9a-f]{64}$' AND `request_idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
                'chk_event_guardian_consent_expiry' => '`requested_at` < `expires_at`',
                'chk_event_guardian_consent_state' => "((`status` = 'pending' AND `active_slot` = 1 AND `token_consumed_at` IS NULL AND `granted_at` IS NULL AND `withdrawn_by_user_id` IS NULL AND `withdrawn_at` IS NULL AND `expired_by_user_id` IS NULL AND `expired_at` IS NULL) OR (`status` = 'active' AND `active_slot` = 1 AND `token_consumed_at` IS NOT NULL AND `granted_at` IS NOT NULL AND `withdrawn_by_user_id` IS NULL AND `withdrawn_at` IS NULL AND `expired_by_user_id` IS NULL AND `expired_at` IS NULL) OR (`status` = 'withdrawn' AND `active_slot` IS NULL AND `withdrawn_by_user_id` IS NOT NULL AND `withdrawn_at` IS NOT NULL AND `expired_by_user_id` IS NULL AND `expired_at` IS NULL) OR (`status` = 'expired' AND `active_slot` IS NULL AND `expired_by_user_id` IS NOT NULL AND `expired_at` IS NOT NULL AND `withdrawn_by_user_id` IS NULL AND `withdrawn_at` IS NULL))",
            ],
            'event_guardian_consent_history' => [
                'chk_event_guardian_history_action' => "`action` IN ('requested','granted','withdrawn','expired')",
                'chk_event_guardian_history_status' => "`status` IN ('pending','active','withdrawn','expired')",
                'chk_event_guardian_history_actor' => "((`actor_type` = 'platform_user' AND `actor_user_id` IS NOT NULL) OR (`actor_type` = 'guardian_external' AND `actor_user_id` IS NULL))",
                'chk_event_guardian_history_version' => '`consent_version` > 0',
                'chk_event_guardian_history_hashes' => "`idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_participation_denials' => [
                'chk_event_participation_denial_decision' => "`decision` IN ('deny','remove')",
                'chk_event_participation_denial_reason' => "`reason_code` IN ('safeguarding_policy','minimum_age','guardian_consent','code_of_conduct','conduct_violation','safety_review','user_block')",
                'chk_event_participation_denial_window' => '`effective_until` IS NULL OR `effective_until` > `effective_from`',
                'chk_event_participation_denial_version' => '`decision_version` > 0',
                'chk_event_participation_denial_state' => "((`status` = 'active' AND `active_slot` = 1 AND `withdrawn_by_user_id` IS NULL AND `withdrawn_at` IS NULL AND `expired_by_user_id` IS NULL AND `expired_at` IS NULL) OR (`status` = 'withdrawn' AND `active_slot` IS NULL AND `withdrawn_by_user_id` IS NOT NULL AND `withdrawn_at` IS NOT NULL AND `expired_by_user_id` IS NULL AND `expired_at` IS NULL) OR (`status` = 'expired' AND `active_slot` IS NULL AND `expired_by_user_id` IS NOT NULL AND `expired_at` IS NOT NULL AND `withdrawn_by_user_id` IS NULL AND `withdrawn_at` IS NULL))",
                'chk_event_participation_denial_hashes' => "`create_idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `create_request_hash` REGEXP '^[0-9a-f]{64}$'",
            ],
            'event_participation_denial_history' => [
                'chk_event_denial_history_action' => "`action` IN ('recorded','withdrawn','expired')",
                'chk_event_denial_history_status' => "`status` IN ('active','withdrawn','expired')",
                'chk_event_denial_history_decision' => "`decision` IN ('deny','remove')",
                'chk_event_denial_history_reason' => "`reason_code` IN ('safeguarding_policy','minimum_age','guardian_consent','code_of_conduct','conduct_violation','safety_review','user_block')",
                'chk_event_denial_history_window' => '`effective_until` IS NULL OR `effective_until` > `effective_from`',
                'chk_event_denial_history_version' => '`decision_version` > 0',
                'chk_event_denial_history_hashes' => "`idempotency_hash` REGEXP '^[0-9a-f]{64}$' AND `request_hash` REGEXP '^[0-9a-f]{64}$'",
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

    private function installTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->createTrigger(
            'trg_event_safety_requirements_insert',
            'BEFORE INSERT ON `event_safety_requirements` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_concrete_event_required'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_safety_requirements_update',
            'BEFORE UPDATE ON `event_safety_requirements` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`created_by_user_id` <=> NEW.`created_by_user_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_identity_immutable'; END IF; "
                . "IF OLD.`status` = 'archived' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_archived_immutable'; END IF; "
                . "IF NEW.`revision` <> OLD.`revision` + 1 OR NEW.`current_version` < OLD.`current_version` OR NEW.`current_version` > OLD.`current_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_version_invalid'; END IF; "
                . "IF NEW.`current_version` = OLD.`current_version` + 1 AND NOT (NEW.`status` = 'draft' AND NEW.`published_version` IS NULL AND NEW.`published_by_user_id` IS NULL AND NEW.`published_at` IS NULL AND NEW.`archived_by_user_id` IS NULL AND NEW.`archived_at` IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_draft_transition_invalid'; END IF; "
                . "IF NEW.`current_version` = OLD.`current_version` AND NOT ((OLD.`status` = 'draft' AND NEW.`status` = 'published' AND NEW.`published_version` = NEW.`current_version`) OR (OLD.`status` IN ('draft','published') AND NEW.`status` = 'archived')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_state_transition_invalid'; END IF; "
                . "IF NEW.`status` = 'published' AND (SELECT COUNT(*) FROM `event_safety_requirement_versions` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `requirements_id` = NEW.`id` AND `version_number` = NEW.`published_version`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirements_publish_version_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_safety_version_insert',
            'BEFORE INSERT ON `event_safety_requirement_versions` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `event_safety_requirements` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`requirements_id` AND `status` = 'draft' AND `current_version` = NEW.`version_number`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_requirement_version_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_safety_coc_insert',
            'BEFORE INSERT ON `event_safety_code_acknowledgements` FOR EACH ROW BEGIN '
                . "IF NEW.`action` = 'acknowledged' AND (SELECT COUNT(*) FROM `event_safety_requirement_versions` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `requirements_id` = NEW.`requirements_id` AND `id` = NEW.`requirements_version_id` AND `version_number` = NEW.`requirements_version_number` AND `code_of_conduct_required` = 1 AND `code_of_conduct_text_version` = NEW.`text_version` AND `code_of_conduct_text_hash` = NEW.`text_hash`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_coc_policy_binding_invalid'; END IF; "
                . "IF NEW.`action` IN ('withdrawn','replaced') AND (SELECT COUNT(*) FROM `event_safety_code_acknowledgements` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `user_id` = NEW.`user_id` AND `id` = NEW.`referenced_acknowledgement_id` AND `action` = 'acknowledged' AND `requirements_id` = NEW.`requirements_id` AND `requirements_version_id` = NEW.`requirements_version_id` AND `requirements_version_number` = NEW.`requirements_version_number` AND `text_version` = NEW.`text_version` AND `text_hash` = NEW.`text_hash` AND `acknowledged_at` = NEW.`acknowledged_at`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_coc_reference_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_guardian_consent_update',
            'BEFORE UPDATE ON `event_guardian_consents` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`requirements_id` <=> NEW.`requirements_id`) OR NOT (OLD.`requirements_version_id` <=> NEW.`requirements_version_id`) OR NOT (OLD.`requirements_version_number` <=> NEW.`requirements_version_number`) OR NOT (OLD.`minor_user_id` <=> NEW.`minor_user_id`) OR NOT (OLD.`guardian_email_ciphertext` <=> NEW.`guardian_email_ciphertext`) OR NOT (OLD.`guardian_identity_ciphertext` <=> NEW.`guardian_identity_ciphertext`) OR NOT (OLD.`guardian_email_blind_hash` <=> NEW.`guardian_email_blind_hash`) OR NOT (OLD.`relationship_code` <=> NEW.`relationship_code`) OR NOT (OLD.`consent_text` <=> NEW.`consent_text`) OR NOT (OLD.`consent_text_version` <=> NEW.`consent_text_version`) OR NOT (OLD.`consent_text_hash` <=> NEW.`consent_text_hash`) OR NOT (OLD.`policy_binding_hash` <=> NEW.`policy_binding_hash`) OR NOT (OLD.`token_hash` <=> NEW.`token_hash`) OR NOT (OLD.`requested_by_user_id` <=> NEW.`requested_by_user_id`) OR NOT (OLD.`request_idempotency_hash` <=> NEW.`request_idempotency_hash`) OR NOT (OLD.`request_hash` <=> NEW.`request_hash`) OR NOT (OLD.`requested_at` <=> NEW.`requested_at`) OR NOT (OLD.`expires_at` <=> NEW.`expires_at`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_guardian_consent_identity_immutable'; END IF; "
                . "IF OLD.`status` IN ('withdrawn','expired') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_guardian_consent_terminal_immutable'; END IF; "
                . "IF NEW.`consent_version` <> OLD.`consent_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_guardian_consent_version_invalid'; END IF; "
                . "IF (OLD.`status` = 'pending' AND NEW.`status` NOT IN ('active','withdrawn','expired')) OR (OLD.`status` = 'active' AND NEW.`status` NOT IN ('withdrawn','expired')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_guardian_consent_transition_invalid'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_participation_denial_insert',
            'BEFORE INSERT ON `event_participation_denials` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_safety_concrete_event_required'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_participation_denial_update',
            'BEFORE UPDATE ON `event_participation_denials` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`create_idempotency_hash` <=> NEW.`create_idempotency_hash`) OR NOT (OLD.`create_request_hash` <=> NEW.`create_request_hash`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_participation_denial_identity_immutable'; END IF; "
                . "IF OLD.`status` IN ('withdrawn','expired') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_participation_denial_terminal_immutable'; END IF; "
                . "IF NEW.`decision_version` <> OLD.`decision_version` + 1 OR (OLD.`status` = 'active' AND NEW.`status` NOT IN ('active','withdrawn','expired')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_participation_denial_transition_invalid'; END IF; END",
        );

        foreach ([
            'trg_event_safety_requirements_no_delete' => ['event_safety_requirements', 'DELETE', 'event_safety_requirements_delete_forbidden'],
            'trg_event_safety_version_no_update' => ['event_safety_requirement_versions', 'UPDATE', 'event_safety_requirement_version_immutable'],
            'trg_event_safety_version_no_delete' => ['event_safety_requirement_versions', 'DELETE', 'event_safety_requirement_version_immutable'],
            'trg_event_safety_history_no_update' => ['event_safety_requirement_history', 'UPDATE', 'event_safety_requirement_history_immutable'],
            'trg_event_safety_history_no_delete' => ['event_safety_requirement_history', 'DELETE', 'event_safety_requirement_history_immutable'],
            'trg_event_safety_coc_no_update' => ['event_safety_code_acknowledgements', 'UPDATE', 'event_safety_code_acknowledgement_immutable'],
            'trg_event_safety_coc_no_delete' => ['event_safety_code_acknowledgements', 'DELETE', 'event_safety_code_acknowledgement_immutable'],
            'trg_event_guardian_consent_no_delete' => ['event_guardian_consents', 'DELETE', 'event_guardian_consent_delete_forbidden'],
            'trg_event_guardian_history_no_update' => ['event_guardian_consent_history', 'UPDATE', 'event_guardian_consent_history_immutable'],
            'trg_event_guardian_history_no_delete' => ['event_guardian_consent_history', 'DELETE', 'event_guardian_consent_history_immutable'],
            'trg_event_participation_denial_no_delete' => ['event_participation_denials', 'DELETE', 'event_participation_denial_delete_forbidden'],
            'trg_event_denial_history_no_update' => ['event_participation_denial_history', 'UPDATE', 'event_participation_denial_history_immutable'],
            'trg_event_denial_history_no_delete' => ['event_participation_denial_history', 'DELETE', 'event_participation_denial_history_immutable'],
        ] as $name => [$table, $operation, $message]) {
            $this->createTrigger(
                $name,
                "BEFORE {$operation} ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
            );
        }
    }

    private function createTrigger(string $name, string $definition): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$definition}");
        }
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
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasDependentSchema(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->whereIn('REFERENCED_TABLE_NAME', self::TABLES)
            ->whereNotIn('TABLE_NAME', self::TABLES)
            ->exists();
    }
};
