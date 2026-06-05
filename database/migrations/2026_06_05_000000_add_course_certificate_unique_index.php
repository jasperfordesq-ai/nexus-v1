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
 * Add a UNIQUE (tenant_id, course_id, user_id) guard to course_certificates.
 *
 * Two completion code paths (the course-completed observer and the /certificate
 * endpoint) could previously race a check-then-create and mint two certificates
 * for the same learner+course. This adds the missing unique index so the database
 * rejects the duplicate; the service catches the violation and returns the winner.
 *
 * De-duplicates any pre-existing rows (keeping the lowest id) before adding the
 * index. Idempotent and fully guarded — safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_certificates')) {
            return;
        }
        foreach (['tenant_id', 'course_id', 'user_id'] as $col) {
            if (!Schema::hasColumn('course_certificates', $col)) {
                return;
            }
        }
        if ($this->indexExists('course_certificates', 'crs_cert_course_user_unique')) {
            return;
        }

        // Remove duplicate certificates (same tenant+course+user), keeping the lowest id.
        DB::statement(
            'DELETE c1 FROM `course_certificates` c1
             JOIN `course_certificates` c2
               ON c1.tenant_id = c2.tenant_id
              AND c1.course_id = c2.course_id
              AND c1.user_id   = c2.user_id
              AND c1.id > c2.id'
        );

        try {
            DB::statement(
                'ALTER TABLE `course_certificates`
                 ADD UNIQUE INDEX `crs_cert_course_user_unique` (`tenant_id`, `course_id`, `user_id`)'
            );
        } catch (\Throwable $e) {
            Log::warning('add_course_certificate_unique: could not add unique index: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_certificates')
            && $this->indexExists('course_certificates', 'crs_cert_course_user_unique')) {
            DB::statement('ALTER TABLE `course_certificates` DROP INDEX `crs_cert_course_user_unique`');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]));
    }
};
