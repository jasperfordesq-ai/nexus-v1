<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Matching v2 — additive match_cache columns.
 *
 * score_breakdown: pillar/signal/adjustment JSON from MatchScorer (analytics +
 * the member-facing "why this score" panel). gate_flags: comma list of gate
 * states (remote_exempt, degraded_mode). explanation(+generated_at): cached
 * LLM natural-language rationale (AI tenants). algorithm_version: v1|v2 so
 * mixed-era cache rows remain distinguishable after deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('match_cache')) {
            return;
        }

        Schema::table('match_cache', function (Blueprint $table) {
            if (!Schema::hasColumn('match_cache', 'score_breakdown')) {
                $table->json('score_breakdown')->nullable()->after('match_reasons');
            }
            if (!Schema::hasColumn('match_cache', 'gate_flags')) {
                $table->string('gate_flags', 255)->nullable()->after('score_breakdown');
            }
            if (!Schema::hasColumn('match_cache', 'explanation')) {
                $table->text('explanation')->nullable()->after('gate_flags');
            }
            if (!Schema::hasColumn('match_cache', 'explanation_generated_at')) {
                $table->timestamp('explanation_generated_at')->nullable()->after('explanation');
            }
            if (!Schema::hasColumn('match_cache', 'algorithm_version')) {
                $table->string('algorithm_version', 8)->default('v1')->after('explanation_generated_at');
            }
        });

        // Index for analytics slicing by engine version (guarded — MariaDB has
        // no IF NOT EXISTS for indexes through the schema builder).
        try {
            $exists = DB::select(
                "SHOW INDEX FROM match_cache WHERE Key_name = 'idx_tenant_algo'"
            );
            if (empty($exists)) {
                Schema::table('match_cache', function (Blueprint $table) {
                    $table->index(['tenant_id', 'algorithm_version'], 'idx_tenant_algo');
                });
            }
        } catch (\Throwable $e) {
            // Non-fatal: index is an optimisation only.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('match_cache')) {
            return;
        }

        Schema::table('match_cache', function (Blueprint $table) {
            foreach (['score_breakdown', 'gate_flags', 'explanation', 'explanation_generated_at', 'algorithm_version'] as $column) {
                if (Schema::hasColumn('match_cache', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
