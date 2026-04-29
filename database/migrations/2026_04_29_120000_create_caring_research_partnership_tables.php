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
        if (! Schema::hasTable('caring_research_partners')) {
            Schema::create('caring_research_partners', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->index();
                $table->string('name');
                $table->string('institution');
                $table->string('contact_email')->nullable();
                $table->string('agreement_reference')->nullable();
                $table->string('methodology_url')->nullable();
                $table->enum('status', ['draft', 'active', 'paused', 'ended'])->default('draft');
                $table->json('data_scope')->nullable();
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('caring_research_consents')) {
            Schema::create('caring_research_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->enum('consent_status', ['opted_in', 'opted_out', 'revoked'])->default('opted_out');
                $table->string('consent_version')->default('research-v1');
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id'], 'caring_research_consents_tenant_user_unique');
            });
        }

        if (! Schema::hasTable('caring_research_dataset_exports')) {
            Schema::create('caring_research_dataset_exports', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedBigInteger('partner_id')->index();
                $table->unsignedInteger('requested_by')->nullable();
                $table->string('dataset_key')->default('caring_community_aggregate_v1');
                $table->date('period_start');
                $table->date('period_end');
                $table->enum('status', ['generated', 'superseded', 'revoked'])->default('generated');
                $table->unsignedInteger('row_count')->default(0);
                $table->string('anonymization_version')->default('aggregate-v1');
                $table->string('data_hash', 64);
                $table->timestamp('generated_at');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'partner_id', 'generated_at'], 'caring_research_exports_partner_generated_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_research_dataset_exports');
        Schema::dropIfExists('caring_research_consents');
        Schema::dropIfExists('caring_research_partners');
    }
};
