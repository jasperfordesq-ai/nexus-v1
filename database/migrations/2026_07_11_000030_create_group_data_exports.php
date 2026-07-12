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
        if (Schema::hasTable('group_data_exports')) {
            return;
        }

        Schema::create('group_data_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('requested_by');
            $table->string('status', 20)->default('queued');
            $table->string('storage_path', 500)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['tenant_id', 'group_id', 'requested_by', 'created_at'], 'idx_group_exports_requester');
            $table->index(['status', 'expires_at'], 'idx_group_exports_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_data_exports');
    }
};
