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
        // Add group conversation columns to messages table.
        // The existing system uses sender_id/receiver_id pairs without a conversations table.
        // For group DMs, we add a conversation_id that messages reference.
        // 1-to-1 messages continue using sender_id/receiver_id with conversation_id = NULL.

        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->boolean('is_group')->default(false);
                $table->string('group_name', 100)->nullable();
                $table->string('group_avatar_url', 500)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'is_group']);
            });
        }

        if (!Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('role', ['admin', 'member'])->default('member');
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('left_at')->nullable();
                $table->timestamp('muted_until')->nullable();
                $table->unique(['tenant_id', 'conversation_id', 'user_id'], 'unique_participant');
                $table->index(['conversation_id', 'user_id'], 'idx_cp_conv_user');
                $table->index('user_id', 'idx_cp_user');
                $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            });
        }

        // Add conversation_id to messages for group messages
        if (!Schema::hasColumn('messages', 'conversation_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('conversation_id')->nullable()->after('tenant_id');
                $table->index('conversation_id', 'idx_msg_conversation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'conversation_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('idx_msg_conversation');
                $table->dropColumn('conversation_id');
            });
        }
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
