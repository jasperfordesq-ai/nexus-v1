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
        if (!Schema::hasTable('event_reminder_delivery_claims')) {
            Schema::create('event_reminder_delivery_claims', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('event_id');
                $table->unsignedInteger('user_id');
                $table->string('reminder_type', 20);
                $table->string('status', 20)->default('claimed');
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'event_id', 'user_id', 'reminder_type'],
                    'uk_event_reminder_delivery_claim'
                );
                $table->index(['tenant_id', 'status', 'claimed_at'], 'idx_event_reminder_claim_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_delivery_claims');
    }
};
