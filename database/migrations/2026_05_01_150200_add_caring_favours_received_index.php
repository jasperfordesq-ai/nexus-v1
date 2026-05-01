<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite (tenant_id, received_by_user_id) index on caring_favours so the
 * "favours received by user X in tenant Y" query uses a covering index.
 */
return new class extends Migration
{
    private string $indexName = 'caring_favours_tenant_received_idx';

    public function up(): void
    {
        if (! Schema::hasTable('caring_favours')) {
            return;
        }
        if (! Schema::hasColumn('caring_favours', 'received_by_user_id')) {
            return;
        }

        $exists = collect(Schema::getConnection()->select(
            'SHOW INDEX FROM caring_favours WHERE Key_name = ?',
            [$this->indexName]
        ))->isNotEmpty();

        if ($exists) {
            return;
        }

        Schema::table('caring_favours', function (Blueprint $table): void {
            $table->index(['tenant_id', 'received_by_user_id'], $this->indexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caring_favours')) {
            return;
        }

        try {
            Schema::table('caring_favours', function (Blueprint $table): void {
                $table->dropIndex($this->indexName);
            });
        } catch (\Throwable $e) {
            // index may not exist — ignore
        }
    }
};
