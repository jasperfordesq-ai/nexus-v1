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
 * AG61 — KI-Agenten: agent_definitions table.
 *
 * Per-tenant registry of available autonomous agents. The DispatchKiAgents
 * runner / new AgentRunner consults this table to know which agents are
 * enabled, their schedule, and their LLM prompt overrides.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('agent_definitions')) {
            return;
        }

        Schema::create('agent_definitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('slug', 100);
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->enum('agent_type', [
                'matchmaker',
                'nudge_drafter',
                'coordinator_router',
                'activity_summariser',
            ]);
            $table->json('config')->nullable(); // schedule cron, prompt template overrides, thresholds
            $table->boolean('is_enabled')->default(false);
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_definitions');
    }
};
