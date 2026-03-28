<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance audit — add missing composite indexes for hot-path queries.
 *
 * Identified by backend performance audit 2026-03-28:
 * - messages: conversations query groups by (tenant_id, sender_id, receiver_id)
 *   but only has separate single-column indexes
 * - messages: unread count query uses (tenant_id, receiver_id, is_read)
 * - notifications: poll endpoint uses (tenant_id, user_id, is_read, deleted_at)
 * - transactions: status filter used by WalletService on every balance/list call
 * - likes: feed batch-loading groups by (tenant_id, target_type, target_id)
 * - listings: tenant + status + id DESC is the primary listing query pattern
 */
return new class extends Migration
{
    public function up(): void
    {
        // messages: composite for conversation grouping & unread queries
        // Query: WHERE tenant_id = ? AND (sender_id = ? OR receiver_id = ?)
        // Query: WHERE tenant_id = ? AND receiver_id = ? AND is_read = 0
        if (! $this->indexExists('messages', 'idx_msg_tenant_sender_receiver')) {
            DB::statement('ALTER TABLE `messages` ADD INDEX `idx_msg_tenant_sender_receiver` (`tenant_id`, `sender_id`, `receiver_id`)');
        }
        if (! $this->indexExists('messages', 'idx_msg_tenant_receiver_unread')) {
            DB::statement('ALTER TABLE `messages` ADD INDEX `idx_msg_tenant_receiver_unread` (`tenant_id`, `receiver_id`, `is_read`)');
        }

        // notifications: composite for the /poll endpoint and notification listing
        // Query: WHERE tenant_id = ? AND user_id = ? AND is_read = 0 AND deleted_at IS NULL
        if (! $this->indexExists('notifications', 'idx_notif_tenant_user_read_deleted')) {
            DB::statement('ALTER TABLE `notifications` ADD INDEX `idx_notif_tenant_user_read_deleted` (`tenant_id`, `user_id`, `is_read`, `deleted_at`)');
        }

        // transactions: composite for WalletService balance & transaction list queries
        // Query: WHERE (sender_id = ? OR receiver_id = ?) AND status = 'completed'
        if (! $this->indexExists('transactions', 'idx_txn_sender_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_sender_status` (`sender_id`, `status`)');
        }
        if (! $this->indexExists('transactions', 'idx_txn_receiver_status')) {
            DB::statement('ALTER TABLE `transactions` ADD INDEX `idx_txn_receiver_status` (`receiver_id`, `status`)');
        }

        // likes: composite for feed batch-loading counts
        // Query: WHERE tenant_id = ? AND target_type = ? AND target_id IN (...)
        if (! $this->indexExists('likes', 'idx_likes_tenant_target')) {
            DB::statement('ALTER TABLE `likes` ADD INDEX `idx_likes_tenant_target` (`tenant_id`, `target_type`, `target_id`)');
        }

        // listings: composite for the primary listing index query
        // Query: WHERE tenant_id = ? AND (status IS NULL OR status = 'active') ORDER BY id DESC
        if (! $this->indexExists('listings', 'idx_listings_tenant_status_id')) {
            DB::statement('ALTER TABLE `listings` ADD INDEX `idx_listings_tenant_status_id` (`tenant_id`, `status`, `id`)');
        }

        // events: composite for the primary event list query
        // Query: WHERE tenant_id = ? AND status = 'active' AND start_time >= NOW()
        if (! $this->indexExists('events', 'idx_events_tenant_status_start')) {
            DB::statement('ALTER TABLE `events` ADD INDEX `idx_events_tenant_status_start` (`tenant_id`, `status`, `start_time`)');
        }
    }

    public function down(): void
    {
        $indexes = [
            'messages' => ['idx_msg_tenant_sender_receiver', 'idx_msg_tenant_receiver_unread'],
            'notifications' => ['idx_notif_tenant_user_read_deleted'],
            'transactions' => ['idx_txn_sender_status', 'idx_txn_receiver_status'],
            'likes' => ['idx_likes_tenant_target'],
            'listings' => ['idx_listings_tenant_status_id'],
            'events' => ['idx_events_tenant_status_start'],
        ];

        foreach ($indexes as $table => $idxNames) {
            foreach ($idxNames as $idx) {
                if ($this->indexExists($table, $idx)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$idx}`");
                }
            }
        }
    }

    /**
     * Check if an index exists on a table (safe for idempotent runs).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return ! empty($result);
    }
};
