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
        if (!Schema::hasTable('verein_member_dues')) {
            return;
        }

        Schema::table('verein_member_dues', function (Blueprint $table): void {
            if (!Schema::hasColumn('verein_member_dues', 'reminder_email_failed_at')) {
                $table->timestamp('reminder_email_failed_at')->nullable()->after('last_reminder_at');
            }
            if (!Schema::hasColumn('verein_member_dues', 'reminder_email_last_error')) {
                $table->text('reminder_email_last_error')->nullable()->after('reminder_email_failed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('verein_member_dues')) {
            return;
        }

        Schema::table('verein_member_dues', function (Blueprint $table): void {
            foreach (['reminder_email_last_error', 'reminder_email_failed_at'] as $column) {
                if (Schema::hasColumn('verein_member_dues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
