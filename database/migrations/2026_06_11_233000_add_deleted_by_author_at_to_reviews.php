<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Author-deleted reviews were indistinguishable from moderator-rejected ones
 * (both status='rejected'), so the admin "rejected" queue could resurrect a
 * review its author had deleted (flag → status='pending').
 *
 * We deliberately do NOT touch the status enum (strict=false MariaDB silently
 * corrupts invalid enum writes to ''; enum ALTERs also trip the destructive-
 * migration deploy gate). Instead a nullable marker column records that the
 * author deleted the review; admin moderation excludes marked rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'deleted_by_author_at')) {
                $table->timestamp('deleted_by_author_at')->nullable()->after('status');
                $table->index('deleted_by_author_at', 'idx_reviews_deleted_by_author');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'deleted_by_author_at')) {
                $table->dropIndex('idx_reviews_deleted_by_author');
                $table->dropColumn('deleted_by_author_at');
            }
        });
    }
};
