<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the listing_reports table for community-driven content moderation.
 * Members can flag inappropriate listings for admin review.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_reports')) {
            return;
        }

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->integer('listing_id');
            $table->integer('reporter_id');
            $table->enum('reason', ['inappropriate', 'safety_concern', 'misleading', 'spam', 'not_timebank_service', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed', 'action_taken'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('listing_id')->references('id')->on('listings');
            $table->foreign('reporter_id')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users');

            // Prevent duplicate reports from same user on same listing
            $table->unique(['listing_id', 'reporter_id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
    }
};
