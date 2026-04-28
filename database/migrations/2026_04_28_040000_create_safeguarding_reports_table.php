<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('safeguarding_reports')) {
            return;
        }

        Schema::create('safeguarding_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('reporter_user_id');
            $table->unsignedInteger('subject_user_id')->nullable();
            $table->unsignedInteger('subject_organisation_id')->nullable();
            $table->enum('category', [
                'inappropriate_behavior',
                'financial_concern',
                'exploitation',
                'neglect',
                'medical_concern',
                'other',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->string('evidence_url', 500)->nullable();
            $table->enum('status', [
                'submitted',
                'triaged',
                'investigating',
                'resolved',
                'dismissed',
            ])->default('submitted');
            $table->unsignedInteger('assigned_to_user_id')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->boolean('escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_safeguard_tenant_status');
            $table->index(['tenant_id', 'severity'], 'idx_safeguard_tenant_severity');
            $table->index(['tenant_id', 'assigned_to_user_id'], 'idx_safeguard_tenant_assigned');
            $table->index(['tenant_id', 'review_due_at'], 'idx_safeguard_tenant_review_due');
            $table->index(['tenant_id', 'reporter_user_id'], 'idx_safeguard_tenant_reporter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_reports');
    }
};
