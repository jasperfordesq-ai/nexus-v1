<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add tenant-scoped composite indexes to job_vacancy_applications.
 *
 * These indexes support the HasTenantScope global scope added to the
 * JobApplication model, ensuring tenant-filtered queries hit an index
 * instead of full-scanning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('job_vacancy_applications')) {
            return;
        }

        $existing = collect(Schema::getIndexes('job_vacancy_applications'))
            ->pluck('name')
            ->all();

        // (tenant_id, vacancy_id) — most common query pattern
        if (!in_array('idx_jva_tenant_vacancy', $existing, true)) {
            DB::statement('ALTER TABLE `job_vacancy_applications` ADD INDEX `idx_jva_tenant_vacancy` (`tenant_id`, `vacancy_id`)');
        }

        // (tenant_id, user_id) — "my applications" queries
        if (!in_array('idx_jva_tenant_user', $existing, true)) {
            DB::statement('ALTER TABLE `job_vacancy_applications` ADD INDEX `idx_jva_tenant_user` (`tenant_id`, `user_id`)');
        }

        // (tenant_id, status, created_at) — analytics/filtering
        if (!in_array('idx_jva_tenant_status_created', $existing, true)) {
            DB::statement('ALTER TABLE `job_vacancy_applications` ADD INDEX `idx_jva_tenant_status_created` (`tenant_id`, `status`, `created_at`)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('job_vacancy_applications')) {
            return;
        }

        $existing = collect(Schema::getIndexes('job_vacancy_applications'))
            ->pluck('name')
            ->all();

        foreach (['idx_jva_tenant_vacancy', 'idx_jva_tenant_user', 'idx_jva_tenant_status_created'] as $idx) {
            if (in_array($idx, $existing, true)) {
                DB::statement("ALTER TABLE `job_vacancy_applications` DROP INDEX `{$idx}`");
            }
        }
    }
};
