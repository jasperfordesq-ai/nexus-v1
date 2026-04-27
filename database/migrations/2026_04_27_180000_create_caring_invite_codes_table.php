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
        if (Schema::hasTable('caring_invite_codes')) {
            return;
        }

        Schema::create('caring_invite_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('code', 10);
            $table->string('label', 200)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->unsignedBigInteger('used_by_user_id')->nullable();
            $table->timestamps();

            // One code is unique within a tenant
            $table->unique(['tenant_id', 'code']);

            // Index for listing by coordinator
            $table->index(['tenant_id', 'created_by_user_id']);

            // Index for expiry sweeps
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_invite_codes');
    }
};
