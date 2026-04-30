<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('caring_sub_regions')) {
            Schema::create('caring_sub_regions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->index();
                $table->string('name');
                $table->string('slug');
                $table->enum('type', ['quartier', 'ortsteil', 'municipality', 'canton', 'other'])->default('quartier');
                $table->text('description')->nullable();
                $table->json('postal_codes')->nullable();
                $table->json('boundary_geojson')->nullable();
                $table->decimal('center_latitude', 10, 7)->nullable();
                $table->decimal('center_longitude', 10, 7)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'type']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (Schema::hasTable('caring_care_providers') && ! Schema::hasColumn('caring_care_providers', 'sub_region_id')) {
            Schema::table('caring_care_providers', function (Blueprint $table) {
                $table->unsignedBigInteger('sub_region_id')->nullable()->after('address');
                $table->index(['tenant_id', 'sub_region_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('caring_care_providers') && Schema::hasColumn('caring_care_providers', 'sub_region_id')) {
            Schema::table('caring_care_providers', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'sub_region_id']);
                $table->dropColumn('sub_region_id');
            });
        }

        Schema::dropIfExists('caring_sub_regions');
    }
};
