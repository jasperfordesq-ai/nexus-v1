<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        Schema::table('caring_hour_transfers', function (Blueprint $table): void {
            if (! Schema::hasColumn('caring_hour_transfers', 'remote_delivery_status')) {
                $table->string('remote_delivery_status', 32)->nullable()->after('is_remote');
            }
            if (! Schema::hasColumn('caring_hour_transfers', 'remote_delivery_attempts')) {
                $table->unsignedSmallInteger('remote_delivery_attempts')->default(0)->after('remote_delivery_status');
            }
            if (! Schema::hasColumn('caring_hour_transfers', 'remote_delivery_last_error')) {
                $table->text('remote_delivery_last_error')->nullable()->after('remote_delivery_attempts');
            }
            if (! Schema::hasColumn('caring_hour_transfers', 'remote_delivery_next_retry_at')) {
                $table->timestamp('remote_delivery_next_retry_at')->nullable()->after('remote_delivery_last_error');
            }
            if (! Schema::hasColumn('caring_hour_transfers', 'remote_delivered_at')) {
                $table->timestamp('remote_delivered_at')->nullable()->after('remote_delivery_next_retry_at');
            }
        });

        Schema::table('caring_hour_transfers', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'role', 'is_remote', 'status', 'remote_delivery_next_retry_at'],
                'idx_caring_hour_remote_outbox_due'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }

        Schema::table('caring_hour_transfers', function (Blueprint $table): void {
            $table->dropIndex('idx_caring_hour_remote_outbox_due');
        });

        Schema::table('caring_hour_transfers', function (Blueprint $table): void {
            foreach ([
                'remote_delivered_at',
                'remote_delivery_next_retry_at',
                'remote_delivery_last_error',
                'remote_delivery_attempts',
                'remote_delivery_status',
            ] as $column) {
                if (Schema::hasColumn('caring_hour_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
