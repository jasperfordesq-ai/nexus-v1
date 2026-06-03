<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Podcasts module (alpha) — shows, episodes, chapters, and lightweight
 * engagement analytics. Tenant-scoped and idempotent; no DB-level FKs, matching
 * the course/marketplace convention for weakly coupled multi-tenant modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('podcast_shows')) {
            Schema::create('podcast_shows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('owner_user_id');
                $table->string('title', 200);
                $table->string('slug', 220);
                $table->string('summary', 600)->nullable();
                $table->longText('description')->nullable();
                $table->string('artwork_url', 1000)->nullable();
                $table->string('language', 20)->default('en');
                $table->string('category', 120)->nullable();
                $table->enum('visibility', ['public', 'members', 'private'])->default('public');
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('approved');
                $table->text('moderation_notes')->nullable();
                $table->unsignedBigInteger('moderated_by')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->integer('episode_count')->default(0);
                $table->integer('subscriber_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug'], 'pod_shows_tenant_slug_unique');
                $table->index(['tenant_id', 'owner_user_id'], 'pod_shows_tenant_owner_idx');
                $table->index(['tenant_id', 'status', 'moderation_status'], 'pod_shows_tenant_lifecycle_idx');
                $table->fullText(['title', 'summary', 'description'], 'pod_shows_title_desc_ft');
            });
        }

        if (!Schema::hasTable('podcast_episodes')) {
            Schema::create('podcast_episodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('show_id');
                $table->unsignedBigInteger('author_user_id');
                $table->string('title', 200);
                $table->string('slug', 220);
                $table->string('summary', 600)->nullable();
                $table->longText('description')->nullable();
                $table->string('audio_url', 1000);
                $table->string('audio_storage_path', 1000)->nullable();
                $table->string('audio_storage_disk', 50)->nullable();
                $table->string('audio_mime', 120)->nullable();
                $table->unsignedBigInteger('audio_bytes')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->integer('episode_number')->nullable();
                $table->integer('season_number')->nullable();
                $table->boolean('explicit')->default(false);
                $table->enum('episode_type', ['full', 'trailer', 'bonus'])->default('full');
                $table->enum('visibility', ['inherit', 'public', 'members', 'private'])->default('inherit');
                $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
                $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('approved');
                $table->text('moderation_notes')->nullable();
                $table->unsignedBigInteger('moderated_by')->nullable();
                $table->timestamp('moderated_at')->nullable();
                $table->longText('transcript')->nullable();
                $table->string('transcript_language', 20)->nullable();
                $table->string('cover_image_url', 1000)->nullable();
                $table->integer('listen_count')->default(0);
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'show_id'], 'pod_eps_tenant_show_idx');
                $table->unique(['tenant_id', 'show_id', 'slug'], 'pod_eps_tenant_show_slug_unique');
                $table->index(['tenant_id', 'status', 'moderation_status'], 'pod_eps_tenant_lifecycle_idx');
                $table->fullText(['title', 'summary', 'description', 'transcript'], 'pod_eps_title_desc_ft');
            });
        }

        if (Schema::hasTable('podcast_episodes') && !Schema::hasColumn('podcast_episodes', 'audio_storage_path')) {
            Schema::table('podcast_episodes', function (Blueprint $table) {
                $table->string('audio_storage_path', 1000)->nullable()->after('audio_url');
                $table->string('audio_storage_disk', 50)->nullable()->after('audio_storage_path');
            });
        }

        if (!Schema::hasTable('podcast_episode_chapters')) {
            Schema::create('podcast_episode_chapters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('episode_id');
                $table->string('title', 200);
                $table->integer('starts_at_seconds');
                $table->string('url', 1000)->nullable();
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'episode_id'], 'pod_chapters_tenant_episode_idx');
            });
        }

        if (!Schema::hasTable('podcast_episode_listens')) {
            Schema::create('podcast_episode_listens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('episode_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('session_hash', 64)->nullable();
                $table->integer('listened_seconds')->default(0);
                $table->boolean('completed')->default(false);
                $table->string('user_agent_hash', 64)->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['tenant_id', 'episode_id'], 'pod_listens_tenant_episode_idx');
                $table->index(['tenant_id', 'user_id'], 'pod_listens_tenant_user_idx');
            });
        }

        if (!Schema::hasTable('podcast_episode_reactions')) {
            Schema::create('podcast_episode_reactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('episode_id');
                $table->unsignedBigInteger('user_id');
                $table->string('reaction', 30)->default('like');
                $table->timestamps();

                $table->unique(['tenant_id', 'episode_id', 'user_id', 'reaction'], 'pod_reactions_unique_idx');
                $table->index(['tenant_id', 'episode_id'], 'pod_reactions_tenant_episode_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_episode_reactions');
        Schema::dropIfExists('podcast_episode_listens');
        Schema::dropIfExists('podcast_episode_chapters');
        Schema::dropIfExists('podcast_episodes');
        Schema::dropIfExists('podcast_shows');
    }
};
