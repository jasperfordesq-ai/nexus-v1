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
        if (! Schema::hasTable('member_residency_verifications')) {
            Schema::create('member_residency_verifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->string('declared_municipality', 120);
                $table->string('declared_postcode', 24);
                $table->string('declared_address', 255)->nullable();
                $table->text('evidence_note')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
                $table->unsignedInteger('attested_by')->nullable()->index();
                $table->timestamp('attested_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_residency_verifications');
    }
};
