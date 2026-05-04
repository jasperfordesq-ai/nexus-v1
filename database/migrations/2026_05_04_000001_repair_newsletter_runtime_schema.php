<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newsletter_segments')) {
            Schema::table('newsletter_segments', function (Blueprint $table) {
                if (!Schema::hasColumn('newsletter_segments', 'match_type')) {
                    $table->enum('match_type', ['all', 'any'])->default('all')->after('is_active');
                }

                if (!Schema::hasColumn('newsletter_segments', 'subscriber_count')) {
                    $table->unsignedInteger('subscriber_count')->default(0)->after('match_type');
                }
            });
        }

        if (!Schema::hasTable('newsletter_queue')) {
            return;
        }

        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletter_queue', 'tenant_id')) {
                $table->integer('tenant_id')->nullable()->after('id');
                $table->index('tenant_id', 'idx_newsletter_queue_tenant');
            }

            if (!Schema::hasColumn('newsletter_queue', 'subject_override')) {
                $table->string('subject_override', 255)->nullable()->after('ab_variant');
            }
        });

        if (Schema::hasTable('newsletters') && Schema::hasColumn('newsletter_queue', 'tenant_id')) {
            DB::statement(
                'UPDATE newsletter_queue q
                 INNER JOIN newsletters n ON n.id = q.newsletter_id
                 SET q.tenant_id = n.tenant_id
                 WHERE q.tenant_id IS NULL'
            );
        }

        if (Schema::hasColumn('newsletter_queue', 'status')) {
            $statusColumn = DB::selectOne(
                "SELECT COLUMN_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'newsletter_queue'
                   AND COLUMN_NAME = 'status'"
            );

            if ($statusColumn && !str_contains((string) $statusColumn->COLUMN_TYPE, "'processing'")) {
                DB::statement(
                    "ALTER TABLE newsletter_queue
                     MODIFY status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'"
                );
            }
        }

        $uniqueQueueEmail = collect(DB::select(
            "SHOW INDEX FROM newsletter_queue WHERE Key_name = 'unique_newsletter_email'"
        ))->isNotEmpty();

        if ($uniqueQueueEmail) {
            DB::statement('ALTER TABLE newsletter_queue DROP INDEX unique_newsletter_email');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('newsletter_queue') && Schema::hasColumn('newsletter_queue', 'status')) {
            DB::statement(
                "UPDATE newsletter_queue SET status = 'pending' WHERE status = 'processing'"
            );
            DB::statement(
                "ALTER TABLE newsletter_queue
                 MODIFY status ENUM('pending','sent','failed') DEFAULT 'pending'"
            );
        }

        if (Schema::hasTable('newsletter_queue') && Schema::hasColumn('newsletter_queue', 'tenant_id')) {
            Schema::table('newsletter_queue', function (Blueprint $table) {
                $table->dropIndex('idx_newsletter_queue_tenant');
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('newsletter_queue') && Schema::hasColumn('newsletter_queue', 'subject_override')) {
            Schema::table('newsletter_queue', function (Blueprint $table) {
                $table->dropColumn('subject_override');
            });
        }

        if (Schema::hasTable('newsletter_segments')) {
            Schema::table('newsletter_segments', function (Blueprint $table) {
                if (Schema::hasColumn('newsletter_segments', 'subscriber_count')) {
                    $table->dropColumn('subscriber_count');
                }
                if (Schema::hasColumn('newsletter_segments', 'match_type')) {
                    $table->dropColumn('match_type');
                }
            });
        }
    }
};
