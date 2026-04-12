<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a DB-level unique index on `reactions` keyed by
 * (tenant_id, user_id, target_type, target_id) to guarantee one reaction
 * row per user per target, complementing the transaction + row lock in
 * App\Services\ReactionService.
 *
 * The existing `unique_user_reaction` key included `emoji`, which does NOT
 * prevent duplicates: the service treats (user, target) as the uniqueness
 * key and updates the emoji in place when a different reaction type is
 * chosen. A race between two concurrent inserts with different emoji
 * values could therefore leave two rows for the same (user, target).
 *
 * This migration:
 *   1. Removes any existing duplicate rows, keeping the oldest (lowest id)
 *      per (tenant_id, user_id, target_type, target_id) key.
 *   2. Drops the old `unique_user_reaction` key if present.
 *   3. Adds `reactions_unique` on the correct columns.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reactions')) {
            return;
        }

        // Step 1: delete duplicate rows, keeping the one with the lowest id per key.
        DB::statement(
            'DELETE r1 FROM reactions r1
             INNER JOIN reactions r2
                 ON r1.tenant_id   = r2.tenant_id
                AND r1.user_id     = r2.user_id
                AND r1.target_type = r2.target_type
                AND r1.target_id   = r2.target_id
                AND r1.id          > r2.id'
        );

        // Step 2: drop the old, emoji-qualified unique key if it still exists.
        $oldKey = DB::select(
            'SHOW INDEX FROM reactions WHERE Key_name = ?',
            ['unique_user_reaction']
        );
        if (!empty($oldKey)) {
            DB::statement('ALTER TABLE reactions DROP INDEX unique_user_reaction');
        }

        // Step 3: add the correct unique index (idempotent).
        $newKey = DB::select(
            'SHOW INDEX FROM reactions WHERE Key_name = ?',
            ['reactions_unique']
        );
        if (empty($newKey)) {
            DB::statement(
                'ALTER TABLE reactions
                 ADD UNIQUE INDEX reactions_unique
                     (tenant_id, user_id, target_type, target_id)'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('reactions')) {
            return;
        }

        $newKey = DB::select(
            'SHOW INDEX FROM reactions WHERE Key_name = ?',
            ['reactions_unique']
        );
        if (!empty($newKey)) {
            DB::statement('ALTER TABLE reactions DROP INDEX reactions_unique');
        }

        // Restore the prior (less-correct) key so down() is a true inverse.
        $oldKey = DB::select(
            'SHOW INDEX FROM reactions WHERE Key_name = ?',
            ['unique_user_reaction']
        );
        if (empty($oldKey)) {
            DB::statement(
                'ALTER TABLE reactions
                 ADD UNIQUE INDEX unique_user_reaction
                     (user_id, target_type, target_id, emoji)'
            );
        }
    }
};
