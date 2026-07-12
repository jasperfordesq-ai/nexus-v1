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
        if (Schema::hasTable('event_domain_outbox')) {
            return;
        }

        Schema::create('event_domain_outbox', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('tenant_id');
            $table->integer('event_id');
            $table->unsignedBigInteger('aggregate_version')->default(1);
            $table->string('action', 80);
            $table->string('idempotency_key', 191);
            $table->string('production_mode', 32)->default('direct');
            $table->string('status', 32)->default('direct');
            $table->json('payload');
            $table->timestamp('available_at')->nullable();
            $table->char('claim_token', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'uq_event_outbox_tenant_key');
            $table->index(['status', 'available_at', 'next_attempt_at', 'id'], 'idx_event_outbox_claim');
            $table->index(['tenant_id', 'event_id', 'aggregate_version'], 'idx_event_outbox_aggregate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_domain_outbox');
    }
};
