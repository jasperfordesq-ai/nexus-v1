<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses module (alpha) — Phase 1 core structure.
 * courses → course_sections → course_lessons, plus course_categories.
 * Tenant-scoped; idempotent. No DB-level FKs (weak coupling for multi-tenancy,
 * matching the Marketplace module convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('course_categories')) {
            Schema::create('course_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->string('description', 500)->nullable();
                $table->string('icon', 60)->nullable();
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'slug'], 'crs_cat_tenant_slug_idx');
            });
        }

        if (!Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('author_user_id');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('title', 200);
                $table->string('slug', 220);
                $table->string('summary', 500)->nullable();
                $table->longText('description')->nullable();
                $table->string('cover_image', 500)->nullable();
                $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
                $table->enum('visibility', ['public', 'members', 'group'])->default('members');
                $table->enum('enrollment_type', ['self_paced', 'cohort'])->default('self_paced');
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
                $table->text('moderation_notes')->nullable();
                $table->unsignedBigInteger('moderated_by')->nullable();
                $table->timestamp('moderated_at')->nullable();
                // Pricing / credits (Phase 3 wires the economy; columns land now to avoid re-migration churn)
                $table->decimal('credit_cost', 8, 2)->default(0);
                $table->decimal('learner_credit_reward', 8, 2)->default(0);
                $table->decimal('instructor_credit_reward', 8, 2)->default(0);
                // Prerequisites: JSON array of course ids
                $table->json('prerequisites')->nullable();
                $table->integer('enrollment_count')->default(0);
                $table->integer('completion_count')->default(0);
                $table->decimal('rating_avg', 3, 2)->default(0);
                $table->integer('rating_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'crs_tenant_status_idx');
                $table->index(['tenant_id', 'category_id'], 'crs_tenant_category_idx');
                $table->index(['tenant_id', 'author_user_id'], 'crs_tenant_author_idx');
                $table->index(['tenant_id', 'slug'], 'crs_tenant_slug_idx');
                $table->fullText(['title', 'summary', 'description'], 'crs_title_desc_ft');
            });
        }

        if (!Schema::hasTable('course_sections')) {
            Schema::create('course_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->string('title', 200);
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'course_id'], 'crs_sec_tenant_course_idx');
            });
        }

        if (!Schema::hasTable('course_lessons')) {
            Schema::create('course_lessons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('section_id')->nullable();
                $table->string('title', 200);
                $table->enum('content_type', ['video', 'text', 'pdf', 'embed', 'quiz'])->default('text');
                $table->longText('body')->nullable();
                $table->string('video_url', 1000)->nullable();
                $table->string('attachment_url', 1000)->nullable();
                $table->string('embed_url', 1000)->nullable();
                $table->integer('position')->default(0);
                $table->integer('min_watch_percent')->default(0);
                // Drip scheduling (Phase 2 enforces; columns land now)
                $table->enum('drip_type', ['none', 'days_after_enroll', 'fixed_date'])->default('none');
                $table->integer('drip_offset_days')->nullable();
                $table->timestamp('drip_date')->nullable();
                $table->boolean('is_preview')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'course_id'], 'crs_les_tenant_course_idx');
                $table->index(['tenant_id', 'section_id'], 'crs_les_tenant_section_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_categories');
    }
};
