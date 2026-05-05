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
        if (Schema::hasTable('caring_favours')) {
            return;
        }

        Schema::create('caring_favours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('offered_by_user_id');
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->string('category', 100)->nullable();
            $table->text('description');
            $table->date('favour_date');
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'offered_by_user_id']);
            $table->index(['tenant_id', 'favour_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_favours');
    }
};
