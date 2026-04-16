<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poll_votes', function (Blueprint $table) {
            // Drop the existing (poll_id, user_id) unique index that lacks tenant scoping
            if ($this->indexExists('poll_votes', 'idx_vote_unique')) {
                $table->dropUnique('idx_vote_unique');
            }

            // Add tenant-scoped unique index to prevent cross-tenant vote collisions
            if (!$this->indexExists('poll_votes', 'idx_vote_unique_tenant')) {
                $table->unique(['tenant_id', 'poll_id', 'user_id'], 'idx_vote_unique_tenant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('poll_votes', function (Blueprint $table) {
            if ($this->indexExists('poll_votes', 'idx_vote_unique_tenant')) {
                $table->dropUnique('idx_vote_unique_tenant');
            }

            if (!$this->indexExists('poll_votes', 'idx_vote_unique')) {
                $table->unique(['poll_id', 'user_id'], 'idx_vote_unique');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $indexes = DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        );
        return !empty($indexes);
    }
};
