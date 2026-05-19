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
        if (! Schema::hasTable('event_reminder_sent') || ! Schema::hasColumn('event_reminder_sent', 'reminder_type')) {
            return;
        }

        DB::statement("ALTER TABLE event_reminder_sent MODIFY reminder_type ENUM('24h','1h','7d') NOT NULL DEFAULT '24h'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_reminder_sent') || ! Schema::hasColumn('event_reminder_sent', 'reminder_type')) {
            return;
        }

        DB::statement("ALTER TABLE event_reminder_sent MODIFY reminder_type ENUM('24h','1h') NOT NULL DEFAULT '24h'");
    }
};
