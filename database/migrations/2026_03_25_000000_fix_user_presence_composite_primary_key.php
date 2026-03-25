<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix user_presence PRIMARY KEY from (user_id) to composite (tenant_id, user_id).
 *
 * The original migration used PRIMARY KEY (user_id) which breaks multi-tenant
 * isolation — a user logged into two different tenants simultaneously would
 * collide on the same row. The composite key ensures one presence row per
 * user per tenant, which is correct for ON DUPLICATE KEY UPDATE logic in
 * PresenceService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_presence')) {
            return;
        }

        // Check if primary key already includes tenant_id (idempotent)
        $columns = DB::select("SHOW COLUMNS FROM user_presence WHERE `Key` = 'PRI'");
        $pkColumns = array_column($columns, 'Field');

        if (in_array('tenant_id', $pkColumns, true)) {
            return; // Already composite — nothing to do
        }

        // Drop the existing single-column PK and create composite PK
        DB::statement('ALTER TABLE user_presence DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, user_id)');
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_presence')) {
            return;
        }

        // Revert to single-column PK (lossy if duplicate user_ids exist across tenants)
        DB::statement('ALTER TABLE user_presence DROP PRIMARY KEY, ADD PRIMARY KEY (user_id)');
    }
};
