<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG70 — Emergency/Safety Alert Tier
 *
 * Creates the caring_emergency_alerts table for tenant-scoped safety broadcasts
 * that bypass quiet-hour preferences and use high-priority FCM delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caring_emergency_alerts')) {
            return;
        }

        Schema::create('caring_emergency_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id')->index();
            $table->string('title');
            $table->text('body');
            $table->enum('severity', ['info', 'warning', 'danger'])->default('warning');
            // {"type": "radius", "lat": float, "lng": float, "radius_km": float} or null = whole tenant
            $table->json('geographic_scope')->nullable();
            // if non-null, only send/show to these user IDs
            $table->json('target_user_ids')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('dismissed_count')->default(0);
            $table->boolean('push_sent')->default(false);
            $table->json('push_result')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_emergency_alerts');
    }
};
