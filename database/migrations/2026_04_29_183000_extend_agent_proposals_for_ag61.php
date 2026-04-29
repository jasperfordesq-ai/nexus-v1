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
 * AG61 — KI-Agenten: extend agent_proposals with reasoning + executed status.
 *
 * Adds:
 *   - reasoning TEXT — LLM chain-of-thought summary of why this proposal was made
 *   - agent_definition_id — link back to the definition that produced it
 *   - 'executed' enum value (added via raw SQL since enum changes can be DB-specific)
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('agent_proposals')) {
            return;
        }

        Schema::table('agent_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_proposals', 'reasoning')) {
                $table->text('reasoning')->nullable()->after('proposal_data');
            }
            if (!Schema::hasColumn('agent_proposals', 'agent_definition_id')) {
                $table->unsignedBigInteger('agent_definition_id')->nullable()->after('run_id')->index();
            }
            if (!Schema::hasColumn('agent_proposals', 'executed_at')) {
                $table->dateTime('executed_at')->nullable()->after('applied_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('agent_proposals')) {
            return;
        }

        Schema::table('agent_proposals', function (Blueprint $table) {
            foreach (['reasoning', 'agent_definition_id', 'executed_at'] as $col) {
                if (Schema::hasColumn('agent_proposals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
