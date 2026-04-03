<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the health_check_history table for storing periodic health check
 * results per tenant, including individual check outcomes and latency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('health_check_history')) {
            return;
        }

        Schema::create('health_check_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->enum('status', ['healthy', 'degraded', 'unhealthy']);
            $table->json('checks');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_check_history');
    }
};
