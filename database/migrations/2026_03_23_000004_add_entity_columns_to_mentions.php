<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add entity_type and entity_id columns to the mentions table.
 *
 * The MentionService stores mentions for posts, comments, and messages.
 * Previously only comment_id existed; now entity_type/entity_id support
 * all entity types. comment_id is made nullable for backward compat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            if (! Schema::hasColumn('mentions', 'entity_type')) {
                $table->string('entity_type', 50)->default('comment')->after('tenant_id');
            }
            if (! Schema::hasColumn('mentions', 'entity_id')) {
                $table->integer('entity_id')->nullable()->after('entity_type');
            }
        });

        // Make comment_id nullable (it was NOT NULL before, but non-comment
        // mentions don't have a comment_id).
        Schema::table('mentions', function (Blueprint $table) {
            $table->integer('comment_id')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropColumn(['entity_type', 'entity_id']);
            $table->integer('comment_id')->nullable(false)->change();
        });
    }
};
