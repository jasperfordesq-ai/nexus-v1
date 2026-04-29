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
        if (Schema::hasTable('caring_paper_onboarding_intakes')) {
            return;
        }

        Schema::create('caring_paper_onboarding_intakes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('uploaded_by')->nullable()->index();
            $table->unsignedInteger('reviewed_by')->nullable()->index();
            $table->unsignedInteger('created_user_id')->nullable()->index();
            $table->enum('status', ['pending_review', 'confirmed', 'rejected'])->default('pending_review');
            $table->string('original_filename', 255);
            $table->string('stored_path', 512);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('ocr_provider', 60)->default('manual_review_stub');
            $table->json('extracted_fields')->nullable();
            $table->json('corrected_fields')->nullable();
            $table->text('coordinator_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_paper_onboarding_intakes');
    }
};
