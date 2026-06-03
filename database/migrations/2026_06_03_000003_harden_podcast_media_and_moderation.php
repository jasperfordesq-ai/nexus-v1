<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('podcast_episodes')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                if (!Schema::hasColumn('podcast_episodes', 'media_processing_status')) {
                    $table->string('media_processing_status', 30)->default('pending')->after('audio_storage_disk');
                }
                if (!Schema::hasColumn('podcast_episodes', 'media_scan_status')) {
                    $table->string('media_scan_status', 30)->default('pending')->after('media_processing_status');
                }
                if (!Schema::hasColumn('podcast_episodes', 'media_waveform_json')) {
                    $table->json('media_waveform_json')->nullable()->after('media_scan_status');
                }
                if (!Schema::hasColumn('podcast_episodes', 'media_duration_source')) {
                    $table->string('media_duration_source', 30)->nullable()->after('media_waveform_json');
                }
            });
        }

        if (Schema::hasTable('podcast_episode_listens') && !Schema::hasColumn('podcast_episode_listens', 'client_family')) {
            Schema::table('podcast_episode_listens', function (Blueprint $table) {
                $table->string('client_family', 40)->nullable()->after('completed');
                $table->string('retention_bucket', 20)->nullable()->after('client_family');
            });
        }

        if (!Schema::hasTable('podcast_show_subscriptions')) {
            Schema::create('podcast_show_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('show_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('notify_new_episodes')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'show_id', 'user_id'], 'pod_subs_tenant_show_user_unique');
                $table->index(['tenant_id', 'user_id'], 'pod_subs_tenant_user_idx');
            });
        }

        if (!Schema::hasTable('podcast_episode_reports')) {
            Schema::create('podcast_episode_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('episode_id');
                $table->unsignedBigInteger('reporter_user_id');
                $table->string('reason', 80);
                $table->text('details')->nullable();
                $table->string('status', 30)->default('open');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'episode_id'], 'pod_reports_tenant_episode_idx');
                $table->index(['tenant_id', 'status'], 'pod_reports_tenant_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_episode_reports');
        Schema::dropIfExists('podcast_show_subscriptions');

        if (Schema::hasTable('podcast_episode_listens')) {
            Schema::table('podcast_episode_listens', function (Blueprint $table) {
                foreach (['retention_bucket', 'client_family'] as $column) {
                    if (Schema::hasColumn('podcast_episode_listens', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('podcast_episodes')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                foreach (['media_duration_source', 'media_waveform_json', 'media_scan_status', 'media_processing_status'] as $column) {
                    if (Schema::hasColumn('podcast_episodes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
