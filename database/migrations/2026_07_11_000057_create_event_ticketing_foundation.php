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
    private const TRIGGERS = [
        'trg_event_ticket_type_validate_insert',
        'trg_event_ticket_type_validate_update',
        'trg_event_ticket_type_no_delete',
        'trg_event_ticket_type_hist_no_update',
        'trg_event_ticket_type_hist_no_delete',
        'trg_event_ticket_entitlement_validate_insert',
        'trg_event_ticket_entitlement_validate_update',
        'trg_event_ticket_entitlement_no_delete',
        'trg_event_ticket_ent_hist_no_update',
        'trg_event_ticket_ent_hist_no_delete',
        'trg_event_ticket_inv_hist_no_update',
        'trg_event_ticket_inv_hist_no_delete',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('event_registrations')) {
            return;
        }

        $this->createTicketTypes();
        $this->createTicketTypeHistory();
        $this->createEntitlements();
        $this->createEntitlementHistory();
        $this->createInventoryHistory();
        $this->installChecks();
        $this->installTriggers();
    }

    public function down(): void
    {
        foreach ([
            'event_ticket_inventory_history',
            'event_ticket_entitlement_history',
            'event_ticket_entitlements',
            'event_ticket_type_history',
            'event_ticket_types',
        ] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new LogicException('event_ticketing_rollback_refused_durable_evidence');
            }
        }

        $this->dropTriggers();
        Schema::dropIfExists('event_ticket_inventory_history');
        Schema::dropIfExists('event_ticket_entitlement_history');
        Schema::dropIfExists('event_ticket_entitlements');
        Schema::dropIfExists('event_ticket_type_history');
        Schema::dropIfExists('event_ticket_types');
    }

    private function createTicketTypes(): void
    {
        if (Schema::hasTable('event_ticket_types')) {
            return;
        }

        Schema::create('event_ticket_types', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->string('occurrence_key', 191);
            $table->unsignedBigInteger('ticket_version')->default(1);
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('kind', 24);
            $table->decimal('unit_price_credits', 10, 2)->default(0);
            $table->unsignedInteger('allocation_limit');
            $table->dateTime('sales_opens_at_utc');
            $table->dateTime('sales_closes_at_utc');
            $table->dateTime('event_starts_at_utc_snapshot');
            $table->string('event_timezone_snapshot', 64);
            $table->unsignedSmallInteger('per_member_limit')->default(1);
            $table->json('eligibility_policy');
            $table->dateTime('refund_cutoff_at_utc')->nullable();
            $table->boolean('organizer_cancel_refundable')->default(false);
            $table->string('status', 16)->default('draft');
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('activated_by')->nullable();
            $table->integer('paused_by')->nullable();
            $table->integer('archived_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'id'], 'uq_event_ticket_type_scope');
            $table->index(
                ['tenant_id', 'event_id', 'status', 'sales_opens_at_utc', 'sales_closes_at_utc', 'id'],
                'idx_event_ticket_type_sales',
            );
            $table->index(
                ['tenant_id', 'event_id', 'kind', 'status', 'id'],
                'idx_event_ticket_type_kind',
            );

            $table->foreign('tenant_id', 'fk_event_ticket_type_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'occurrence_key'],
                'fk_event_ticket_type_event',
            )->references(['tenant_id', 'id', 'occurrence_key'])
                ->on('events')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_event_ticket_type_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['updated_by', 'tenant_id'], 'fk_event_ticket_type_updater')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['activated_by', 'tenant_id'], 'fk_event_ticket_type_activator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['paused_by', 'tenant_id'], 'fk_event_ticket_type_pauser')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['archived_by', 'tenant_id'], 'fk_event_ticket_type_archiver')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createTicketTypeHistory(): void
    {
        if (Schema::hasTable('event_ticket_type_history')) {
            return;
        }

        Schema::create('event_ticket_type_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('ticket_version');
            $table->string('action', 16);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('changed_fields');
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'ticket_type_id', 'ticket_version'],
                'uq_event_ticket_type_hist_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_ticket_type_hist_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'created_at', 'id'],
                'idx_event_ticket_type_hist_event',
            );

            $table->foreign('tenant_id', 'fk_event_ticket_type_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'ticket_type_id'],
                'fk_event_ticket_type_hist_type',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_ticket_types')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_ticket_type_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createEntitlements(): void
    {
        if (Schema::hasTable('event_ticket_entitlements')) {
            return;
        }

        Schema::create('event_ticket_entitlements', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->unsignedInteger('units');
            $table->string('ticket_kind_snapshot', 24);
            $table->decimal('unit_price_credits_snapshot', 10, 2)->default(0);
            $table->decimal('total_price_credits_snapshot', 12, 2)->default(0);
            $table->string('status', 16)->default('confirmed');
            $table->unsignedBigInteger('entitlement_version')->default(1);
            $table->char('allocation_idempotency_hash', 64);
            $table->char('allocation_request_hash', 64);
            $table->integer('created_by');
            $table->integer('cancelled_by')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'allocation_idempotency_hash'],
                'uq_event_ticket_entitlement_key',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'id'],
                'uq_event_ticket_entitlement_scope',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'ticket_type_id', 'id'],
                'uq_event_ticket_entitlement_type',
            );
            $table->unique(
                ['tenant_id', 'event_id', 'ticket_type_id', 'registration_id', 'user_id', 'id'],
                'uq_event_ticket_entitlement_identity',
            );
            $table->index(
                ['tenant_id', 'event_id', 'ticket_type_id', 'status', 'user_id', 'id'],
                'idx_event_ticket_entitlement_cohort',
            );
            $table->index(
                ['tenant_id', 'user_id', 'status', 'event_id', 'id'],
                'idx_event_ticket_entitlement_user',
            );

            $table->foreign('tenant_id', 'fk_event_ticket_entitlement_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'ticket_type_id'],
                'fk_event_ticket_entitlement_type',
            )->references(['tenant_id', 'event_id', 'id'])
                ->on('event_ticket_types')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'registration_id', 'user_id'],
                'fk_event_ticket_entitlement_registration',
            )->references(['tenant_id', 'event_id', 'id', 'user_id'])
                ->on('event_registrations')->restrictOnDelete();
            $table->foreign(['created_by', 'tenant_id'], 'fk_event_ticket_entitlement_creator')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
            $table->foreign(['cancelled_by', 'tenant_id'], 'fk_event_ticket_entitlement_canceller')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createEntitlementHistory(): void
    {
        if (Schema::hasTable('event_ticket_entitlement_history')) {
            return;
        }

        Schema::create('event_ticket_entitlement_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('entitlement_id');
            $table->unsignedBigInteger('registration_id');
            $table->integer('user_id');
            $table->unsignedBigInteger('entitlement_version');
            $table->string('action', 16);
            $table->unsignedInteger('units');
            $table->string('ticket_kind_snapshot', 24);
            $table->decimal('unit_price_credits_snapshot', 10, 2);
            $table->decimal('total_price_credits_snapshot', 12, 2);
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->string('reason', 500)->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'entitlement_id', 'entitlement_version'],
                'uq_event_ticket_ent_hist_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_ticket_ent_hist_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'ticket_type_id', 'created_at', 'id'],
                'idx_event_ticket_ent_hist_event',
            );

            $table->foreign('tenant_id', 'fk_event_ticket_ent_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'ticket_type_id', 'registration_id', 'user_id', 'entitlement_id'],
                'fk_event_ticket_ent_hist_entitlement',
            )->references(['tenant_id', 'event_id', 'ticket_type_id', 'registration_id', 'user_id', 'id'])
                ->on('event_ticket_entitlements')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_ticket_ent_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function createInventoryHistory(): void
    {
        if (Schema::hasTable('event_ticket_inventory_history')) {
            return;
        }

        Schema::create('event_ticket_inventory_history', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('entitlement_id');
            $table->unsignedBigInteger('entitlement_version');
            $table->string('action', 16);
            $table->integer('quantity_delta');
            $table->unsignedInteger('confirmed_units_after');
            $table->integer('actor_user_id');
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'entitlement_id', 'entitlement_version'],
                'uq_event_ticket_inv_hist_version',
            );
            $table->unique(
                ['tenant_id', 'idempotency_hash'],
                'uq_event_ticket_inv_hist_key',
            );
            $table->index(
                ['tenant_id', 'event_id', 'ticket_type_id', 'created_at', 'id'],
                'idx_event_ticket_inv_hist_event',
            );

            $table->foreign('tenant_id', 'fk_event_ticket_inv_hist_tenant')
                ->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'event_id', 'ticket_type_id', 'entitlement_id'],
                'fk_event_ticket_inv_hist_entitlement',
            )->references(['tenant_id', 'event_id', 'ticket_type_id', 'id'])
                ->on('event_ticket_entitlements')->restrictOnDelete();
            $table->foreign(['actor_user_id', 'tenant_id'], 'fk_event_ticket_inv_hist_actor')
                ->references(['id', 'tenant_id'])->on('users')->restrictOnDelete();
        });
    }

    private function installChecks(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $checks = [
            'event_ticket_types' => [
                'chk_event_ticket_type_version' => '`ticket_version` > 0',
                'chk_event_ticket_type_name' => 'CHAR_LENGTH(TRIM(`name`)) BETWEEN 1 AND 191',
                'chk_event_ticket_type_kind' => "`kind` IN ('free','time_credit')",
                'chk_event_ticket_type_price' => "((`kind` = 'free' AND `unit_price_credits` = 0.00) OR (`kind` = 'time_credit' AND `unit_price_credits` > 0.00 AND `unit_price_credits` <= 100000.00))",
                'chk_event_ticket_type_allocation' => '`allocation_limit` BETWEEN 1 AND 1000000 AND `per_member_limit` BETWEEN 1 AND 1000 AND `per_member_limit` <= `allocation_limit`',
                'chk_event_ticket_type_sales' => '`sales_opens_at_utc` < `sales_closes_at_utc` AND `sales_closes_at_utc` <= `event_starts_at_utc_snapshot`',
                'chk_event_ticket_type_refund' => '`refund_cutoff_at_utc` IS NULL OR `refund_cutoff_at_utc` <= `event_starts_at_utc_snapshot`',
                'chk_event_ticket_type_policy' => "JSON_TYPE(`eligibility_policy`) = 'OBJECT'",
                'chk_event_ticket_type_status' => "`status` IN ('draft','active','paused','archived')",
                'chk_event_ticket_type_lifecycle' => "((`status` = 'draft' AND `activated_by` IS NULL AND `activated_at` IS NULL AND `paused_by` IS NULL AND `paused_at` IS NULL AND `archived_by` IS NULL AND `archived_at` IS NULL) OR (`status` = 'active' AND `activated_by` IS NOT NULL AND `activated_at` IS NOT NULL AND `archived_by` IS NULL AND `archived_at` IS NULL) OR (`status` = 'paused' AND `activated_by` IS NOT NULL AND `activated_at` IS NOT NULL AND `paused_by` IS NOT NULL AND `paused_at` IS NOT NULL AND `archived_by` IS NULL AND `archived_at` IS NULL) OR (`status` = 'archived' AND `archived_by` IS NOT NULL AND `archived_at` IS NOT NULL))",
            ],
            'event_ticket_type_history' => [
                'chk_event_ticket_type_hist_action' => "`action` IN ('created','updated','activated','paused','archived')",
                'chk_event_ticket_type_hist_version' => '`ticket_version` > 0',
                'chk_event_ticket_type_hist_reason' => "((`action` IN ('created','updated','activated') AND `reason` IS NULL) OR (`action` IN ('paused','archived') AND `reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`reason`)) > 0))",
            ],
            'event_ticket_entitlements' => [
                'chk_event_ticket_ent_units' => '`units` BETWEEN 1 AND 1000',
                'chk_event_ticket_ent_free_only' => "`ticket_kind_snapshot` = 'free' AND `unit_price_credits_snapshot` = 0.00 AND `total_price_credits_snapshot` = 0.00",
                'chk_event_ticket_ent_total' => '`total_price_credits_snapshot` = `unit_price_credits_snapshot` * `units`',
                'chk_event_ticket_ent_status' => "`status` IN ('confirmed','cancelled') AND `entitlement_version` > 0",
                'chk_event_ticket_ent_lifecycle' => "((`status` = 'confirmed' AND `cancelled_by` IS NULL AND `cancellation_reason` IS NULL AND `cancelled_at` IS NULL) OR (`status` = 'cancelled' AND `cancelled_by` IS NOT NULL AND `cancellation_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`cancellation_reason`)) > 0 AND `cancelled_at` IS NOT NULL))",
            ],
            'event_ticket_entitlement_history' => [
                'chk_event_ticket_ent_hist_action' => "`action` IN ('confirmed','cancelled')",
                'chk_event_ticket_ent_hist_version' => '`entitlement_version` > 0',
                'chk_event_ticket_ent_hist_units' => '`units` BETWEEN 1 AND 1000',
                'chk_event_ticket_ent_hist_free' => "`ticket_kind_snapshot` = 'free' AND `unit_price_credits_snapshot` = 0.00 AND `total_price_credits_snapshot` = 0.00",
                'chk_event_ticket_ent_hist_total' => '`total_price_credits_snapshot` = `unit_price_credits_snapshot` * `units`',
                'chk_event_ticket_ent_hist_reason' => "((`action` = 'confirmed' AND `reason` IS NULL) OR (`action` = 'cancelled' AND `reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`reason`)) > 0))",
            ],
            'event_ticket_inventory_history' => [
                'chk_event_ticket_inv_hist_version' => '`entitlement_version` > 0',
                'chk_event_ticket_inv_hist_action' => "((`action` = 'allocated' AND `quantity_delta` > 0) OR (`action` = 'released' AND `quantity_delta` < 0))",
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
            'trg_event_ticket_type_validate_insert',
            'BEFORE INSERT ON `event_ticket_types` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `events` WHERE `tenant_id` = NEW.`tenant_id` AND `id` = NEW.`event_id` AND `occurrence_key` = NEW.`occurrence_key` AND `is_recurring_template` = 0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_concrete_occurrence_required'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_ticket_type_validate_update',
            'BEFORE UPDATE ON `event_ticket_types` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`occurrence_key` <=> NEW.`occurrence_key`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_type_identity_immutable'; END IF; "
                . "IF OLD.`status` = 'archived' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_type_archived_immutable'; END IF; "
                . "IF NEW.`ticket_version` <> OLD.`ticket_version` + 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_type_version_invalid'; END IF; "
                . "IF NOT ((OLD.`status` = 'draft' AND NEW.`status` IN ('draft','active','archived')) OR (OLD.`status` = 'active' AND NEW.`status` IN ('paused','archived')) OR (OLD.`status` = 'paused' AND NEW.`status` IN ('paused','active','archived'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_type_transition_invalid'; END IF; "
                . "IF (SELECT COUNT(*) FROM `event_ticket_entitlements` WHERE `tenant_id` = OLD.`tenant_id` AND `event_id` = OLD.`event_id` AND `ticket_type_id` = OLD.`id`) > 0 AND (NOT (OLD.`kind` <=> NEW.`kind`) OR NOT (OLD.`unit_price_credits` <=> NEW.`unit_price_credits`) OR NOT (OLD.`allocation_limit` <=> NEW.`allocation_limit`) OR NOT (OLD.`per_member_limit` <=> NEW.`per_member_limit`)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_type_inventory_fields_immutable'; END IF; END",
        );
        $this->createSignalTrigger(
            'trg_event_ticket_type_no_delete',
            'BEFORE DELETE ON `event_ticket_types` FOR EACH ROW',
            'event_ticket_type_delete_forbidden',
        );
        $this->createSignalTrigger(
            'trg_event_ticket_type_hist_no_update',
            'BEFORE UPDATE ON `event_ticket_type_history` FOR EACH ROW',
            'event_ticket_type_history_immutable',
        );
        $this->createSignalTrigger(
            'trg_event_ticket_type_hist_no_delete',
            'BEFORE DELETE ON `event_ticket_type_history` FOR EACH ROW',
            'event_ticket_type_history_immutable',
        );

        $this->createTrigger(
            'trg_event_ticket_entitlement_validate_insert',
            'BEFORE INSERT ON `event_ticket_entitlements` FOR EACH ROW BEGIN '
                . "IF (SELECT COUNT(*) FROM `event_ticket_types` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`ticket_type_id` AND `status` = 'active' AND `kind` = 'free' AND `unit_price_credits` = 0.00 AND UTC_TIMESTAMP(6) >= `sales_opens_at_utc` AND UTC_TIMESTAMP(6) < `sales_closes_at_utc`) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_free_type_not_allocatable'; END IF; "
                . "IF (SELECT COUNT(*) FROM `event_registrations` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`registration_id` AND `user_id` = NEW.`user_id` AND `registration_state` = 'confirmed') <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_confirmed_registration_required'; END IF; "
                . "IF NEW.`status` <> 'confirmed' OR NEW.`entitlement_version` <> 1 OR NEW.`ticket_kind_snapshot` <> 'free' OR NEW.`unit_price_credits_snapshot` <> 0.00 OR NEW.`total_price_credits_snapshot` <> 0.00 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_free_snapshot_required'; END IF; "
                . "IF (SELECT COALESCE(SUM(`units`), 0) FROM `event_ticket_entitlements` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `ticket_type_id` = NEW.`ticket_type_id` AND `status` = 'confirmed') + NEW.`units` > (SELECT `allocation_limit` FROM `event_ticket_types` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`ticket_type_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_allocation_exhausted'; END IF; "
                . "IF (SELECT COALESCE(SUM(`units`), 0) FROM `event_ticket_entitlements` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `ticket_type_id` = NEW.`ticket_type_id` AND `user_id` = NEW.`user_id` AND `status` = 'confirmed') + NEW.`units` > (SELECT `per_member_limit` FROM `event_ticket_types` WHERE `tenant_id` = NEW.`tenant_id` AND `event_id` = NEW.`event_id` AND `id` = NEW.`ticket_type_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_per_member_limit_exceeded'; END IF; END",
        );
        $this->createTrigger(
            'trg_event_ticket_entitlement_validate_update',
            'BEFORE UPDATE ON `event_ticket_entitlements` FOR EACH ROW BEGIN '
                . "IF NOT (OLD.`tenant_id` <=> NEW.`tenant_id`) OR NOT (OLD.`event_id` <=> NEW.`event_id`) OR NOT (OLD.`ticket_type_id` <=> NEW.`ticket_type_id`) OR NOT (OLD.`registration_id` <=> NEW.`registration_id`) OR NOT (OLD.`user_id` <=> NEW.`user_id`) OR NOT (OLD.`units` <=> NEW.`units`) OR NOT (OLD.`ticket_kind_snapshot` <=> NEW.`ticket_kind_snapshot`) OR NOT (OLD.`unit_price_credits_snapshot` <=> NEW.`unit_price_credits_snapshot`) OR NOT (OLD.`total_price_credits_snapshot` <=> NEW.`total_price_credits_snapshot`) OR NOT (OLD.`allocation_idempotency_hash` <=> NEW.`allocation_idempotency_hash`) OR NOT (OLD.`allocation_request_hash` <=> NEW.`allocation_request_hash`) OR NOT (OLD.`created_by` <=> NEW.`created_by`) OR NOT (OLD.`confirmed_at` <=> NEW.`confirmed_at`) OR NOT (OLD.`created_at` <=> NEW.`created_at`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_entitlement_identity_immutable'; END IF; "
                . "IF OLD.`status` <> 'confirmed' OR NEW.`status` <> 'cancelled' OR NEW.`entitlement_version` <> OLD.`entitlement_version` + 1 OR NEW.`cancelled_by` IS NULL OR NEW.`cancellation_reason` IS NULL OR CHAR_LENGTH(TRIM(NEW.`cancellation_reason`)) = 0 OR NEW.`cancelled_at` IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'event_ticket_entitlement_transition_invalid'; END IF; END",
        );
        $this->createSignalTrigger(
            'trg_event_ticket_entitlement_no_delete',
            'BEFORE DELETE ON `event_ticket_entitlements` FOR EACH ROW',
            'event_ticket_entitlement_delete_forbidden',
        );
        foreach ([
            'trg_event_ticket_ent_hist_no_update' => ['event_ticket_entitlement_history', 'UPDATE', 'event_ticket_entitlement_history_immutable'],
            'trg_event_ticket_ent_hist_no_delete' => ['event_ticket_entitlement_history', 'DELETE', 'event_ticket_entitlement_history_immutable'],
            'trg_event_ticket_inv_hist_no_update' => ['event_ticket_inventory_history', 'UPDATE', 'event_ticket_inventory_history_immutable'],
            'trg_event_ticket_inv_hist_no_delete' => ['event_ticket_inventory_history', 'DELETE', 'event_ticket_inventory_history_immutable'],
        ] as $name => [$table, $operation, $message]) {
            $this->createSignalTrigger(
                $name,
                "BEFORE {$operation} ON `{$table}` FOR EACH ROW",
                $message,
            );
        }
    }

    private function createTrigger(string $name, string $definition): void
    {
        if (! $this->triggerExists($name)) {
            DB::unprepared("CREATE TRIGGER `{$name}` {$definition}");
        }
    }

    private function createSignalTrigger(string $name, string $timing, string $message): void
    {
        $this->createTrigger(
            $name,
            "{$timing} SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
        );
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
};
