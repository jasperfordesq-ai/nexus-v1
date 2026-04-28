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
        if (Schema::hasTable('caring_smart_nudges')) {
            return;
        }

        Schema::create('caring_smart_nudges', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('target_user_id');
            $table->unsignedInteger('related_user_id')->nullable();
            $table->string('source_type', 64)->default('tandem_candidate');
            $table->decimal('score', 5, 3)->default(0);
            $table->json('signals')->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->string('status', 32)->default('sent');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'target_user_id', 'sent_at'], 'caring_nudges_target_sent_idx');
            $table->index(['tenant_id', 'related_user_id', 'sent_at'], 'caring_nudges_related_sent_idx');
            $table->index(['tenant_id', 'status', 'sent_at'], 'caring_nudges_status_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_smart_nudges');
    }
};
