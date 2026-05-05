<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenCaregiverLinks();
        $this->hardenSurveyResponses();
        $this->hardenCoverRequests();
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_cover_requests')) {
            $this->dropForeignIfExists('caring_cover_requests', 'ccr_support_relationship_id_foreign');
            $this->dropIndexIfExists('caring_cover_requests', 'idx_ccr_support_relationship');
        }

        if (Schema::hasTable('municipality_survey_responses')) {
            $this->dropIndexIfExists('municipality_survey_responses', 'msr_tenant_survey_user_unique');
            $this->dropIndexIfExists('municipality_survey_responses', 'msr_tenant_survey_session_unique');
        }

        if (Schema::hasTable('caring_caregiver_links')) {
            $this->dropIndexIfExists('caring_caregiver_links', 'ccl_tenant_caregiver_recipient_status_unique');

            if (Schema::hasColumn('caring_caregiver_links', 'status')) {
                DB::table('caring_caregiver_links')
                    ->where('status', 'pending')
                    ->update(['status' => 'inactive']);

                $this->safeStatement(
                    "ALTER TABLE caring_caregiver_links MODIFY status ENUM('active','inactive') NOT NULL DEFAULT 'active'",
                    'Could not restore caring_caregiver_links.status enum',
                );
            }
        }
    }

    private function hardenCaregiverLinks(): void
    {
        if (!Schema::hasTable('caring_caregiver_links') || !Schema::hasColumn('caring_caregiver_links', 'status')) {
            return;
        }

        $this->safeStatement(
            "ALTER TABLE caring_caregiver_links MODIFY status ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending'",
            'Could not expand caring_caregiver_links.status enum',
        );

        if ($this->indexExists('caring_caregiver_links', 'ccl_tenant_caregiver_recipient_status_unique')) {
            return;
        }

        $duplicates = DB::table('caring_caregiver_links')
            ->select('tenant_id', 'caregiver_id', 'cared_for_id', 'status', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('tenant_id', 'caregiver_id', 'cared_for_id', 'status')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            logger()->warning('Skipped caring_caregiver_links uniqueness guard because duplicate link rows already exist.');
            return;
        }

        $this->safeStatement(
            'ALTER TABLE caring_caregiver_links ADD UNIQUE KEY ccl_tenant_caregiver_recipient_status_unique (tenant_id, caregiver_id, cared_for_id, status)',
            'Could not add caring_caregiver_links uniqueness guard',
        );
    }

    private function hardenSurveyResponses(): void
    {
        if (!Schema::hasTable('municipality_survey_responses')) {
            return;
        }

        if (!$this->indexExists('municipality_survey_responses', 'msr_tenant_survey_user_unique')
            && !$this->hasSurveyResponseDuplicates('user_id')
        ) {
            $this->safeStatement(
                'ALTER TABLE municipality_survey_responses ADD UNIQUE KEY msr_tenant_survey_user_unique (tenant_id, survey_id, user_id)',
                'Could not add municipality_survey_responses user uniqueness guard',
            );
        }

        if (!$this->indexExists('municipality_survey_responses', 'msr_tenant_survey_session_unique')
            && !$this->hasSurveyResponseDuplicates('session_token')
        ) {
            $this->safeStatement(
                'ALTER TABLE municipality_survey_responses ADD UNIQUE KEY msr_tenant_survey_session_unique (tenant_id, survey_id, session_token)',
                'Could not add municipality_survey_responses session uniqueness guard',
            );
        }
    }

    private function hardenCoverRequests(): void
    {
        if (!Schema::hasTable('caring_cover_requests')
            || !Schema::hasTable('caring_support_relationships')
            || !Schema::hasColumn('caring_cover_requests', 'support_relationship_id')
        ) {
            return;
        }

        if (!$this->indexExists('caring_cover_requests', 'idx_ccr_support_relationship')) {
            $this->safeStatement(
                'ALTER TABLE caring_cover_requests ADD INDEX idx_ccr_support_relationship (support_relationship_id)',
                'Could not add caring_cover_requests support relationship index',
            );
        }

        if ($this->foreignKeyExists('caring_cover_requests', 'ccr_support_relationship_id_foreign')) {
            return;
        }

        $this->safeStatement(
            'UPDATE caring_cover_requests cr LEFT JOIN caring_support_relationships sr ON sr.id = cr.support_relationship_id SET cr.support_relationship_id = NULL WHERE cr.support_relationship_id IS NOT NULL AND sr.id IS NULL',
            'Could not clear orphaned caring_cover_requests.support_relationship_id values',
        );

        $this->safeStatement(
            'ALTER TABLE caring_cover_requests ADD CONSTRAINT ccr_support_relationship_id_foreign FOREIGN KEY (support_relationship_id) REFERENCES caring_support_relationships(id) ON DELETE SET NULL',
            'Could not add caring_cover_requests support relationship foreign key',
        );
    }

    private function hasSurveyResponseDuplicates(string $column): bool
    {
        $duplicates = DB::table('municipality_survey_responses')
            ->select('tenant_id', 'survey_id', $column, DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull($column)
            ->groupBy('tenant_id', 'survey_id', $column)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($duplicates) {
            logger()->warning("Skipped municipality_survey_responses {$column} uniqueness guard because duplicate responses already exist.");
        }

        return $duplicates;
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        );

        return $rows !== [];
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ? LIMIT 1',
            [$table, $constraint, 'FOREIGN KEY'],
        );

        return $rows !== [];
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!$this->indexExists($table, $index)) {
            return;
        }

        $this->safeStatement("ALTER TABLE {$table} DROP INDEX {$index}", "Could not drop {$table}.{$index}");
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (!$this->foreignKeyExists($table, $constraint)) {
            return;
        }

        $this->safeStatement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}", "Could not drop {$table}.{$constraint}");
    }

    private function safeStatement(string $sql, string $warning): void
    {
        try {
            DB::statement($sql);
        } catch (Throwable $e) {
            logger()->warning($warning . ': ' . $e->getMessage());
        }
    }
};
