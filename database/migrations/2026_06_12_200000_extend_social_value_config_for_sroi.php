<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends social_value_config with the parameters required for a
 * methodology-correct SROI calculation (Social Value International model):
 * total investment, counterfactual deduction coefficients (deadweight,
 * displacement, attribution), temporal drop-off, discount rate, and
 * projection horizon. Adds social_value_outcomes for per-tenant outcome
 * categories (stakeholder quantity × financial proxy value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_value_config', function (Blueprint $table) {
            if (!Schema::hasColumn('social_value_config', 'investment_amount')) {
                $table->decimal('investment_amount', 12, 2)->nullable()->default(null)
                    ->comment('Total investment for the reporting period (SROI denominator); null = not configured');
            }
            if (!Schema::hasColumn('social_value_config', 'deadweight_pct')) {
                $table->decimal('deadweight_pct', 5, 2)->default(10.00)
                    ->comment('% of outcome that would have happened anyway');
            }
            if (!Schema::hasColumn('social_value_config', 'displacement_pct')) {
                $table->decimal('displacement_pct', 5, 2)->default(10.00)
                    ->comment('% of outcome displaced from elsewhere');
            }
            if (!Schema::hasColumn('social_value_config', 'attribution_pct')) {
                $table->decimal('attribution_pct', 5, 2)->default(10.00)
                    ->comment('% of outcome attributable to other actors');
            }
            if (!Schema::hasColumn('social_value_config', 'dropoff_pct')) {
                $table->decimal('dropoff_pct', 5, 2)->default(70.00)
                    ->comment('% of impact lost per subsequent year');
            }
            if (!Schema::hasColumn('social_value_config', 'discount_rate_pct')) {
                $table->decimal('discount_rate_pct', 5, 2)->default(3.50)
                    ->comment('Social discount rate (HM Treasury Green Book default 3.5%)');
            }
            if (!Schema::hasColumn('social_value_config', 'projection_years')) {
                $table->unsignedTinyInteger('projection_years')->default(2)
                    ->comment('Years of benefit projection including year 1');
            }
        });

        if (!Schema::hasTable('social_value_outcomes')) {
            Schema::create('social_value_outcomes', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('tenant_id')->index();
                $table->string('name', 150);
                $table->unsignedInteger('quantity')->default(0)
                    ->comment('Verified stakeholders experiencing this outcome (Q)');
                $table->decimal('proxy_value', 12, 2)->default(0)
                    ->comment('Financial proxy value per stakeholder (P)');
                $table->string('proxy_source')->nullable()
                    ->comment('Provenance of the proxy, e.g. HACT Social Value Bank');
                $table->smallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_value_outcomes');

        Schema::table('social_value_config', function (Blueprint $table) {
            foreach ([
                'investment_amount', 'deadweight_pct', 'displacement_pct',
                'attribution_pct', 'dropoff_pct', 'discount_rate_pct', 'projection_years',
            ] as $column) {
                if (Schema::hasColumn('social_value_config', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
