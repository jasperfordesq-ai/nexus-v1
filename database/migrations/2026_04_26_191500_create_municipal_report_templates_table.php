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
        if (Schema::hasTable('municipal_report_templates')) {
            return;
        }

        Schema::create('municipal_report_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('audience', 40)->default('municipality');
            $table->string('date_preset', 40)->default('last_90_days');
            $table->boolean('include_social_value')->default(true);
            $table->unsignedSmallInteger('hour_value_chf')->nullable();
            $table->json('sections')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name'], 'municipal_report_templates_tenant_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_report_templates');
    }
};
