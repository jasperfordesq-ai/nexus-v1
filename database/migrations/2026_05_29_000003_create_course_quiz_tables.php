<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses module (alpha) — Phase 1 assessment.
 * course_quizzes, course_questions, course_quiz_attempts.
 * Phase 1 grades MCQ/multi/truefalse automatically; essay/short land as columns
 * now and are graded by instructors in Phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_quizzes')) {
            Schema::create('course_quizzes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('lesson_id')->nullable();
                $table->string('title', 200);
                $table->string('description', 500)->nullable();
                $table->integer('pass_mark_percent')->default(70);
                $table->integer('max_attempts')->default(0); // 0 = unlimited
                $table->integer('time_limit_minutes')->nullable();
                $table->boolean('shuffle_questions')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'course_id'], 'crs_quiz_tenant_course_idx');
                $table->index(['tenant_id', 'lesson_id'], 'crs_quiz_tenant_lesson_idx');
            });
        }

        if (!Schema::hasTable('course_questions')) {
            Schema::create('course_questions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->enum('type', ['mcq', 'multi', 'truefalse', 'short', 'essay'])->default('mcq');
                $table->text('prompt');
                $table->json('options')->nullable();   // [{id,label}] for mcq/multi
                $table->json('correct')->nullable();    // correct option id(s)
                $table->string('explanation', 1000)->nullable();
                $table->integer('points')->default(1);
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'quiz_id'], 'crs_q_tenant_quiz_idx');
            });
        }

        if (!Schema::hasTable('course_quiz_attempts')) {
            Schema::create('course_quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('quiz_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('enrollment_id')->nullable();
                $table->json('answers')->nullable();
                $table->decimal('score_percent', 5, 2)->default(0);
                $table->boolean('passed')->default(false);
                $table->enum('grading_status', ['auto', 'pending_review', 'graded'])->default('auto');
                $table->unsignedBigInteger('graded_by')->nullable();
                $table->text('feedback')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'quiz_id', 'user_id'], 'crs_qa_tenant_quiz_user_idx');
                $table->index(['tenant_id', 'grading_status'], 'crs_qa_tenant_grading_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_quiz_attempts');
        Schema::dropIfExists('course_questions');
        Schema::dropIfExists('course_quizzes');
    }
};
