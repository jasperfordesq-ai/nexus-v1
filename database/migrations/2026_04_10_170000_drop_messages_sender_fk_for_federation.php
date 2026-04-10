<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Drop the foreign key on messages.sender_id so that external federation
 * partners can store messages where sender_id references a user on a
 * remote server (not in the local users table).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check if the FK exists before trying to drop (idempotent)
        $fks = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'messages_ibfk_2'
        ");

        if (!empty($fks)) {
            Schema::table('messages', function ($table) {
                $table->dropForeign('messages_ibfk_2');
            });
        }
    }

    public function down(): void
    {
        // Re-add FK if needed (may fail if orphan rows exist)
        Schema::table('messages', function ($table) {
            $table->foreign('sender_id', 'messages_ibfk_2')
                ->references('id')->on('users')->onDelete('cascade');
        });
    }
};
