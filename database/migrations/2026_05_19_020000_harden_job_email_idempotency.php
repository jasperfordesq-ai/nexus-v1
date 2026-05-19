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
        if (Schema::hasTable('job_interviews')) {
            Schema::table('job_interviews', function (Blueprint $table): void {
                if (!Schema::hasColumn('job_interviews', 'reminder_24h_sent_at')) {
                    $table->timestamp('reminder_24h_sent_at')->nullable()->after('reminder_sent_at');
                }
                if (!Schema::hasColumn('job_interviews', 'reminder_1h_sent_at')) {
                    $table->timestamp('reminder_1h_sent_at')->nullable()->after('reminder_24h_sent_at');
                }
            });
        }

        if (Schema::hasTable('job_expiry_notifications')) {
            $this->addIndexIfMissing(
                'job_expiry_notifications',
                'uk_job_expiry_notification_type',
                'CREATE UNIQUE INDEX `uk_job_expiry_notification_type` ON `job_expiry_notifications` (`tenant_id`, `vacancy_id`, `notification_type`)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('job_expiry_notifications')) {
            $this->dropIndexIfExists('job_expiry_notifications', 'uk_job_expiry_notification_type');
        }

        if (Schema::hasTable('job_interviews')) {
            Schema::table('job_interviews', function (Blueprint $table): void {
                if (Schema::hasColumn('job_interviews', 'reminder_1h_sent_at')) {
                    $table->dropColumn('reminder_1h_sent_at');
                }
                if (Schema::hasColumn('job_interviews', 'reminder_24h_sent_at')) {
                    $table->dropColumn('reminder_24h_sent_at');
                }
            });
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) === 0) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $database = DB::connection()->getDatabaseName();
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        }
    }
};
