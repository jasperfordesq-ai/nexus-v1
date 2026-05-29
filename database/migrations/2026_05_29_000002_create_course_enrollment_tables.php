<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses module (alpha) — Phase 1 enrollment & progress.
 * course_cohorts, course_enrollments, course_lesson_progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_cohorts')) {
            Schema::create('course_cohorts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->string('name', 200);
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->integer('capacity')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'course_id'], 'crs_coh_tenant_course_idx');
            });
        }

        if (!Schema::hasTable('course_enrollments')) {
            Schema::create('course_enrollments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('cohort_id')->nullable();
                $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
                $table->decimal('progress_percent', 5, 2)->default(0);
                $table->decimal('credits_paid', 8, 2)->default(0);
                $table->decimal('credits_earned', 8, 2)->default(0);
                $table->timestamp('enrolled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamps();

                // A user enrolls in a given course at most once per tenant.
                $table->unique(['tenant_id', 'course_id', 'user_id'], 'crs_enr_unique');
                $table->index(['tenant_id', 'user_id', 'status'], 'crs_enr_tenant_user_status_idx');
                $table->index(['tenant_id', 'course_id', 'status'], 'crs_enr_tenant_course_status_idx');
            });
        }

        if (!Schema::hasTable('course_lesson_progress')) {
            Schema::create('course_lesson_progress', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('enrollment_id');
                $table->unsignedBigInteger('lesson_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('in_progress');
                $table->integer('watch_percent')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'enrollment_id', 'lesson_id'], 'crs_lp_unique');
                $table->index(['tenant_id', 'lesson_id'], 'crs_lp_tenant_lesson_idx');
                $table->index(['tenant_id', 'user_id'], 'crs_lp_tenant_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson_progress');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_cohorts');
    }
};
