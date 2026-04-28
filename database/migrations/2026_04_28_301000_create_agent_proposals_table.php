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
        if (Schema::hasTable('agent_proposals')) {
            return;
        }

        Schema::create('agent_proposals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('run_id');
            $table->string('proposal_type', 100);
            $table->unsignedBigInteger('subject_user_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->json('proposal_data');
            $table->enum('status', [
                'pending_review',
                'approved',
                'auto_applied',
                'rejected',
                'expired',
            ])->default('pending_review');
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('run_id')
                  ->references('id')
                  ->on('agent_runs')
                  ->cascadeOnDelete();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index('run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_proposals');
    }
};
