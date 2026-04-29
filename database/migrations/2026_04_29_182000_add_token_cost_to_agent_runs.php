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
 * AG61 — KI-Agenten: add LLM cost tracking columns to agent_runs.
 *
 * Each agent_run now records input/output token counts and an estimated
 * cost in cents so admins can monitor LLM spend.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('agent_runs')) {
            return;
        }

        Schema::table('agent_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_runs', 'agent_definition_id')) {
                $table->unsignedBigInteger('agent_definition_id')->nullable()->after('agent_type')->index();
            }
            if (!Schema::hasColumn('agent_runs', 'llm_input_tokens')) {
                $table->unsignedInteger('llm_input_tokens')->default(0)->after('proposals_applied');
            }
            if (!Schema::hasColumn('agent_runs', 'llm_output_tokens')) {
                $table->unsignedInteger('llm_output_tokens')->default(0)->after('llm_input_tokens');
            }
            if (!Schema::hasColumn('agent_runs', 'cost_cents')) {
                $table->unsignedInteger('cost_cents')->default(0)->after('llm_output_tokens');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('agent_runs')) {
            return;
        }

        Schema::table('agent_runs', function (Blueprint $table) {
            foreach (['agent_definition_id', 'llm_input_tokens', 'llm_output_tokens', 'cost_cents'] as $col) {
                if (Schema::hasColumn('agent_runs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
