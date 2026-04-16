<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (!$this->indexExists('listings', 'idx_listings_tenant_featured')) {
                $table->index(['tenant_id', 'is_featured', 'featured_until'], 'idx_listings_tenant_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if ($this->indexExists('listings', 'idx_listings_tenant_featured')) {
                $table->dropIndex('idx_listings_tenant_featured');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $indexes = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        );
        return !empty($indexes);
    }
};
