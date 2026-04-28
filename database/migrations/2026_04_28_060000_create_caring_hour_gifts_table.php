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
        if (Schema::hasTable('caring_hour_gifts')) {
            return;
        }

        Schema::create('caring_hour_gifts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('sender_user_id');
            $table->unsignedInteger('recipient_user_id');
            $table->decimal('hours', 8, 2);
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'reverted'])->default('pending');
            $table->text('decline_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sender_user_id'], 'idx_caring_hour_gift_tenant_sender');
            $table->index(['tenant_id', 'recipient_user_id'], 'idx_caring_hour_gift_tenant_recipient');
            $table->index(['tenant_id', 'status'], 'idx_caring_hour_gift_tenant_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_hour_gifts');
    }
};
