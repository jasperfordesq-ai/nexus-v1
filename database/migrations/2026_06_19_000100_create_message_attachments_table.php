<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The message_attachments table is created by the legacy SQL migration
 * migrations/2026_02_07_message_attachments.sql and exists on production. This
 * Laravel migration is GUARDED (hasTable) so it is a no-op wherever the table
 * already exists; its only job is to recreate the SAME schema on a fresh
 * environment that has neither the legacy SQL nor the schema dump applied.
 *
 * MessageService::send / MessageAttachmentUploader write file_path (NOT NULL)
 * and file_type as well as file_url/file_name/mime_type/file_size, so this
 * definition MUST match the legacy table exactly (file_path/file_type included)
 * or attachment inserts would fail. Additive + idempotent (safe for blue-green).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_attachments')) {
            return;
        }

        // Mirror migrations/2026_02_07_message_attachments.sql exactly.
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->increments('id'); // int unsigned auto-increment
            $table->unsignedInteger('message_id');
            $table->unsignedInteger('tenant_id');
            $table->string('file_name', 255);
            $table->string('file_path', 500);                 // storage path (NOT NULL)
            $table->string('file_url', 500);                  // public URL (NOT NULL)
            $table->string('file_type', 20)->default('file'); // 'image' | 'file'
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('message_id', 'idx_message_id');
            $table->index('tenant_id', 'idx_tenant_id');
        });
    }

    public function down(): void
    {
        // Intentionally NOT dropped: the table predates this migration (created by
        // the 2026_02_07 legacy SQL), so a rollback here must not destroy it.
    }
};
