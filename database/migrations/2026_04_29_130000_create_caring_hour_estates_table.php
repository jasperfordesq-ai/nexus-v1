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
        if (Schema::hasTable('caring_hour_estates')) {
            return;
        }

        Schema::create('caring_hour_estates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->unsignedInteger('member_user_id')->index();
            $table->unsignedInteger('beneficiary_user_id')->nullable()->index();
            $table->enum('policy_action', ['transfer_to_beneficiary', 'donate_to_solidarity', 'expire'])->default('donate_to_solidarity');
            $table->enum('status', ['nominated', 'reported', 'settled', 'cancelled'])->default('nominated');
            $table->decimal('reported_balance_hours', 8, 2)->nullable();
            $table->decimal('settled_hours', 8, 2)->nullable();
            $table->string('policy_document_reference')->nullable();
            $table->text('member_notes')->nullable();
            $table->text('coordinator_notes')->nullable();
            $table->timestamp('nominated_at')->nullable();
            $table->timestamp('reported_deceased_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedInteger('reported_by')->nullable();
            $table->unsignedInteger('settled_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_user_id'], 'caring_hour_estates_tenant_member_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_hour_estates');
    }
};
