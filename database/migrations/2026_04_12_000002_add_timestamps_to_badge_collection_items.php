<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9: Add created_at / updated_at to badge_collection_items.
 *
 * The BadgeCollectionItem model previously set $timestamps = false because
 * the table had no timestamp columns. Adding them enables Eloquent's
 * standard auditing and unblocks the model to track row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('badge_collection_items')) {
            return;
        }

        Schema::table('badge_collection_items', function (Blueprint $table) {
            if (!Schema::hasColumn('badge_collection_items', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
            if (!Schema::hasColumn('badge_collection_items', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('badge_collection_items')) {
            return;
        }

        Schema::table('badge_collection_items', function (Blueprint $table) {
            if (Schema::hasColumn('badge_collection_items', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            if (Schema::hasColumn('badge_collection_items', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};
