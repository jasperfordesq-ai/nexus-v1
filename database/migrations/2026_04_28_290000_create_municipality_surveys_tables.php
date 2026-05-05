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
 * AG62 — Municipality Survey & Feedback Tool
 *
 * Creates three tables:
 *   municipality_surveys          — survey headers (one per Gemeinde survey)
 *   municipality_survey_questions — ordered questions for each survey
 *   municipality_survey_responses — member responses (anonymous-safe)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── municipality_surveys ─────────────────────────────────────────────
        if (! Schema::hasTable('municipality_surveys')) {
            Schema::create('municipality_surveys', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('created_by');   // user_id of creator
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                // if 1, response user_ids are NOT stored
                $table->tinyInteger('is_anonymous')->default(0);
                // {"radius_km": null, "member_tier_min": null}
                $table->json('target_audience')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                // cached count — incremented atomically on each submitted response
                $table->unsignedInteger('response_count')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // ── municipality_survey_questions ────────────────────────────────────
        if (! Schema::hasTable('municipality_survey_questions')) {
            Schema::create('municipality_survey_questions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('survey_id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('question_text', 500);
                $table->enum('question_type', [
                    'single_choice',
                    'multi_choice',
                    'likert',
                    'open_text',
                    'yes_no',
                ]);
                // for single_choice/multi_choice/yes_no: array of option strings
                // for likert: ["Sehr unzufrieden","Eher unzufrieden","Neutral","Eher zufrieden","Sehr zufrieden"]
                $table->json('options')->nullable();
                $table->tinyInteger('is_required')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('survey_id')
                    ->references('id')
                    ->on('municipality_surveys')
                    ->cascadeOnDelete();

                $table->index(['survey_id', 'sort_order']);
            });
        }

        // ── municipality_survey_responses ────────────────────────────────────
        if (! Schema::hasTable('municipality_survey_responses')) {
            Schema::create('municipality_survey_responses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('survey_id');
                $table->unsignedBigInteger('tenant_id');
                // NULL when is_anonymous = 1
                $table->unsignedBigInteger('user_id')->nullable();
                // sha256(user_id + survey_id + date) for anonymous dedup
                $table->string('session_token', 64)->nullable();
                // {"question_id": "answer_value_or_array"}
                $table->json('answers');
                $table->dateTime('submitted_at');
                // sha256 of submitter IP — stored for anti-spam only, never exposed
                $table->string('ip_hash', 64)->nullable();

                $table->foreign('survey_id')
                    ->references('id')
                    ->on('municipality_surveys')
                    ->cascadeOnDelete();

                $table->unique(['tenant_id', 'survey_id', 'user_id'], 'msr_tenant_survey_user_unique');
                $table->unique(['tenant_id', 'survey_id', 'session_token'], 'msr_tenant_survey_session_unique');
                $table->index(['tenant_id', 'submitted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_survey_responses');
        Schema::dropIfExists('municipality_survey_questions');
        Schema::dropIfExists('municipality_surveys');
    }
};
