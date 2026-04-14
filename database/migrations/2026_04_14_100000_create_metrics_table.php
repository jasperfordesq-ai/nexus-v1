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
        if (!Schema::hasTable('metrics')) {
            Schema::create('metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('event', 100);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'event', 'created_at']);
            });
        } elseif (!Schema::hasColumn('metrics', 'tenant_id')) {
            Schema::table('metrics', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->index()->after('id');
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
