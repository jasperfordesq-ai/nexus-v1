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
    private const TABLES = [
        'event_registration_retention_items',
        'event_registration_retention_runs',
        'event_registration_guests',
        'event_invitation_history',
        'event_invitations',
        'event_invitation_campaigns',
        'event_registration_answer_access_audits',
        'event_registration_submission_history',
        'event_registration_form_answers',
        'event_registration_form_submissions',
        'event_registration_form_questions',
        'event_registration_form_versions',
        'event_registration_settings_history',
        'event_registration_settings',
    ];

    private const IMMUTABLE_TABLES = [
        'event_registration_settings_history' => 'ev_reg_settings_history',
        'event_registration_submission_history' => 'ev_reg_submission_history',
        'event_registration_answer_access_audits' => 'ev_reg_answer_access_audit',
        'event_invitation_history' => 'event_invitation_history',
        'event_registration_retention_runs' => 'ev_reg_retention_run',
        'event_registration_retention_items' => 'ev_reg_retention_item',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('event_registrations')) {
            return;
        }

        $this->addPartySize();
        $this->createSettings();
        $this->createSettingsHistory();
        $this->createFormVersions();
        $this->createFormQuestions();
        $this->addPublishedFormReference();
        $this->createSubmissions();
        $this->createAnswers();
        $this->createSubmissionHistory();
        $this->createAnswerAccessAudits();
        $this->createInvitationCampaigns();
        $this->createInvitations();
        $this->createInvitationHistory();
        $this->createGuests();
        $this->createRetentionRuns();
        $this->createRetentionItems();
        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        if ($this->containsDurableEvidence()) {
            throw new LogicException('event_registration_forms_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        if (Schema::hasTable('event_registration_settings')
            && $this->constraintExists('event_registration_settings', 'fk_ev_reg_settings_form')) {
            Schema::table('event_registration_settings', static function (Blueprint $table): void {
                $table->dropForeign('fk_ev_reg_settings_form');
            });
        }
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('event_registrations')
            && Schema::hasColumn('event_registrations', 'party_size')) {
            if ($this->constraintExists('event_registrations', 'chk_event_reg_party_size')) {
                DB::statement(
                    'ALTER TABLE `event_registrations` DROP CONSTRAINT `chk_event_reg_party_size`',
                );
            }
            Schema::table('event_registrations', static function (Blueprint $table): void {
                $table->dropColumn('party_size');
            });
        }
    }

    private function addPartySize(): void
    {
        if (Schema::hasColumn('event_registrations', 'party_size')) {
            return;
        }

        Schema::table('event_registrations', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('party_size')->default(1)->after('registration_version')
                ->comment('Canonical capacity units; guest activation remains opt-in');
        });
    }

    private function createSettings(): void
    {
        if (Schema::hasTable('event_registration_settings')) {
            return;
        }

        Schema::create('event_registration_settings', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('status', 16)->default('draft');
            $table->string('approval_mode', 16)->default('auto');
            $table->dateTime('event_starts_at_utc_snapshot');
            $table->string('event_timezone_snapshot', 64);
            $table->dateTime('opens_at_utc')->nullable();
            $table->dateTime('closes_at_utc')->nullable();
            $table->dateTime('cancellation_cutoff_at_utc')->nullable();
            $table->unsignedTinyInteger('per_member_limit')->default(1);
            $table->boolean('guests_enabled')->default(false);
            $table->unsignedTinyInteger('max_guests_per_registration')->default(0);
            $table->unsignedSmallInteger('guest_retention_days')->default(30);
            $table->string('form_state', 16)->default('none');
            $table->unsignedInteger('published_form_version')->nullable();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id'], 'uq_ev_reg_settings_event');
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_ev_reg_settings_id');
            $table->unique(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'uq_ev_reg_settings_occurrence',
            );
            $table->index(
                ['tenant_id', 'status', 'closes_at_utc', 'event_id'],
                'idx_ev_reg_settings_window',
            );

            $table->foreign('tenant_id', 'fk_ev_reg_settings_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_ev_reg_settings_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_ev_reg_settings_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_ev_reg_settings_updater')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['published_by', 'tenant_id'], 'fk_ev_reg_settings_publisher')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createSettingsHistory(): void
    {
        if (Schema::hasTable('event_registration_settings_history')) {
            return;
        }

        Schema::create('event_registration_settings_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('settings_id');
            $table->unsignedBigInteger('revision');
            $table->string('action', 32);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('changed_fields');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'settings_id', 'revision'], 'uq_ev_reg_settings_hist_rev');
            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_ev_reg_settings_hist_key');
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_ev_reg_settings_hist_event');

            $table->foreign('tenant_id', 'fk_ev_reg_settings_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'settings_id'],
                'fk_ev_reg_settings_hist_settings',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_settings')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_reg_settings_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createFormVersions(): void
    {
        if (Schema::hasTable('event_registration_form_versions')) {
            return;
        }

        Schema::create('event_registration_form_versions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('status', 16)->default('draft');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->char('definition_hash', 64)->nullable();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'event_id', 'version_number'],
                'uq_ev_reg_form_version_number',
            );
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_ev_reg_form_version_id');
            $table->index(['tenant_id', 'event_id', 'status', 'id'], 'idx_ev_reg_form_event_status');

            $table->foreign('tenant_id', 'fk_ev_reg_form_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_reg_form_settings')
                ->references(['tenant_id', 'event_id'])
                ->on('event_registration_settings')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_ev_reg_form_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_ev_reg_form_updater')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['published_by', 'tenant_id'], 'fk_ev_reg_form_publisher')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createFormQuestions(): void
    {
        if (Schema::hasTable('event_registration_form_questions')) {
            return;
        }

        Schema::create('event_registration_form_questions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('form_version_id');
            $table->string('stable_key', 64);
            $table->unsignedSmallInteger('position');
            $table->string('question_type', 32);
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('data_classification', 24);
            $table->string('purpose', 500);
            $table->unsignedSmallInteger('retention_days');
            $table->json('choice_options')->nullable();
            $table->longText('displayed_text')->nullable();
            $table->string('displayed_text_version', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'form_version_id', 'stable_key'],
                'uq_ev_reg_question_key',
            );
            $table->unique(
                ['tenant_id', 'form_version_id', 'position'],
                'uq_ev_reg_question_position',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'form_version_id', 'id'],
                'uq_ev_reg_question_id',
            );

            $table->foreign('tenant_id', 'fk_ev_reg_question_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'form_version_id'],
                'fk_ev_reg_question_form',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_form_versions')->restrictOnDelete();
        });
    }

    private function addPublishedFormReference(): void
    {
        if (! Schema::hasTable('event_registration_settings')
            || ! Schema::hasTable('event_registration_form_versions')
            || $this->constraintExists('event_registration_settings', 'fk_ev_reg_settings_form')) {
            return;
        }

        Schema::table('event_registration_settings', static function (Blueprint $table): void {
            $table->foreign(
                ['tenant_id', 'event_id', 'published_form_version'],
                'fk_ev_reg_settings_form',
            )->references(['tenant_id', 'event_id', 'version_number'])
                ->on('event_registration_form_versions')->restrictOnDelete();
        });
    }

    private function createSubmissions(): void
    {
        if (Schema::hasTable('event_registration_form_submissions')) {
            return;
        }

        Schema::create('event_registration_form_submissions', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('form_version_id');
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('status', 16)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'registration_id', 'form_version_id'],
                'uq_ev_reg_submission_registration_form',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'form_version_id', 'id'],
                'uq_ev_reg_submission_id',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_ev_reg_submission_event_id',
            );
            $table->index(['tenant_id', 'event_id', 'status', 'id'], 'idx_ev_reg_submission_event');
            $table->index(['tenant_id', 'user_id', 'created_at', 'id'], 'idx_ev_reg_submission_user');

            $table->foreign('tenant_id', 'fk_ev_reg_submission_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id'],
                'fk_ev_reg_submission_registration',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'form_version_id'],
                'fk_ev_reg_submission_form',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_form_versions')->restrictOnDelete();
            $table->foreign(['user_id', 'tenant_id'], 'fk_ev_reg_submission_user')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createAnswers(): void
    {
        if (Schema::hasTable('event_registration_form_answers')) {
            return;
        }

        Schema::create('event_registration_form_answers', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('form_version_id');
            $table->unsignedBigInteger('question_id');
            $table->string('data_classification', 24);
            $table->longText('answer_ciphertext')->nullable();
            $table->dateTime('retention_due_at');
            $table->timestamp('consented_at')->nullable();
            $table->char('displayed_text_hash', 64)->nullable();
            $table->string('displayed_text_version', 64)->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'submission_id', 'question_id'], 'uq_ev_reg_answer_question');
            $table->unique(
                ['tenant_id', 'event_id', 'submission_id', 'id'],
                'uq_ev_reg_answer_id',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_ev_reg_answer_event_id',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'submission_id', 'question_id', 'id'],
                'uq_ev_reg_answer_question_id',
            );
            $table->index(
                ['tenant_id', 'event_id', 'retention_due_at', 'purged_at', 'id'],
                'idx_ev_reg_answer_retention',
            );

            $table->foreign('tenant_id', 'fk_ev_reg_answer_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'form_version_id', 'submission_id'],
                'fk_ev_reg_answer_submission',
            )->references(['tenant_id', 'event_id', 'form_version_id', 'id'])
                ->on('event_registration_form_submissions')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'form_version_id', 'question_id'],
                'fk_ev_reg_answer_question',
            )->references(['tenant_id', 'event_id', 'form_version_id', 'id'])
                ->on('event_registration_form_questions')->restrictOnDelete();
        });
    }

    private function createSubmissionHistory(): void
    {
        if (Schema::hasTable('event_registration_submission_history')) {
            return;
        }

        Schema::create('event_registration_submission_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('submission_id');
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('revision');
            $table->string('action', 24);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('answer_keys');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'submission_id', 'revision'], 'uq_ev_reg_submission_hist_rev');
            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_ev_reg_submission_hist_key');
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_ev_reg_submission_hist_event');

            $table->foreign('tenant_id', 'fk_ev_reg_submission_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'submission_id'],
                'fk_ev_reg_submission_hist_submission',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_form_submissions')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_reg_submission_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createAnswerAccessAudits(): void
    {
        if (Schema::hasTable('event_registration_answer_access_audits')) {
            return;
        }

        Schema::create('event_registration_answer_access_audits', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('answer_id');
            $table->unsignedBigInteger('question_id');
            $table->integer('actor_user_id');
            $table->string('action', 16);
            $table->string('purpose', 500);
            $table->char('correlation_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['tenant_id', 'event_id', 'submission_id', 'created_at', 'id'],
                'idx_ev_reg_answer_audit_submission',
            );
            $table->index(
                ['tenant_id', 'actor_user_id', 'created_at', 'id'],
                'idx_ev_reg_answer_audit_actor',
            );

            $table->foreign('tenant_id', 'fk_ev_reg_answer_audit_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'submission_id', 'question_id', 'answer_id'],
                'fk_ev_reg_answer_audit_answer',
            )->references(['tenant_id', 'event_id', 'submission_id', 'question_id', 'id'])
                ->on('event_registration_form_answers')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_reg_answer_audit_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createInvitationCampaigns(): void
    {
        if (Schema::hasTable('event_invitation_campaigns')) {
            return;
        }

        Schema::create('event_invitation_campaigns', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->string('campaign_type', 16);
            $table->string('status', 16)->default('previewed');
            $table->unsignedBigInteger('revision')->default(1);
            $table->char('source_hash', 64);
            $table->string('source_reference', 191)->nullable();
            $table->unsignedInteger('preview_count');
            $table->unsignedInteger('valid_count');
            $table->unsignedInteger('error_count');
            $table->json('preview_errors');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_event_inv_campaign_key');
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_event_inv_campaign_id');
            $table->index(['tenant_id', 'event_id', 'status', 'id'], 'idx_event_inv_campaign_event');

            $table->foreign('tenant_id', 'fk_event_inv_campaign_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_inv_campaign_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_event_inv_campaign_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_event_inv_campaign_updater')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createInvitations(): void
    {
        if (Schema::hasTable('event_invitations')) {
            return;
        }

        Schema::create('event_invitations', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('campaign_id');
            $table->string('target_type', 16);
            $table->integer('member_user_id')->nullable();
            $table->longText('email_ciphertext')->nullable();
            $table->char('email_blind_hash', 64)->nullable();
            $table->string('status', 16)->default('issued');
            $table->unsignedBigInteger('invitation_version')->default(1);
            $table->char('token_hash', 64);
            $table->char('token_fingerprint', 16);
            $table->dateTime('token_expires_at');
            $table->timestamp('token_used_at')->nullable();
            $table->integer('accepted_by_user_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->char('issue_idempotency_hash', 64);
            $table->char('issue_request_hash', 64);
            $table->integer('created_by');
            $table->timestamps();

            $table->unique('token_hash', 'uq_event_invitation_token');
            $table->unique(['tenant_id', 'issue_idempotency_hash'], 'uq_event_invitation_issue_key');
            $table->unique(
                ['tenant_id', 'campaign_id', 'member_user_id'],
                'uq_event_invitation_member',
            );
            $table->unique(
                ['tenant_id', 'campaign_id', 'email_blind_hash'],
                'uq_event_invitation_email',
            );
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_event_invitation_id');
            $table->index(
                ['tenant_id', 'event_id', 'status', 'token_expires_at', 'id'],
                'idx_event_invitation_event_status',
            );

            $table->foreign('tenant_id', 'fk_event_invitation_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'campaign_id'],
                'fk_event_invitation_campaign',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_invitation_campaigns')->restrictOnDelete();
            $table->foreign(['member_user_id', 'tenant_id'], 'fk_event_invitation_member')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['accepted_by_user_id', 'tenant_id'], 'fk_event_invitation_acceptor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_event_invitation_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createInvitationHistory(): void
    {
        if (Schema::hasTable('event_invitation_history')) {
            return;
        }

        Schema::create('event_invitation_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('invitation_id');
            $table->unsignedBigInteger('invitation_version');
            $table->string('action', 16);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'invitation_id', 'invitation_version'],
                'uq_event_invitation_hist_version',
            );
            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_event_invitation_hist_key');
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_event_invitation_hist_event');

            $table->foreign('tenant_id', 'fk_event_invitation_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'invitation_id'],
                'fk_event_invitation_hist_invitation',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_invitations')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_invitation_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createGuests(): void
    {
        if (Schema::hasTable('event_registration_guests')) {
            return;
        }

        Schema::create('event_registration_guests', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedTinyInteger('guest_number');
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('status', 16)->default('captured');
            $table->longText('display_name_ciphertext')->nullable();
            $table->longText('email_ciphertext')->nullable();
            $table->longText('phone_ciphertext')->nullable();
            $table->char('identity_fingerprint', 64)->nullable();
            $table->string('consent_text_version', 64);
            $table->char('consent_text_hash', 64);
            $table->timestamp('consented_at');
            $table->dateTime('retention_due_at');
            $table->integer('captured_by_user_id');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'registration_id', 'guest_number'],
                'uq_ev_reg_guest_number',
            );
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_ev_reg_guest_event_id');
            $table->unique(['tenant_id', 'event_id', 'registration_id', 'id'], 'uq_ev_reg_guest_id');
            $table->index(
                ['tenant_id', 'event_id', 'retention_due_at', 'anonymised_at', 'id'],
                'idx_ev_reg_guest_retention',
            );

            $table->foreign('tenant_id', 'fk_ev_reg_guest_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_reg_guest_settings')
                ->references(['tenant_id', 'event_id'])
                ->on('event_registration_settings')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id'],
                'fk_ev_reg_guest_registration',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(['captured_by_user_id', 'tenant_id'], 'fk_ev_reg_guest_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRetentionRuns(): void
    {
        if (Schema::hasTable('event_registration_retention_runs')) {
            return;
        }

        Schema::create('event_registration_retention_runs', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('mode', 16);
            $table->unsignedBigInteger('dry_run_id')->nullable();
            $table->dateTime('as_of_utc');
            $table->unsignedInteger('eligible_count');
            $table->unsignedInteger('affected_count');
            $table->char('candidate_hash', 64);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->integer('actor_user_id');
            $table->timestamp('completed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_ev_reg_retention_run_key');
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_ev_reg_retention_run_id');
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_ev_reg_retention_event');

            $table->foreign('tenant_id', 'fk_ev_reg_retention_run_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'fk_ev_reg_retention_run_settings')
                ->references(['tenant_id', 'event_id'])
                ->on('event_registration_settings')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'dry_run_id'],
                'fk_ev_reg_retention_run_preview',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_retention_runs')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_reg_retention_run_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createRetentionItems(): void
    {
        if (Schema::hasTable('event_registration_retention_items')) {
            return;
        }

        Schema::create('event_registration_retention_items', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('answer_id')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable();
            $table->string('action', 16);
            $table->char('ciphertext_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'run_id', 'answer_id'], 'uq_ev_reg_retention_answer');
            $table->unique(['tenant_id', 'run_id', 'guest_id'], 'uq_ev_reg_retention_guest');
            $table->index(['tenant_id', 'event_id', 'run_id', 'id'], 'idx_ev_reg_retention_items_run');

            $table->foreign('tenant_id', 'fk_ev_reg_retention_item_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'run_id'],
                'fk_ev_reg_retention_item_run',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_retention_runs')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'answer_id'],
                'fk_ev_reg_retention_item_answer',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_form_answers')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'guest_id'],
                'fk_ev_reg_retention_item_guest',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_guests')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_registrations' => [
                'chk_event_reg_party_size' => '`party_size` BETWEEN 1 AND 11',
            ],
            'event_registration_settings' => [
                'chk_ev_reg_settings_revision' => '`revision` > 0',
                'chk_ev_reg_settings_status' => "`status` IN ('draft','published')",
                'chk_ev_reg_settings_approval' => "`approval_mode` IN ('auto','manual')",
                'chk_ev_reg_settings_limit' => '`per_member_limit` BETWEEN 1 AND 10',
                'chk_ev_reg_settings_guests' => '((`guests_enabled` = 0 AND `max_guests_per_registration` = 0) OR (`guests_enabled` = 1 AND `max_guests_per_registration` BETWEEN 1 AND 10))',
                'chk_ev_reg_settings_guest_retention' => '`guest_retention_days` BETWEEN 1 AND 36500',
                'chk_ev_reg_settings_form_state' => "((`form_state` IN ('none','draft') AND `published_form_version` IS NULL) OR (`form_state` = 'published' AND `published_form_version` IS NOT NULL AND `published_form_version` > 0))",
                'chk_ev_reg_settings_window_pair' => '((`opens_at_utc` IS NULL AND `closes_at_utc` IS NULL) OR (`opens_at_utc` IS NOT NULL AND `closes_at_utc` IS NOT NULL AND `opens_at_utc` < `closes_at_utc`))',
                'chk_ev_reg_settings_event_bounds' => '((`closes_at_utc` IS NULL OR `closes_at_utc` <= `event_starts_at_utc_snapshot`) AND (`cancellation_cutoff_at_utc` IS NULL OR `cancellation_cutoff_at_utc` <= `event_starts_at_utc_snapshot`))',
                'chk_ev_reg_settings_publish' => "((`status` = 'draft' AND `published_by` IS NULL AND `published_at` IS NULL) OR (`status` = 'published' AND `published_by` IS NOT NULL AND `published_at` IS NOT NULL))",
            ],
            'event_registration_form_versions' => [
                'chk_ev_reg_form_version' => '`version_number` > 0 AND `revision` > 0',
                'chk_ev_reg_form_status' => "`status` IN ('draft','published')",
                'chk_ev_reg_form_publish' => "((`status` = 'draft' AND `definition_hash` IS NULL AND `published_by` IS NULL AND `published_at` IS NULL) OR (`status` = 'published' AND `definition_hash` IS NOT NULL AND `published_by` IS NOT NULL AND `published_at` IS NOT NULL))",
            ],
            'event_registration_form_questions' => [
                'chk_ev_reg_question_key' => "`stable_key` REGEXP '^[a-z][a-z0-9_]{0,63}$'",
                'chk_ev_reg_question_type' => "`question_type` IN ('short_text','long_text','single_choice','multiple_choice','dietary','accessibility','consent','waiver')",
                'chk_ev_reg_question_class' => "`data_classification` IN ('public','internal','confidential','sensitive')",
                'chk_ev_reg_question_retention' => '`retention_days` BETWEEN 1 AND 36500',
                'chk_ev_reg_question_choices' => "((`question_type` IN ('single_choice','multiple_choice') AND `choice_options` IS NOT NULL) OR (`question_type` NOT IN ('single_choice','multiple_choice') AND `choice_options` IS NULL))",
                'chk_ev_reg_question_consent' => "((`question_type` IN ('consent','waiver') AND `displayed_text` IS NOT NULL AND CHAR_LENGTH(TRIM(`displayed_text`)) > 0 AND `displayed_text_version` IS NOT NULL AND CHAR_LENGTH(TRIM(`displayed_text_version`)) > 0) OR (`question_type` NOT IN ('consent','waiver') AND `displayed_text` IS NULL AND `displayed_text_version` IS NULL))",
            ],
            'event_registration_form_submissions' => [
                'chk_ev_reg_submission_revision' => '`revision` > 0',
                'chk_ev_reg_submission_status' => "`status` IN ('draft','submitted','withdrawn','anonymised')",
                'chk_ev_reg_submission_state' => "((`status` = 'draft' AND `submitted_at` IS NULL AND `withdrawn_at` IS NULL AND `anonymised_at` IS NULL) OR (`status` = 'submitted' AND `submitted_at` IS NOT NULL AND `withdrawn_at` IS NULL AND `anonymised_at` IS NULL) OR (`status` = 'withdrawn' AND `withdrawn_at` IS NOT NULL AND `anonymised_at` IS NULL) OR (`status` = 'anonymised' AND `anonymised_at` IS NOT NULL))",
            ],
            'event_registration_form_answers' => [
                'chk_ev_reg_answer_class' => "`data_classification` IN ('public','internal','confidential','sensitive')",
                'chk_ev_reg_answer_purge' => '((`purged_at` IS NULL AND `answer_ciphertext` IS NOT NULL) OR (`purged_at` IS NOT NULL AND `answer_ciphertext` IS NULL))',
                'chk_ev_reg_answer_consent' => '((`consented_at` IS NULL AND `displayed_text_hash` IS NULL AND `displayed_text_version` IS NULL) OR (`consented_at` IS NOT NULL AND `displayed_text_hash` IS NOT NULL AND `displayed_text_version` IS NOT NULL))',
            ],
            'event_registration_answer_access_audits' => [
                'chk_ev_reg_answer_audit_action' => "`action` IN ('read','export')",
            ],
            'event_invitation_campaigns' => [
                'chk_event_inv_campaign_type' => "`campaign_type` IN ('member','email','group','audience','csv')",
                'chk_event_inv_campaign_status' => "`status` IN ('previewed','issued','cancelled')",
                'chk_event_inv_campaign_counts' => '`preview_count` = `valid_count` + `error_count`',
                'chk_event_inv_campaign_state' => "((`status` = 'previewed' AND `issued_at` IS NULL AND `cancelled_at` IS NULL) OR (`status` = 'issued' AND `issued_at` IS NOT NULL AND `cancelled_at` IS NULL) OR (`status` = 'cancelled' AND `cancelled_at` IS NOT NULL))",
            ],
            'event_invitations' => [
                'chk_event_invitation_target' => "((`target_type` = 'member' AND `member_user_id` IS NOT NULL AND `email_ciphertext` IS NULL AND `email_blind_hash` IS NULL) OR (`target_type` = 'email' AND `member_user_id` IS NULL AND `email_ciphertext` IS NOT NULL AND `email_blind_hash` IS NOT NULL))",
                'chk_event_invitation_status' => "`status` IN ('issued','accepted','revoked','expired')",
                'chk_event_invitation_version' => '`invitation_version` > 0',
                'chk_event_invitation_state' => "((`status` = 'issued' AND `token_used_at` IS NULL AND `accepted_by_user_id` IS NULL AND `accepted_at` IS NULL AND `revoked_at` IS NULL AND `expired_at` IS NULL) OR (`status` = 'accepted' AND `token_used_at` IS NOT NULL AND `accepted_by_user_id` IS NOT NULL AND `accepted_at` IS NOT NULL AND `revoked_at` IS NULL AND `expired_at` IS NULL) OR (`status` = 'revoked' AND `token_used_at` IS NULL AND `accepted_at` IS NULL AND `revoked_at` IS NOT NULL AND `expired_at` IS NULL) OR (`status` = 'expired' AND `token_used_at` IS NULL AND `accepted_at` IS NULL AND `revoked_at` IS NULL AND `expired_at` IS NOT NULL))",
            ],
            'event_registration_guests' => [
                'chk_ev_reg_guest_number' => '`guest_number` BETWEEN 1 AND 10 AND `revision` > 0',
                'chk_ev_reg_guest_status' => "`status` IN ('captured','withdrawn','anonymised')",
                'chk_ev_reg_guest_identity' => "((`status` IN ('captured','withdrawn') AND `display_name_ciphertext` IS NOT NULL AND `identity_fingerprint` IS NOT NULL AND `anonymised_at` IS NULL) OR (`status` = 'anonymised' AND `display_name_ciphertext` IS NULL AND `email_ciphertext` IS NULL AND `phone_ciphertext` IS NULL AND `identity_fingerprint` IS NULL AND `anonymised_at` IS NOT NULL))",
                'chk_ev_reg_guest_withdrawal' => "((`status` = 'captured' AND `withdrawn_at` IS NULL) OR (`status` = 'withdrawn' AND `withdrawn_at` IS NOT NULL) OR (`status` = 'anonymised'))",
            ],
            'event_registration_retention_runs' => [
                'chk_ev_reg_retention_mode' => "((`mode` = 'dry_run' AND `dry_run_id` IS NULL AND `affected_count` = 0) OR (`mode` = 'apply' AND `dry_run_id` IS NOT NULL AND `affected_count` <= `eligible_count`))",
            ],
            'event_registration_retention_items' => [
                'chk_ev_reg_retention_subject' => '((`answer_id` IS NOT NULL AND `guest_id` IS NULL) OR (`answer_id` IS NULL AND `guest_id` IS NOT NULL))',
                'chk_ev_reg_retention_action' => "`action` IN ('preview','purged','skipped')",
            ],
        ];

        foreach ($checks as $table => $tableChecks) {
            if (! Schema::hasTable($table)) {
                continue;
            }
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

        foreach (self::IMMUTABLE_TABLES as $table => $prefix) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->createSignalTrigger(
                "trg_{$prefix}_no_update",
                "BEFORE UPDATE ON `{$table}` FOR EACH ROW",
                "{$prefix}_immutable",
            );
            $this->createSignalTrigger(
                "trg_{$prefix}_no_delete",
                "BEFORE DELETE ON `{$table}` FOR EACH ROW",
                "{$prefix}_immutable",
            );
        }

        $this->createBodyTrigger(
            'trg_ev_reg_settings_concrete_insert',
            'BEFORE INSERT ON `event_registration_settings` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_concrete_occurrence_required'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_settings_concrete_update',
            'BEFORE UPDATE ON `event_registration_settings` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_concrete_occurrence_required'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_event_inv_campaign_concrete_insert',
            'BEFORE INSERT ON `event_invitation_campaigns` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_concrete_occurrence_required'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_event_inv_campaign_concrete_update',
            'BEFORE UPDATE ON `event_invitation_campaigns` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_concrete_occurrence_required'; END IF;",
        );

        $this->createBodyTrigger(
            'trg_ev_reg_form_version_update',
            'BEFORE UPDATE ON `event_registration_form_versions` FOR EACH ROW',
            "IF OLD.`status` = 'published' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_published_form_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_form_version_delete',
            'BEFORE DELETE ON `event_registration_form_versions` FOR EACH ROW',
            "IF OLD.`status` = 'published' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_published_form_immutable'; END IF;",
        );
        foreach (['insert' => 'NEW', 'update' => 'NEW', 'delete' => 'OLD'] as $operation => $row) {
            $this->createBodyTrigger(
                "trg_ev_reg_question_{$operation}",
                'BEFORE ' . strtoupper($operation) . ' ON `event_registration_form_questions` FOR EACH ROW',
                "IF (SELECT `status` FROM `event_registration_form_versions` WHERE `tenant_id` = {$row}.`tenant_id` AND `event_id` = {$row}.`event_id` AND `id` = {$row}.`form_version_id`) = 'published' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_published_question_immutable'; END IF;",
            );
        }
        $this->createBodyTrigger(
            'trg_ev_reg_guest_privacy_update',
            'BEFORE UPDATE ON `event_registration_guests` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`guest_number` <=> NEW.`guest_number`) OR NOT (OLD.`consent_text_version` <=> NEW.`consent_text_version`) OR NOT (OLD.`consent_text_hash` <=> NEW.`consent_text_hash`) OR NOT (OLD.`consented_at` <=> NEW.`consented_at`) OR NOT (OLD.`retention_due_at` <=> NEW.`retention_due_at`) OR NOT (OLD.`captured_by_user_id` <=> NEW.`captured_by_user_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_evidence_immutable'; END IF; IF OLD.`status` = 'anonymised' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_anonymisation_terminal'; END IF; IF NEW.`status` = 'anonymised' AND NOT (OLD.`status` IN ('captured','withdrawn') AND NEW.`display_name_ciphertext` IS NULL AND NEW.`email_ciphertext` IS NULL AND NEW.`phone_ciphertext` IS NULL AND NEW.`identity_fingerprint` IS NULL AND NEW.`anonymised_at` IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_anonymisation_invalid'; END IF; IF NEW.`status` <> 'anonymised' AND (NOT (OLD.`display_name_ciphertext` <=> NEW.`display_name_ciphertext`) OR NOT (OLD.`email_ciphertext` <=> NEW.`email_ciphertext`) OR NOT (OLD.`phone_ciphertext` <=> NEW.`phone_ciphertext`) OR NOT (OLD.`identity_fingerprint` <=> NEW.`identity_fingerprint`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_identity_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_guest_insert_guard',
            'BEFORE INSERT ON `event_registration_guests` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `event_registration_settings` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `status` = 'published' AND `guests_enabled` = 1 AND NEW.`guest_number` <= `max_guests_per_registration`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guests_disabled_or_unbounded'; END IF; IF (SELECT COUNT(*) FROM `event_registration_guests` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `registration_id` = NEW.`registration_id` AND `status` <> 'anonymised') >= (SELECT `max_guests_per_registration` FROM `event_registration_settings` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_limit_reached'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_submission_insert_guard',
            'BEFORE INSERT ON `event_registration_form_submissions` FOR EACH ROW',
            "IF (SELECT COUNT(*) FROM `event_registration_form_versions` AS form INNER JOIN `event_registration_settings` AS settings ON settings.`tenant_id` = form.`tenant_id` AND settings.`event_id` = form.`event_id` AND settings.`published_form_version` = form.`version_number` WHERE form.`tenant_id` = NEW.`tenant_id` AND form.`event_id` = NEW.`event_id` AND form.`id` = NEW.`form_version_id` AND form.`status` = 'published' AND settings.`form_state` = 'published') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_active_published_form_required'; END IF; IF (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_mismatch'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_submission_update_guard',
            'BEFORE UPDATE ON `event_registration_form_submissions` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`form_version_id` <=> NEW.`form_version_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_immutable'; END IF; IF OLD.`status` IN ('submitted','withdrawn','anonymised') AND NOT (OLD.`status` IN ('submitted','withdrawn') AND NEW.`status` = 'anonymised' AND NEW.`anonymised_at` IS NOT NULL AND OLD.`revision` <=> NEW.`revision` AND OLD.`submitted_at` <=> NEW.`submitted_at` AND OLD.`withdrawn_at` <=> NEW.`withdrawn_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_evidence_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_submission_delete_guard',
            'BEFORE DELETE ON `event_registration_form_submissions` FOR EACH ROW',
            "IF OLD.`status` <> 'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_evidence_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_answer_insert_guard',
            'BEFORE INSERT ON `event_registration_form_answers` FOR EACH ROW',
            "IF (SELECT `status` FROM `event_registration_form_submissions` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`submission_id`) <> 'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submitted_answer_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_answer_update_guard',
            'BEFORE UPDATE ON `event_registration_form_answers` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`submission_id` <=> NEW.`submission_id`) OR NOT (OLD.`form_version_id` <=> NEW.`form_version_id`) OR NOT (OLD.`question_id` <=> NEW.`question_id`) OR NOT (OLD.`data_classification` <=> NEW.`data_classification`) OR NOT (OLD.`retention_due_at` <=> NEW.`retention_due_at`) OR NOT (OLD.`consented_at` <=> NEW.`consented_at`) OR NOT (OLD.`displayed_text_hash` <=> NEW.`displayed_text_hash`) OR NOT (OLD.`displayed_text_version` <=> NEW.`displayed_text_version`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_answer_evidence_immutable'; END IF; IF (SELECT `status` FROM `event_registration_form_submissions` WHERE `tenant_id` = OLD.`tenant_id` AND `event_id` = OLD.`event_id` AND `id` = OLD.`submission_id`) <> 'draft' AND NOT (OLD.`answer_ciphertext` IS NOT NULL AND OLD.`purged_at` IS NULL AND NEW.`answer_ciphertext` IS NULL AND NEW.`purged_at` IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submitted_answer_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_ev_reg_answer_delete_guard',
            'BEFORE DELETE ON `event_registration_form_answers` FOR EACH ROW',
            "IF (SELECT `status` FROM `event_registration_form_submissions` WHERE `tenant_id` = OLD.`tenant_id` AND `event_id` = OLD.`event_id` AND `id` = OLD.`submission_id`) <> 'draft' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submitted_answer_immutable'; END IF;",
        );
        $this->createBodyTrigger(
            'trg_event_invitation_identity_update',
            'BEFORE UPDATE ON `event_invitations` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`campaign_id` <=> NEW.`campaign_id`) OR NOT (OLD.`target_type` <=> NEW.`target_type`) OR NOT (OLD.`member_user_id` <=> NEW.`member_user_id`) OR NOT (OLD.`email_ciphertext` <=> NEW.`email_ciphertext`) OR NOT (OLD.`email_blind_hash` <=> NEW.`email_blind_hash`) OR NOT (OLD.`token_hash` <=> NEW.`token_hash`) OR NOT (OLD.`token_fingerprint` <=> NEW.`token_fingerprint`) OR NOT (OLD.`token_expires_at` <=> NEW.`token_expires_at`) OR NOT (OLD.`issue_idempotency_hash` <=> NEW.`issue_idempotency_hash`) OR NOT (OLD.`issue_request_hash` <=> NEW.`issue_request_hash`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_identity_immutable'; END IF; IF OLD.`status` <> 'issued' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_terminal_state_immutable'; END IF; IF (NEW.`status` = 'issued' AND NEW.`invitation_version` <> OLD.`invitation_version`) OR (NEW.`status` <> 'issued' AND NEW.`invitation_version` <> OLD.`invitation_version` + 1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_version_invalid'; END IF;",
        );
        $this->createSignalTrigger(
            'trg_event_invitation_no_delete',
            'BEFORE DELETE ON `event_invitations` FOR EACH ROW',
            'event_invitation_evidence_immutable',
        );
        $this->createBodyTrigger(
            'trg_event_inv_campaign_evidence_update',
            'BEFORE UPDATE ON `event_invitation_campaigns` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`campaign_type` <=> NEW.`campaign_type`) OR NOT (OLD.`source_hash` <=> NEW.`source_hash`) OR NOT (OLD.`source_reference` <=> NEW.`source_reference`) OR NOT (OLD.`preview_count` <=> NEW.`preview_count`) OR NOT (OLD.`valid_count` <=> NEW.`valid_count`) OR NOT (OLD.`error_count` <=> NEW.`error_count`) OR NOT (OLD.`preview_errors` <=> NEW.`preview_errors`) OR NOT (OLD.`idempotency_hash` <=> NEW.`idempotency_hash`) OR NOT (OLD.`request_hash` <=> NEW.`request_hash`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_evidence_immutable'; END IF; IF OLD.`status` <> 'previewed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_terminal_state_immutable'; END IF; IF NEW.`revision` <> OLD.`revision` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_revision_invalid'; END IF;",
        );
    }

    private function createSignalTrigger(string $name, string $timing, string $message): void
    {
        if ($this->triggerExists($name)) {
            return;
        }
        DB::unprepared(
            "CREATE TRIGGER `{$name}` {$timing} SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
        );
    }

    private function createBodyTrigger(string $name, string $timing, string $body): void
    {
        if ($this->triggerExists($name)) {
            return;
        }
        DB::unprepared("CREATE TRIGGER `{$name}` {$timing} BEGIN {$body} END");
    }

    private function dropTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::IMMUTABLE_TABLES as $prefix) {
            DB::unprepared("DROP TRIGGER IF EXISTS `trg_{$prefix}_no_update`");
            DB::unprepared("DROP TRIGGER IF EXISTS `trg_{$prefix}_no_delete`");
        }
        foreach ([
            'trg_ev_reg_settings_concrete_insert',
            'trg_ev_reg_settings_concrete_update',
            'trg_event_inv_campaign_concrete_insert',
            'trg_event_inv_campaign_concrete_update',
            'trg_ev_reg_form_version_update',
            'trg_ev_reg_form_version_delete',
            'trg_ev_reg_question_insert',
            'trg_ev_reg_question_update',
            'trg_ev_reg_question_delete',
            'trg_ev_reg_guest_privacy_update',
            'trg_ev_reg_guest_insert_guard',
            'trg_ev_reg_submission_insert_guard',
            'trg_ev_reg_submission_update_guard',
            'trg_ev_reg_submission_delete_guard',
            'trg_ev_reg_answer_insert_guard',
            'trg_ev_reg_answer_update_guard',
            'trg_ev_reg_answer_delete_guard',
            'trg_event_invitation_identity_update',
            'trg_event_invitation_no_delete',
            'trg_event_inv_campaign_evidence_update',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }

    private function containsDurableEvidence(): bool
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('event_registrations')
            && Schema::hasColumn('event_registrations', 'party_size')
            && DB::table('event_registrations')->where('party_size', '!=', 1)->exists();
    }
};
