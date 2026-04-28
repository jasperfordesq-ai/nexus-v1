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
        if (Schema::hasTable('marketplace_seller_regional_point_settings')) {
            return;
        }

        Schema::create('marketplace_seller_regional_point_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('seller_user_id');
            $table->boolean('accepts_regional_points')->default(false);
            $table->decimal('regional_points_per_chf', 10, 2)->default(10.00);
            $table->unsignedTinyInteger('regional_points_max_discount_pct')->default(25);
            $table->timestamps();

            $table->unique(['tenant_id', 'seller_user_id'], 'msrps_tenant_seller_unique');
            $table->index(['tenant_id', 'accepts_regional_points'], 'msrps_tenant_accepts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_seller_regional_point_settings');
    }
};
