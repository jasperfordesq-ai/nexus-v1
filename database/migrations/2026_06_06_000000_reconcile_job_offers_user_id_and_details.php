<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcile job_offers schema with the code that uses it.
 *
 * The JobOffer model ($fillable) and JobOfferService::create() write `user_id`
 * (the candidate the offer is for) and `details` (the offer text), and
 * JobGdprService queries job_offers.user_id. But the table was created by legacy
 * SQL (migrations/2026_03_22_add_job_offers.sql) with only `message` and no
 * `user_id`, so every offer insert throws "unknown column", is swallowed by the
 * service's try/catch, and returns false — i.e. offers cannot be created.
 *
 * This migration is ADDITIVE and idempotent:
 *   - adds `user_id` (nullable, indexed) and `details` (nullable text)
 *   - backfills user_id from the offer's application (the applicant)
 *   - backfills details from the legacy `message` column
 *
 * It intentionally does NOT add a hard FK on user_id (users.id column type is
 * environment-dependent — see reference_jobs_schema_drift) or drop the legacy
 * `message` column. Referential integrity for user_id is enforced at the app layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_offers')) {
            return;
        }

        Schema::table('job_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('job_offers', 'user_id')) {
                // INT UNSIGNED to match tenant_id in this table and the
                // unsignedInteger() convention used by the other job migrations.
                $table->unsignedInteger('user_id')->nullable()->after('application_id');
                $table->index('user_id', 'idx_jo_user');
            }

            if (! Schema::hasColumn('job_offers', 'details')) {
                $table->text('details')->nullable()->after('message');
            }
        });

        // Backfill user_id from each offer's application (the candidate it is for).
        // application_id references job_vacancy_applications(id) per the table's FK.
        if (Schema::hasColumn('job_offers', 'user_id') && Schema::hasTable('job_vacancy_applications')) {
            DB::statement(
                'UPDATE job_offers jo
                 JOIN job_vacancy_applications a ON a.id = jo.application_id
                 SET jo.user_id = a.user_id
                 WHERE jo.user_id IS NULL'
            );
        }

        // Backfill details from the legacy `message` column (historical offer text).
        if (Schema::hasColumn('job_offers', 'details') && Schema::hasColumn('job_offers', 'message')) {
            DB::statement(
                "UPDATE job_offers
                 SET details = message
                 WHERE details IS NULL AND message IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('job_offers')) {
            return;
        }

        Schema::table('job_offers', function (Blueprint $table) {
            if (Schema::hasColumn('job_offers', 'user_id')) {
                $table->dropIndex('idx_jo_user');
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('job_offers', 'details')) {
                $table->dropColumn('details');
            }
        });
    }
};
