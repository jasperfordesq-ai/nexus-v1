<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * push_log — delivery observability for device push (web + FCM).
 *
 * Mirrors the role email_log plays for email: one row per fanOutPush() that
 * actually attempted or achieved a delivery, recording per-channel outcome so
 * "did this push reach the user?" is answerable. Rows are only written when
 * something was sent or genuinely failed — pure "user has no device / push
 * disabled" cases are not logged (they are not delivery events). Best-effort:
 * NotificationDispatcher writes here inside its afterResponse send and never
 * lets a logging failure affect push or the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_log')) {
            return;
        }

        Schema::create('push_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('activity_type', 64)->nullable()
                ->comment('Notification type that triggered the push (drives the title).');
            $table->string('title', 255)->nullable();

            // Web push (browser) — service returns only a bool, so we record
            // true (delivered to >=1 subscription) / false (send failed) / null
            // (not attempted). It cannot distinguish "no subscription" from a
            // genuine failure, hence the coarser signal than FCM below.
            $table->boolean('web_ok')->nullable();

            // FCM (mobile) — service returns precise per-token counts.
            $table->unsignedInteger('fcm_sent')->default(0);
            $table->unsignedInteger('fcm_failed')->default(0);

            // Overall coarse outcome: delivered | partial | failed | no_targets.
            $table->string('status', 16)->default('delivered');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at'], 'push_log_tenant_created_idx');
            $table->index(['tenant_id', 'status'], 'push_log_tenant_status_idx');
            $table->index('activity_type', 'push_log_activity_type_idx');
            $table->index('user_id', 'push_log_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_log');
    }
};
