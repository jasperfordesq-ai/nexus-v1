<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes for the high-traffic cursor queries identified in
     * the production performance audit. These are intentionally additive and
     * guarded so mixed legacy/prod schemas can run the migration safely.
     */
    public function up(): void
    {
        if ($this->hasColumns('marketplace_listings', ['tenant_id', 'status', 'moderation_status', 'id'])) {
            $this->addIndex('marketplace_listings', 'idx_mpl_public_newest', ['tenant_id', 'status', 'moderation_status', 'id']);
        }

        if ($this->hasColumns('marketplace_listings', ['tenant_id', 'status', 'moderation_status', 'price', 'id'])) {
            $this->addIndex('marketplace_listings', 'idx_mpl_public_price', ['tenant_id', 'status', 'moderation_status', 'price', 'id']);
        }

        if ($this->hasColumns('marketplace_listings', ['tenant_id', 'status', 'moderation_status', 'views_count', 'id'])) {
            $this->addIndex('marketplace_listings', 'idx_mpl_public_popular', ['tenant_id', 'status', 'moderation_status', 'views_count', 'id']);
        }

        if ($this->hasColumns('marketplace_listings', ['tenant_id', 'status', 'moderation_status', 'promoted_until', 'id'])) {
            $this->addIndex('marketplace_listings', 'idx_mpl_public_promoted', ['tenant_id', 'status', 'moderation_status', 'promoted_until', 'id']);
        }

        if ($this->hasColumns('messages', ['tenant_id', 'is_federated', 'sender_id', 'archived_by_sender', 'receiver_id', 'id'])) {
            $this->addIndex('messages', 'idx_msg_inbox_sender_latest', ['tenant_id', 'is_federated', 'sender_id', 'archived_by_sender', 'receiver_id', 'id']);
        }

        if ($this->hasColumns('messages', ['tenant_id', 'is_federated', 'receiver_id', 'archived_by_receiver', 'sender_id', 'id'])) {
            $this->addIndex('messages', 'idx_msg_inbox_receiver_latest', ['tenant_id', 'is_federated', 'receiver_id', 'archived_by_receiver', 'sender_id', 'id']);
        }

        if ($this->hasColumns('messages', ['tenant_id', 'is_federated', 'receiver_id', 'is_read', 'sender_id'])) {
            $this->addIndex('messages', 'idx_msg_unread_sender_counts', ['tenant_id', 'is_federated', 'receiver_id', 'is_read', 'sender_id']);
        }

        if ($this->hasColumns('notifications', ['tenant_id', 'user_id', 'id'])) {
            $this->addIndex('notifications', 'idx_notif_tenant_user_id', ['tenant_id', 'user_id', 'id']);
        }

        if ($this->hasColumns('notifications', ['tenant_id', 'user_id', 'is_read', 'id'])) {
            $this->addIndex('notifications', 'idx_notif_tenant_user_read_id', ['tenant_id', 'user_id', 'is_read', 'id']);
        }

        if ($this->hasColumns('notifications', ['tenant_id', 'user_id', 'type', 'id'])) {
            $this->addIndex('notifications', 'idx_notif_tenant_user_type_id', ['tenant_id', 'user_id', 'type', 'id']);
        }

        if ($this->hasColumns('listings', ['tenant_id', 'status', 'moderation_status', 'id'])) {
            $this->addIndex('listings', 'idx_listings_public_newest', ['tenant_id', 'status', 'moderation_status', 'id']);
        }

        if ($this->hasColumns('listings', ['tenant_id', 'status', 'moderation_status', 'is_featured', 'id'])) {
            $this->addIndex('listings', 'idx_listings_public_featured', ['tenant_id', 'status', 'moderation_status', 'is_featured', 'id']);
        }
    }

    public function down(): void
    {
        $this->dropIndex('listings', 'idx_listings_public_featured');
        $this->dropIndex('listings', 'idx_listings_public_newest');
        $this->dropIndex('notifications', 'idx_notif_tenant_user_type_id');
        $this->dropIndex('notifications', 'idx_notif_tenant_user_read_id');
        $this->dropIndex('notifications', 'idx_notif_tenant_user_id');
        $this->dropIndex('messages', 'idx_msg_unread_sender_counts');
        $this->dropIndex('messages', 'idx_msg_inbox_receiver_latest');
        $this->dropIndex('messages', 'idx_msg_inbox_sender_latest');
        $this->dropIndex('marketplace_listings', 'idx_mpl_public_promoted');
        $this->dropIndex('marketplace_listings', 'idx_mpl_public_popular');
        $this->dropIndex('marketplace_listings', 'idx_mpl_public_price');
        $this->dropIndex('marketplace_listings', 'idx_mpl_public_newest');
    }

    /**
     * @param list<string> $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $columns
     */
    private function addIndex(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnSql})");
    }

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($rows);
    }
};
