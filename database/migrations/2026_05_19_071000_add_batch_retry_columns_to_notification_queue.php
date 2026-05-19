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
        if (!Schema::hasTable('notification_queue')) {
            return;
        }

        Schema::table('notification_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_queue', 'processing_batch_id')) {
                $table->char('processing_batch_id', 36)->nullable()->after('status');
            }
            if (!Schema::hasColumn('notification_queue', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('processing_batch_id');
            }
            if (!Schema::hasColumn('notification_queue', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('processing_started_at');
            }
            if (!Schema::hasColumn('notification_queue', 'last_attempted_at')) {
                $table->timestamp('last_attempted_at')->nullable()->after('attempts');
            }
            if (!Schema::hasColumn('notification_queue', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_attempted_at');
            }
        });

        $this->createIndexIfMissing(
            'notification_queue',
            'notification_queue_frequency_status_batch_idx',
            'CREATE INDEX notification_queue_frequency_status_batch_idx ON notification_queue (frequency, status, processing_batch_id)'
        );
        $this->createIndexIfMissing(
            'notification_queue',
            'notification_queue_retry_idx',
            'CREATE INDEX notification_queue_retry_idx ON notification_queue (frequency, status, last_attempted_at, attempts)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_queue')) {
            return;
        }

        $this->dropIndexIfExists('notification_queue', 'notification_queue_retry_idx');
        $this->dropIndexIfExists('notification_queue', 'notification_queue_frequency_status_batch_idx');

        Schema::table('notification_queue', function (Blueprint $table) {
            foreach (['last_error', 'last_attempted_at', 'attempts', 'processing_started_at', 'processing_batch_id'] as $column) {
                if (Schema::hasColumn('notification_queue', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function createIndexIfMissing(string $table, string $index, string $sql): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if (!$exists) {
            DB::statement($sql);
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if ($exists) {
            DB::statement("DROP INDEX {$index} ON {$table}");
        }
    }
};
