<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'rejected' to the status ENUM so rejected requests are distinguishable
        // from terminated partnerships and can be re-requested
        DB::statement("ALTER TABLE federation_partnerships MODIFY COLUMN status ENUM('pending','active','suspended','terminated','rejected') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Convert any 'rejected' rows back to 'terminated' before shrinking the ENUM
        DB::table('federation_partnerships')->where('status', 'rejected')->update(['status' => 'terminated']);
        DB::statement("ALTER TABLE federation_partnerships MODIFY COLUMN status ENUM('pending','active','suspended','terminated') NOT NULL DEFAULT 'pending'");
    }
};
