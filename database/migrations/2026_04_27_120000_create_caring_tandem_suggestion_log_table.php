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
        if (Schema::hasTable('caring_tandem_suggestion_log')) {
            return;
        }

        Schema::create('caring_tandem_suggestion_log', function (Blueprint $table): void {
            $table->id();
            $table->integer('tenant_id')->index();
            $table->integer('supporter_user_id');
            $table->integer('recipient_user_id');
            $table->enum('action', ['created_relationship', 'dismissed']);
            $table->integer('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['tenant_id', 'supporter_user_id', 'recipient_user_id'],
                'idx_ctsl_tenant_pair_unique'
            );
            $table->index(['tenant_id', 'created_at'], 'idx_ctsl_tenant_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_tandem_suggestion_log');
    }
};
