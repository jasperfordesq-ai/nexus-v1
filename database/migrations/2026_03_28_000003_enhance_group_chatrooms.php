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
        Schema::table('group_chatrooms', function (Blueprint $table) {
            if (!Schema::hasColumn('group_chatrooms', 'category')) {
                $table->string('category', 100)->nullable()->after('description');
            }
            if (!Schema::hasColumn('group_chatrooms', 'is_private')) {
                $table->boolean('is_private')->default(false)->after('category');
            }
            if (!Schema::hasColumn('group_chatrooms', 'permissions')) {
                $table->json('permissions')->nullable()->after('is_private');
            }
        });

        if (!Schema::hasTable('group_chatroom_pinned_messages')) {
            Schema::create('group_chatroom_pinned_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('chatroom_id');
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('pinned_by');
                $table->unsignedInteger('tenant_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['chatroom_id', 'message_id'], 'uq_chatroom_pinned_msg');

                $table->foreign('chatroom_id')
                    ->references('id')
                    ->on('group_chatrooms')
                    ->onDelete('cascade');

                $table->foreign('message_id')
                    ->references('id')
                    ->on('group_chatroom_messages')
                    ->onDelete('cascade');

                $table->foreign('pinned_by')
                    ->references('id')
                    ->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chatroom_pinned_messages');

        Schema::table('group_chatrooms', function (Blueprint $table) {
            if (Schema::hasColumn('group_chatrooms', 'permissions')) {
                $table->dropColumn('permissions');
            }
            if (Schema::hasColumn('group_chatrooms', 'is_private')) {
                $table->dropColumn('is_private');
            }
            if (Schema::hasColumn('group_chatrooms', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
