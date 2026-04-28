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
        if (Schema::hasTable('municipal_verifications')) {
            return;
        }

        Schema::create('municipal_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->string('domain', 253);
            $table->enum('method', ['dns_txt', 'admin_attestation'])->default('dns_txt');
            $table->enum('status', ['pending', 'verified', 'revoked'])->default('pending');
            $table->string('dns_record_name', 253)->nullable();
            $table->string('dns_record_value', 255)->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('attestation_note', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'domain'], 'municipal_verifications_tenant_domain_unique');
            $table->index(['tenant_id', 'status'], 'municipal_verifications_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_verifications');
    }
};
