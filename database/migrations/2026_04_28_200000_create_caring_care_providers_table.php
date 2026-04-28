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
        if (Schema::hasTable('caring_care_providers')) {
            return;
        }

        Schema::create('caring_care_providers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('name');
            $table->enum('type', ['spitex', 'tagesstätte', 'private', 'verein', 'volunteer']);
            $table->text('description')->nullable();
            $table->json('categories')->nullable(); // array of category strings
            $table->string('address')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website_url')->nullable();
            $table->json('opening_hours')->nullable(); // { mon: "08:00-17:00", ... }
            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('created_by')->nullable(); // admin user id
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_care_providers');
    }
};
