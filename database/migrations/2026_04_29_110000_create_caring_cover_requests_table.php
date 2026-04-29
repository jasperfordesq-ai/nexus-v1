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
 * AG73 — substitute / cover-care services.
 *
 * Tracks temporary cover needs for linked care receivers so caregivers can
 * request trusted substitutes for holidays, illness, work shifts, or respite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caring_cover_requests')) {
            Schema::create('caring_cover_requests', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedBigInteger('caregiver_link_id');
                $table->unsignedInteger('caregiver_id')->index();
                $table->unsignedInteger('cared_for_id')->index();
                $table->unsignedBigInteger('support_relationship_id')->nullable();
                $table->unsignedInteger('matched_supporter_id')->nullable()->index();
                $table->string('title', 255);
                $table->text('briefing')->nullable();
                $table->json('required_skills')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->decimal('expected_hours', 5, 2)->nullable();
                $table->unsignedTinyInteger('minimum_trust_tier')->default(1);
                $table->enum('urgency', ['planned', 'soon', 'urgent'])->default('planned');
                $table->enum('status', ['open', 'matched', 'accepted', 'cancelled', 'completed'])->default('open');
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();

                $table->foreign('caregiver_link_id')
                    ->references('id')
                    ->on('caring_caregiver_links')
                    ->cascadeOnDelete();

                $table->index(['tenant_id', 'caregiver_id', 'status']);
                $table->index(['tenant_id', 'cared_for_id', 'starts_at']);
                $table->index(['tenant_id', 'status', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_cover_requests');
    }
};
