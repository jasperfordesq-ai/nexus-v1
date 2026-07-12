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
    private const UNIQUE_RULE = 'uq_event_recurrence_rule_tenant_event';
    private const EVENT_TENANT_FK = 'fk_event_recurrence_rule_event_tenant';
    private const DUE_INDEX = 'idx_event_recurrence_materialization_due';

    /**
     * Expand-only rolling materialization state and tenant-safe rule identity.
     *
     * Existing dirty data is never guessed or rewritten. Operators must repair
     * duplicate, cross-tenant, orphaned, or non-template links before retrying.
     */
    public function up(): void
    {
        foreach (['events', 'event_recurrence_rules'] as $required) {
            if (! Schema::hasTable($required)) {
                throw new \LogicException("event_recurrence_materialization_prerequisite_missing:{$required}");
            }
        }
        foreach (['id', 'tenant_id', 'is_recurring_template'] as $column) {
            if (! Schema::hasColumn('events', $column)) {
                throw new \LogicException("event_recurrence_materialization_event_column_missing:{$column}");
            }
        }
        foreach (['id', 'tenant_id', 'event_id'] as $column) {
            if (! Schema::hasColumn('event_recurrence_rules', $column)) {
                throw new \LogicException("event_recurrence_materialization_rule_column_missing:{$column}");
            }
        }

        $duplicateGroups = DB::table('event_recurrence_rules')
            ->select(['tenant_id', 'event_id'])
            ->groupBy('tenant_id', 'event_id')
            ->havingRaw('COUNT(*) > 1');
        if (DB::query()->fromSub($duplicateGroups, 'duplicate_recurrence_rules')->exists()) {
            throw new \LogicException('event_recurrence_materialization_preflight_duplicate_rules');
        }

        $invalidLinks = DB::table('event_recurrence_rules as rule')
            ->leftJoin('events as root', 'root.id', '=', 'rule.event_id')
            ->where(static function ($query): void {
                $query->whereNull('root.id')
                    ->orWhereColumn('root.tenant_id', '!=', 'rule.tenant_id')
                    ->orWhere('root.is_recurring_template', '!=', 1);
            });
        if ($invalidLinks->exists()) {
            throw new \LogicException('event_recurrence_materialization_preflight_invalid_root_link');
        }

        $columns = [
            'materialized_through_at' => fn (Blueprint $table) => $table->dateTime('materialized_through_at')->nullable(),
            'materialization_resume_at' => fn (Blueprint $table) => $table->dateTime('materialization_resume_at')->nullable(),
            'materialization_last_attempted_at' => fn (Blueprint $table) => $table->timestamp('materialization_last_attempted_at')->nullable(),
            'materialization_last_succeeded_at' => fn (Blueprint $table) => $table->timestamp('materialization_last_succeeded_at')->nullable(),
            'materialization_last_failed_at' => fn (Blueprint $table) => $table->timestamp('materialization_last_failed_at')->nullable(),
            'materialization_error_code' => fn (Blueprint $table) => $table->string('materialization_error_code', 64)->nullable(),
            'materialization_truncated' => fn (Blueprint $table) => $table->boolean('materialization_truncated')->default(false),
        ];
        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('event_recurrence_rules', $name)) {
                Schema::table('event_recurrence_rules', static function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }

        if (! $this->indexExists('event_recurrence_rules', self::UNIQUE_RULE)) {
            Schema::table('event_recurrence_rules', static function (Blueprint $table): void {
                $table->unique(['tenant_id', 'event_id'], self::UNIQUE_RULE);
            });
        }
        if (! $this->indexExists('event_recurrence_rules', self::DUE_INDEX)) {
            Schema::table('event_recurrence_rules', static function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'recurrence_engine', 'ends_type', 'materialized_through_at'],
                    self::DUE_INDEX,
                );
            });
        }
        if (! $this->foreignKeyExists('event_recurrence_rules', self::EVENT_TENANT_FK)) {
            Schema::table('event_recurrence_rules', static function (Blueprint $table): void {
                $table->foreign(['tenant_id', 'event_id'], self::EVENT_TENANT_FK)
                    ->references(['tenant_id', 'id'])
                    ->on('events')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_recurrence_rules')) {
            return;
        }

        $hasDurableState = DB::table('event_recurrence_rules')
            ->whereNotNull('materialization_last_attempted_at')
            ->orWhereNotNull('materialized_through_at')
            ->orWhereNotNull('materialization_error_code')
            ->exists();
        if ($hasDurableState) {
            throw new \LogicException('event_recurrence_materialization_rollback_refused_state_exists');
        }

        Schema::table('event_recurrence_rules', function (Blueprint $table): void {
            if ($this->foreignKeyExists('event_recurrence_rules', self::EVENT_TENANT_FK)) {
                $table->dropForeign(self::EVENT_TENANT_FK);
            }
            if ($this->indexExists('event_recurrence_rules', self::DUE_INDEX)) {
                $table->dropIndex(self::DUE_INDEX);
            }
            if ($this->indexExists('event_recurrence_rules', self::UNIQUE_RULE)) {
                $table->dropUnique(self::UNIQUE_RULE);
            }
        });

        $columns = array_values(array_filter([
            'materialized_through_at',
            'materialization_resume_at',
            'materialization_last_attempted_at',
            'materialization_last_succeeded_at',
            'materialization_last_failed_at',
            'materialization_error_code',
            'materialization_truncated',
        ], static fn (string $column): bool => Schema::hasColumn('event_recurrence_rules', $column)));
        if ($columns !== []) {
            Schema::table('event_recurrence_rules', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
