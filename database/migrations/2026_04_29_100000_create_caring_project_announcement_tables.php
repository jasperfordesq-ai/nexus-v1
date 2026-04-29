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
 * AG69 — Multi-stage project announcement tracking.
 *
 * Enables municipality / cooperative project pages with milestone updates,
 * member subscriptions, and coordinator-published status pushes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caring_project_announcements')) {
            Schema::create('caring_project_announcements', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedInteger('created_by')->nullable();
                $table->string('title', 255);
                $table->text('summary')->nullable();
                $table->string('location', 255)->nullable();
                $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
                $table->string('current_stage', 120)->nullable();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->dateTime('published_at')->nullable();
                $table->dateTime('last_update_at')->nullable();
                $table->unsignedInteger('subscriber_count')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'published_at']);
            });
        }

        if (! Schema::hasTable('caring_project_updates')) {
            Schema::create('caring_project_updates', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedBigInteger('project_id');
                $table->unsignedInteger('created_by')->nullable();
                $table->string('stage_label', 120)->nullable();
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->unsignedTinyInteger('progress_percent')->nullable();
                $table->boolean('is_milestone')->default(false);
                $table->enum('status', ['draft', 'published'])->default('draft');
                $table->dateTime('published_at')->nullable();
                $table->unsignedInteger('notification_count')->default(0);
                $table->timestamps();

                $table->foreign('project_id')
                    ->references('id')
                    ->on('caring_project_announcements')
                    ->cascadeOnDelete();

                $table->index(['project_id', 'status']);
                $table->index(['tenant_id', 'published_at']);
            });
        }

        if (! Schema::hasTable('caring_project_subscriptions')) {
            Schema::create('caring_project_subscriptions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedBigInteger('project_id');
                $table->unsignedInteger('user_id');
                $table->dateTime('subscribed_at');
                $table->dateTime('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->foreign('project_id')
                    ->references('id')
                    ->on('caring_project_announcements')
                    ->cascadeOnDelete();

                $table->unique(['project_id', 'user_id'], 'caring_project_subscriptions_project_user_unique');
                $table->index(['tenant_id', 'user_id']);
                $table->index(['project_id', 'unsubscribed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_project_subscriptions');
        Schema::dropIfExists('caring_project_updates');
        Schema::dropIfExists('caring_project_announcements');
    }
};
