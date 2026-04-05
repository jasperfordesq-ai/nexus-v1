<?php
// Copyright © 2024–2026 Jasper Ford
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
        // ─── DSA Compliance Reports (MKT6) ──────────────────────────────
        if (!Schema::hasTable('marketplace_reports')) {
            Schema::create('marketplace_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->unsignedBigInteger('reporter_id');
                $table->enum('reason', [
                    'counterfeit', 'illegal', 'unsafe', 'misleading',
                    'discrimination', 'ip_violation', 'other',
                ]);
                $table->text('description');
                $table->json('evidence_urls')->nullable();
                $table->enum('status', [
                    'received', 'acknowledged', 'under_review',
                    'action_taken', 'no_action', 'appealed', 'appeal_resolved',
                ])->default('received');
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_reason')->nullable();
                $table->enum('action_taken', [
                    'none', 'warning', 'listing_removed', 'seller_suspended',
                ])->nullable();
                $table->text('appeal_text')->nullable();
                $table->timestamp('appeal_resolved_at')->nullable();
                $table->unsignedBigInteger('handled_by')->nullable();
                $table->boolean('transparency_report_included')->default(false);
                $table->timestamps();

                $table->foreign('marketplace_listing_id')
                    ->references('id')->on('marketplace_listings')
                    ->onDelete('cascade');

                // user indexes (no FK — users table has different charset/collation)
                $table->index('reporter_id', 'mr_reporter_id_idx');
                $table->index('handled_by', 'mr_handled_by_idx');

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'marketplace_listing_id']);
            });
        }

        // ─── Shipping Options (MKT31) ───────────────────────────────────
        if (!Schema::hasTable('marketplace_shipping_options')) {
            Schema::create('marketplace_shipping_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('seller_id');
                $table->string('courier_name', 100);
                $table->string('courier_code', 50)->nullable();
                $table->decimal('price', 8, 2);
                $table->string('currency', 3)->default('EUR');
                $table->unsignedInteger('estimated_days')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('seller_id')
                    ->references('id')->on('marketplace_seller_profiles')
                    ->onDelete('cascade');

                $table->index(['tenant_id', 'seller_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shipping_options');
        Schema::dropIfExists('marketplace_reports');
    }
};
