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
        if (Schema::hasTable('marketplace_report_notifications')) {
            return;
        }

        Schema::create('marketplace_report_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('marketplace_report_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->string('event_type', 40);
            $table->string('channel', 20);
            $table->string('dedupe_key', 191);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'dedupe_key', 'channel'], 'mrn_tenant_dedupe_channel_unique');
            $table->index(['tenant_id', 'status', 'next_retry_at'], 'mrn_tenant_status_retry_idx');
            $table->index(['tenant_id', 'marketplace_report_id'], 'mrn_tenant_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_report_notifications');
    }
};
