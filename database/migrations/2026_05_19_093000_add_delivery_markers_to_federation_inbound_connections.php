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
        if (!Schema::hasTable('federation_inbound_connections')) {
            return;
        }

        Schema::table('federation_inbound_connections', function (Blueprint $table): void {
            if (!Schema::hasColumn('federation_inbound_connections', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable()->after('message');
            }
            if (!Schema::hasColumn('federation_inbound_connections', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('notification_sent_at');
            }
            if (!Schema::hasColumn('federation_inbound_connections', 'email_failed_at')) {
                $table->timestamp('email_failed_at')->nullable()->after('email_sent_at');
            }
            if (!Schema::hasColumn('federation_inbound_connections', 'email_last_error')) {
                $table->text('email_last_error')->nullable()->after('email_failed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('federation_inbound_connections')) {
            return;
        }

        Schema::table('federation_inbound_connections', function (Blueprint $table): void {
            foreach (['email_last_error', 'email_failed_at', 'email_sent_at', 'notification_sent_at'] as $column) {
                if (Schema::hasColumn('federation_inbound_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
