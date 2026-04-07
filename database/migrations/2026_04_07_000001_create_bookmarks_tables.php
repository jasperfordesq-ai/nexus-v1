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
        if (!Schema::hasTable('bookmark_collections')) {
            Schema::create('bookmark_collections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'user_id', 'name']);
            });
        }

        if (!Schema::hasTable('bookmarks')) {
            Schema::create('bookmarks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('bookmarkable_type', 50);
                $table->unsignedBigInteger('bookmarkable_id');
                $table->unsignedBigInteger('collection_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'user_id', 'bookmarkable_type', 'bookmarkable_id'], 'bookmarks_unique');
                $table->index(['bookmarkable_type', 'bookmarkable_id']);

                $table->foreign('collection_id')
                    ->references('id')
                    ->on('bookmark_collections')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('bookmark_collections');
    }
};
