<?php
// Copyright (c) 2024-2026 Jasper Ford
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
        if (Schema::hasTable('caring_support_relationships')) {
            return;
        }

        Schema::create('caring_support_relationships', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id')->index();
            $table->integer('supporter_id')->index();
            $table->integer('recipient_id')->index();
            $table->integer('coordinator_id')->nullable()->index();
            $table->integer('organization_id')->nullable()->index();
            $table->integer('category_id')->nullable()->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('frequency', ['weekly', 'fortnightly', 'monthly', 'ad_hoc'])->default('weekly');
            $table->decimal('expected_hours', 5, 2)->default(1.00);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->timestamp('last_logged_at')->nullable();
            $table->timestamp('next_check_in_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_csr_tenant_status');
            $table->index(['tenant_id', 'recipient_id', 'status'], 'idx_csr_recipient_status');
            $table->index(['tenant_id', 'supporter_id', 'status'], 'idx_csr_supporter_status');
            $table->index(['tenant_id', 'next_check_in_at'], 'idx_csr_next_check_in');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_support_relationships');
    }
};
