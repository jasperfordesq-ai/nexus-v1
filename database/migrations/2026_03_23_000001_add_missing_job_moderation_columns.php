<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns and tables for the jobs moderation, spam detection,
 * AI chat, gamification, and identity provider features.
 *
 * All additions are idempotent via Schema::hasColumn() / hasTable() checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // job_vacancies — moderation & employer branding columns
        // ============================================================
        if (Schema::hasTable('job_vacancies')) {
            Schema::table('job_vacancies', function (Blueprint $table) {
                if (! Schema::hasColumn('job_vacancies', 'moderation_status')) {
                    $table->string('moderation_status', 30)->nullable()->after('status');
                }
                if (! Schema::hasColumn('job_vacancies', 'moderation_notes')) {
                    $table->text('moderation_notes')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'moderated_by')) {
                    $table->unsignedInteger('moderated_by')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'moderated_at')) {
                    $table->dateTime('moderated_at')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'spam_score')) {
                    $table->integer('spam_score')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'spam_flags')) {
                    $table->json('spam_flags')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'tagline')) {
                    $table->string('tagline', 255)->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'video_url')) {
                    $table->string('video_url', 500)->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'culture_photos')) {
                    $table->json('culture_photos')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'company_size')) {
                    $table->string('company_size', 50)->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'benefits')) {
                    $table->json('benefits')->nullable();
                }
                if (! Schema::hasColumn('job_vacancies', 'blind_hiring')) {
                    $table->boolean('blind_hiring')->default(false);
                }
            });
        }

        // ============================================================
        // job_moderation_logs
        // ============================================================
        if (! Schema::hasTable('job_moderation_logs')) {
            Schema::create('job_moderation_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('admin_id');
                $table->string('action', 50);
                $table->string('previous_status', 30)->nullable();
                $table->string('new_status', 30)->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_bias_audits
        // ============================================================
        if (! Schema::hasTable('job_bias_audits')) {
            Schema::create('job_bias_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id')->nullable();
                $table->string('audit_type', 50)->default('posting');
                $table->json('findings')->nullable();
                $table->decimal('bias_score', 5, 2)->nullable();
                $table->text('recommendations')->nullable();
                $table->unsignedInteger('audited_by')->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_spam_patterns
        // ============================================================
        if (! Schema::hasTable('job_spam_patterns')) {
            Schema::create('job_spam_patterns', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('pattern_type', 50);
                $table->text('pattern_value');
                $table->integer('weight')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        // ============================================================
        // job_applications
        // ============================================================
        if (! Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('user_id');
                $table->string('status', 30)->default('pending');
                $table->text('cover_letter')->nullable();
                $table->string('resume_path', 500)->nullable();
                $table->json('answers')->nullable();
                $table->text('notes')->nullable();
                $table->string('pipeline_stage', 50)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // job_offers
        // ============================================================
        if (! Schema::hasTable('job_offers')) {
            Schema::create('job_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('application_id')->nullable();
                $table->unsignedInteger('user_id');
                $table->string('status', 30)->default('pending');
                $table->text('details')->nullable();
                $table->decimal('salary_offered', 12, 2)->nullable();
                $table->date('start_date')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_saved_profiles
        // ============================================================
        if (! Schema::hasTable('job_saved_profiles')) {
            Schema::create('job_saved_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('profile_name', 255);
                $table->text('headline')->nullable();
                $table->text('summary')->nullable();
                $table->json('skills')->nullable();
                $table->json('experience')->nullable();
                $table->json('education')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // job_scorecards
        // ============================================================
        if (! Schema::hasTable('job_scorecards')) {
            Schema::create('job_scorecards', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('application_id');
                $table->unsignedInteger('reviewer_id');
                $table->json('criteria_scores')->nullable();
                $table->decimal('overall_score', 5, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('recommendation', 30)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'application_id']);
            });
        }

        // ============================================================
        // job_interview_scheduling
        // ============================================================
        if (! Schema::hasTable('job_interview_scheduling')) {
            Schema::create('job_interview_scheduling', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('application_id');
                $table->unsignedInteger('interviewer_id');
                $table->string('interview_type', 50)->default('video');
                $table->dateTime('scheduled_at')->nullable();
                $table->integer('duration_minutes')->default(30);
                $table->string('location', 500)->nullable();
                $table->string('meeting_link', 500)->nullable();
                $table->string('status', 30)->default('scheduled');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'application_id']);
            });
        }

        // ============================================================
        // job_pipeline_rules
        // ============================================================
        if (! Schema::hasTable('job_pipeline_rules')) {
            Schema::create('job_pipeline_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id')->nullable();
                $table->string('stage', 50);
                $table->string('rule_type', 50);
                $table->json('config')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_team_members
        // ============================================================
        if (! Schema::hasTable('job_team_members')) {
            Schema::create('job_team_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('user_id');
                $table->string('role', 50)->default('viewer');
                $table->timestamps();
                $table->unique(['vacancy_id', 'user_id']);
                $table->index('tenant_id');
            });
        }

        // ============================================================
        // job_templates
        // ============================================================
        if (! Schema::hasTable('job_templates')) {
            Schema::create('job_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('created_by');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->json('template_data')->nullable();
                $table->boolean('is_global')->default(false);
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        // ============================================================
        // salary_benchmarks
        // ============================================================
        if (! Schema::hasTable('salary_benchmarks')) {
            Schema::create('salary_benchmarks', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('job_title', 255);
                $table->string('location', 255)->nullable();
                $table->string('industry', 100)->nullable();
                $table->decimal('median_salary', 12, 2)->nullable();
                $table->decimal('p25_salary', 12, 2)->nullable();
                $table->decimal('p75_salary', 12, 2)->nullable();
                $table->string('currency', 10)->default('EUR');
                $table->string('source', 100)->nullable();
                $table->date('data_date')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'job_title']);
            });
        }

        // ============================================================
        // job_referrals
        // ============================================================
        if (! Schema::hasTable('job_referrals')) {
            Schema::create('job_referrals', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->unsignedInteger('referrer_id');
                $table->string('referee_email', 255);
                $table->string('referee_name', 255)->nullable();
                $table->string('status', 30)->default('pending');
                $table->text('message')->nullable();
                $table->string('token', 100)->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_gdpr_consents
        // ============================================================
        if (! Schema::hasTable('job_gdpr_consents')) {
            Schema::create('job_gdpr_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('vacancy_id')->nullable();
                $table->string('consent_type', 50);
                $table->boolean('consented')->default(false);
                $table->dateTime('consented_at')->nullable();
                $table->dateTime('withdrawn_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // job_alert_subscriptions
        // ============================================================
        if (! Schema::hasTable('job_alert_subscriptions')) {
            Schema::create('job_alert_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->json('criteria')->nullable();
                $table->string('frequency', 20)->default('weekly');
                $table->boolean('is_active')->default(true);
                $table->dateTime('last_sent_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // job_expiry_notifications
        // ============================================================
        if (! Schema::hasTable('job_expiry_notifications')) {
            Schema::create('job_expiry_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('vacancy_id');
                $table->string('notification_type', 50);
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'vacancy_id']);
            });
        }

        // ============================================================
        // job_feeds
        // ============================================================
        if (! Schema::hasTable('job_feeds')) {
            Schema::create('job_feeds', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->string('feed_type', 50)->default('rss');
                $table->string('feed_url', 500)->nullable();
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->dateTime('last_synced_at')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        // ============================================================
        // ai_conversations
        // ============================================================
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('title', 255)->nullable();
                $table->string('model', 50)->default('gpt-4');
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ============================================================
        // ai_messages
        // ============================================================
        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->string('role', 20);
                $table->text('content');
                $table->integer('tokens_used')->default(0);
                $table->timestamps();
                $table->index('conversation_id');
            });
        }

        // ============================================================
        // ai_usage
        // ============================================================
        if (! Schema::hasTable('ai_usage')) {
            Schema::create('ai_usage', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->date('usage_date');
                $table->integer('messages_count')->default(0);
                $table->integer('tokens_used')->default(0);
                $table->timestamps();
                $table->unique(['tenant_id', 'user_id', 'usage_date']);
            });
        }

        // ============================================================
        // gamification_challenges
        // ============================================================
        if (! Schema::hasTable('gamification_challenges')) {
            Schema::create('gamification_challenges', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('type', 50)->default('one_time');
                $table->string('badge_key', 100)->nullable();
                $table->integer('xp_reward')->default(0);
                $table->json('criteria')->nullable();
                $table->string('status', 20)->default('active');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
            });
        }

        // ============================================================
        // identity_providers
        // ============================================================
        if (! Schema::hasTable('identity_providers')) {
            Schema::create('identity_providers', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('provider', 50);
                $table->string('name', 255);
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status', 20)->default('active');
                $table->dateTime('last_health_check')->nullable();
                $table->json('health_data')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'provider']);
            });
        }

        // ============================================================
        // identity_provider_mappings
        // ============================================================
        if (! Schema::hasTable('identity_provider_mappings')) {
            Schema::create('identity_provider_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedInteger('user_id');
                $table->string('external_id', 255);
                $table->json('profile_data')->nullable();
                $table->timestamps();
                $table->index('provider_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        // Drop tables in reverse order
        Schema::dropIfExists('identity_provider_mappings');
        Schema::dropIfExists('identity_providers');
        Schema::dropIfExists('gamification_challenges');
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('job_feeds');
        Schema::dropIfExists('job_expiry_notifications');
        Schema::dropIfExists('job_alert_subscriptions');
        Schema::dropIfExists('job_gdpr_consents');
        Schema::dropIfExists('job_referrals');
        Schema::dropIfExists('salary_benchmarks');
        Schema::dropIfExists('job_templates');
        Schema::dropIfExists('job_team_members');
        Schema::dropIfExists('job_pipeline_rules');
        Schema::dropIfExists('job_interview_scheduling');
        Schema::dropIfExists('job_scorecards');
        Schema::dropIfExists('job_saved_profiles');
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_spam_patterns');
        Schema::dropIfExists('job_bias_audits');
        Schema::dropIfExists('job_moderation_logs');

        // Drop columns from job_vacancies
        if (Schema::hasTable('job_vacancies')) {
            Schema::table('job_vacancies', function (Blueprint $table) {
                $columns = ['moderation_status', 'moderation_notes', 'moderated_by', 'moderated_at',
                           'spam_score', 'spam_flags', 'tagline', 'video_url', 'culture_photos',
                           'company_size', 'benefits', 'blind_hiring'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('job_vacancies', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
