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
use App\Support\Events\EventRecurrenceRevisionSchemaGuard;

return new class extends Migration
{
    private const REVISION_TABLE = 'event_recurrence_revisions';
    private const OCCURRENCE_LEDGER = 'event_recurrence_occurrence_ledger';
    private const REVISION_UPDATE_TRIGGER = 'trg_event_recurrence_revision_no_update';
    private const REVISION_DELETE_TRIGGER = 'trg_event_recurrence_revision_no_delete';
    private const LEDGER_UPDATE_TRIGGER = 'trg_event_recur_occ_ledger_no_update';
    private const LEDGER_DELETE_TRIGGER = 'trg_event_recur_occ_ledger_no_delete';

    /**
     * Expand-only effective-dated series revisions and occurrence evidence.
     *
     * A recurrence identity is never inferred from a mutable start time here.
     * Migration 68 owns the deterministic, immutable V2 `recurrence_id`
     * backfill. Any remaining ambiguous V2 child blocks this migration.
     */
    public function up(): void
    {
        foreach (['events', 'event_recurrence_rules', 'users', 'tenants'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \LogicException("event_recurrence_revision_prerequisite_missing:{$table}");
            }
        }
        foreach ([
            'id',
            'tenant_id',
            'parent_event_id',
            'occurrence_key',
            'recurrence_id',
            'recurrence_engine',
            'recurrence_engine_version',
            'is_recurring_template',
            'is_recurrence_exception',
            'recurrence_override_fields',
            'recurrence_override_version',
        ] as $column) {
            if (! Schema::hasColumn('events', $column)) {
                throw new \LogicException("event_recurrence_revision_event_column_missing:{$column}");
            }
        }

        // MariaDB DDL auto-commits. Detect every partial-artifact permutation
        // before adding anything; a retry must never silently repair an
        // interrupted attempt. Laravel's migration registry owns idempotence;
        // a manually pre-created "complete-looking" subset is still unsafe
        // because its columns, indexes, constraints or triggers may differ.
        $revisionTableExists = Schema::hasTable(self::REVISION_TABLE);
        $ledgerTableExists = Schema::hasTable(self::OCCURRENCE_LEDGER);
        $ruleVersionExists = Schema::hasColumn(
            'event_recurrence_rules',
            'effective_revision_version',
        );
        $setVersionExists = Schema::hasColumn(
            'event_recurrence_rules',
            'materialized_set_version',
        );
        EventRecurrenceRevisionSchemaGuard::assertFresh(
            $revisionTableExists,
            $ledgerTableExists,
            $ruleVersionExists,
            $setVersionExists,
        );

        $ambiguousV2Children = DB::table('events')
            ->whereNotNull('parent_event_id')
            ->where('is_recurring_template', 0)
            ->where('recurrence_engine', 'sabre-vobject')
            ->where(static function ($query): void {
                $query->whereNull('recurrence_id')
                    ->orWhereRaw("recurrence_id NOT REGEXP '^[0-9]{8}T[0-9]{6}Z$'");
            });
        if ($ambiguousV2Children->exists()) {
            throw new \LogicException('event_recurrence_revision_preflight_ambiguous_identity');
        }

        $invalidParentLinks = DB::table('events as child')
            ->leftJoin('events as root', 'root.id', '=', 'child.parent_event_id')
            ->where('child.recurrence_engine', 'sabre-vobject')
            ->whereNotNull('child.parent_event_id')
            ->where('child.is_recurring_template', 0)
            ->where(static function ($query): void {
                $query->whereNull('root.id')
                    ->orWhereColumn('child.tenant_id', '!=', 'root.tenant_id')
                    ->orWhere('root.is_recurring_template', '!=', 1);
            });
        if ($invalidParentLinks->exists()) {
            throw new \LogicException('event_recurrence_revision_preflight_invalid_parent');
        }

        Schema::table('event_recurrence_rules', static function (Blueprint $table): void {
            $table->unsignedBigInteger('effective_revision_version')->default(0)
                ->after('rule_hash');
            $table->unsignedBigInteger('materialized_set_version')->default(0)
                ->after('effective_revision_version');
        });

        Schema::create(self::REVISION_TABLE, static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('root_event_id');
            $table->unsignedBigInteger('revision_version');
            $table->string('effective_from_recurrence_id', 32);
            $table->dateTime('effective_from_utc');
            $table->string('effective_until_recurrence_id', 32)->nullable();
            $table->dateTime('effective_until_utc')->nullable();
            $table->string('canonical_timezone', 64);
            $table->text('canonical_rrule');
            $table->char('rule_hash', 64);
            $table->json('blueprint_patch');
            $table->char('patch_hash', 64);
            $table->integer('actor_user_id');
            $table->unsignedBigInteger('root_calendar_sequence');
            $table->unsignedBigInteger('rule_version');
            $table->unsignedBigInteger('materialized_set_version');
            $table->char('materialized_checksum_before', 64);
            $table->char('materialized_checksum_after', 64);
            $table->char('idempotency_hash', 64);
            $table->char('request_hash', 64);
            $table->json('impact_summary');
            $table->timestamp('previewed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'root_event_id', 'revision_version'],
                'uq_event_recur_revision_version',
            );
            $table->unique(
                ['tenant_id', 'root_event_id', 'idempotency_hash'],
                'uq_event_recur_revision_idempotency',
            );
            $table->index(
                ['tenant_id', 'root_event_id', 'effective_from_recurrence_id', 'revision_version'],
                'idx_event_recur_revision_effective',
            );
            $table->index(
                ['tenant_id', 'actor_user_id', 'created_at', 'id'],
                'idx_event_recur_revision_actor',
            );

            $table->foreign(['tenant_id', 'root_event_id'], 'fk_event_recur_revision_root')
                ->references(['tenant_id', 'id'])->on('events');
            $table->foreign(['tenant_id', 'root_event_id'], 'fk_event_recur_revision_rule')
                ->references(['tenant_id', 'event_id'])->on('event_recurrence_rules');
            // actor_user_id is an immutable pseudonymous audit reference, not
            // an FK: account erasure must not delete evidence or block the
            // user's independent lifecycle.
        });

        Schema::create(self::OCCURRENCE_LEDGER, static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('root_event_id');
            $table->integer('event_id');
            $table->string('recurrence_id', 32);
            $table->string('occurrence_key', 191);
            $table->string('state', 32);
            $table->unsignedBigInteger('state_version');
            $table->unsignedBigInteger('revision_version')->nullable();
            $table->dateTime('start_time_utc')->nullable();
            $table->dateTime('end_time_utc')->nullable();
            $table->integer('actor_user_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'root_event_id', 'event_id', 'state_version'],
                'uq_event_recur_occ_ledger_event_version',
            );
            $table->unique(
                ['tenant_id', 'root_event_id', 'recurrence_id', 'state_version'],
                'uq_event_recur_occ_ledger_identity_version',
            );
            $table->index(
                ['tenant_id', 'root_event_id', 'recurrence_id', 'id'],
                'idx_event_recur_occ_ledger_effective',
            );
            $table->index(
                ['tenant_id', 'root_event_id', 'state', 'created_at', 'id'],
                'idx_event_recur_occ_ledger_state',
            );

            $table->foreign(['tenant_id', 'root_event_id'], 'fk_event_recur_occ_ledger_root')
                ->references(['tenant_id', 'id'])->on('events');
            $table->foreign(['tenant_id', 'event_id'], 'fk_event_recur_occ_ledger_event')
                ->references(['tenant_id', 'id'])->on('events');
            // actor_user_id intentionally has no FK for the same erasure-safe
            // immutable-audit reason as the revision ledger.
        });

        DB::statement(
            "ALTER TABLE `event_recurrence_revisions` ADD CONSTRAINT `chk_event_recur_revision_identity` "
            . "CHECK (`effective_from_recurrence_id` REGEXP '^[0-9]{8}T[0-9]{6}Z$' "
            . "AND (`effective_until_recurrence_id` IS NULL OR (`effective_until_recurrence_id` REGEXP '^[0-9]{8}T[0-9]{6}Z$' "
            . "AND `effective_until_recurrence_id` > `effective_from_recurrence_id`)) "
            . "AND (`effective_until_utc` IS NULL) = (`effective_until_recurrence_id` IS NULL))",
        );
        DB::statement(
            "ALTER TABLE `event_recurrence_occurrence_ledger` ADD CONSTRAINT `chk_event_recur_occ_ledger_state` "
            . "CHECK (`state` IN ('materialized','customized','excluded','retired') "
            . "AND `recurrence_id` REGEXP '^[0-9]{8}T[0-9]{6}Z$' AND `state_version` > 0)",
        );

        $now = now();
        DB::table(self::OCCURRENCE_LEDGER)->insertUsing([
            'tenant_id',
            'root_event_id',
            'event_id',
            'recurrence_id',
            'occurrence_key',
            'state',
            'state_version',
            'revision_version',
            'start_time_utc',
            'end_time_utc',
            'actor_user_id',
            'metadata',
            'created_at',
        ], DB::table('events')
            ->select([
                'tenant_id',
                'parent_event_id',
                'id',
                'recurrence_id',
                'occurrence_key',
            ])
            ->selectRaw(
                "CASE WHEN is_recurrence_exception = 1 OR recurrence_override_version > 0 "
                . "THEN 'customized' ELSE 'materialized' END",
            )
            ->selectRaw('1')
            ->selectRaw('NULL')
            ->addSelect(['start_time', 'end_time'])
            ->selectRaw('NULL')
            ->selectRaw("JSON_OBJECT('source', 'migration_70_bootstrap')")
            ->selectRaw('?', [$now])
            ->whereNotNull('parent_event_id')
            ->whereNotNull('recurrence_id')
            ->whereNotNull('occurrence_key')
            ->where('recurrence_engine', 'sabre-vobject')
            ->where('recurrence_engine_version', '2'));

        DB::table('event_recurrence_rules as rule')
            ->where('rule.recurrence_engine', 'sabre-vobject')
            ->where('rule.recurrence_engine_version', '2')
            ->whereExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('events as child')
                    ->whereColumn('child.tenant_id', 'rule.tenant_id')
                    ->whereColumn('child.parent_event_id', 'rule.event_id')
                    ->where('child.recurrence_engine', 'sabre-vobject')
                    ->where('child.recurrence_engine_version', '2')
                    ->whereNotNull('child.recurrence_id');
            })
            ->update(['materialized_set_version' => 1]);

        DB::unprepared(
            'CREATE TRIGGER `' . self::REVISION_UPDATE_TRIGGER . '` BEFORE UPDATE ON `'
            . self::REVISION_TABLE . "` FOR EACH ROW SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'event_recurrence_revision_immutable'",
        );
        DB::unprepared(
            'CREATE TRIGGER `' . self::REVISION_DELETE_TRIGGER . '` BEFORE DELETE ON `'
            . self::REVISION_TABLE . "` FOR EACH ROW SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'event_recurrence_revision_immutable'",
        );
        DB::unprepared(
            'CREATE TRIGGER `' . self::LEDGER_UPDATE_TRIGGER . '` BEFORE UPDATE ON `'
            . self::OCCURRENCE_LEDGER . "` FOR EACH ROW SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'event_recurrence_occurrence_ledger_immutable'",
        );
        DB::unprepared(
            'CREATE TRIGGER `' . self::LEDGER_DELETE_TRIGGER . '` BEFORE DELETE ON `'
            . self::OCCURRENCE_LEDGER . "` FOR EACH ROW SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'event_recurrence_occurrence_ledger_immutable'",
        );
    }

    public function down(): void
    {
        if ((Schema::hasTable(self::REVISION_TABLE)
                && DB::table(self::REVISION_TABLE)->exists())
            || (Schema::hasTable(self::OCCURRENCE_LEDGER)
                && DB::table(self::OCCURRENCE_LEDGER)->exists())) {
            throw new \LogicException('event_recurrence_revision_rollback_refused_evidence_exists');
        }

        foreach ([
            self::REVISION_UPDATE_TRIGGER,
            self::REVISION_DELETE_TRIGGER,
            self::LEDGER_UPDATE_TRIGGER,
            self::LEDGER_DELETE_TRIGGER,
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
        }
        Schema::dropIfExists(self::OCCURRENCE_LEDGER);
        Schema::dropIfExists(self::REVISION_TABLE);

        if (Schema::hasTable('event_recurrence_rules')) {
            $columns = array_values(array_filter([
                'effective_revision_version',
                'materialized_set_version',
            ], static fn (string $column): bool => Schema::hasColumn(
                'event_recurrence_rules',
                $column,
            )));
            if ($columns !== []) {
                Schema::table('event_recurrence_rules', static function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }

};
