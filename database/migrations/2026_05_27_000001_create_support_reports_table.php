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
        if (Schema::hasTable('support_reports')) {
            return;
        }

        Schema::create('support_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('reference', 40)->unique();
            $table->string('source', 40)->default('in_app');
            $table->string('summary', 180);
            $table->text('description');
            $table->string('impact', 20)->default('minor');
            $table->string('status', 24)->default('open');
            $table->string('module', 100)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('sentry_event_id', 191)->nullable();
            $table->string('sentry_issue_url', 2048)->nullable();
            $table->json('diagnostics')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('triage_notes')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'idx_support_reports_tenant_status_created');
            $table->index(['tenant_id', 'impact', 'created_at'], 'idx_support_reports_tenant_impact_created');
            $table->index(['tenant_id', 'assigned_user_id'], 'idx_support_reports_assignee');
            $table->index(['tenant_id', 'sentry_event_id'], 'idx_support_reports_sentry_event');
            $table->index(['tenant_id', 'user_id', 'created_at'], 'idx_support_reports_user_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reports');
    }
};
