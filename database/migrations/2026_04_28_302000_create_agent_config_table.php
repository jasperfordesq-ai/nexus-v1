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
        if (Schema::hasTable('agent_config')) {
            return;
        }

        Schema::create('agent_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->unique();
            $table->tinyInteger('enabled')->default(0);
            $table->decimal('auto_apply_threshold', 5, 4)->default('0.9000');
            $table->tinyInteger('tandem_matching_enabled')->default(1);
            $table->tinyInteger('nudge_dispatch_enabled')->default(1);
            $table->tinyInteger('activity_summary_enabled')->default(1);
            $table->tinyInteger('demand_forecast_enabled')->default(1);
            $table->tinyInteger('help_routing_enabled')->default(1);
            $table->tinyInteger('schedule_hour')->default(2);
            $table->unsignedInteger('max_proposals_per_run')->default(50);
            $table->string('notification_email', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_config');
    }
};
