<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix 8: Expand reports.target_type ENUM to include 'post', 'comment', 'story'.
 *
 * The existing ENUM only covered 'listing','user','message' but FeedController
 * writes 'post', 'comment', and 'story' — causing silent truncation / DB errors.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        if (!Schema::hasColumn('reports', 'target_type')) {
            return;
        }

        DB::statement("ALTER TABLE `reports`
            MODIFY COLUMN `target_type`
            ENUM('listing','user','message','post','comment','story') NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('reports')) {
            return;
        }

        if (!Schema::hasColumn('reports', 'target_type')) {
            return;
        }

        // Revert to original ENUM values (rows with 'post','comment','story' will
        // need to be removed manually if strict mode is enabled)
        DB::statement("ALTER TABLE `reports`
            MODIFY COLUMN `target_type`
            ENUM('listing','user','message') NOT NULL");
    }
};
