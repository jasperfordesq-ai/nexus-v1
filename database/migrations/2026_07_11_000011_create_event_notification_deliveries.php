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
        if (Schema::hasTable('event_notification_deliveries')) {
            return;
        }

        Schema::create('event_notification_deliveries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->unsignedBigInteger('outbox_id');
            $table->integer('recipient_user_id');
            $table->string('channel', 32);
            $table->string('delivery_key', 191);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->string('preference_reason', 100)->nullable();
            $table->string('suppression_reason', 191)->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_evidence_id', 255)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'delivery_key'], 'uq_event_delivery_tenant_key');
            $table->unique(['outbox_id', 'recipient_user_id', 'channel'], 'uq_event_delivery_recipient_channel');
            $table->index(['status', 'next_attempt_at', 'id'], 'idx_event_delivery_claim');
            $table->index(['tenant_id', 'recipient_user_id', 'status'], 'idx_event_delivery_recipient');
            $table->foreign('outbox_id', 'fk_event_delivery_outbox')
                ->references('id')
                ->on('event_domain_outbox')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_notification_deliveries');
    }
};
