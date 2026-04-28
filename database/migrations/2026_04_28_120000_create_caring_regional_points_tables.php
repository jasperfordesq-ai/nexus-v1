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
        if (!Schema::hasTable('caring_regional_point_accounts')) {
            Schema::create('caring_regional_point_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->decimal('balance', 12, 2)->default(0);
                $table->decimal('lifetime_earned', 12, 2)->default(0);
                $table->decimal('lifetime_spent', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id'], 'crpa_tenant_user_unique');
                $table->index(['tenant_id', 'balance'], 'crpa_tenant_balance_idx');
            });
        }

        if (!Schema::hasTable('caring_regional_point_transactions')) {
            Schema::create('caring_regional_point_transactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedBigInteger('account_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('actor_user_id')->nullable();
                $table->enum('type', [
                    'admin_issue',
                    'admin_adjustment',
                    'earned_for_hours',
                    'transfer_in',
                    'transfer_out',
                    'redemption',
                    'reversal',
                ]);
                $table->enum('direction', ['credit', 'debit']);
                $table->decimal('points', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('description', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'user_id', 'created_at'], 'crpt_tenant_user_created_idx');
                $table->index(['tenant_id', 'type', 'created_at'], 'crpt_tenant_type_created_idx');
                $table->index(['tenant_id', 'reference_type', 'reference_id'], 'crpt_tenant_ref_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_regional_point_transactions');
        Schema::dropIfExists('caring_regional_point_accounts');
    }
};
