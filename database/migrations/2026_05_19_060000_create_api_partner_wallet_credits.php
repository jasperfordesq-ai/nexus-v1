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
        if (!Schema::hasTable('api_partner_wallet_credits')) {
            Schema::create('api_partner_wallet_credits', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('reference', 191);
                $table->unsignedInteger('transaction_id')->nullable();
                $table->decimal('hours', 10, 2);
                $table->string('status', 20)->default('processing');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'partner_id', 'reference'], 'uk_partner_wallet_credit_reference');
                $table->index(['tenant_id', 'user_id', 'completed_at'], 'idx_partner_wallet_credit_user');
                $table->index('transaction_id', 'idx_partner_wallet_credit_transaction');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_partner_wallet_credits');
    }
};
