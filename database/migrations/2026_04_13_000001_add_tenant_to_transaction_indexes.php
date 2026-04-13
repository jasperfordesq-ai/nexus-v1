<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rework transactions composite indexes to lead with tenant_id.
 *
 * The prior migration (2026_03_28_000006_add_performance_indexes) created:
 *   idx_txn_sender_status   (sender_id, status)
 *   idx_txn_receiver_status (receiver_id, status)
 *
 * These omit tenant_id, which means:
 *   1. Queries that always scope by tenant_id (WalletService etc.) can't use
 *      them as efficiently — the optimizer must still hit the tenant_id index.
 *   2. In a worst-case query plan, MySQL could choose an index that doesn't
 *      enforce tenant scoping at the storage layer.
 *
 * Replace with tenant_id-leading composites:
 *   idx_txn_tenant_sender_status   (tenant_id, sender_id, status)
 *   idx_txn_tenant_receiver_status (tenant_id, receiver_id, status)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        // Drop the old non-tenant-leading indexes (if present)
        if ($this->indexExists('transactions', 'idx_txn_sender_status')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `idx_txn_sender_status`');
        }
        if ($this->indexExists('transactions', 'idx_txn_receiver_status')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `idx_txn_receiver_status`');
        }

        // Add tenant_id-leading composites
        if (! $this->indexExists('transactions', 'idx_txn_tenant_sender_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_tenant_sender_status` (`tenant_id`, `sender_id`, `status`)');
        }
        if (! $this->indexExists('transactions', 'idx_txn_tenant_receiver_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_tenant_receiver_status` (`tenant_id`, `receiver_id`, `status`)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if ($this->indexExists('transactions', 'idx_txn_tenant_sender_status')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `idx_txn_tenant_sender_status`');
        }
        if ($this->indexExists('transactions', 'idx_txn_tenant_receiver_status')) {
            DB::statement('ALTER TABLE `transactions` DROP INDEX `idx_txn_tenant_receiver_status`');
        }

        // Restore the originals so down() is symmetric with the prior migration
        if (! $this->indexExists('transactions', 'idx_txn_sender_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_sender_status` (`sender_id`, `status`)');
        }
        if (! $this->indexExists('transactions', 'idx_txn_receiver_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_receiver_status` (`receiver_id`, `status`)');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return ! empty($result);
    }
};
