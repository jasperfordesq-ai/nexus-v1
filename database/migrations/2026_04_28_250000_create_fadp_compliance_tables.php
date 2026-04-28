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
        // ---------------------------------------------------------------
        // fadp_consent_records — audit log of every consent action
        // ---------------------------------------------------------------
        if (! Schema::hasTable('fadp_consent_records')) {
            Schema::create('fadp_consent_records', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->string('consent_type', 100); // e.g. 'profiling', 'ai_matching', 'data_sharing', 'marketing_push'
                $table->enum('action', ['granted', 'withdrawn', 'updated'])->default('granted');
                $table->string('consent_version', 20)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // ---------------------------------------------------------------
        // fadp_data_retention_config — per-tenant retention + residency
        // ---------------------------------------------------------------
        if (! Schema::hasTable('fadp_data_retention_config')) {
            Schema::create('fadp_data_retention_config', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->unique();
                // JSON: {"member_data_years":7,"transaction_data_years":10,"activity_logs_years":3,"messages_years":2,"ai_embeddings_years":1}
                $table->json('config');
                // 'Switzerland' | 'EU' | 'International'
                $table->string('data_residency', 50)->default('EU');
                $table->string('dpa_contact_email', 255)->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        // ---------------------------------------------------------------
        // fadp_processing_activities — record of processing activities
        // ---------------------------------------------------------------
        if (! Schema::hasTable('fadp_processing_activities')) {
            Schema::create('fadp_processing_activities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->string('activity_name', 255);
                $table->text('purpose');
                $table->json('data_categories');   // array of strings
                $table->json('recipients')->nullable();
                $table->string('retention_period', 100);
                // 'consent' | 'contract' | 'legal_obligation' | 'legitimate_interest'
                $table->string('legal_basis', 100);
                $table->boolean('is_automated_profiling')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fadp_processing_activities');
        Schema::dropIfExists('fadp_data_retention_config');
        Schema::dropIfExists('fadp_consent_records');
    }
};
