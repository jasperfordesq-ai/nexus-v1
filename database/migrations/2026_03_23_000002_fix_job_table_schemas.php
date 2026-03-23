<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix table schemas and create missing tables for the advanced job platform features.
 *
 * Addresses mismatches between Eloquent models and the actual database schema.
 * All operations are idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // job_vacancy_applications — add missing columns
        // ============================================================
        if (Schema::hasTable('job_vacancy_applications')) {
            Schema::table('job_vacancy_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('job_vacancy_applications', 'tenant_id')) {
                    $table->unsignedInteger('tenant_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('job_vacancy_applications', 'cv_path')) {
                    $table->string('cv_path', 500)->nullable()->after('message');
                }
                if (! Schema::hasColumn('job_vacancy_applications', 'cv_filename')) {
                    $table->string('cv_filename', 255)->nullable()->after('cv_path');
                }
                if (! Schema::hasColumn('job_vacancy_applications', 'cv_size')) {
                    $table->integer('cv_size')->nullable()->after('cv_filename');
                }
            });
        }

        // ============================================================
        // job_interviews (model: JobInterview)
        // ============================================================
        if (! Schema::hasTable('job_interviews')) {
            Schema::create('job_interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('application_id')->nullable();
                $table->unsignedInteger('proposed_by')->nullable();
                $table->string('interview_type', 50)->default('video');
                $table->dateTime('scheduled_at')->nullable();
                $table->integer('duration_mins')->default(30);
                $table->text('location_notes')->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->text('candidate_notes')->nullable();
                $table->text('interviewer_notes')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
                $table->index(['tenant_id', 'application_id']);
            });
        }

        // ============================================================
        // job_interview_slots (model: JobInterviewSlot)
        // ============================================================
        if (! Schema::hasTable('job_interview_slots')) {
            Schema::create('job_interview_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('job_id');
                $table->unsignedInteger('employer_user_id');
                $table->dateTime('slot_start');
                $table->dateTime('slot_end');
                $table->boolean('is_booked')->default(false);
                $table->unsignedInteger('booked_by_user_id')->nullable();
                $table->dateTime('booked_at')->nullable();
                $table->string('interview_type', 50)->default('video');
                $table->string('meeting_link', 500)->nullable();
                $table->string('location', 500)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'job_id']);
            });
        }

        // ============================================================
        // job_vacancy_team (model: JobVacancyTeam)
        // ============================================================
        if (! Schema::hasTable('job_vacancy_team')) {
            Schema::create('job_vacancy_team', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('user_id');
                $table->string('role', 50)->default('viewer');
                $table->unsignedInteger('added_by')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_alerts (model: JobAlert)
        // ============================================================
        if (! Schema::hasTable('job_alerts')) {
            Schema::create('job_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('keywords', 500)->nullable();
                $table->string('categories', 500)->nullable();
                $table->string('type', 50)->nullable();
                $table->string('commitment', 50)->nullable();
                $table->string('location', 255)->nullable();
                $table->boolean('is_remote_only')->default(false);
                $table->boolean('is_active')->default(true);
                $table->dateTime('last_notified_at')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // job_application_history (model: JobApplicationHistory)
        // ============================================================
        if (! Schema::hasTable('job_application_history')) {
            Schema::create('job_application_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('application_id');
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->unsignedInteger('changed_by')->nullable();
                $table->dateTime('changed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'application_id']);
            });
        }

        // ============================================================
        // job_pipeline_rules — add missing columns
        // ============================================================
        if (Schema::hasTable('job_pipeline_rules')) {
            Schema::table('job_pipeline_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('job_pipeline_rules', 'name')) {
                    $table->string('name', 255)->nullable()->after('vacancy_id');
                }
                if (! Schema::hasColumn('job_pipeline_rules', 'trigger_stage')) {
                    $table->string('trigger_stage', 50)->nullable()->after('name');
                }
                if (! Schema::hasColumn('job_pipeline_rules', 'condition_days')) {
                    $table->integer('condition_days')->nullable()->after('trigger_stage');
                }
                if (! Schema::hasColumn('job_pipeline_rules', 'action')) {
                    $table->string('action', 50)->nullable()->after('condition_days');
                }
                if (! Schema::hasColumn('job_pipeline_rules', 'action_target')) {
                    $table->string('action_target', 100)->nullable()->after('action');
                }
                if (! Schema::hasColumn('job_pipeline_rules', 'last_run_at')) {
                    $table->dateTime('last_run_at')->nullable();
                }
            });
        }

        // ============================================================
        // job_templates — fix schema (needs user_id, use_count, etc.)
        // ============================================================
        if (Schema::hasTable('job_templates')) {
            Schema::table('job_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('job_templates', 'user_id')) {
                    $table->unsignedInteger('user_id')->nullable()->after('tenant_id');
                }
                if (! Schema::hasColumn('job_templates', 'type')) {
                    $table->string('type', 50)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'commitment')) {
                    $table->string('commitment', 50)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'category')) {
                    $table->string('category', 100)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'skills_required')) {
                    $table->text('skills_required')->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'is_remote')) {
                    $table->boolean('is_remote')->default(false);
                }
                if (! Schema::hasColumn('job_templates', 'salary_type')) {
                    $table->string('salary_type', 30)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'salary_currency')) {
                    $table->string('salary_currency', 10)->default('EUR');
                }
                if (! Schema::hasColumn('job_templates', 'salary_min')) {
                    $table->decimal('salary_min', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'salary_max')) {
                    $table->decimal('salary_max', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'hours_per_week')) {
                    $table->decimal('hours_per_week', 5, 1)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'time_credits')) {
                    $table->decimal('time_credits', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'benefits')) {
                    $table->json('benefits')->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'tagline')) {
                    $table->string('tagline', 255)->nullable();
                }
                if (! Schema::hasColumn('job_templates', 'is_public')) {
                    $table->boolean('is_public')->default(false);
                }
                if (! Schema::hasColumn('job_templates', 'use_count')) {
                    $table->integer('use_count')->default(0);
                }
            });
        }

        // ============================================================
        // job_referrals — fix schema (needs referrer_user_id, applied, etc.)
        // ============================================================
        if (Schema::hasTable('job_referrals')) {
            Schema::table('job_referrals', function (Blueprint $table) {
                if (! Schema::hasColumn('job_referrals', 'referrer_user_id')) {
                    $table->unsignedInteger('referrer_user_id')->nullable()->after('vacancy_id');
                }
                if (! Schema::hasColumn('job_referrals', 'referred_user_id')) {
                    $table->unsignedInteger('referred_user_id')->nullable()->after('referrer_user_id');
                }
                if (! Schema::hasColumn('job_referrals', 'ref_token')) {
                    $table->string('ref_token', 100)->nullable();
                }
                if (! Schema::hasColumn('job_referrals', 'applied')) {
                    $table->boolean('applied')->default(false);
                }
                if (! Schema::hasColumn('job_referrals', 'applied_at')) {
                    $table->dateTime('applied_at')->nullable();
                }
            });
        }

        // ============================================================
        // users — add resume/talent search columns
        // ============================================================
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'resume_searchable')) {
                    $table->boolean('resume_searchable')->default(false);
                }
                if (! Schema::hasColumn('users', 'resume_headline')) {
                    $table->string('resume_headline', 255)->nullable();
                }
                if (! Schema::hasColumn('users', 'resume_summary')) {
                    $table->text('resume_summary')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_application_history');
        Schema::dropIfExists('job_alerts');
        Schema::dropIfExists('job_vacancy_team');
        Schema::dropIfExists('job_interview_slots');
        Schema::dropIfExists('job_interviews');

        // Columns cannot be easily rolled back individually, skipping column drops
    }
};
