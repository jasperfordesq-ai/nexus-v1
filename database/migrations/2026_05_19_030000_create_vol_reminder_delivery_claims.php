<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_reminder_delivery_claims')) {
            Schema::create('vol_reminder_delivery_claims', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('reminder_type', 50);
                $table->unsignedInteger('reference_id')->nullable();
                $table->string('channel', 20);
                $table->string('status', 20)->default('claimed');
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'user_id', 'reminder_type', 'reference_id', 'channel'],
                    'uk_vol_reminder_delivery_claim'
                );
                $table->index(['tenant_id', 'status', 'claimed_at'], 'idx_vol_reminder_claim_status');
            });
        }

        if (Schema::hasTable('vol_reminders_sent')) {
            DB::statement(
                'DELETE newer FROM vol_reminders_sent newer
                 INNER JOIN vol_reminders_sent older
                   ON newer.tenant_id = older.tenant_id
                  AND newer.user_id = older.user_id
                  AND newer.reminder_type = older.reminder_type
                  AND newer.reference_id <=> older.reference_id
                  AND newer.channel = older.channel
                  AND newer.id > older.id'
            );

            Schema::table('vol_reminders_sent', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'user_id', 'reminder_type', 'reference_id', 'channel'],
                    'uk_vol_reminder_sent_delivery'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vol_reminders_sent')) {
            Schema::table('vol_reminders_sent', function (Blueprint $table): void {
                $table->dropUnique('uk_vol_reminder_sent_delivery');
            });
        }

        Schema::dropIfExists('vol_reminder_delivery_claims');
    }
};
