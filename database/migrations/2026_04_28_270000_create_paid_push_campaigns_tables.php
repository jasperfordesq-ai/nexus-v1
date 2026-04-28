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
        // -----------------------------------------------------------------------
        // paid_push_campaigns — each advertiser-created push campaign
        // -----------------------------------------------------------------------
        if (! Schema::hasTable('paid_push_campaigns')) {
            Schema::create('paid_push_campaigns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('created_by');   // user_id of advertiser

                $table->string('name', 255);

                $table->enum('status', [
                    'draft',
                    'pending_review',
                    'scheduled',
                    'sending',
                    'sent',
                    'paused',
                    'rejected',
                    'cancelled',
                ])->default('draft');

                $table->enum('advertiser_type', [
                    'sme',
                    'verein',
                    'gemeinde',
                    'private',
                ])->default('sme');

                // Push notification payload
                $table->string('title', 100);
                $table->string('body', 400);
                $table->string('cta_url', 500)->nullable();

                // Targeting — {"radius_km":5,"lat":null,"lng":null,"member_tier_min":0,"interests":[]}
                $table->json('audience_filter')->nullable();

                // Recipient counts
                $table->integer('target_count')->nullable();          // estimated before send
                $table->integer('actual_send_count')->default(0);     // confirmed after dispatch

                // Scheduling & billing
                $table->dateTime('scheduled_at')->nullable();          // null = send on approval
                $table->dateTime('sent_at')->nullable();
                $table->integer('cost_per_send')->default(5);          // cents per notification
                $table->bigInteger('total_cost_cents')->default(0);    // calculated on completion

                // Admin review
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();

                // Engagement
                $table->integer('open_count')->default(0);
                $table->integer('click_count')->default(0);

                $table->timestamps();

                // Compound indexes for tenant-scoped queries
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'scheduled_at']);
            });
        }

        // -----------------------------------------------------------------------
        // paid_push_campaign_sends — one row per notification sent per user
        // -----------------------------------------------------------------------
        if (! Schema::hasTable('paid_push_campaign_sends')) {
            Schema::create('paid_push_campaign_sends', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->dateTime('sent_at');
                $table->dateTime('opened_at')->nullable();     // set when user taps notification
                $table->string('fcm_message_id', 255)->nullable();

                // FK with cascade delete so campaign removal cleans sends
                $table->foreign('campaign_id')
                    ->references('id')
                    ->on('paid_push_campaigns')
                    ->onDelete('cascade');

                $table->index('campaign_id');
                $table->index(['user_id', 'sent_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_push_campaign_sends');
        Schema::dropIfExists('paid_push_campaigns');
    }
};
