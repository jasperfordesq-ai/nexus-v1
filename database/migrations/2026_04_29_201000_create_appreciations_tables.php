<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('appreciations')) {
            Schema::create('appreciations', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Match users.id and tenants.id which are int(11) signed on this DB
                $table->integer('sender_id');
                $table->integer('receiver_id');
                $table->integer('tenant_id');
                $table->text('message');
                $table->string('context_type', 32)->nullable();
                $table->unsignedBigInteger('context_id')->nullable();
                $table->boolean('is_public')->default(true);
                $table->unsignedInteger('reactions_count')->default(0);
                $table->timestamps();

                $table->index(['receiver_id', 'created_at'], 'appreciations_receiver_created_idx');
                $table->index('sender_id', 'appreciations_sender_idx');
                $table->index(['tenant_id', 'is_public'], 'appreciations_tenant_public_idx');
                $table->index(['context_type', 'context_id'], 'appreciations_context_idx');

                $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('receiver_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('appreciation_reactions')) {
            Schema::create('appreciation_reactions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('appreciation_id');
                // Match users.id and tenants.id which are int(11) signed on this DB
                $table->integer('user_id');
                $table->string('reaction_type', 16);
                $table->integer('tenant_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['appreciation_id', 'user_id'], 'appreciation_reactions_unique');
                $table->index('tenant_id', 'appreciation_reactions_tenant_idx');

                $table->foreign('appreciation_id')->references('id')->on('appreciations')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciation_reactions');
        Schema::dropIfExists('appreciations');
    }
};
