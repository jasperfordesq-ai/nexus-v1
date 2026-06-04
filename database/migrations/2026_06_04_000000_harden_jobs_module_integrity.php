<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Harden the Jobs module's relational integrity.
 *
 * Several job tables shipped with FK columns declared BIGINT UNSIGNED while their
 * parents (`job_vacancies.id`, `job_vacancy_applications.id`) are INT UNSIGNED, so
 * MariaDB silently created the tables WITHOUT the intended foreign keys — leaving no
 * ON DELETE CASCADE and a real orphan-row risk. Other tables were created with the
 * correct INT type by a later Laravel migration, so the actual column type is
 * environment-dependent. This migration therefore inspects the live column type at
 * runtime and only narrows + adds the FK where needed. It also:
 *   - backfills + NOT-NULLs job_vacancy_applications.tenant_id (cross-tenant safety),
 *   - adds a (tenant_id, category, status) browse index on job_vacancies,
 *   - drops the redundant single-column is_active index on job_alerts,
 *   - adds a UNIQUE(tenant_id, job_id, slot_start) guard on job_interview_slots.
 *
 * Every step is idempotent and guarded so it is safe to re-run.
 */
return new class extends Migration
{
    /** Child table => [ FK column => parent table ]. Parent PK is always `id`. */
    private array $fkMap = [
        'job_interviews'      => ['vacancy_id' => 'job_vacancies', 'application_id' => 'job_vacancy_applications'],
        'job_offers'          => ['vacancy_id' => 'job_vacancies', 'application_id' => 'job_vacancy_applications'],
        'job_scorecards'      => ['vacancy_id' => 'job_vacancies', 'application_id' => 'job_vacancy_applications'],
        'job_vacancy_team'    => ['vacancy_id' => 'job_vacancies'],
        'job_pipeline_rules'  => ['vacancy_id' => 'job_vacancies'],
        'job_referrals'       => ['vacancy_id' => 'job_vacancies'],
        'job_interview_slots' => ['job_id' => 'job_vacancies'],
    ];

    public function up(): void
    {
        $this->backfillApplicationTenantId();
        $this->addJobVacancyCategoryIndex();
        $this->dropRedundantAlertIndex();
        $this->addInterviewSlotUniqueGuard();
        $this->repairForeignKeys();
    }

    public function down(): void
    {
        // Intentionally minimal: dropping the restored FKs / indexes would re-introduce
        // the integrity gaps this migration closes. Column widths are not rolled back.
        foreach ($this->fkMap as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $column => $parent) {
                $fk = $this->existingForeignKey($table, $column);
                if ($fk !== null) {
                    try {
                        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
                    } catch (\Throwable $e) {
                        Log::warning("harden_jobs: could not drop FK {$fk} on {$table}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // ── job_vacancy_applications.tenant_id ─────────────────────────────────────
    private function backfillApplicationTenantId(): void
    {
        if (!Schema::hasTable('job_vacancy_applications') || !Schema::hasColumn('job_vacancy_applications', 'tenant_id')) {
            return;
        }

        // Backfill any NULL tenant_id from the owning vacancy.
        DB::statement(
            'UPDATE job_vacancy_applications jva
             JOIN job_vacancies jv ON jv.id = jva.vacancy_id
             SET jva.tenant_id = jv.tenant_id
             WHERE jva.tenant_id IS NULL'
        );

        // Only enforce NOT NULL when nothing is left unresolved (e.g. orphaned rows whose
        // vacancy was hard-deleted). Otherwise leave it nullable and log for follow-up.
        $remaining = (int) DB::table('job_vacancy_applications')->whereNull('tenant_id')->count();
        if ($remaining === 0) {
            $info = $this->columnInfo('job_vacancy_applications', 'tenant_id');
            if ($info !== null && $info['nullable']) {
                DB::statement('ALTER TABLE `job_vacancy_applications` MODIFY COLUMN `tenant_id` INT UNSIGNED NOT NULL');
            }
        } else {
            Log::warning("harden_jobs: job_vacancy_applications still has {$remaining} rows with NULL tenant_id; left nullable.");
        }
    }

    // ── job_vacancies browse index ─────────────────────────────────────────────
    private function addJobVacancyCategoryIndex(): void
    {
        if (!Schema::hasTable('job_vacancies') || !Schema::hasColumn('job_vacancies', 'category')) {
            return;
        }
        if (!$this->indexExists('job_vacancies', 'idx_jv_tenant_cat_status')) {
            DB::statement('ALTER TABLE `job_vacancies` ADD INDEX `idx_jv_tenant_cat_status` (`tenant_id`, `category`, `status`)');
        }
    }

    // ── job_alerts redundant index ─────────────────────────────────────────────
    private function dropRedundantAlertIndex(): void
    {
        if (Schema::hasTable('job_alerts') && $this->indexExists('job_alerts', 'idx_job_alerts_is_active')) {
            DB::statement('ALTER TABLE `job_alerts` DROP INDEX `idx_job_alerts_is_active`');
        }
    }

    // ── job_interview_slots duplicate-slot guard ───────────────────────────────
    private function addInterviewSlotUniqueGuard(): void
    {
        if (!Schema::hasTable('job_interview_slots')
            || !Schema::hasColumn('job_interview_slots', 'slot_start')
            || $this->indexExists('job_interview_slots', 'uq_job_slot')
        ) {
            return;
        }

        $dupes = DB::table('job_interview_slots')
            ->select('tenant_id', 'job_id', 'slot_start')
            ->groupBy('tenant_id', 'job_id', 'slot_start')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($dupes) {
            Log::warning('harden_jobs: duplicate job_interview_slots exist; skipped UNIQUE(tenant_id, job_id, slot_start).');
            return;
        }

        DB::statement('ALTER TABLE `job_interview_slots` ADD UNIQUE INDEX `uq_job_slot` (`tenant_id`, `job_id`, `slot_start`)');
    }

    // ── FK type repair + restoration ───────────────────────────────────────────
    private function repairForeignKeys(): void
    {
        foreach ($this->fkMap as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($cols as $column => $parent) {
                if (!Schema::hasColumn($table, $column) || !Schema::hasTable($parent)) {
                    continue;
                }

                // Skip if a FK already covers this column.
                if ($this->existingForeignKey($table, $column) !== null) {
                    continue;
                }

                $info = $this->columnInfo($table, $column);
                if ($info === null) {
                    continue;
                }

                // Match the parent's INT UNSIGNED width so the FK can be created.
                if ($info['type'] !== 'int' || !$info['unsigned']) {
                    $nullClause = $info['nullable'] ? 'NULL' : 'NOT NULL';
                    DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` INT UNSIGNED {$nullClause}");
                }

                // Remove orphan rows that would block the FK (point at a non-existent parent).
                DB::statement(
                    "DELETE c FROM `{$table}` c
                     LEFT JOIN `{$parent}` p ON p.id = c.`{$column}`
                     WHERE c.`{$column}` IS NOT NULL AND p.id IS NULL"
                );

                $fkName = "fk_{$table}_{$column}";
                try {
                    DB::statement(
                        "ALTER TABLE `{$table}`
                         ADD CONSTRAINT `{$fkName}`
                         FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`) ON DELETE CASCADE"
                    );
                } catch (\Throwable $e) {
                    // Don't abort the whole hardening pass on one table's quirk; log and continue.
                    Log::warning("harden_jobs: could not add FK {$fkName} on {$table}.{$column}: " . $e->getMessage());
                }
            }
        }
    }

    // ── helpers ────────────────────────────────────────────────────────────────
    /** @return array{type:string,unsigned:bool,nullable:bool}|null */
    private function columnInfo(string $table, string $column): ?array
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE as data_type, COLUMN_TYPE as column_type, IS_NULLABLE as is_nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        if ($row === null) {
            return null;
        }
        return [
            'type'     => strtolower((string) $row->data_type),
            'unsigned' => str_contains(strtolower((string) $row->column_type), 'unsigned'),
            'nullable' => strtoupper((string) $row->is_nullable) === 'YES',
        ];
    }

    private function existingForeignKey(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME as name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1",
            [$table, $column]
        );
        return $row->name ?? null;
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }
};
