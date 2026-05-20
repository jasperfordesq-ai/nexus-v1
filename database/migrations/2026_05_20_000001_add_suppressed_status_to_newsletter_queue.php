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
        if (!Schema::hasTable('newsletter_queue') || !Schema::hasColumn('newsletter_queue', 'status')) {
            return;
        }

        $statusColumn = DB::selectOne(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'newsletter_queue'
               AND COLUMN_NAME = 'status'"
        );

        if ($statusColumn && str_contains((string) $statusColumn->COLUMN_TYPE, "'suppressed'")) {
            return;
        }

        DB::statement(
            "ALTER TABLE newsletter_queue
             MODIFY status ENUM('pending','processing','sent','failed','suppressed') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('newsletter_queue') || !Schema::hasColumn('newsletter_queue', 'status')) {
            return;
        }

        DB::table('newsletter_queue')
            ->where('status', 'suppressed')
            ->update(['status' => 'failed']);

        DB::statement(
            "ALTER TABLE newsletter_queue
             MODIFY status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'"
        );
    }
};
