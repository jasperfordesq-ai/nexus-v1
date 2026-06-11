<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug-hunt 2026-06-11 round 4: two check-then-create races had no
 * database-level backstop.
 *
 * - group_members had no UNIQUE(group_id, user_id): a double-click on
 *   "Join" created duplicate membership rows (double member counts,
 *   duplicate welcome messages/webhooks).
 * - marketplace_escrow had no UNIQUE(order_id): holdFunds() idempotency
 *   was application-level only.
 *
 * Both blocks dedupe existing rows first (keeping the best-role/oldest
 * row), then add the constraint. Idempotent via index-existence checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_members') && ! $this->indexExists('group_members', 'uq_group_members_group_user')) {
            // Dedupe: keep one row per (group_id, user_id) — prefer
            // owner > admin > member, then the oldest row.
            DB::statement("
                DELETE gm FROM group_members gm
                INNER JOIN group_members keeper
                    ON keeper.group_id = gm.group_id
                   AND keeper.user_id = gm.user_id
                   AND (
                        FIELD(keeper.role, 'owner', 'admin', 'member') < FIELD(gm.role, 'owner', 'admin', 'member')
                        OR (keeper.role = gm.role AND keeper.id < gm.id)
                   )
            ");

            Schema::table('group_members', function (Blueprint $table) {
                $table->unique(['group_id', 'user_id'], 'uq_group_members_group_user');
            });
        }

        if (Schema::hasTable('marketplace_escrow') && ! $this->indexExists('marketplace_escrow', 'uq_marketplace_escrow_order')) {
            // Dedupe: keep the oldest escrow row per order.
            DB::statement("
                DELETE e FROM marketplace_escrow e
                INNER JOIN marketplace_escrow keeper
                    ON keeper.order_id = e.order_id
                   AND keeper.id < e.id
            ");

            Schema::table('marketplace_escrow', function (Blueprint $table) {
                $table->unique('order_id', 'uq_marketplace_escrow_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_members') && $this->indexExists('group_members', 'uq_group_members_group_user')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropUnique('uq_group_members_group_user');
            });
        }

        if (Schema::hasTable('marketplace_escrow') && $this->indexExists('marketplace_escrow', 'uq_marketplace_escrow_order')) {
            Schema::table('marketplace_escrow', function (Blueprint $table) {
                $table->dropUnique('uq_marketplace_escrow_order');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(DB::select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$index]
        ));
    }
};
