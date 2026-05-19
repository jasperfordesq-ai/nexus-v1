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
        if (!Schema::hasTable('civic_digest_delivery_claims')) {
            Schema::create('civic_digest_delivery_claims', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('user_id');
                $table->string('cadence', 20);
                $table->string('window_key', 32);
                $table->string('status', 20)->default('claimed');
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('sent_at')->nullable();
                $table->json('delivery_evidence')->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'user_id', 'cadence', 'window_key'],
                    'uk_civic_digest_delivery_claim'
                );
                $table->index(['tenant_id', 'status', 'claimed_at'], 'idx_civic_digest_claim_status');
                $table->index(['tenant_id', 'user_id', 'sent_at'], 'idx_civic_digest_user_sent');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_digest_delivery_claims');
    }
};
