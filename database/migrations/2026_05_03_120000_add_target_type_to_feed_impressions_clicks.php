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
 * Polymorphic CTR tracking for listings, events, and other feed items.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addTargetTypeAndWidenUniqueKey('feed_impressions', 'uq_impression');
        $this->addTargetTypeAndWidenUniqueKey('feed_clicks', 'uq_click');
    }

    public function down(): void
    {
        $this->removeTargetTypeAndRestoreUniqueKey('feed_impressions', 'uq_impression');
        $this->removeTargetTypeAndRestoreUniqueKey('feed_clicks', 'uq_click');
    }

    private function addTargetTypeAndWidenUniqueKey(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        if (!Schema::hasColumn($tableName, 'target_type')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('target_type', 32)->default('post')->after('post_id');
            });
        }

        $this->dropIndexIfExists($tableName, $indexName);

        if (!$this->indexExists($tableName, $indexName)) {
            DB::statement(
                "ALTER TABLE {$tableName} ADD UNIQUE KEY {$indexName} (post_id, target_type, user_id, tenant_id)"
            );
        }

        if (!$this->indexExists($tableName, $indexName)) {
            throw new RuntimeException("Failed to create {$indexName} on {$tableName}");
        }
    }

    private function removeTargetTypeAndRestoreUniqueKey(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'target_type')) {
            return;
        }

        $this->dropIndexIfExists($tableName, $indexName);
        $this->collapsePolymorphicDuplicatesForRollback($tableName);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('target_type');
        });

        if (!$this->indexExists($tableName, $indexName)) {
            DB::statement(
                "ALTER TABLE {$tableName} ADD UNIQUE KEY {$indexName} (post_id, user_id, tenant_id)"
            );
        }
    }

    private function collapsePolymorphicDuplicatesForRollback(string $tableName): void
    {
        $countColumn = $tableName === 'feed_clicks' ? 'click_count' : 'view_count';

        DB::statement(
            "UPDATE {$tableName} keep_row
             JOIN (
                 SELECT MIN(id) AS keep_id, post_id, user_id, tenant_id, SUM({$countColumn}) AS total_count, MAX(updated_at) AS latest_update
                 FROM {$tableName}
                 GROUP BY post_id, user_id, tenant_id
             ) grouped ON grouped.keep_id = keep_row.id
             SET keep_row.{$countColumn} = grouped.total_count,
                 keep_row.updated_at = grouped.latest_update"
        );

        DB::statement(
            "DELETE duplicate_row FROM {$tableName} duplicate_row
             JOIN {$tableName} keep_row
               ON keep_row.post_id = duplicate_row.post_id
              AND keep_row.user_id = duplicate_row.user_id
              AND keep_row.tenant_id = duplicate_row.tenant_id
              AND keep_row.id < duplicate_row.id"
        );
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$indexName]);

        return count($rows) > 0;
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            DB::statement("ALTER TABLE {$tableName} DROP INDEX {$indexName}");
        }
    }
};
