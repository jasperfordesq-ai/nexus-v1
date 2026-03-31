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
        if (Schema::hasTable('knowledge_base_attachments')) {
            return;
        }

        Schema::create('knowledge_base_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('article_id');
            $table->unsignedInteger('tenant_id');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_url', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->default(0);
            $table->integer('sort_order')->default(0);
            $table->datetime('created_at')->useCurrent();

            $table->index(['article_id', 'tenant_id'], 'idx_kb_attach_article_tenant');
            $table->index('tenant_id', 'idx_kb_attach_tenant');

            $table->foreign('article_id')
                ->references('id')
                ->on('knowledge_base_articles')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_attachments');
    }
};
