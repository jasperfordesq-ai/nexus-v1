<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing tenant_id indexes on 16 tenant-scoped tables.
 *
 * An audit identified these tables with tenant_id columns but no index.
 * Without an index, every tenant-filtered query on these tables causes a
 * full table scan, degrading performance as row counts grow.
 *
 * Tables covered:
 *   cron_logs, federation_rate_limits, group_answers,
 *   group_chatroom_pinned_messages, group_notification_preferences,
 *   group_qa_votes, group_user_bans, group_user_warnings,
 *   listing_images, org_wallet_limits, progress_notifications,
 *   push_subscriptions, role_permissions, salary_benchmarks,
 *   user_permissions, user_roles
 *
 * listing_reports is intentionally excluded — it already has a composite
 * index on (tenant_id, status) from 2026_04_03_100001_create_listing_reports_table.
 */
return new class extends Migration
{
    /**
     * The 16 tables that need a plain tenant_id index.
     * Each entry is [table_name, index_name].
     */
    private array $tables = [
        ['cron_logs',                       'cron_logs_tenant_id_index'],
        ['federation_rate_limits',           'federation_rate_limits_tenant_id_index'],
        ['group_answers',                    'group_answers_tenant_id_index'],
        ['group_chatroom_pinned_messages',   'group_chatroom_pinned_messages_tenant_id_index'],
        ['group_notification_preferences',   'group_notification_preferences_tenant_id_index'],
        ['group_qa_votes',                   'group_qa_votes_tenant_id_index'],
        ['group_user_bans',                  'group_user_bans_tenant_id_index'],
        ['group_user_warnings',              'group_user_warnings_tenant_id_index'],
        ['listing_images',                   'listing_images_tenant_id_index'],
        ['org_wallet_limits',               'org_wallet_limits_tenant_id_index'],
        ['progress_notifications',          'progress_notifications_tenant_id_index'],
        ['push_subscriptions',              'push_subscriptions_tenant_id_index'],
        ['role_permissions',                'role_permissions_tenant_id_index'],
        ['salary_benchmarks',               'salary_benchmarks_tenant_id_index'],
        ['user_permissions',                'user_permissions_tenant_id_index'],
        ['user_roles',                      'user_roles_tenant_id_index'],
    ];

    public function up(): void
    {
        foreach ($this->tables as [$table, $indexName]) {
            if (Schema::hasTable($table) && ! $this->indexExists($table, $indexName)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`tenant_id`)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as [$table, $indexName]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $indexName)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
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
