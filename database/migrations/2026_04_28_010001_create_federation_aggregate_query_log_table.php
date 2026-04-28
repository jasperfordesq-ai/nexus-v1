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
        if (Schema::hasTable('federation_aggregate_query_log')) {
            return;
        }

        Schema::create('federation_aggregate_query_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('requester_origin', 255)->nullable();
            $table->date('period_from');
            $table->date('period_to');
            $table->json('fields_returned')->nullable();
            $table->string('response_signature', 128);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'requester_origin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_aggregate_query_log');
    }
};
