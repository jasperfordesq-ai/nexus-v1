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
        if (!Schema::hasTable('notification_queue') || Schema::hasColumn('notification_queue', 'tenant_id')) {
            return;
        }

        Schema::table('notification_queue', function (Blueprint $table): void {
            $table->integer('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'status', 'frequency'], 'notification_queue_tenant_status_frequency_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_queue') || !Schema::hasColumn('notification_queue', 'tenant_id')) {
            return;
        }

        Schema::table('notification_queue', function (Blueprint $table): void {
            $table->dropIndex('notification_queue_tenant_status_frequency_idx');
            $table->dropColumn('tenant_id');
        });
    }
};
