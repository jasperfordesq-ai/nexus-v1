<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Remove leftover junk columns from notification_queue that were never used
 * and had no business purpose (clicked_at, match, reset_token, simplicity).
 *
 * Also ensures the 'processing' status enum value exists (added by legacy
 * migration 2026_03_29_add_tenant_id_to_notification_queue.sql but not
 * reflected in the schema dump).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop junk columns if they still exist
        $columns = ['clicked_at', 'match', 'reset_token', 'simplicity'];
        foreach ($columns as $col) {
            if (Schema::hasColumn('notification_queue', $col)) {
                Schema::table('notification_queue', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        // Ensure 'processing' is in the status enum (idempotent)
        $type = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'notification_queue'
               AND COLUMN_NAME  = 'status'"
        );
        if ($type && strpos($type->COLUMN_TYPE, 'processing') === false) {
            DB::statement(
                "ALTER TABLE `notification_queue`
                 MODIFY COLUMN `status`
                 ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        // Restore dropped columns (best-effort rollback)
        Schema::table('notification_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_queue', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable();
            }
            if (!Schema::hasColumn('notification_queue', 'match')) {
                $table->timestamp('match')->nullable();
            }
            if (!Schema::hasColumn('notification_queue', 'reset_token')) {
                $table->string('reset_token', 255)->nullable();
            }
            if (!Schema::hasColumn('notification_queue', 'simplicity')) {
                $table->string('simplicity', 255)->nullable();
            }
        });
    }
};
