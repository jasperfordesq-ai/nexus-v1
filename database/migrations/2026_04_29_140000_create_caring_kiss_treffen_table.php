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
        if (Schema::hasTable('caring_kiss_treffen')) {
            return;
        }

        Schema::create('caring_kiss_treffen', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->integer('event_id');
            $table->enum('treffen_type', [
                'monthly_stamm',
                'annual_general_assembly',
                'governance_circle',
                'cooperative_workshop',
                'other',
            ])->default('monthly_stamm');
            $table->boolean('members_only')->default(true);
            $table->unsignedInteger('quorum_required')->nullable();
            $table->string('fondation_header')->nullable();
            $table->string('minutes_document_url', 512)->nullable();
            $table->timestamp('minutes_uploaded_at')->nullable();
            $table->unsignedInteger('minutes_uploaded_by')->nullable();
            $table->text('coordinator_notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id'], 'caring_kiss_treffen_tenant_event_unique');
            $table->index(['tenant_id', 'treffen_type']);
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_kiss_treffen');
    }
};
