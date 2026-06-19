<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct (1:1 sender→receiver) messages support voice already (messages.audio_url),
 * but file/image attachments sent by the React composer (FormData attachments[])
 * were silently dropped: MessageService::send never persisted them and there was
 * no column/table to hold them. This adds a dedicated message_attachments table so
 * a single message can carry multiple files, mirroring the React MessageBubble
 * file_url/file_name expectation. Additive + idempotent (safe for blue-green).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_attachments')) {
            return;
        }

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            // tenant_id mirrors the int(11) width used across the schema so the
            // column type matches the tenants/messages tables (signed int).
            $table->integer('tenant_id');
            $table->integer('message_id');
            $table->string('file_url', 1000);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'message_id'], 'idx_msg_attach_tenant_message');
            $table->index('message_id', 'idx_msg_attach_message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
