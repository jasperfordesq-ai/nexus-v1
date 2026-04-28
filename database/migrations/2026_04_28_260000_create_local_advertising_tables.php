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
 * AG56 — Local Advertising Platform
 *
 * Creates four tables:
 *   ad_campaigns    — campaign metadata, budget, audience targeting, lifecycle
 *   ad_creatives    — headline/body/image assets attached to a campaign
 *   ad_impressions  — per-placement view events (lightweight, no FK to keep inserts fast)
 *   ad_clicks       — click events linked back to an impression
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ad_campaigns ─────────────────────────────────────────────────────
        if (!Schema::hasTable('ad_campaigns')) {
            Schema::create('ad_campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('created_by');       // user_id of advertiser
                $table->string('name', 255);
                $table->enum('status', [
                    'pending_review',
                    'active',
                    'paused',
                    'completed',
                    'rejected',
                ])->default('pending_review');
                $table->enum('advertiser_type', [
                    'sme',
                    'verein',
                    'gemeinde',
                    'private',
                ])->default('sme');
                $table->unsignedBigInteger('budget_cents')->default(0);
                $table->unsignedBigInteger('spent_cents')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                // {"radius_km":5,"lat":47.1758,"lng":8.4622,"min_age":null,"interests":["gardening"]}
                $table->json('audience_filters')->nullable();
                $table->enum('placement', ['feed', 'discovery', 'markt', 'all'])->default('feed');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('impression_count')->default(0);
                $table->unsignedBigInteger('click_count')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'ad_campaigns_tenant_status_idx');
            });
        }

        // ── ad_creatives ─────────────────────────────────────────────────────
        if (!Schema::hasTable('ad_creatives')) {
            Schema::create('ad_creatives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('headline', 100);
                $table->string('body', 280);
                $table->string('cta_text', 50)->nullable();       // "Jetzt besuchen", "Mehr erfahren"
                $table->string('image_url', 500)->nullable();
                $table->string('destination_url', 500)->nullable();
                $table->tinyInteger('is_active')->default(1);
                $table->timestamps();

                $table->foreign('campaign_id')
                      ->references('id')
                      ->on('ad_campaigns')
                      ->cascadeOnDelete();
            });
        }

        // ── ad_impressions ───────────────────────────────────────────────────
        if (!Schema::hasTable('ad_impressions')) {
            Schema::create('ad_impressions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('creative_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable(); // null for anonymous
                $table->string('placement', 50);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['campaign_id', 'created_at'], 'ad_impressions_campaign_date_idx');
            });
        }

        // ── ad_clicks ────────────────────────────────────────────────────────
        if (!Schema::hasTable('ad_clicks')) {
            Schema::create('ad_clicks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('impression_id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['campaign_id', 'created_at'], 'ad_clicks_campaign_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
    }
};
