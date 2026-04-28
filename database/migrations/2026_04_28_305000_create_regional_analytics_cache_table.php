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
 * AG59 — Regional Analytics Product
 * Creates the cache table for expensive aggregation results.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('regional_analytics_cache')) {
            return;
        }

        Schema::create('regional_analytics_cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('report_type', 100);  // 'member_heatmap','demand_supply','demographics','engagement_trend','volunteer_breakdown','help_requests','overview'
            $table->string('period', 20);         // 'last_30d','last_90d','last_12m','all_time'
            $table->longText('payload');           // JSON — the computed result
            $table->dateTime('computed_at');
            $table->dateTime('expires_at');

            $table->index(['tenant_id', 'report_type', 'period'], 'rac_tenant_type_period');
            $table->unique(['tenant_id', 'report_type', 'period'], 'rac_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_analytics_cache');
    }
};
