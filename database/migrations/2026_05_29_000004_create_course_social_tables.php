<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses module (alpha) — instructor capability + recognition/social tables.
 * course_instructors (Phase 1 authoring), plus certificates, reviews,
 * discussions, and group links used across Phases 2–3. Laid down now to avoid
 * re-migration churn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_instructors')) {
            Schema::create('course_instructors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('granted_by')->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id'], 'crs_inst_unique');
            });
        }

        if (!Schema::hasTable('course_certificates')) {
            Schema::create('course_certificates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('user_id');
                $table->string('serial', 64);
                $table->string('pdf_path', 500)->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'serial'], 'crs_cert_serial_unique');
                $table->index(['tenant_id', 'user_id'], 'crs_cert_tenant_user_idx');
                $table->index(['tenant_id', 'course_id'], 'crs_cert_tenant_course_idx');
            });
        }

        if (!Schema::hasTable('course_reviews')) {
            Schema::create('course_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedTinyInteger('rating');
                $table->text('body')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
                $table->timestamps();

                $table->unique(['tenant_id', 'course_id', 'user_id'], 'crs_rev_unique');
                $table->index(['tenant_id', 'course_id', 'status'], 'crs_rev_tenant_course_status_idx');
            });
        }

        if (!Schema::hasTable('course_discussions')) {
            Schema::create('course_discussions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('lesson_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('body');
                $table->enum('status', ['visible', 'hidden', 'flagged'])->default('visible');
                $table->timestamps();

                $table->index(['tenant_id', 'course_id'], 'crs_disc_tenant_course_idx');
                $table->index(['tenant_id', 'lesson_id'], 'crs_disc_tenant_lesson_idx');
                $table->index(['tenant_id', 'parent_id'], 'crs_disc_tenant_parent_idx');
            });
        }

        if (!Schema::hasTable('course_group_links')) {
            Schema::create('course_group_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('group_id');
                $table->timestamps();

                $table->unique(['tenant_id', 'course_id', 'group_id'], 'crs_gl_unique');
                $table->index(['tenant_id', 'group_id'], 'crs_gl_tenant_group_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_group_links');
        Schema::dropIfExists('course_discussions');
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('course_certificates');
        Schema::dropIfExists('course_instructors');
    }
};
