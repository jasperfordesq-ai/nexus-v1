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
        if (!Schema::hasTable('notification_queue')) {
            return;
        }

        if (!Schema::hasColumn('notification_queue', 'event_delivery_id')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->unsignedBigInteger('event_delivery_id')->nullable()->after('id');
                $table->index('event_delivery_id', 'idx_notification_queue_event_delivery');
            });
        }

        if (!Schema::hasColumn('notification_queue', 'idempotency_key')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->string('idempotency_key', 191)->nullable()->after('event_delivery_id');
            });
        }

        if (!Schema::hasIndex('notification_queue', 'uq_notification_queue_tenant_key')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'idempotency_key'], 'uq_notification_queue_tenant_key');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_queue')) {
            return;
        }

        if (Schema::hasIndex('notification_queue', 'uq_notification_queue_tenant_key')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->dropUnique('uq_notification_queue_tenant_key');
            });
        }

        if (Schema::hasColumn('notification_queue', 'event_delivery_id')) {
            $hasDeliveryIndex = Schema::hasIndex('notification_queue', 'idx_notification_queue_event_delivery');
            Schema::table('notification_queue', function (Blueprint $table) use ($hasDeliveryIndex): void {
                if ($hasDeliveryIndex) {
                    $table->dropIndex('idx_notification_queue_event_delivery');
                }
                $table->dropColumn('event_delivery_id');
            });
        }

        if (Schema::hasColumn('notification_queue', 'idempotency_key')) {
            Schema::table('notification_queue', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
