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
        if (!Schema::hasTable('newsletter_queue')) {
            return;
        }

        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletter_queue', 'processing_batch_id')) {
                $table->char('processing_batch_id', 36)->nullable()->after('status');
            }
            if (!Schema::hasColumn('newsletter_queue', 'processing_started_at')) {
                $table->timestamp('processing_started_at')->nullable()->after('processing_batch_id');
            }
        });

        $this->createIndexIfMissing(
            'newsletter_queue',
            'idx_newsletter_queue_processing_batch',
            'CREATE INDEX idx_newsletter_queue_processing_batch ON newsletter_queue (newsletter_id, tenant_id, status, processing_batch_id)'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('newsletter_queue')) {
            return;
        }

        $this->dropIndexIfExists('newsletter_queue', 'idx_newsletter_queue_processing_batch');

        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (Schema::hasColumn('newsletter_queue', 'processing_started_at')) {
                $table->dropColumn('processing_started_at');
            }
            if (Schema::hasColumn('newsletter_queue', 'processing_batch_id')) {
                $table->dropColumn('processing_batch_id');
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
