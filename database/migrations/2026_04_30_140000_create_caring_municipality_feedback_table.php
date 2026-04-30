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
 * AG92 — Two-Way Municipality Feedback Inbox.
 *
 * Resident-to-municipality questions, ideas, issue reports, and sentiment.
 * Distinct from formal surveys (AG62) and one-way announcements (AG14).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('caring_municipality_feedback')) {
            return;
        }

        Schema::create('caring_municipality_feedback', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('submitter_user_id')->nullable();
            $table->unsignedInteger('sub_region_id')->nullable();
            $table->enum('category', ['question', 'idea', 'issue_report', 'sentiment'])->default('question');
            $table->string('subject', 200);
            $table->text('body');
            $table->enum('sentiment_tag', ['positive', 'neutral', 'negative', 'concerned'])->nullable();
            $table->enum('status', ['new', 'triaging', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->unsignedInteger('assigned_user_id')->nullable();
            $table->string('assigned_role', 64)->nullable();
            $table->text('triage_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('submitter_user_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'sub_region_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_municipality_feedback');
    }
};
