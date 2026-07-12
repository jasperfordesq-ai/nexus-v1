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
    private const CAMPAIGN_TRIGGER = 'trg_event_inv_campaign_evidence_update';
    private const GUEST_PHASE_B_TRIGGER = 'trg_ev_reg_guest_phase_b_update';
    private const CAMPAIGN_HISTORY_UPDATE_TRIGGER = 'trg_event_inv_campaign_hist_no_update';
    private const CAMPAIGN_HISTORY_DELETE_TRIGGER = 'trg_event_inv_campaign_hist_no_delete';
    private const DELIVERY_UPDATE_TRIGGER = 'trg_event_inv_delivery_no_update';
    private const DELIVERY_DELETE_TRIGGER = 'trg_event_inv_delivery_no_delete';
    private const SUBMISSION_INSERT_TRIGGER = 'trg_ev_reg_submission_insert_guard';
    private const SUBMISSION_UPDATE_TRIGGER = 'trg_ev_reg_submission_update_guard';
    private const GUEST_ATTENDANCE_INSERT_TRIGGER = 'trg_ev_reg_guest_attendance_insert';
    private const GUEST_ATTENDANCE_UPDATE_TRIGGER = 'trg_ev_reg_guest_attendance_update';
    private const GUEST_ATTENDANCE_DELETE_TRIGGER = 'trg_ev_reg_guest_attendance_no_delete';
    private const GUEST_ATTENDANCE_HISTORY_UPDATE_TRIGGER = 'trg_ev_reg_guest_att_hist_no_update';
    private const GUEST_ATTENDANCE_HISTORY_DELETE_TRIGGER = 'trg_ev_reg_guest_att_hist_no_delete';

    public function up(): void
    {
        foreach ([
            'tenants',
            'users',
            'event_registration_form_questions',
            'event_registration_form_submissions',
            'event_invitation_campaigns',
            'event_invitations',
            'event_registration_guests',
            'event_ticket_entitlements',
            'event_notification_deliveries',
            'event_domain_outbox',
        ] as $required) {
            if (! Schema::hasTable($required)) {
                throw new LogicException(
                    "event_registration_phase_b_prerequisite_missing:{$required}",
                );
            }
        }

        $this->extendQuestions();
        $this->extendSubmissions();
        $this->extendCampaigns();
        $this->createCampaignHistory();
        $this->createDeliveryEvidence();
        $this->extendGuests();
        $this->createGuestAttendance();
        $this->createGuestAttendanceHistory();
        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        if ($this->containsPhaseBEvidence()) {
            throw new LogicException('event_registration_phase_b_rollback_refused_evidence_exists');
        }

        $this->dropTriggers();
        Schema::dropIfExists('event_registration_guest_attendance_history');
        Schema::dropIfExists('event_registration_guest_attendance');
        Schema::dropIfExists('event_invitation_delivery_evidence');
        Schema::dropIfExists('event_invitation_campaign_history');

        $this->dropChecks();
        $this->restoreSubmissionIdentity();
        $this->dropGuestForeignKey();
        $this->dropColumns('event_registration_guests', [
            'preferred_locale',
            'notification_consent',
            'notification_consent_version',
            'notification_consent_text_hash',
            'notification_consented_at',
            'ticket_entitlement_id',
        ]);
        $this->dropColumns('event_invitation_campaigns', [
            'source_schema_version',
            'source_snapshot_ciphertext',
            'segment_criteria_summary',
            'default_locale',
            'scheduled_for_utc',
            'started_at',
            'completed_at',
            'cancelled_reason',
        ]);
        $this->dropColumns('event_registration_form_questions', [
            'validation_rules',
            'visibility_rules',
        ]);
        $this->dropColumns('event_registration_form_submissions', [
            'supersedes_submission_id',
            'lineage_root_submission_id',
            'attempt_number',
            'effective_slot',
            'superseded_at',
        ]);

        $this->restoreFoundationSubmissionTriggers();
        $this->restoreFoundationCampaignChecks();
        $this->restoreFoundationCampaignTrigger();
    }

    private function extendQuestions(): void
    {
        Schema::table('event_registration_form_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registration_form_questions', 'validation_rules')) {
                $table->json('validation_rules')->nullable()->after('choice_options');
            }
            if (! Schema::hasColumn('event_registration_form_questions', 'visibility_rules')) {
                $table->json('visibility_rules')->nullable()->after('validation_rules');
            }
        });
    }

    private function extendSubmissions(): void
    {
        if ($this->indexExists(
            'event_registration_form_submissions',
            'uq_ev_reg_submission_registration_form',
        )) {
            DB::statement(
                'ALTER TABLE `event_registration_form_submissions` '
                . 'DROP INDEX `uq_ev_reg_submission_registration_form`',
            );
        }
        Schema::table('event_registration_form_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registration_form_submissions', 'supersedes_submission_id')) {
                $table->unsignedBigInteger('supersedes_submission_id')->nullable()->after('form_version_id');
            }
            if (! Schema::hasColumn('event_registration_form_submissions', 'lineage_root_submission_id')) {
                $table->unsignedBigInteger('lineage_root_submission_id')->nullable()->after('supersedes_submission_id');
            }
            if (! Schema::hasColumn('event_registration_form_submissions', 'attempt_number')) {
                $table->unsignedInteger('attempt_number')->default(1)->after('lineage_root_submission_id');
            }
            if (! Schema::hasColumn('event_registration_form_submissions', 'effective_slot')) {
                $table->unsignedTinyInteger('effective_slot')->nullable()->default(1)->after('attempt_number');
            }
            if (! Schema::hasColumn('event_registration_form_submissions', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('anonymised_at');
            }
        });
        if (! $this->indexExists(
            'event_registration_form_submissions',
            'uq_ev_reg_submission_effective',
        )) {
            Schema::table('event_registration_form_submissions', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'registration_id', 'form_version_id', 'effective_slot'],
                    'uq_ev_reg_submission_effective',
                );
            });
        }
        if (! $this->constraintExists(
            'event_registration_form_submissions',
            'fk_ev_reg_submission_supersedes',
        )) {
            Schema::table('event_registration_form_submissions', static function (Blueprint $table): void {
                $table->foreign(
                    ['tenant_id', 'event_id', 'supersedes_submission_id'],
                    'fk_ev_reg_submission_supersedes',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_registration_form_submissions')->restrictOnDelete();
            });
        }
        if (! $this->constraintExists(
            'event_registration_form_submissions',
            'fk_ev_reg_submission_lineage_root',
        )) {
            Schema::table('event_registration_form_submissions', static function (Blueprint $table): void {
                $table->foreign(
                    ['tenant_id', 'event_id', 'lineage_root_submission_id'],
                    'fk_ev_reg_submission_lineage_root',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_registration_form_submissions')->restrictOnDelete();
            });
        }
    }

    private function extendCampaigns(): void
    {
        Schema::table('event_invitation_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_invitation_campaigns', 'source_schema_version')) {
                $table->unsignedSmallInteger('source_schema_version')->default(1)->after('source_hash');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'source_snapshot_ciphertext')) {
                $table->longText('source_snapshot_ciphertext')->nullable()->after('source_schema_version');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'segment_criteria_summary')) {
                $table->json('segment_criteria_summary')->nullable()->after('source_snapshot_ciphertext');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'default_locale')) {
                $table->string('default_locale', 15)->default('en')->after('preview_errors');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'scheduled_for_utc')) {
                $table->dateTime('scheduled_for_utc')->nullable()->after('default_locale');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('issued_at');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (! Schema::hasColumn('event_invitation_campaigns', 'cancelled_reason')) {
                $table->string('cancelled_reason', 500)->nullable()->after('cancelled_at');
            }
        });
    }

    private function createCampaignHistory(): void
    {
        if (Schema::hasTable('event_invitation_campaign_history')) {
            return;
        }

        Schema::create('event_invitation_campaign_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('revision');
            $table->string('action', 24);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'campaign_id', 'revision'], 'uq_event_inv_campaign_hist_rev');
            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_event_inv_campaign_hist_key');
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_event_inv_campaign_hist_event');
            $table->foreign('tenant_id', 'fk_event_inv_campaign_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'campaign_id'],
                'fk_event_inv_campaign_hist_campaign',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_invitation_campaigns')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_inv_campaign_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createDeliveryEvidence(): void
    {
        if (Schema::hasTable('event_invitation_delivery_evidence')) {
            return;
        }

        Schema::create('event_invitation_delivery_evidence', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('invitation_id');
            $table->unsignedBigInteger('outbox_id')->nullable();
            $table->unsignedBigInteger('notification_delivery_id')->nullable();
            $table->unsignedBigInteger('evidence_version');
            $table->string('channel', 32);
            $table->string('recipient_locale', 15);
            $table->string('preference_decision', 16);
            $table->string('preference_reason', 100)->nullable();
            $table->string('status', 32);
            $table->char('idempotency_hash', 64);
            $table->string('provider_evidence_id', 255)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'invitation_id', 'channel', 'evidence_version'],
                'uq_event_inv_delivery_version',
            );
            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_event_inv_delivery_key');
            $table->index(['tenant_id', 'event_id', 'campaign_id', 'status', 'id'], 'idx_event_inv_delivery_campaign');
            $table->foreign('tenant_id', 'fk_event_inv_delivery_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'campaign_id'],
                'fk_event_inv_delivery_campaign',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_invitation_campaigns')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'invitation_id'],
                'fk_event_inv_delivery_invitation',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_invitations')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'outbox_id'],
                'fk_event_inv_delivery_outbox',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_domain_outbox')->restrictOnDelete();
            $table->foreign('notification_delivery_id', 'fk_event_inv_delivery_notification')
                ->references('id')->on('event_notification_deliveries')->restrictOnDelete();
        });
    }

    private function extendGuests(): void
    {
        Schema::table('event_registration_guests', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registration_guests', 'preferred_locale')) {
                $table->string('preferred_locale', 15)->nullable()->after('phone_ciphertext');
            }
            if (! Schema::hasColumn('event_registration_guests', 'notification_consent')) {
                $table->boolean('notification_consent')->default(false)->after('preferred_locale');
            }
            if (! Schema::hasColumn('event_registration_guests', 'notification_consent_version')) {
                $table->string('notification_consent_version', 64)->nullable()->after('notification_consent');
            }
            if (! Schema::hasColumn('event_registration_guests', 'notification_consent_text_hash')) {
                $table->char('notification_consent_text_hash', 64)->nullable()->after('notification_consent_version');
            }
            if (! Schema::hasColumn('event_registration_guests', 'notification_consented_at')) {
                $table->timestamp('notification_consented_at')->nullable()->after('notification_consent_text_hash');
            }
            if (! Schema::hasColumn('event_registration_guests', 'ticket_entitlement_id')) {
                $table->unsignedBigInteger('ticket_entitlement_id')->nullable()->after('registration_id');
            }
        });

        if (! $this->constraintExists('event_registration_guests', 'fk_ev_reg_guest_ticket')) {
            Schema::table('event_registration_guests', static function (Blueprint $table): void {
                $table->foreign(
                    ['tenant_id', 'event_id', 'ticket_entitlement_id'],
                    'fk_ev_reg_guest_ticket',
                )->references(['tenant_id', 'event_id', 'id'])
                    ->on('event_ticket_entitlements')->restrictOnDelete();
            });
        }
    }

    private function createGuestAttendance(): void
    {
        if (Schema::hasTable('event_registration_guest_attendance')) {
            return;
        }

        Schema::create('event_registration_guest_attendance', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('guest_id');
            $table->string('attendance_status', 32)->default('not_checked_in');
            $table->unsignedBigInteger('attendance_version')->default(1);
            $table->timestamp('status_changed_at');
            $table->integer('status_changed_by');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'guest_id'], 'uq_ev_reg_guest_attendance_guest');
            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_ev_reg_guest_attendance_scope');
            $table->index(['tenant_id', 'event_id', 'attendance_status', 'id'], 'idx_ev_reg_guest_attendance_event');
            $table->foreign('tenant_id', 'fk_ev_reg_guest_attendance_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id', 'guest_id'],
                'fk_ev_reg_guest_attendance_guest',
            )->references(['tenant_id', 'event_id', 'registration_id', 'id'])
                ->on('event_registration_guests')->restrictOnDelete();
            $table->foreign(['status_changed_by', 'tenant_id'], 'fk_ev_reg_guest_attendance_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createGuestAttendanceHistory(): void
    {
        if (Schema::hasTable('event_registration_guest_attendance_history')) {
            return;
        }

        Schema::create('event_registration_guest_attendance_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('attendance_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('guest_id');
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('attendance_version');
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->string('reason', 500)->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_hash'], 'uq_ev_reg_guest_att_hist_key');
            $table->unique(
                ['tenant_id', 'attendance_id', 'attendance_version'],
                'uq_ev_reg_guest_att_hist_version',
            );
            $table->index(['tenant_id', 'event_id', 'created_at', 'id'], 'idx_ev_reg_guest_att_hist_event');
            $table->foreign('tenant_id', 'fk_ev_reg_guest_att_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'attendance_id'],
                'fk_ev_reg_guest_att_hist_attendance',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_registration_guest_attendance')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id', 'guest_id'],
                'fk_ev_reg_guest_att_hist_guest',
            )->references(['tenant_id', 'event_id', 'registration_id', 'id'])
                ->on('event_registration_guests')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_ev_reg_guest_att_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->replaceCheck(
            'event_invitation_campaigns',
            'chk_event_inv_campaign_status',
            "`status` IN ('previewed','scheduled','issuing','issued','cancelled')",
        );
        $this->replaceCheck(
            'event_invitation_campaigns',
            'chk_event_inv_campaign_state',
            "((`status` = 'previewed' AND `scheduled_for_utc` IS NULL AND `issued_at` IS NULL AND `started_at` IS NULL AND `completed_at` IS NULL AND `cancelled_at` IS NULL AND `cancelled_reason` IS NULL) OR (`status` = 'scheduled' AND `scheduled_for_utc` IS NOT NULL AND `issued_at` IS NULL AND `started_at` IS NULL AND `completed_at` IS NULL AND `cancelled_at` IS NULL AND `cancelled_reason` IS NULL) OR (`status` = 'issuing' AND `started_at` IS NOT NULL AND `issued_at` IS NULL AND `completed_at` IS NULL AND `cancelled_at` IS NULL AND `cancelled_reason` IS NULL) OR (`status` = 'issued' AND `issued_at` IS NOT NULL AND `started_at` IS NOT NULL AND `completed_at` IS NOT NULL AND `cancelled_at` IS NULL AND `cancelled_reason` IS NULL) OR (`status` = 'cancelled' AND `issued_at` IS NULL AND `completed_at` IS NULL AND `cancelled_at` IS NOT NULL AND `cancelled_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`cancelled_reason`)) > 0))",
        );
        $this->addCheck(
            'event_registration_form_questions',
            'chk_ev_reg_question_validation_rules',
            '(`validation_rules` IS NULL OR JSON_TYPE(`validation_rules`) = \'OBJECT\')',
        );
        $this->addCheck(
            'event_registration_form_questions',
            'chk_ev_reg_question_visibility_rules',
            '(`visibility_rules` IS NULL OR JSON_TYPE(`visibility_rules`) = \'OBJECT\')',
        );
        $this->addCheck(
            'event_registration_form_submissions',
            'chk_ev_reg_submission_lineage',
            '(`attempt_number` > 0 AND ((`attempt_number` = 1 AND `supersedes_submission_id` IS NULL AND `lineage_root_submission_id` IS NULL) OR (`attempt_number` > 1 AND `supersedes_submission_id` IS NOT NULL AND `lineage_root_submission_id` IS NOT NULL)) AND ((`effective_slot` = 1 AND `superseded_at` IS NULL) OR (`effective_slot` IS NULL AND `superseded_at` IS NOT NULL)))',
        );
        $this->addCheck(
            'event_invitation_campaigns',
            'chk_event_inv_campaign_source_snapshot',
            '(`source_schema_version` > 0 AND (`source_snapshot_ciphertext` IS NULL OR CHAR_LENGTH(`source_snapshot_ciphertext`) > 0) AND (`segment_criteria_summary` IS NULL OR JSON_TYPE(`segment_criteria_summary`) = \'OBJECT\'))',
        );
        $this->addCheck(
            'event_registration_guests',
            'chk_ev_reg_guest_notification_consent',
            '((`notification_consent` = 0 AND `notification_consent_version` IS NULL AND `notification_consent_text_hash` IS NULL AND `notification_consented_at` IS NULL) OR (`notification_consent` = 1 AND `preferred_locale` IS NOT NULL AND (`email_ciphertext` IS NOT NULL OR `status` = \'anonymised\') AND `notification_consent_version` IS NOT NULL AND `notification_consent_text_hash` IS NOT NULL AND `notification_consented_at` IS NOT NULL))',
        );
        $this->replaceCheck(
            'event_registration_guest_attendance',
            'chk_ev_reg_guest_attendance_status',
            "(`attendance_version` > 0 AND ((`attendance_status` = 'not_checked_in' AND `checked_in_at` IS NULL AND `checked_out_at` IS NULL AND `attended_at` IS NULL AND `no_show_at` IS NULL) OR (`attendance_status` = 'checked_in' AND `checked_in_at` IS NOT NULL AND `checked_out_at` IS NULL AND `attended_at` IS NULL AND `no_show_at` IS NULL) OR (`attendance_status` = 'checked_out' AND `checked_in_at` IS NOT NULL AND `checked_out_at` IS NOT NULL AND `attended_at` IS NULL AND `no_show_at` IS NULL) OR (`attendance_status` = 'attended' AND `attended_at` IS NOT NULL AND `no_show_at` IS NULL) OR (`attendance_status` = 'no_show' AND `checked_in_at` IS NULL AND `checked_out_at` IS NULL AND `attended_at` IS NULL AND `no_show_at` IS NOT NULL)))",
        );
        $this->addCheck(
            'event_registration_guest_attendance_history',
            'chk_ev_reg_guest_att_hist_status',
            "(`to_status` IN ('not_checked_in','checked_in','checked_out','attended','no_show') AND (`from_status` IS NULL OR `from_status` IN ('not_checked_in','checked_in','checked_out','attended','no_show')) AND `attendance_version` > 0)",
        );
        $this->addCheck(
            'event_invitation_campaign_history',
            'chk_event_inv_campaign_hist_action',
            "`action` IN ('previewed','scheduled','cancelled','issuing','issued')",
        );
        $this->addCheck(
            'event_invitation_delivery_evidence',
            'chk_event_inv_delivery_fields',
            "(`evidence_version` > 0 AND `channel` IN ('email','in_app','web_push','fcm','realtime') AND `preference_decision` IN ('deliver','suppressed') AND `status` IN ('queued','suppressed','dispatched','delivered','failed','dead_letter'))",
        );
    }

    private function installTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::CAMPAIGN_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::CAMPAIGN_TRIGGER . '` BEFORE UPDATE ON `event_invitation_campaigns` FOR EACH ROW BEGIN '
            . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`campaign_type` <=> NEW.`campaign_type`) OR NOT (OLD.`source_hash` <=> NEW.`source_hash`) OR NOT (OLD.`source_schema_version` <=> NEW.`source_schema_version`) OR NOT (OLD.`source_snapshot_ciphertext` <=> NEW.`source_snapshot_ciphertext`) OR NOT (OLD.`segment_criteria_summary` <=> NEW.`segment_criteria_summary`) OR NOT (OLD.`source_reference` <=> NEW.`source_reference`) OR NOT (OLD.`preview_count` <=> NEW.`preview_count`) OR NOT (OLD.`valid_count` <=> NEW.`valid_count`) OR NOT (OLD.`error_count` <=> NEW.`error_count`) OR NOT (OLD.`preview_errors` <=> NEW.`preview_errors`) OR NOT (OLD.`default_locale` <=> NEW.`default_locale`) OR NOT (OLD.`idempotency_hash` <=> NEW.`idempotency_hash`) OR NOT (OLD.`request_hash` <=> NEW.`request_hash`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_evidence_immutable'; END IF; "
            . "IF OLD.`status` IN ('issued','cancelled') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_terminal_state_immutable'; END IF; "
            . "IF NEW.`revision` <> OLD.`revision` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_revision_invalid'; END IF; "
            . "IF NOT ((OLD.`status` = 'previewed' AND NEW.`status` IN ('scheduled','issuing','issued','cancelled')) OR (OLD.`status` = 'scheduled' AND NEW.`status` IN ('issuing','cancelled')) OR (OLD.`status` = 'issuing' AND NEW.`status` = 'issued') OR (OLD.`status` = NEW.`status`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_transition_invalid'; END IF; END",
        );

        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::SUBMISSION_UPDATE_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::SUBMISSION_UPDATE_TRIGGER . '` BEFORE UPDATE ON `event_registration_form_submissions` FOR EACH ROW BEGIN '
            . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`form_version_id` <=> NEW.`form_version_id`) OR NOT (OLD.`supersedes_submission_id` <=> NEW.`supersedes_submission_id`) OR NOT (OLD.`lineage_root_submission_id` <=> NEW.`lineage_root_submission_id`) OR NOT (OLD.`attempt_number` <=> NEW.`attempt_number`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_immutable'; END IF; "
            . "IF NOT (OLD.`effective_slot` <=> NEW.`effective_slot`) OR NOT (OLD.`superseded_at` <=> NEW.`superseded_at`) THEN IF NOT (OLD.`effective_slot` = 1 AND NEW.`effective_slot` IS NULL AND OLD.`superseded_at` IS NULL AND NEW.`superseded_at` IS NOT NULL AND OLD.`status` = 'submitted' AND NEW.`status` = OLD.`status` AND NEW.`revision` = OLD.`revision` + 1 AND OLD.`submitted_at` <=> NEW.`submitted_at` AND OLD.`withdrawn_at` <=> NEW.`withdrawn_at` AND OLD.`anonymised_at` <=> NEW.`anonymised_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_supersession_invalid'; END IF; END IF; "
            . "IF OLD.`status` IN ('submitted','withdrawn','anonymised') AND NOT ((OLD.`status` = 'submitted' AND NEW.`status` = 'submitted' AND OLD.`effective_slot` = 1 AND NEW.`effective_slot` IS NULL AND NEW.`revision` = OLD.`revision` + 1 AND NEW.`superseded_at` IS NOT NULL) OR (OLD.`status` IN ('submitted','withdrawn') AND NEW.`status` = 'anonymised' AND NEW.`anonymised_at` IS NOT NULL AND OLD.`revision` <=> NEW.`revision` AND OLD.`submitted_at` <=> NEW.`submitted_at` AND OLD.`withdrawn_at` <=> NEW.`withdrawn_at` AND OLD.`effective_slot` <=> NEW.`effective_slot` AND OLD.`superseded_at` <=> NEW.`superseded_at`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_evidence_immutable'; END IF; END",
        );
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::SUBMISSION_INSERT_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::SUBMISSION_INSERT_TRIGGER . '` BEFORE INSERT ON `event_registration_form_submissions` FOR EACH ROW BEGIN '
            . "IF (SELECT COUNT(*) FROM `event_registration_form_versions` AS form INNER JOIN `event_registration_settings` AS settings ON settings.`tenant_id` = form.`tenant_id` AND settings.`event_id` = form.`event_id` AND settings.`published_form_version` = form.`version_number` WHERE form.`tenant_id` = NEW.`tenant_id` AND form.`event_id` = NEW.`event_id` AND form.`id` = NEW.`form_version_id` AND form.`status` = 'published' AND settings.`form_state` = 'published') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_active_published_form_required'; END IF; "
            . "IF (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_mismatch'; END IF; "
            . "IF NEW.`attempt_number` = 1 AND (NEW.`supersedes_submission_id` IS NOT NULL OR NEW.`lineage_root_submission_id` IS NOT NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_lineage_invalid'; END IF; "
            . "IF NEW.`attempt_number` > 1 AND (SELECT COUNT(*) FROM `event_registration_form_submissions` AS predecessor WHERE predecessor.`tenant_id` = NEW.`tenant_id` AND predecessor.`event_id` = NEW.`event_id` AND predecessor.`id` = NEW.`supersedes_submission_id` AND predecessor.`registration_id` = NEW.`registration_id` AND predecessor.`user_id` = NEW.`user_id` AND predecessor.`form_version_id` = NEW.`form_version_id` AND predecessor.`attempt_number` + 1 = NEW.`attempt_number` AND predecessor.`effective_slot` IS NULL AND predecessor.`superseded_at` IS NOT NULL AND COALESCE(predecessor.`lineage_root_submission_id`, predecessor.`id`) = NEW.`lineage_root_submission_id`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_lineage_invalid'; END IF; END",
        );

        $this->immutableTrigger('event_invitation_campaign_history', self::CAMPAIGN_HISTORY_UPDATE_TRIGGER, self::CAMPAIGN_HISTORY_DELETE_TRIGGER, 'event_invitation_campaign_history_immutable');
        $this->immutableTrigger('event_invitation_delivery_evidence', self::DELIVERY_UPDATE_TRIGGER, self::DELIVERY_DELETE_TRIGGER, 'event_invitation_delivery_evidence_immutable');
        $this->immutableTrigger('event_registration_guest_attendance_history', self::GUEST_ATTENDANCE_HISTORY_UPDATE_TRIGGER, self::GUEST_ATTENDANCE_HISTORY_DELETE_TRIGGER, 'event_registration_guest_attendance_history_immutable');
        $this->bodyTrigger(
            self::GUEST_ATTENDANCE_INSERT_TRIGGER,
            'BEFORE INSERT ON `event_registration_guest_attendance` FOR EACH ROW',
            "IF NEW.`attendance_version` <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_version_invalid'; END IF; IF NEW.`attendance_status` NOT IN ('checked_in','no_show') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_transition_invalid'; END IF; IF (SELECT COUNT(*) FROM `event_registration_guests` AS guest INNER JOIN `event_registrations` AS registration ON registration.`tenant_id` = guest.`tenant_id` AND registration.`event_id` = guest.`event_id` AND registration.`id` = guest.`registration_id` WHERE guest.`tenant_id` = NEW.`tenant_id` AND guest.`event_id` = NEW.`event_id` AND guest.`registration_id` = NEW.`registration_id` AND guest.`id` = NEW.`guest_id` AND guest.`status` = 'captured' AND registration.`registration_state` = 'confirmed') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_identity_invalid'; END IF;",
        );
        $this->bodyTrigger(
            self::GUEST_ATTENDANCE_UPDATE_TRIGGER,
            'BEFORE UPDATE ON `event_registration_guest_attendance` FOR EACH ROW',
            "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`guest_id` <=> NEW.`guest_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_identity_immutable'; END IF; IF NEW.`attendance_version` <> OLD.`attendance_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_version_invalid'; END IF; IF NOT ((OLD.`attendance_status` = 'not_checked_in' AND NEW.`attendance_status` IN ('checked_in','no_show')) OR (OLD.`attendance_status` = 'checked_in' AND NEW.`attendance_status` IN ('checked_out','not_checked_in')) OR (OLD.`attendance_status` = 'checked_out' AND NEW.`attendance_status` = 'checked_in') OR (OLD.`attendance_status` = 'no_show' AND NEW.`attendance_status` = 'not_checked_in')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_attendance_transition_invalid'; END IF;",
        );
        $this->signalTrigger(
            self::GUEST_ATTENDANCE_DELETE_TRIGGER,
            'BEFORE DELETE ON `event_registration_guest_attendance` FOR EACH ROW',
            'event_registration_guest_attendance_immutable',
        );
        $this->bodyTrigger(
            self::GUEST_PHASE_B_TRIGGER,
            'BEFORE UPDATE ON `event_registration_guests` FOR EACH ROW',
            "IF NOT (OLD.`preferred_locale` <=> NEW.`preferred_locale`) OR NOT (OLD.`notification_consent` <=> NEW.`notification_consent`) OR NOT (OLD.`notification_consent_version` <=> NEW.`notification_consent_version`) OR NOT (OLD.`notification_consent_text_hash` <=> NEW.`notification_consent_text_hash`) OR NOT (OLD.`notification_consented_at` <=> NEW.`notification_consented_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_notification_evidence_immutable'; END IF; IF OLD.`ticket_entitlement_id` IS NOT NULL AND NOT (OLD.`ticket_entitlement_id` <=> NEW.`ticket_entitlement_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_guest_ticket_link_immutable'; END IF;",
        );
    }

    private function dropTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ([
            self::CAMPAIGN_TRIGGER,
            self::GUEST_PHASE_B_TRIGGER,
            self::CAMPAIGN_HISTORY_UPDATE_TRIGGER,
            self::CAMPAIGN_HISTORY_DELETE_TRIGGER,
            self::DELIVERY_UPDATE_TRIGGER,
            self::DELIVERY_DELETE_TRIGGER,
            self::SUBMISSION_INSERT_TRIGGER,
            self::SUBMISSION_UPDATE_TRIGGER,
            self::GUEST_ATTENDANCE_INSERT_TRIGGER,
            self::GUEST_ATTENDANCE_UPDATE_TRIGGER,
            self::GUEST_ATTENDANCE_DELETE_TRIGGER,
            self::GUEST_ATTENDANCE_HISTORY_UPDATE_TRIGGER,
            self::GUEST_ATTENDANCE_HISTORY_DELETE_TRIGGER,
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
    }

    private function immutableTrigger(string $table, string $update, string $delete, string $message): void
    {
        $this->signalTrigger($update, "BEFORE UPDATE ON `{$table}` FOR EACH ROW", $message);
        $this->signalTrigger($delete, "BEFORE DELETE ON `{$table}` FOR EACH ROW", $message);
    }

    private function signalTrigger(string $name, string $timing, string $message): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$timing} SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'");
        }
    }

    private function bodyTrigger(string $name, string $timing, string $body): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$timing} BEGIN {$body} END");
        }
    }

    private function restoreFoundationCampaignChecks(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('event_invitation_campaigns')) {
            return;
        }
        $this->replaceCheck(
            'event_invitation_campaigns',
            'chk_event_inv_campaign_status',
            "`status` IN ('previewed','issued','cancelled')",
        );
        $this->replaceCheck(
            'event_invitation_campaigns',
            'chk_event_inv_campaign_state',
            "((`status` = 'previewed' AND `issued_at` IS NULL AND `cancelled_at` IS NULL) OR (`status` = 'issued' AND `issued_at` IS NOT NULL AND `cancelled_at` IS NULL) OR (`status` = 'cancelled' AND `cancelled_at` IS NOT NULL))",
        );
    }

    private function restoreSubmissionIdentity(): void
    {
        if (! Schema::hasTable('event_registration_form_submissions')) {
            return;
        }
        foreach ([
            'fk_ev_reg_submission_supersedes',
            'fk_ev_reg_submission_lineage_root',
        ] as $foreign) {
            if ($this->constraintExists('event_registration_form_submissions', $foreign)) {
                Schema::table('event_registration_form_submissions', static function (Blueprint $table) use ($foreign): void {
                    $table->dropForeign($foreign);
                });
            }
        }
        if ($this->indexExists('event_registration_form_submissions', 'uq_ev_reg_submission_effective')) {
            DB::statement(
                'ALTER TABLE `event_registration_form_submissions` '
                . 'DROP INDEX `uq_ev_reg_submission_effective`',
            );
        }
        if (! $this->indexExists(
            'event_registration_form_submissions',
            'uq_ev_reg_submission_registration_form',
        )) {
            Schema::table('event_registration_form_submissions', static function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'registration_id', 'form_version_id'],
                    'uq_ev_reg_submission_registration_form',
                );
            });
        }
    }

    private function restoreFoundationSubmissionTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql'
            || ! Schema::hasTable('event_registration_form_submissions')) {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::SUBMISSION_INSERT_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::SUBMISSION_INSERT_TRIGGER . '` BEFORE INSERT ON `event_registration_form_submissions` FOR EACH ROW BEGIN '
            . "IF (SELECT COUNT(*) FROM `event_registration_form_versions` AS form INNER JOIN `event_registration_settings` AS settings ON settings.`tenant_id` = form.`tenant_id` AND settings.`event_id` = form.`event_id` AND settings.`published_form_version` = form.`version_number` WHERE form.`tenant_id` = NEW.`tenant_id` AND form.`event_id` = NEW.`event_id` AND form.`id` = NEW.`form_version_id` AND form.`status` = 'published' AND settings.`form_state` = 'published') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_active_published_form_required'; END IF; IF (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_mismatch'; END IF; END",
        );
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::SUBMISSION_UPDATE_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::SUBMISSION_UPDATE_TRIGGER . '` BEFORE UPDATE ON `event_registration_form_submissions` FOR EACH ROW BEGIN '
            . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`form_version_id` <=> NEW.`form_version_id`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_identity_immutable'; END IF; IF OLD.`status` IN ('submitted','withdrawn','anonymised') AND NOT (OLD.`status` IN ('submitted','withdrawn') AND NEW.`status` = 'anonymised' AND NEW.`anonymised_at` IS NOT NULL AND OLD.`revision` <=> NEW.`revision` AND OLD.`submitted_at` <=> NEW.`submitted_at` AND OLD.`withdrawn_at` <=> NEW.`withdrawn_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_registration_submission_evidence_immutable'; END IF; END",
        );
    }

    private function restoreFoundationCampaignTrigger(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('event_invitation_campaigns')) {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS `' . self::CAMPAIGN_TRIGGER . '`');
        DB::unprepared(
            'CREATE TRIGGER `' . self::CAMPAIGN_TRIGGER . '` BEFORE UPDATE ON `event_invitation_campaigns` FOR EACH ROW BEGIN '
            . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`campaign_type` <=> NEW.`campaign_type`) OR NOT (OLD.`source_hash` <=> NEW.`source_hash`) OR NOT (OLD.`source_reference` <=> NEW.`source_reference`) OR NOT (OLD.`preview_count` <=> NEW.`preview_count`) OR NOT (OLD.`valid_count` <=> NEW.`valid_count`) OR NOT (OLD.`error_count` <=> NEW.`error_count`) OR NOT (OLD.`preview_errors` <=> NEW.`preview_errors`) OR NOT (OLD.`idempotency_hash` <=> NEW.`idempotency_hash`) OR NOT (OLD.`request_hash` <=> NEW.`request_hash`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_evidence_immutable'; END IF; IF OLD.`status` <> 'previewed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_terminal_state_immutable'; END IF; IF NEW.`revision` <> OLD.`revision` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_invitation_campaign_revision_invalid'; END IF; END",
        );
    }

    private function containsPhaseBEvidence(): bool
    {
        foreach ([
            'event_invitation_campaign_history',
            'event_invitation_delivery_evidence',
            'event_registration_guest_attendance',
            'event_registration_guest_attendance_history',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }
        if (Schema::hasColumn('event_registration_form_questions', 'validation_rules')
            && DB::table('event_registration_form_questions')
                ->where(static fn ($query) => $query->whereNotNull('validation_rules')->orWhereNotNull('visibility_rules'))
                ->exists()) {
            return true;
        }
        if (Schema::hasColumn('event_registration_form_submissions', 'attempt_number')
            && DB::table('event_registration_form_submissions')->where(static function ($query): void {
                $query->where('attempt_number', '>', 1)
                    ->orWhereNotNull('supersedes_submission_id')
                    ->orWhereNotNull('lineage_root_submission_id')
                    ->orWhereNull('effective_slot')
                    ->orWhereNotNull('superseded_at');
            })->exists()) {
            return true;
        }
        if (Schema::hasColumn('event_invitation_campaigns', 'source_snapshot_ciphertext')
            && DB::table('event_invitation_campaigns')->where(static function ($query): void {
                $query->whereNotNull('source_snapshot_ciphertext')
                    ->orWhereNotNull('segment_criteria_summary')
                    ->orWhereNotNull('scheduled_for_utc')
                    ->orWhereNotNull('started_at')
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('cancelled_reason')
                    ->orWhereIn('status', ['scheduled', 'issuing']);
            })->exists()) {
            return true;
        }

        return Schema::hasColumn('event_registration_guests', 'preferred_locale')
            && DB::table('event_registration_guests')->where(static function ($query): void {
                $query->whereNotNull('preferred_locale')
                    ->orWhere('notification_consent', 1)
                    ->orWhereNotNull('ticket_entitlement_id');
            })->exists();
    }

    private function dropChecks(): void
    {
        foreach ([
            'event_registration_form_questions' => [
                'chk_ev_reg_question_validation_rules',
                'chk_ev_reg_question_visibility_rules',
            ],
            'event_registration_form_submissions' => ['chk_ev_reg_submission_lineage'],
            'event_invitation_campaigns' => [
                'chk_event_inv_campaign_source_snapshot',
                'chk_event_inv_campaign_status',
                'chk_event_inv_campaign_state',
            ],
            'event_registration_guests' => ['chk_ev_reg_guest_notification_consent'],
            'event_registration_guest_attendance' => ['chk_ev_reg_guest_attendance_status'],
            'event_registration_guest_attendance_history' => ['chk_ev_reg_guest_att_hist_status'],
            'event_invitation_campaign_history' => ['chk_event_inv_campaign_hist_action'],
            'event_invitation_delivery_evidence' => ['chk_event_inv_delivery_fields'],
        ] as $table => $checks) {
            foreach ($checks as $check) {
                $this->dropCheck($table, $check);
            }
        }
    }

    private function dropGuestForeignKey(): void
    {
        if (Schema::hasTable('event_registration_guests')
            && $this->constraintExists('event_registration_guests', 'fk_ev_reg_guest_ticket')) {
            Schema::table('event_registration_guests', static function (Blueprint $table): void {
                $table->dropForeign('fk_ev_reg_guest_ticket');
            });
        }
    }

    /** @param list<string> $columns */
    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
        if ($existing !== []) {
            Schema::table($table, static function (Blueprint $blueprint) use ($existing): void {
                $blueprint->dropColumn($existing);
            });
        }
    }

    private function replaceCheck(string $table, string $name, string $expression): void
    {
        $this->dropCheck($table, $name);
        $this->addCheck($table, $name, $expression);
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        if (Schema::hasTable($table) && ! $this->constraintExists($table, $name)) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    private function dropCheck(string $table, string $name): void
    {
        if (DB::getDriverName() === 'mysql'
            && Schema::hasTable($table)
            && $this->constraintExists($table, $name)) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$name}`");
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

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function triggerExists(string $trigger): bool
    {
        return DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->where('TRIGGER_NAME', $trigger)
            ->exists();
    }
};
