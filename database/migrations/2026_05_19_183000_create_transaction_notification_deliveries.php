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
        if (Schema::hasTable('transaction_notification_deliveries')) {
            return;
        }

        Schema::create('transaction_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->string('event', 50);
            $table->string('channel', 20);
            $table->string('status', 20)->default('claimed');
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('claimed_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('evidence_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'transaction_id', 'event', 'user_id', 'channel'],
                'uk_transaction_notification_delivery'
            );
            $table->index(['tenant_id', 'status', 'claimed_at'], 'idx_transaction_notification_status');
            $table->index(['tenant_id', 'transaction_id', 'event'], 'idx_transaction_notification_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_notification_deliveries');
    }
};
