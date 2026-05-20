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
        if (Schema::hasTable('marketplace_seller_ratings')) {
            Schema::table('marketplace_seller_ratings', function (Blueprint $table): void {
                if (!Schema::hasColumn('marketplace_seller_ratings', 'notification_email_sent_at')) {
                    $table->timestamp('notification_email_sent_at')->nullable()->after('is_anonymous');
                }
                if (!Schema::hasColumn('marketplace_seller_ratings', 'notification_email_failed_at')) {
                    $table->timestamp('notification_email_failed_at')->nullable()->after('notification_email_sent_at');
                }
                if (!Schema::hasColumn('marketplace_seller_ratings', 'notification_email_last_error')) {
                    $table->text('notification_email_last_error')->nullable()->after('notification_email_failed_at');
                }
            });
        }

        if (Schema::hasTable('marketplace_disputes')) {
            Schema::table('marketplace_disputes', function (Blueprint $table): void {
                if (!Schema::hasColumn('marketplace_disputes', 'notification_email_sent_at')) {
                    $table->timestamp('notification_email_sent_at')->nullable()->after('refund_amount');
                }
                if (!Schema::hasColumn('marketplace_disputes', 'notification_email_failed_at')) {
                    $table->timestamp('notification_email_failed_at')->nullable()->after('notification_email_sent_at');
                }
                if (!Schema::hasColumn('marketplace_disputes', 'notification_email_last_error')) {
                    $table->text('notification_email_last_error')->nullable()->after('notification_email_failed_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'marketplace_seller_ratings' => ['notification_email_last_error', 'notification_email_failed_at', 'notification_email_sent_at'],
            'marketplace_disputes' => ['notification_email_last_error', 'notification_email_failed_at', 'notification_email_sent_at'],
        ] as $tableName => $columns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
