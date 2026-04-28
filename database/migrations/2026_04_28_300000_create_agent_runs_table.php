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
        if (Schema::hasTable('agent_runs')) {
            return;
        }

        Schema::create('agent_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->index();
            $table->enum('agent_type', [
                'tandem_matching',
                'help_routing',
                'activity_summary',
                'demand_forecast',
                'nudge_dispatch',
                'member_welcome',
            ])->index();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->enum('triggered_by', ['schedule', 'admin', 'manual'])->default('schedule');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->json('input_context')->nullable();
            $table->text('output_summary')->nullable();
            $table->unsignedInteger('proposals_generated')->default(0);
            $table->unsignedInteger('proposals_applied')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'agent_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
