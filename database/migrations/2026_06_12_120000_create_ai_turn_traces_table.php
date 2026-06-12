<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ai_turn_traces was only ever shipped as a legacy SQL migration
 * (migrations/2026_05_11_create_ai_turn_traces.sql) which never auto-runs,
 * so the table never existed in production: per-turn traces were silently
 * dropped, POST /v2/ai/chat/feedback 500'd, and the admin AI-metrics page
 * 500'd. Recreated here as a Laravel migration so it applies at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_turn_traces')) {
            return;
        }

        Schema::create('ai_turn_traces', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('conversation_id')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->text('user_text');
            $table->mediumText('assistant_text')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->unsignedInteger('tokens_total')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('tool_calls')->nullable();
            $table->string('error', 255)->nullable();
            $table->enum('feedback', ['up', 'down'])->nullable();
            $table->string('feedback_note', 500)->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'idx_tenant_created');
            $table->index(['tenant_id', 'feedback'], 'idx_tenant_feedback');
            $table->index('message_id', 'idx_message');
            $table->index('conversation_id', 'idx_conversation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_turn_traces');
    }
};
