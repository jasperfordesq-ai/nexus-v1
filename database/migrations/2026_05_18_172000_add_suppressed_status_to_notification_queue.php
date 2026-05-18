<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasColumn('notification_queue', 'status')) {
            return;
        }

        DB::statement("ALTER TABLE notification_queue MODIFY status ENUM('pending','processing','sent','failed','suppressed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasColumn('notification_queue', 'status')) {
            return;
        }

        DB::table('notification_queue')
            ->where('status', 'suppressed')
            ->update(['status' => 'failed']);

        DB::statement("ALTER TABLE notification_queue MODIFY status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'");
    }
};
