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
        if (Schema::hasTable('caring_help_requests')) {
            return;
        }

        Schema::create('caring_help_requests', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id')->index();
            $table->integer('user_id')->index();
            $table->text('what');
            $table->string('when_needed', 200);
            $table->enum('contact_preference', ['phone', 'message', 'either'])->default('either');
            $table->enum('status', ['pending', 'matched', 'closed'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'idx_chr_tenant_status');
            $table->index(['tenant_id', 'user_id'], 'idx_chr_tenant_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_help_requests');
    }
};
