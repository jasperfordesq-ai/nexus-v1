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
        if (! Schema::hasTable('monthly_engagement')) {
            Schema::create('monthly_engagement', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('year_month', 7); // '2026-03'
                $table->boolean('was_active')->default(false);
                $table->unsignedInteger('activity_count')->default(0);
                $table->timestamp('recognized_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'year_month'], 'uniq_monthly_engagement');
                $table->index('tenant_id', 'idx_me_tenant');
                $table->index(['user_id', 'year_month'], 'idx_me_user_month');
            });
        }

        if (! Schema::hasTable('seasonal_recognition')) {
            Schema::create('seasonal_recognition', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('season', 20); // '2026-Q1'
                $table->unsignedSmallInteger('months_active')->default(0);
                $table->timestamp('recognized_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'season'], 'uniq_seasonal_recognition');
                $table->index('tenant_id', 'idx_sr_tenant');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seasonal_recognition');
        Schema::dropIfExists('monthly_engagement');
    }
};
