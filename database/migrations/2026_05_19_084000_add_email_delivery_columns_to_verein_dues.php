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
            if (!Schema::hasColumn('verein_member_dues', 'generated_email_sent_at')) {
                $table->timestamp('generated_email_sent_at')->nullable()->after('last_reminder_at');
            }
            if (!Schema::hasColumn('verein_member_dues', 'generated_email_failed_at')) {
                $table->timestamp('generated_email_failed_at')->nullable()->after('generated_email_sent_at');
            }
            if (!Schema::hasColumn('verein_member_dues', 'paid_email_sent_at')) {
                $table->timestamp('paid_email_sent_at')->nullable()->after('generated_email_failed_at');
            }
            if (!Schema::hasColumn('verein_member_dues', 'paid_email_failed_at')) {
                $table->timestamp('paid_email_failed_at')->nullable()->after('paid_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('verein_member_dues')) {
            return;
        }

        Schema::table('verein_member_dues', function (Blueprint $table): void {
            foreach ([
                'paid_email_failed_at',
                'paid_email_sent_at',
                'generated_email_failed_at',
                'generated_email_sent_at',
            ] as $column) {
                if (Schema::hasColumn('verein_member_dues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
