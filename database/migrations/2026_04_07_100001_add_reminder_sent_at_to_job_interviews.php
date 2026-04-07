<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_interviews') && !Schema::hasColumn('job_interviews', 'reminder_sent_at')) {
            Schema::table('job_interviews', function (Blueprint $table) {
                $table->timestamp('reminder_sent_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_interviews', 'reminder_sent_at')) {
            Schema::table('job_interviews', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }
    }
};
