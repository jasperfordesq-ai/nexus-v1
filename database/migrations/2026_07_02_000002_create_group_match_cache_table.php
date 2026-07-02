<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart Matching v2 — dedicated cache for group matches.
 *
 * Group matches cannot live in match_cache (its listing_id is a NOT NULL FK
 * to listings). GroupMatchingService warms this table from
 * GroupRecommendationEngine in the same 30-min cron slot as match_cache;
 * dismissals set status='dismissed', which survives re-warms.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_match_cache')) {
            return;
        }

        Schema::create('group_match_cache', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('group_id');
            $table->decimal('match_score', 5, 2)->default(0);
            $table->json('score_breakdown')->nullable();
            $table->json('match_reasons')->nullable();
            $table->enum('status', ['new', 'viewed', 'joined', 'dismissed'])->default('new');
            $table->string('algorithm_version', 8)->default('v1');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['user_id', 'group_id', 'tenant_id'], 'uk_user_group_tenant');
            $table->index(['tenant_id', 'expires_at'], 'idx_tenant_expires');
            $table->index(['user_id', 'match_score'], 'idx_user_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_match_cache');
    }
};
