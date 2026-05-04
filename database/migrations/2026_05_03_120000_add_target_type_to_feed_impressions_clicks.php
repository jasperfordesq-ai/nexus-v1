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

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('target_type');
        });

        if (!$this->indexExists($tableName, $indexName)) {
            DB::statement(
                "ALTER TABLE {$tableName} ADD UNIQUE KEY {$indexName} (post_id, user_id, tenant_id)"
            );
        }
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
