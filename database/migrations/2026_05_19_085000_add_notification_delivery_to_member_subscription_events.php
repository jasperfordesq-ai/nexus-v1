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
        if (!Schema::hasTable('member_subscription_events')) {
            return;
        }

        Schema::table('member_subscription_events', function (Blueprint $table): void {
            if (!Schema::hasColumn('member_subscription_events', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable()->after('payload');
            }
            if (!Schema::hasColumn('member_subscription_events', 'notification_failed_at')) {
                $table->timestamp('notification_failed_at')->nullable()->after('notification_sent_at');
            }
            if (!Schema::hasColumn('member_subscription_events', 'notification_last_error')) {
                $table->text('notification_last_error')->nullable()->after('notification_failed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('member_subscription_events')) {
            return;
        }

        Schema::table('member_subscription_events', function (Blueprint $table): void {
            foreach (['notification_last_error', 'notification_failed_at', 'notification_sent_at'] as $column) {
                if (Schema::hasColumn('member_subscription_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
