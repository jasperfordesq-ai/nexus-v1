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
        // Add 'pending' to the users.status ENUM so that RegistrationService
        // can set status='pending' for newly registered users awaiting email verification.
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive','suspended','banned','pending') DEFAULT 'active'");
    }

    public function down(): void
    {
        // First update any pending users to inactive so we don't lose data
        DB::table('users')->where('status', 'pending')->update(['status' => 'inactive']);
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive','suspended','banned') DEFAULT 'active'");
    }
};
